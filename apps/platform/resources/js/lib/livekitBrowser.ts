const LIVEKIT_BROWSER_SDK_URL = 'https://cdn.jsdelivr.net/npm/livekit-client@2.21.0/+esm';

export interface LiveKitTrack {
    kind?: string;
    attach: () => HTMLElement;
}

export interface LiveKitTrackPublication {
    track?: LiveKitTrack | null;
}

export interface LiveKitPermissions {
    canPublish?: boolean;
}

export interface LiveKitParticipant {
    identity: string;
    name?: string;
    permissions?: LiveKitPermissions;
    getTrackPublications: () => LiveKitTrackPublication[];
}

export interface LiveKitLocalParticipant extends LiveKitParticipant {
    setCameraEnabled: (enabled: boolean) => Promise<unknown>;
    setMicrophoneEnabled: (enabled: boolean) => Promise<unknown>;
}

export interface LiveKitRoom {
    localParticipant: LiveKitLocalParticipant;
    remoteParticipants: Map<string, LiveKitParticipant>;
    connect: (url: string, token: string) => Promise<void>;
    disconnect: () => Promise<void>;
    startAudio: () => Promise<void>;
    on: (event: string, listener: (...args: unknown[]) => void) => LiveKitRoom;
}

export interface LiveKitModule {
    Room: new (options?: Record<string, unknown>) => LiveKitRoom;
    RoomEvent: Record<string, string>;
}

let modulePromise: Promise<LiveKitModule> | null = null;

export function loadLiveKit(): Promise<LiveKitModule> {
    if (!modulePromise) {
        modulePromise = import(/* @vite-ignore */ LIVEKIT_BROWSER_SDK_URL) as unknown as Promise<LiveKitModule>;
    }

    return modulePromise;
}
