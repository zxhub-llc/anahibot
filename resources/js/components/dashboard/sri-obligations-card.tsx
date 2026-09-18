import {
    Alert02Icon,
    ArrowRight01Icon,
    Calendar03Icon,
    CheckmarkCircle02Icon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';

import type { DashboardSriObligation } from '@/types/dashboard';

type Props = {
    obligations?: DashboardSriObligation[];
};

const money = (value: number | null) =>
    value === null
        ? '—'
        : new Intl.NumberFormat('es-EC', {
            style: 'currency',
            currency: 'USD',
        }).format(value);

export function SriObligationsCard({
    obligations = [],
}: Props) {
    const overdue = obligations.filter(
        (obligation) => obligation.status === 'overdue',
    );

    const pending = obligations.filter(
        (obligation) => obligation.status === 'pending',
    );

    return (
        <div className="rounded-2xl border bg-card shadow-sm">
            <div className="flex flex-col gap-4 border-b p-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                        Cumplimiento tributario
                    </p>

                    <h2 className="mt-1 text-lg font-semibold">
                        Obligaciones tributarias
                    </h2>

                    <p className="mt-1 text-sm text-muted-foreground">
                        Listado de obligaciones tributarias que el SRI le ha
                        asignado.
                    </p>
                </div>

                <div className="flex gap-2">
                    <div className="rounded-xl bg-red-500/10 px-3 py-2 text-center">
                        <p className="text-lg font-semibold text-red-600">
                            {overdue.length}
                        </p>

                        <p className="text-[11px] text-red-600/80">
                            Vencidas
                        </p>
                    </div>

                    <div className="rounded-xl bg-amber-500/10 px-3 py-2 text-center">
                        <p className="text-lg font-semibold text-amber-600">
                            {pending.length}
                        </p>

                        <p className="text-[11px] text-amber-600/80">
                            Pendientes
                        </p>
                    </div>
                </div>
            </div>

            {obligations.length > 0 ? (
                <div className="divide-y">
                    {obligations.map((obligation) => {
                        const isOverdue =
                            obligation.status === 'overdue';

                        const isCompleted =
                            obligation.status === 'completed';

                        return (
                            <div
                                key={obligation.id}
                                className="flex flex-col gap-4 p-4 sm:flex-row sm:items-center"
                            >
                                <div
                                    className={`flex size-10 shrink-0 items-center justify-center rounded-xl ${isOverdue
                                        ? 'bg-red-500/10 text-red-600'
                                        : isCompleted
                                            ? 'bg-emerald-500/10 text-emerald-600'
                                            : 'bg-amber-500/10 text-amber-600'
                                        }`}
                                >
                                    <HugeiconsIcon
                                        icon={
                                            isOverdue
                                                ? Alert02Icon
                                                : isCompleted
                                                    ? CheckmarkCircle02Icon
                                                    : Calendar03Icon
                                        }
                                        className="size-5"
                                    />
                                </div>

                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <p className="text-sm font-medium">
                                            {obligation.name}
                                        </p>

                                        {obligation.code && (
                                            <span className="rounded-md bg-muted px-1.5 py-0.5 font-mono text-[10px] text-muted-foreground">
                                                {obligation.code}
                                            </span>
                                        )}
                                    </div>

                                    <div className="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                        {obligation.period && (
                                            <span>
                                                Período:{' '}
                                                {obligation.period}
                                            </span>
                                        )}

                                        {obligation.due_date && (
                                            <span>
                                                Vencimiento:{' '}
                                                {obligation.due_date}
                                            </span>
                                        )}
                                    </div>
                                </div>

                                <div className="text-left sm:text-right">
                                    <p
                                        className={`text-sm font-semibold ${isOverdue
                                            ? 'text-red-600'
                                            : ''
                                            }`}
                                    >
                                        {isOverdue
                                            ? 'VENCIDA'
                                            : isCompleted
                                                ? 'Cumplida'
                                                : 'Pendiente'}
                                    </p>

                                    {obligation.amount !== null && (
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {money(obligation.amount)}
                                        </p>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>
            ) : (
                <div className="flex min-h-[220px] flex-col items-center justify-center p-6 text-center">
                    <div className="flex size-12 items-center justify-center rounded-full bg-muted">
                        <HugeiconsIcon
                            icon={Calendar03Icon}
                            className="size-5 text-muted-foreground"
                        />
                    </div>

                    <p className="mt-4 text-sm font-medium">
                        No hay obligaciones sincronizadas
                    </p>

                    <p className="mt-1 max-w-md text-xs leading-5 text-muted-foreground">
                        Cuando el sistema consulte las obligaciones
                        tributarias asignadas por el SRI, aparecerán en esta
                        sección.
                    </p>
                </div>
            )}

            <div className="border-t p-4">
                <button
                    type="button"
                    className="flex w-full items-center justify-center gap-2 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                >
                    Consultar obligaciones

                    <HugeiconsIcon
                        icon={ArrowRight01Icon}
                        className="size-4"
                    />
                </button>
            </div>
        </div>
    );
}