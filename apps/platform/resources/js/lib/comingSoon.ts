import { ref } from 'vue';

/**
 * Petites actions visuelles encore non livrées. Les raccourcis Carte Wasplex
 * et Live sont maintenant de vraies entrées transversales et quittent ce mécanisme.
 */
export function useComingSoon(message = 'Bientôt disponible') {
    const notice = ref<string | null>(null);
    let timer: ReturnType<typeof setTimeout> | null = null;

    function announce(event?: Event): void {
        const target = event?.currentTarget;
        if (target instanceof HTMLElement) {
            const label = target.textContent?.trim() ?? '';

            if (label.includes('Carte Wasplex')) {
                window.location.assign('/services/wasplex');
                return;
            }

            if (label === 'Live') {
                window.location.assign('/live');
                return;
            }
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
