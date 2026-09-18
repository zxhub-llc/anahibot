import type { DashboardInvitation } from '@/types';

export type DashboardFiscalSummary = {
    total: number;
    subtotal: number;
    tax_amount: number;
    by_type: {
        type: string;
        total: number;
        count: number;
    }[];
};

export type DashboardSriStatus = {
    configured: boolean;
    last_authenticated_at: string | null;
    has_error: boolean;
};

export type DashboardSriProfile = {
    ruc: string | null;
    business_name: string | null;
    email: string | null;
    phone: string | null;
    legal_representative: string | null;
    legal_representative_id: string | null;
};

export type DashboardSriNotification = {
    id: string | number;
    title: string;
    description: string | null;
    type: 'info' | 'warning' | 'danger' | 'success';
    date: string | null;
    read: boolean;
};

export type DashboardSriObligation = {
    id: string | number;
    code: string | null;
    name: string;
    period: string | null;
    due_date: string | null;
    status: 'pending' | 'overdue' | 'completed';
    amount: number | null;
};

export type DashboardExtraction = {
    id: number;
    date_from: string | null;
    date_to: string | null;
    status: string;
    completed_at: string | null;
};

export type DashboardClientTeam = {
    id: number;
    name: string;
    slug: string;
    ruc: string | null;
    documentsCount: number;
};

export type DashboardProps = {
    pendingInvitations?: DashboardInvitation[];

    accountType: string;
    isAccountant: boolean;

    currentTeam?: {
        id: number;
        name: string;
        slug: string;
        type: string;
    } | null;

    company?: DashboardSriProfile | null;

    sriStatus?: DashboardSriStatus | null;

    sri?: {
        profile: DashboardSriProfile;
        notifications: DashboardSriNotification[];
        obligations: DashboardSriObligation[];
    } | null;

    summary?: {
        sales: DashboardFiscalSummary;
        purchases: DashboardFiscalSummary;
        voided_count: number;
    };

    recentExtractions?: DashboardExtraction[];

    clientTeams?: DashboardClientTeam[];
};