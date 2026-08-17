import { ref, watch } from 'vue';

const STORAGE_KEY = 'wpx:wallet:amounts-hidden';
const MASK = '••••••';

function readInitialState(): boolean {
    if (typeof window === 'undefined') return false;
    try {
        return window.localStorage.getItem(STORAGE_KEY) === '1';
    } catch {
        return false;
    }
}

// Module-level (singleton) state: every component calling useWalletPrivacy()
// shares the same reactive flag, so the eye toggle in one place instantly
// masks amounts rendered anywhere else in the Wallet without prop drilling.
const hidden = ref(readInitialState());

watch(hidden, (value) => {
    if (typeof window === 'undefined') return;
    try {
        window.localStorage.setItem(STORAGE_KEY, value ? '1' : '0');
    } catch {
        // Le stockage local peut être indisponible (navigation privée) ;
        // le mode reste actif pour la session en cours sans persistance.
    }
});

export function useWalletPrivacy() {
    function toggle(): void {
        hidden.value = !hidden.value;
    }

    /** Formats a WP/FCFA amount, replacing it with a mask when privacy mode is on. */
    function maskAmount(formatted: string): string {
        return hidden.value ? MASK : formatted;
    }

    return { hidden, toggle, maskAmount };
}
