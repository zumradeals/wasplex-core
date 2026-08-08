// Rôles humains — concept purement frontend (aucune notion de rôle n'existe
// côté backend, seulement des CapabilityGrant individuels). Chaque rôle est
// un paquet de codes de capacité réels, déjà utilisés ailleurs dans le code
// (voir grep EnsureCapability::class à travers apps/platform/app/Modules).
// Choisir un rôle déclenche un appel POST /admin/capabilities par code du
// paquet — aucune capacité inventée, aucun endpoint bulk qui n'existe pas.
export interface AdminRole {
    code: string;
    name: string;
    description: string;
    capabilities: string[];
}

export const ADMIN_ROLES: AdminRole[] = [
    {
        code: 'support',
        name: 'Support client',
        description: 'Voir les comptes utilisateurs, répondre aux demandes — ne touche pas aux finances.',
        capabilities: ['admin.accounts.view', 'admin.dashboard.view'],
    },
    {
        code: 'finance',
        name: 'Responsable finances',
        description: 'Valide les dépôts et retraits, consulte le Grand livre.',
        capabilities: [
            'wallet.ledger.view',
            'wallet.audit.view',
            'wallet.correction.propose',
            'wallet.correction.approve',
            'admin.advertiser-wallet.supervised-deposit',
            'admin.reconciliation.review',
        ],
    },
    {
        code: 'advertising',
        name: 'Responsable publicité',
        description: 'Examine et approuve les campagnes des annonceurs.',
        capabilities: [
            'admin.campaign-reviews.view',
            'admin.campaign-reviews.decide',
            'admin.campaigns.suspend',
            'admin.advertisers.manage',
            'admin.brands.moderate',
            'admin.advertising.pricing.manage',
        ],
    },
    {
        code: 'read-only',
        name: 'Lecture seule',
        description: 'Peut tout consulter, ne peut rien modifier.',
        capabilities: [
            'admin.dashboard.view',
            'admin.audit.view',
            'admin.accounts.view',
            'wallet.ledger.view',
            'admin.campaign-reviews.view',
            'admin.matching.audit.view',
        ],
    },
];

// Capacités accordées au fondateur par identity:seed-founder — reproduites
// ici uniquement pour détecter/afficher le badge "Fondateur" dans la liste
// de l'équipe, jamais pour accorder quoi que ce soit depuis le frontend.
export const FOUNDER_CAPABILITIES = [
    'admin.dashboard.view',
    'admin.capabilities.grant',
    'admin.capabilities.revoke',
    'admin.audit.view',
    'wallet.ledger.view',
    'wallet.correction.propose',
    'wallet.correction.approve',
    'wallet.audit.view',
    'admin.advertisers.manage',
    'admin.brands.moderate',
    'admin.advertiser-wallet.supervised-deposit',
    'admin.subscriptions.plans.manage',
    'admin.subscriptions.classes.manage',
    'admin.advertising.pricing.manage',
    'admin.campaign-reviews.view',
    'admin.campaign-reviews.decide',
    'admin.campaigns.suspend',
    'admin.smartprofile.taxonomies.manage',
    'admin.smartprofile.consents.manage',
    'admin.matching.configuration.manage',
    'admin.matching.audit.view',
    'admin.feed.dashboard.view',
    'admin.feed.risk.review',
    'admin.reconciliation.review',
];

export function roleForCapabilities(codes: string[]): AdminRole | null {
    const set = new Set(codes);

    if (FOUNDER_CAPABILITIES.every((code) => set.has(code))) {
        return {
            code: 'founder',
            name: 'Fondateur',
            description: 'Accès complet à toute l’administration.',
            capabilities: [],
        };
    }

    const matches = ADMIN_ROLES.filter((role) => role.capabilities.every((code) => set.has(code)));

    if (matches.length === 0) {
        return null;
    }

    return matches.reduce((best, role) => (role.capabilities.length > best.capabilities.length ? role : best));
}
