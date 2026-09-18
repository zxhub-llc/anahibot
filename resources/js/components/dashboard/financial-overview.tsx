import {
    ShoppingCart02Icon,
    AnalyticsUpIcon,
    Chart02Icon,
    TaxesIcon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';

import { FinancialStatCard } from './financial-stat-card';

type Summary = {
    sales: {
        total: number;
        subtotal: number;
        tax_amount: number;
        by_type: {
            type: string;
            total: number;
            count: number;
        }[];
    };
    purchases: {
        total: number;
        subtotal: number;
        tax_amount: number;
        by_type: {
            type: string;
            total: number;
            count: number;
        }[];
    };
    voided_count: number;
};

type Props = {
    summary?: Summary;
};

const money = (value: number) =>
    new Intl.NumberFormat('es-EC', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
    }).format(value);

export function FinancialOverview({ summary }: Props) {
    const sales = summary?.sales.total ?? 0;
    const purchases = summary?.purchases.total ?? 0;

    const net = sales - purchases;

    const tax =
        (summary?.sales.tax_amount ?? 0) -
        (summary?.purchases.tax_amount ?? 0);

    return (
        <section className="space-y-4">
            {/* <div>
                <h2 className="text-lg font-semibold tracking-tight">
                    Resumen financiero
                </h2>

                <p className="text-sm text-muted-foreground">
                    Comportamiento consolidado de tus documentos fiscales.
                </p>
            </div> */}

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <FinancialStatCard
                    title="Ventas"
                    value={money(sales)}
                    description="Documentos emitidos"
                    icon={(props) => (
                        <HugeiconsIcon
                            icon={AnalyticsUpIcon}
                            {...props}
                        />
                    )}
                    iconClassName="text-emerald-600"
                />

                <FinancialStatCard
                    title="Compras"
                    value={money(purchases)}
                    description="Documentos recibidos"
                    icon={(props) => (
                        <HugeiconsIcon
                            icon={ShoppingCart02Icon}
                            {...props}
                        />
                    )}
                    iconClassName="text-orange-600"
                />

                <FinancialStatCard
                    title="Resultado"
                    value={money(net)}
                    description="Ventas menos compras"
                    icon={(props) => (
                        <HugeiconsIcon
                            icon={Chart02Icon}
                            {...props}
                        />
                    )}
                />

                <FinancialStatCard
                    title="IVA neto"
                    value={money(tax)}
                    description={`${summary?.voided_count ?? 0} documentos anulados`}
                    icon={(props) => (
                        <HugeiconsIcon
                            icon={TaxesIcon}
                            {...props}
                        />
                    )}
                    iconClassName="text-red-500"
                />
            </div>
        </section>
    );
}