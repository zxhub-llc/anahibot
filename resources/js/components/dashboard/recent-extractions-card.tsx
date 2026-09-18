import {
    CheckmarkCircle02Icon,
    Clock01Icon,
    Loading03Icon,
    Alert02Icon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';

import type { DashboardExtraction } from '@/types/dashboard';

type Props = {
    extractions?: DashboardExtraction[];
};

function statusLabel(status: string) {
    const labels: Record<string, string> = {
        pending: 'Pendiente',
        queued: 'En cola',
        running: 'Procesando',
        completed: 'Completada',
        completed_with_errors: 'Con errores',
        failed: 'Fallida',
        cancelled: 'Cancelada',
    };

    return labels[status] ?? status;
}

function StatusIcon({ status }: { status: string }) {
    if (status === 'completed') {
        return (
            <HugeiconsIcon
                icon={CheckmarkCircle02Icon}
                className="size-4 text-emerald-600"
            />
        );
    }

    if (
        status === 'failed' ||
        status === 'completed_with_errors'
    ) {
        return (
            <HugeiconsIcon
                icon={Alert02Icon}
                className="size-4 text-red-600"
            />
        );
    }

    if (status === 'running' || status === 'queued') {
        return (
            <HugeiconsIcon
                icon={Loading03Icon}
                className="size-4 animate-spin text-blue-600"
            />
        );
    }

    return (
        <HugeiconsIcon
            icon={Clock01Icon}
            className="size-4 text-muted-foreground"
        />
    );
}

export function RecentExtractionsCard({
    extractions = [],
}: Props) {
    return (
        <div className="rounded-2xl border bg-card shadow-sm">
            <div className="border-b p-5">
                <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                    Automatización
                </p>

                <h2 className="mt-1 text-lg font-semibold">
                    Extracciones recientes
                </h2>
            </div>

            {extractions.length > 0 ? (
                <div className="divide-y">
                    {extractions.map((extraction) => (
                        <div
                            key={extraction.id}
                            className="flex items-center gap-3 p-4"
                        >
                            <div className="flex size-9 items-center justify-center rounded-lg bg-muted">
                                <StatusIcon
                                    status={extraction.status}
                                />
                            </div>

                            <div className="min-w-0 flex-1">
                                <p className="text-sm font-medium">
                                    Extracción #{extraction.id}
                                </p>

                                <p className="mt-0.5 text-xs text-muted-foreground">
                                    {extraction.date_from ?? '—'} —{' '}
                                    {extraction.date_to ?? '—'}
                                </p>
                            </div>

                            <span className="text-xs text-muted-foreground">
                                {statusLabel(extraction.status)}
                            </span>
                        </div>
                    ))}
                </div>
            ) : (
                <div className="p-6 text-center text-sm text-muted-foreground">
                    No existen extracciones recientes.
                </div>
            )}
        </div>
    );
}