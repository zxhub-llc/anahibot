import { Head, router, usePage } from '@inertiajs/react';
import { store } from '@/routes/extractions';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { DateRange } from 'react-day-picker';
import { DateRangePicker } from '@/components/extractions/date-range-picker';
import { DataTable } from '@/components/extractions/data-table';
import { buildColumns } from '@/components/extractions/columns';
import type { Extraction, Paginated } from '@/types/fiscal-extraction';

interface Flash {
    toast?: { type: 'success' | 'error'; message: string };
}

export default function Index({ extractions }: { extractions: Paginated<Extraction> }) {
    const { currentTeam, flash } = usePage<{ flash: Flash }>().props;
    const teamSlug = currentTeam?.slug ?? '';

    const [companyRuc, setCompanyRuc] = useState('');
    const [range, setRange] = useState<DateRange | undefined>();
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        if (flash?.toast) {
            const { type, message } = flash.toast;
            if (type === 'success') toast.success(message);
            else toast.error(message);
        }
    }, [flash]);

    useEffect(() => {
        const hasRunning = extractions.data.some((e) => ['queued', 'running'].includes(e.status));
        if (!hasRunning) return;

        const interval = setInterval(() => {
            router.reload({ only: ['extractions'] });
        }, 4000);

        return () => clearInterval(interval);
    }, [extractions.data]);

    function handleCreate(event: React.FormEvent) {
        event.preventDefault();
        if (!teamSlug || !range?.from || !range?.to) {
            toast.error('Selecciona el RUC y el rango de fechas.');
            return;
        }

        setSubmitting(true);

        router.post(
            store({ current_team: teamSlug }).url,
            {
                company_ruc: companyRuc,
                date_from: range.from.toISOString().slice(0, 10),
                date_to: range.to.toISOString().slice(0, 10),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setCompanyRuc('');
                    setRange(undefined);
                },
                onFinish: () => setSubmitting(false),
            },
        );
    }

    const columns = buildColumns(teamSlug);

    return (
        <>
            <Head title="Extracciones" />
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">Extracciones</h1>
                </div>

                <form onSubmit={handleCreate} className="flex flex-wrap items-end gap-3">
                    <div className="flex flex-col gap-1">
                        <label className="text-sm font-medium" htmlFor="company_ruc">
                            RUC de la compañía
                        </label>
                        <Input
                            id="company_ruc"
                            value={companyRuc}
                            onChange={(event) => setCompanyRuc(event.target.value)}
                            placeholder="1790012345001"
                            maxLength={13}
                            required
                        />
                    </div>
                    <div className="flex flex-col gap-1">
                        <label className="text-sm font-medium">Rango de fechas</label>
                        <DateRangePicker value={range} onChange={setRange} />
                    </div>
                    <Button type="submit" disabled={!teamSlug || submitting}>
                        {submitting ? 'Enviando...' : 'Nueva extracción'}
                    </Button>
                </form>

                <DataTable columns={columns} data={extractions.data} />
            </div>
        </>
    );
}