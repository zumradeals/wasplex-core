type Module = boolean | null;

const VERSION = 6;
const SIZE = 17 + VERSION * 4;
const DATA_CODEWORDS = 136;
const BLOCK_COUNT = 2;
const DATA_PER_BLOCK = 68;
const ECC_PER_BLOCK = 18;
const REMAINDER_BITS = 7;
const GF_EXP = new Array<number>(512).fill(0);
const GF_LOG = new Array<number>(256).fill(0);

let x = 1;
for (let i = 0; i < 255; i += 1) {
    GF_EXP[i] = x;
    GF_LOG[x] = i;
    x <<= 1;
    if ((x & 0x100) !== 0) x ^= 0x11d;
}
for (let i = 255; i < 512; i += 1) GF_EXP[i] = GF_EXP[i - 255];

function gfMul(a: number, b: number): number {
    if (a === 0 || b === 0) return 0;
    return GF_EXP[GF_LOG[a] + GF_LOG[b]];
}

function generator(degree: number): number[] {
    let result = [1];
    for (let i = 0; i < degree; i += 1) {
        const next = new Array<number>(result.length + 1).fill(0);
        for (let j = 0; j < result.length; j += 1) {
            next[j] ^= result[j];
            next[j + 1] ^= gfMul(result[j], GF_EXP[i]);
        }
        result = next;
    }
    return result;
}

function ecc(data: number[]): number[] {
    const gen = generator(ECC_PER_BLOCK);
    const remainder = new Array<number>(ECC_PER_BLOCK).fill(0);
    for (const value of data) {
        const factor = value ^ remainder[0];
        remainder.shift();
        remainder.push(0);
        for (let i = 0; i < ECC_PER_BLOCK; i += 1) remainder[i] ^= gfMul(gen[i + 1], factor);
    }
    return remainder;
}

function appendBits(target: number[], value: number, count: number): void {
    for (let i = count - 1; i >= 0; i -= 1) target.push((value >>> i) & 1);
}

function encodePayload(payload: string): number[] {
    const bytes = Array.from(new TextEncoder().encode(payload));
    if (bytes.length > 134) throw new Error('QR payload too long for Wasplex Card QR v1.');

    const bits: number[] = [];
    appendBits(bits, 0b0100, 4);
    appendBits(bits, bytes.length, 8);
    for (const byte of bytes) appendBits(bits, byte, 8);

    const capacity = DATA_CODEWORDS * 8;
    for (let i = 0; i < Math.min(4, capacity - bits.length); i += 1) bits.push(0);
    while (bits.length % 8 !== 0) bits.push(0);

    const data: number[] = [];
    for (let i = 0; i < bits.length; i += 8) {
        let value = 0;
        for (let j = 0; j < 8; j += 1) value = (value << 1) | bits[i + j];
        data.push(value);
    }
    let pad = true;
    while (data.length < DATA_CODEWORDS) {
        data.push(pad ? 0xec : 0x11);
        pad = !pad;
    }

    const blocks = new Array<number[]>(BLOCK_COUNT);
    const eccBlocks = new Array<number[]>(BLOCK_COUNT);
    for (let block = 0; block < BLOCK_COUNT; block += 1) {
        blocks[block] = data.slice(block * DATA_PER_BLOCK, (block + 1) * DATA_PER_BLOCK);
        eccBlocks[block] = ecc(blocks[block]);
    }

    const codewords: number[] = [];
    for (let i = 0; i < DATA_PER_BLOCK; i += 1) {
        for (let block = 0; block < BLOCK_COUNT; block += 1) codewords.push(blocks[block][i]);
    }
    for (let i = 0; i < ECC_PER_BLOCK; i += 1) {
        for (let block = 0; block < BLOCK_COUNT; block += 1) codewords.push(eccBlocks[block][i]);
    }

    const result: number[] = [];
    for (const word of codewords) appendBits(result, word, 8);
    for (let i = 0; i < REMAINDER_BITS; i += 1) result.push(0);
    return result;
}

function bchDigit(value: number): number {
    let digit = 0;
    while (value !== 0) {
        digit += 1;
        value >>>= 1;
    }
    return digit;
}

function typeInfoBits(data: number): number {
    const generator = 0x537;
    let value = data << 10;
    while (bchDigit(value) - bchDigit(generator) >= 0) {
        value ^= generator << (bchDigit(value) - bchDigit(generator));
    }
    return ((data << 10) | value) ^ 0x5412;
}

function finder(matrix: Module[][], row: number, col: number): void {
    for (let r = -1; r <= 7; r += 1) {
        for (let c = -1; c <= 7; c += 1) {
            const rr = row + r;
            const cc = col + c;
            if (rr < 0 || rr >= SIZE || cc < 0 || cc >= SIZE) continue;
            const inside = r >= 0 && r <= 6 && c >= 0 && c <= 6;
            matrix[rr][cc] =
                inside && (r === 0 || r === 6 || c === 0 || c === 6 || (r >= 2 && r <= 4 && c >= 2 && c <= 4));
        }
    }
}

function alignment(matrix: Module[][], row: number, col: number): void {
    if (matrix[row][col] !== null) return;
    for (let r = -2; r <= 2; r += 1) {
        for (let c = -2; c <= 2; c += 1) {
            matrix[row + r][col + c] = Math.abs(r) === 2 || Math.abs(c) === 2 || (r === 0 && c === 0);
        }
    }
}

function formatInfo(matrix: Module[][]): void {
    const bits = typeInfoBits(0b01000);
    for (let i = 0; i < 15; i += 1) {
        const value = ((bits >> i) & 1) === 1;
        if (i < 6) matrix[i][8] = value;
        else if (i < 8) matrix[i + 1][8] = value;
        else matrix[SIZE - 15 + i][8] = value;

        if (i < 8) matrix[8][SIZE - i - 1] = value;
        else if (i < 9) matrix[8][15 - i] = value;
        else matrix[8][15 - i - 1] = value;
    }
    matrix[SIZE - 8][8] = true;
}

export function makeQrMatrix(payload: string): boolean[][] {
    const matrix: Module[][] = Array.from({ length: SIZE }, () => new Array<Module>(SIZE).fill(null));
    finder(matrix, 0, 0);
    finder(matrix, SIZE - 7, 0);
    finder(matrix, 0, SIZE - 7);

    for (let i = 8; i < SIZE - 8; i += 1) {
        if (matrix[6][i] === null) matrix[6][i] = i % 2 === 0;
        if (matrix[i][6] === null) matrix[i][6] = i % 2 === 0;
    }
    alignment(matrix, 34, 34);
    formatInfo(matrix);

    const bits = encodePayload(payload);
    let bitIndex = 0;
    let row = SIZE - 1;
    let direction = -1;

    for (let col = SIZE - 1; col > 0; col -= 2) {
        if (col === 6) col -= 1;
        while (true) {
            for (let offset = 0; offset < 2; offset += 1) {
                const cc = col - offset;
                if (matrix[row][cc] !== null) continue;
                let value = bitIndex < bits.length ? bits[bitIndex] === 1 : false;
                bitIndex += 1;
                if ((row + cc) % 2 === 0) value = !value;
                matrix[row][cc] = value;
            }
            row += direction;
            if (row < 0 || row >= SIZE) {
                row -= direction;
                direction = -direction;
                break;
            }
        }
    }

    return matrix.map((line) => line.map((cell) => cell === true));
}
