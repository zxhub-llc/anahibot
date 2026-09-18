import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { fiscalDocumentTypeLabels } from '@/types/fiscal-navigation';
import { BanIcon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';

export interface FiscalSummaryByType {
    type: string;
    total: number;
    count: number;
}

export interface FiscalSummaryGroup {
    total: number;
    subtotal: number;
    tax_amount: number;
    by_type: FiscalSummaryByType[];
}

export interface FiscalSummary {
    sales: FiscalSummaryGroup;
    purchases: FiscalSummaryGroup;
    voided_count: number;
}

interface SectionCardsProps {
    summary: FiscalSummary;
}

function formatMoney(value: number): string {
    return `$${value.toFixed(2)}`;
}

function typeLabel(type: string): string {
    return fiscalDocumentTypeLabels[type as keyof typeof fiscalDocumentTypeLabels] ?? type;
}

function SummaryCard({ title, group }: { title: string; group: FiscalSummaryGroup }) {
    const documentCount = group.by_type.reduce((acc, item) => acc + item.count, 0);

    return (
        <Card className="@container/card">
            <CardHeader>
                <CardDescription>{title}</CardDescription>
                <CardTitle className="text-2xl font-semibold tabular-nums @[250px]/card:text-3xl">
                    {formatMoney(group.total)}
                </CardTitle>
                <CardAction>
                    <Badge variant="outline">{documentCount} docs.</Badge>
                </CardAction>
            </CardHeader>

            <CardContent className="flex flex-col gap-2 text-sm">
                <div className="flex items-center justify-between text-muted-foreground">
                    <span>Subtotal</span>
                    <span className="tabular-nums">{formatMoney(group.subtotal)}</span>
                </div>
                <div className="flex items-center justify-between text-muted-foreground">
                    <span>IVA</span>
                    <span className="tabular-nums">{formatMoney(group.tax_amount)}</span>
                </div>
            </CardContent>

            <CardFooter className="flex-col items-start gap-2 border-t pt-4 text-sm">
                {group.by_type.length ? (
                    group.by_type.map((item) => (
                        <div key={item.type} className="flex w-full items-center justify-between">
                            <span className="text-muted-foreground">{typeLabel(item.type)}</span>
                            <span className="font-medium tabular-nums">{formatMoney(item.total)}</span>
                        </div>
                    ))
                ) : (
                    <span className="text-muted-foreground">Sin documentos en este período.</span>
                )}
            </CardFooter>
        </Card>
    );
}

export function SectionCards({ summary }: SectionCardsProps) {
    const grandTotal = summary.sales.total + summary.purchases.total;
    const totalDocuments =
        summary.sales.by_type.reduce((acc, item) => acc + item.count, 0) +
        summary.purchases.by_type.reduce((acc, item) => acc + item.count, 0);

    return (
        <div className="@container/main grid grid-cols-1 gap-4 *:data-[slot=card]:bg-gradient-to-t *:data-[slot=card]:from-primary/5 *:data-[slot=card]:to-card *:data-[slot=card]:shadow-xs @xl/main:grid-cols-2 @5xl/main:grid-cols-4 dark:*:data-[slot=card]:bg-card">
            <SummaryCard title="Ventas (Emitidos)" group={summary.sales} />
            <SummaryCard title="Compras (Recibidos)" group={summary.purchases} />

            <Card className="@container/card">
                <CardHeader>
                    <CardDescription>Total General</CardDescription>
                    <CardTitle className="text-2xl font-semibold tabular-nums @[250px]/card:text-3xl">
                        {formatMoney(grandTotal)}
                    </CardTitle>
                </CardHeader>
                <CardFooter className="flex-col items-start gap-1.5 text-sm">
                    <div className="text-muted-foreground">{totalDocuments} documentos procesados</div>
                </CardFooter>
            </Card>

            <Card className="@container/card">
                <CardHeader>
                    <CardDescription>Documentos Anulados</CardDescription>
                    <CardTitle className="text-2xl font-semibold tabular-nums @[250px]/card:text-3xl">
                        {summary.voided_count}
                    </CardTitle>
                    <CardAction>
                        <Badge variant="destructive">
                            <HugeiconsIcon icon={BanIcon} className="size-3.5" />
                            Anulados
                        </Badge>
                    </CardAction>
                </CardHeader>
                <CardFooter className="flex-col items-start gap-1.5 text-sm">
                    <div className="text-muted-foreground">Excluidos del cálculo de ventas y compras</div>
                </CardFooter>
            </Card>
        </div>
    );
}