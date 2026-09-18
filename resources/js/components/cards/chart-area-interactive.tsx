'use client';

import * as React from 'react';
import { Area, AreaChart, CartesianGrid, XAxis } from 'recharts';

import { useIsMobile } from '@/hooks/use-mobile';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartContainer, ChartTooltip, ChartTooltipContent, type ChartConfig } from '@/components/ui/chart';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';

export interface FiscalChartPoint {
    date: string;
    issued: number;
    received: number;
}

interface ChartAreaInteractiveProps {
    data: FiscalChartPoint[];
}

const chartConfig = {
    amounts: {
        label: 'Monto',
    },
    issued: {
        label: 'Ventas (Emitidas)',
        color: 'var(--chart-1)',
    },
    received: {
        label: 'Compras (Recibidas)',
        color: 'var(--chart-2)',
    },
} satisfies ChartConfig;

export function ChartAreaInteractive({ data }: ChartAreaInteractiveProps) {
    const isMobile = useIsMobile();
    const [timeRange, setTimeRange] = React.useState('90d');

    React.useEffect(() => {
        if (isMobile) {
            setTimeRange('7d');
        }
    }, [isMobile]);

    const filteredData = React.useMemo(() => {
        if (!data.length) return [];

        const referenceDate = new Date(data[data.length - 1].date);
        let daysToSubtract = 90;
        if (timeRange === '30d') {
            daysToSubtract = 30;
        } else if (timeRange === '7d') {
            daysToSubtract = 7;
        }

        const startDate = new Date(referenceDate);
        startDate.setDate(startDate.getDate() - daysToSubtract);

        return data.filter((item) => new Date(item.date) >= startDate);
    }, [data, timeRange]);

    return (
        <Card className="@container/card">
            <CardHeader>
                <CardTitle>Ventas vs. Compras</CardTitle>
                <CardDescription>
                    <span className="hidden @[540px]/card:block">Comparativo de montos emitidos y recibidos</span>
                    <span className="@[540px]/card:hidden">Emitidos vs. recibidos</span>
                </CardDescription>
                <CardAction>
                    <ToggleGroup
                        type="single"
                        value={timeRange}
                        onValueChange={(value) => value && setTimeRange(value)}
                        variant="outline"
                        className="hidden *:data-[slot=toggle-group-item]:px-4! @[767px]/card:flex"
                    >
                        <ToggleGroupItem value="90d">Últimos 3 meses</ToggleGroupItem>
                        <ToggleGroupItem value="30d">Últimos 30 días</ToggleGroupItem>
                        <ToggleGroupItem value="7d">Últimos 7 días</ToggleGroupItem>
                    </ToggleGroup>
                    <Select value={timeRange} onValueChange={setTimeRange}>
                        <SelectTrigger
                            className="flex w-40 **:data-[slot=select-value]:block **:data-[slot=select-value]:truncate @[767px]/card:hidden"
                            size="sm"
                            aria-label="Seleccionar rango"
                        >
                            <SelectValue placeholder="Últimos 3 meses" />
                        </SelectTrigger>
                        <SelectContent className="rounded-xl">
                            <SelectItem value="90d" className="rounded-lg">
                                Últimos 3 meses
                            </SelectItem>
                            <SelectItem value="30d" className="rounded-lg">
                                Últimos 30 días
                            </SelectItem>
                            <SelectItem value="7d" className="rounded-lg">
                                Últimos 7 días
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </CardAction>
            </CardHeader>
            <CardContent className="px-2 pt-4 sm:px-6 sm:pt-6">
                {filteredData.length === 0 ? (
                    <div className="flex h-[250px] items-center justify-center text-sm text-muted-foreground">
                        No hay datos suficientes para mostrar el gráfico.
                    </div>
                ) : (
                    <ChartContainer config={chartConfig} className="aspect-auto h-[250px] w-full">
                        <AreaChart data={filteredData}>
                            <defs>
                                <linearGradient id="fillIssued" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="5%" stopColor="var(--color-issued)" stopOpacity={1.0} />
                                    <stop offset="95%" stopColor="var(--color-issued)" stopOpacity={0.1} />
                                </linearGradient>
                                <linearGradient id="fillReceived" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="5%" stopColor="var(--color-received)" stopOpacity={0.8} />
                                    <stop offset="95%" stopColor="var(--color-received)" stopOpacity={0.1} />
                                </linearGradient>
                            </defs>
                            <CartesianGrid vertical={false} />
                            <XAxis
                                dataKey="date"
                                tickLine={false}
                                axisLine={false}
                                tickMargin={8}
                                minTickGap={32}
                                tickFormatter={(value) =>
                                    new Date(value).toLocaleDateString('es-EC', { month: 'short', day: 'numeric' })
                                }
                            />
                            <ChartTooltip
                                cursor={false}
                                content={
                                    <ChartTooltipContent
                                        labelFormatter={(value) =>
                                            new Date(value).toLocaleDateString('es-EC', {
                                                month: 'short',
                                                day: 'numeric',
                                            })
                                        }
                                        formatter={(value, name) => [
                                            `$${Number(value).toFixed(2)}`,
                                            chartConfig[name as keyof typeof chartConfig]?.label ?? name,
                                        ]}
                                        indicator="dot"
                                    />
                                }
                            />
                            <Area
                                dataKey="received"
                                type="natural"
                                fill="url(#fillReceived)"
                                stroke="var(--color-received)"
                                stackId="a"
                            />
                            <Area
                                dataKey="issued"
                                type="natural"
                                fill="url(#fillIssued)"
                                stroke="var(--color-issued)"
                                stackId="a"
                            />
                        </AreaChart>
                    </ChartContainer>
                )}
            </CardContent>
        </Card>
    );
}