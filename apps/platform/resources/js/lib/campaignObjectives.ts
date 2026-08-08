export const CAMPAIGN_OBJECTIVES: Record<string, string> = {
    faire_connaitre: 'En savoir plus',
    obtenir_appels: 'Appeler',
    recevoir_messages: 'Envoyer un message',
    visiter_site: 'Visiter le site',
    promouvoir_produit: 'Découvrir',
    promouvoir_evenement: "Voir l'événement",
    obtenir_inscriptions: "S'inscrire",
    inviter_live: 'Rejoindre le Live',
};

export function campaignDisplayName(objectiveCode: string | null, title?: string | null): string {
    if (title && title.trim() !== '') {
        return title;
    }

    return objectiveCode ? (CAMPAIGN_OBJECTIVES[objectiveCode] ?? 'Campagne') : 'Campagne';
}
