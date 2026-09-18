import { Head } from '@inertiajs/react';
import { useState } from 'react';

import PendingInvitationsModal from '@/components/pending-invitations-modal';

import { AccountantClientsCard } from '@/components/dashboard/accountant-clients-card';
import { FinancialOverview } from '@/components/dashboard/financial-overview';
import { FiscalSummaryCard } from '@/components/dashboard/fiscal-summary-card';
import { RecentExtractionsCard } from '@/components/dashboard/recent-extractions-card';
import { SriNotificationsCard } from '@/components/dashboard/sri-notifications-card';
import { SriObligationsCard } from '@/components/dashboard/sri-obligations-card';
import { SriProfileCard } from '@/components/dashboard/sri-profile-card';

import { dashboard } from '@/routes';

import type { DashboardProps } from '@/types/dashboard';
import Heading from '@/components/heading';

export default function Dashboard({
    pendingInvitations = [],
    currentTeam,
    company,
    sriStatus,
    sri,
    summary,
    recentExtractions = [],
    clientTeams = [],
    isAccountant,
}: DashboardProps) {
    const [showInvitations, setShowInvitations] = useState(
        pendingInvitations.length > 0,
    );

    const profile =
        sri?.profile ??
        company ?? {
            ruc: null,
            business_name: null,
            email: null,
            phone: null,
            legal_representative: null,
            legal_representative_id: null,
        };

    return (
        <>
            <Head title="Dashboard" />

            <PendingInvitationsModal
                invitations={pendingInvitations}
                open={
                    pendingInvitations.length > 0 &&
                    showInvitations
                }
                onOpenChange={setShowInvitations}
            />

            <main className="w-full">
                <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6 p-4 sm:p-6 lg:p-8">
                    <Heading
                        variant="small"
                        title={`${currentTeam?.name ?? 'Empresa'}`}
                        description="Resumen financiero, obligaciones tributarias y actividad reciente de tu empresa."
                    />

                    {/* Financial overview */}
                    <FinancialOverview summary={summary} />

                    {/* SRI main section */}
                    <section className="grid gap-6 xl:grid-cols-[minmax(0,1.55fr)_minmax(360px,0.8fr)]">
                        <SriObligationsCard
                            obligations={sri?.obligations}
                        />

                        <SriNotificationsCard
                            notifications={sri?.notifications}
                        />
                    </section>

                    {/* Company / SRI information */}
                    <section className="grid gap-6 xl:grid-cols-[minmax(0,1.55fr)_minmax(360px,0.8fr)]">
                        <SriProfileCard
                            profile={profile}
                            status={sriStatus}
                        />

                        <RecentExtractionsCard
                            extractions={recentExtractions}
                        />
                    </section>

                    {/* Fiscal documents */}
                    {summary && (
                        <section className="grid gap-6 lg:grid-cols-2">
                            <FiscalSummaryCard
                                title="Ventas"
                                description="Documentos fiscales emitidos"
                                summary={summary.sales}
                                type="sales"
                            />

                            <FiscalSummaryCard
                                title="Compras"
                                description="Documentos fiscales recibidos"
                                summary={summary.purchases}
                                type="purchases"
                            />
                        </section>
                    )}

                    {/* Accountant */}
                    {isAccountant && (
                        <section>
                            <AccountantClientsCard
                                clients={clientTeams}
                            />
                        </section>
                    )}
                </div>
            </main>
        </>
    );
}

Dashboard.layout = (props: {
    currentTeam?: { slug: string } | null;
}) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: props.currentTeam
                ? dashboard(props.currentTeam.slug)
                : '/',
        },
    ],
});