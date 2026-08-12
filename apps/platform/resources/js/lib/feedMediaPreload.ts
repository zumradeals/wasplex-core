import http from '@/lib/http';

interface PreloadCreative {
    url: string;
    type: string;
}

interface FeedPreloadHint {
    campaign_id: string;
    creative: PreloadCreative;
}

let prefetchedMedia: HTMLVideoElement | HTMLImageElement | null = null;
let prefetchedCampaignId: string | null = null;
let attemptedForCampaignId: string | null = null;
let inFlight: Promise<void> | null = null;

function releaseMedia(): void {
    if (prefetchedMedia instanceof HTMLVideoElement) {
        prefetchedMedia.removeAttribute('src');
        prefetchedMedia.load();
    }

    prefetchedMedia = null;
    prefetchedCampaignId = null;
}

function warmMedia(hint: FeedPreloadHint | null): void {
    releaseMedia();

    if (hint?.creative?.url === undefined || typeof document === 'undefined') return;

    if (hint.creative.type === 'video') {
        const media = document.createElement('video');
        media.preload = 'auto';
        media.muted = true;
        media.playsInline = true;
        media.src = hint.creative.url;
        media.load();
        prefetchedMedia = media;
        prefetchedCampaignId = hint.campaign_id;
        return;
    }

    if (hint.creative.type === 'image') {
        const image = new Image();
        image.src = hint.creative.url;
        prefetchedMedia = image;
        prefetchedCampaignId = hint.campaign_id;
    }
}

export function feedMediaPreloadActivated(campaignId: string | null): void {
    attemptedForCampaignId = null;

    if (prefetchedCampaignId !== null && prefetchedCampaignId !== campaignId) {
        releaseMedia();
    }
}

export function feedMediaPreloadPlaying(campaignId: string | null): void {
    if (campaignId === null) return;

    if (prefetchedCampaignId === campaignId) {
        releaseMedia();
    }

    void prepareFeedMediaPreload(campaignId);
}

export async function prepareFeedMediaPreload(currentCampaignId: string): Promise<void> {
    if (
        typeof document === 'undefined' ||
        inFlight !== null ||
        attemptedForCampaignId === currentCampaignId
    ) {
        return;
    }

    attemptedForCampaignId = currentCampaignId;

    inFlight = http
        .get('/feed/preload', {
            params: { current_campaign_id: currentCampaignId },
        })
        .then(({ data }) => {
            if (attemptedForCampaignId !== currentCampaignId) return;
            warmMedia((data.preload ?? null) as FeedPreloadHint | null);
        })
        .catch(() => {
            if (attemptedForCampaignId === currentCampaignId) {
                attemptedForCampaignId = null;
            }
        })
        .finally(() => {
            inFlight = null;
        });

    await inFlight;
}

export function releaseFeedMediaPreload(): void {
    attemptedForCampaignId = null;
    releaseMedia();
}
