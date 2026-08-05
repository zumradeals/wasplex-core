export interface SpaceSummary {
    user_space_id: string;
    space_type: 'user' | 'advertiser' | 'admin';
    organization_id: string | null;
    organization_name: string | null;
}

export interface AuthAccount {
    id: string;
    status: string;
    mfa_enabled: boolean;
}

export interface AuthShared {
    account: AuthAccount;
    spaces: SpaceSummary[];
    active_space_id: string | null;
}

export function shellPathForSpaceType(spaceType: SpaceSummary['space_type']): string {
    switch (spaceType) {
        case 'advertiser':
            return '/studio';
        case 'admin':
            return '/admin';
        default:
            return '/app';
    }
}
