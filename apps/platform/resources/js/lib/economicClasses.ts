// Codes réels et stables (app/Modules/Subscriptions/Infrastructure/Models/EconomicClass.php
// et docs/03-abonnements-et-classes-economiques-wasplex.md) — l'API ne renvoie que le code,
// jamais de libellé, donc on reprend ici exactement les noms déjà utilisés au seed du
// catalogue (SeedEconomicCatalogCommand) plutôt que d'afficher les codes bruts.
export const ECONOMIC_CLASS_LABELS: Record<string, string> = {
    FREE: 'Gratuit',
    PREMIUM: 'Premium',
    GOLD: 'Gold',
    PLATINUM: 'Platine',
};

export function economicClassLabel(code: string): string {
    return ECONOMIC_CLASS_LABELS[code] ?? code;
}
