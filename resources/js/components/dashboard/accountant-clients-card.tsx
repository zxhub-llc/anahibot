import { Building02Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Link } from '@inertiajs/react';

import type { DashboardClientTeam } from '@/types/dashboard';

type Props = {
    clients?: DashboardClientTeam[];
};

export function AccountantClientsCard({
    clients = [],
}: Props) {
    return (
        <div className="rounded-2xl border bg-card shadow-sm">
            <div className="border-b p-5">
                <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                    Contabilidad
                </p>

                <h2 className="mt-1 text-lg font-semibold">
                    Empresas administradas
                </h2>
            </div>

            {clients.length > 0 ? (
                <div className="divide-y">
                    {clients.map((client) => (
                        <Link
                            key={client.id}
                            href={`/${client.slug}/dashboard`}
                            className="flex items-center gap-3 p-4 transition-colors hover:bg-muted/40"
                        >
                            <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-muted">
                                <HugeiconsIcon
                                    icon={Building02Icon}
                                    className="size-5"
                                />
                            </div>

                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-medium">
                                    {client.name}
                                </p>

                                <p className="mt-0.5 font-mono text-xs text-muted-foreground">
                                    {client.ruc ?? 'Sin RUC'}
                                </p>
                            </div>

                            <div className="text-right">
                                <p className="text-sm font-semibold">
                                    {client.documentsCount}
                                </p>

                                <p className="text-[11px] text-muted-foreground">
                                    documentos
                                </p>
                            </div>
                        </Link>
                    ))}
                </div>
            ) : (
                <p className="p-6 text-sm text-muted-foreground">
                    No hay empresas asociadas.
                </p>
            )}
        </div>
    );
}