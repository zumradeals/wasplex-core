import { ref } from 'vue';

/**
 * Petites actions visuelles de la maquette P014 sans backend (Carte Wasplex,
 * parrainage, KYC, retrait/dépôt/transfert...). Affiche un message bref au lieu
 * de fabriquer une donnée ou de rediriger vers une route inexistante.
 */
export function useComingSoon(message = 'Bientôt disponible') {
    const notice = ref<string | null>(null);
    let timer: ReturnType<typeof setTimeout> | null = null;

    function announce(): void {
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
