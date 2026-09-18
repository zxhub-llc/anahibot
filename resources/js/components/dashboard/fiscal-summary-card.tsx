import {
    File02Icon,
    Invoice03Icon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';

import type { DashboardFiscalSummary } from '@/types/dashboard';

type Props = {
    title: string;
    description: string;
    summary: DashboardFiscalSummary;
    type: 'sales' | 'purchases';
};

const labels: Record<string, string> = {
    invoice: 'Facturas',
    credit_note: 'Notas de crédito',
    debit_note: 'Notas de débito',
    retention: 'Retenciones',
    purchase_settlement: 'Liquidaciones',
    remission_guide: 'Guías de remisión',
};

const money = (value: number) =>
    new Intl.NumberFormat('es-EC', {
        style: 'currency',
        currency: 'USD',
    }).format(value);

export function FiscalSummaryCard({
    title,
    description,
    summary,
    type,
}: Props) {
    const max = Math.max(
        ...summary.by_type.map((item) => item.total),
        1,
    );

    return (
        <div className="rounded-2xl border bg-card shadow-sm">
            <div className="border-b p-5">
                <div className="flex items-center gap-3">
                    <div className="flex size-10 items-center justify-center rounded-xl bg-muted">
                        <HugeiconsIcon
                            icon={
                                type === 'sales'
                                    ? Invoice03Icon
                                    : File02Icon
                            }
                            className="size-5"
                        />
                    </div>

                    <div>
                        <h2 className="font-semibold">{title}</h2>
                        <p className="text-xs text-muted-foreground">
                            {description}
                        </p>
                    </div>
                </div>

                <p className="mt-5 text-3xl font-semibold tracking-tight">
                    {money(summary.total)}
                </p>
            </div>

            <div className="space-y-4 p-5">
                {summary.by_type.length > 0 ? (
                    summary.by_type.map((item) => (
                        <div key={item.type}>
                            <div className="mb-1.5 flex items-center justify-between gap-3">
                                <span className="text-xs text-muted-foreground">
                                    {labels[item.type] ?? item.type}
                                </span>

                                <span className="text-xs font-medium">
                                    {money(item.total)}
                                </span>
                            </div>

                            <div className="h-2 overflow-hidden rounded-full bg-muted">
                                <div
                                    className="h-full rounded-full bg-foreground/80 transition-all"
                                    style={{
                                        width: `${Math.max(
                                            4,
                                            (item.total / max) * 100,
                                        )}%`,
                                    }}
                                />
                            </div>

                            <p className="mt-1 text-[11px] text-muted-foreground">
                                {item.count} documentos
                            </p>
                        </div>
                    ))
                ) : (
                    <p className="text-sm text-muted-foreground">
                        No hay documentos disponibles.
                    </p>
                )}
            </div>
        </div>
    );
}