import type { ComponentType } from 'react';

type FinancialStatCardProps = {
    title: string;
    value: string;
    description: string;
    icon: ComponentType<{ className?: string }>;
    iconClassName?: string;
};

export function FinancialStatCard({
    title,
    value,
    description,
    icon: Icon,
    iconClassName,
}: FinancialStatCardProps) {
    return (
        <div className="rounded-2xl border bg-card p-5 shadow-sm">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="text-sm text-muted-foreground">{title}</p>

                    <p className="mt-2 text-2xl font-semibold tracking-tight">
                        {value}
                    </p>

                    <p className="mt-1 text-xs text-muted-foreground">
                        {description}
                    </p>
                </div>

                <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-muted">
                    <Icon
                        className={`size-5 ${iconClassName ?? 'text-foreground'}`}
                    />
                </div>
            </div>
        </div>
    );
}