import { ref } from 'vue';

/**
 * Petites actions visuelles encore non livrées. Le raccourci Carte Wasplex
 * est maintenant une vraie entrée transversale et quitte ce mécanisme.
 */
export function useComingSoon(message = 'Bientôt disponible') {
    const notice = ref<string | null>(null);
    let timer: ReturnType<typeof setTimeout> | null = null;

    function announce(event?: Event): void {
        const target = event?.currentTarget;
        if (target instanceof HTMLElement && target.textContent?.includes('Carte Wasplex')) {
            window.location.assign('/services/wasplex');
            return;
        }

        notice.value = message;

        if (timer !== null) {
            clearTimeout(timer);
        }

        timer = setTimeout(() => {
            notice.value = null;
        }, 1800);
    }

    return { notice, announce };
}
