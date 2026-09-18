import {
    columnFilteringFeature,
    columnVisibilityFeature,
    createColumnHelper,
    createFilteredRowModel,
    rowSortingFeature,
    tableFeatures,
} from '@tanstack/react-table';
import { Link, router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { retry, cancel } from '@/routes/extractions';
import type { Extraction } from '@/types/fiscal-extraction';

export const extractionTableFeatures = tableFeatures({
    columnFilteringFeature,
    columnVisibilityFeature,
    rowSortingFeature,
    filteredRowModel: createFilteredRowModel(),
});

const columnHelper = createColumnHelper<typeof extractionTableFeatures, Extraction>();

const statusVariant: Record<Extraction['status'], 'default' | 'secondary' | 'destructive' | 'outline'> = {
    pending: 'secondary',
    queued: 'secondary',
    running: 'default',
    completed: 'outline',
    completed_with_errors: 'destructive',
    failed: 'destructive',
    cancelled: 'secondary',
};

export function buildColumns(teamSlug: string) {
    return columnHelper.columns([
        columnHelper.accessor('company_ruc', {
            header: 'RUC',
            cell: ({ row }) => (
                <Link href={`/${teamSlug}/extractions/${row.original.id}`} className="underline underline-offset-4">
                    {row.original.company_ruc}
                </Link>
            ),
        }),
        columnHelper.display({
            id: 'range',
            header: 'Rango',
            cell: ({ row }) => `${row.original.date_from} → ${row.original.date_to}`,
        }),
        columnHelper.accessor('trigger', {
            header: 'Origen',
            cell: ({ row }) => <span className="capitalize">{row.original.trigger.replace('_', ' ')}</span>,
        }),
        columnHelper.accessor('status', {
            header: 'Estado',
            cell: ({ row }) => {
                const status = row.original.status;
                return <Badge variant={statusVariant[status]}>{status.replace(/_/g, ' ')}</Badge>;
            },
        }),
        columnHelper.display({
            id: 'progress',
            header: 'Progreso',
            cell: ({ row }) => {
                const e = row.original;
                const inProgress = e.status === 'running' || e.status === 'queued';

                if (!inProgress && e.documents_found === 0) {
                    return <span className="text-xs text-muted-foreground">—</span>;
                }

                return (
                    <div className="flex w-40 items-center gap-2">
                        <Progress value={e.progress} className="h-2" />
                        <span className="text-xs text-muted-foreground">
                            {e.documents_processed + e.documents_failed}/{e.documents_found}
                        </span>
                    </div>
                );
            },
        }),
        columnHelper.accessor('attempts', {
            header: 'Intentos',
        }),
        columnHelper.accessor('created_at', {
            header: 'Creada',
            cell: ({ row }) => new Date(row.original.created_at).toLocaleString(),
        }),
        columnHelper.display({
            id: 'actions',
            header: () => <div className="text-right">Acciones</div>,
            cell: ({ row }) => {
                const extraction = row.original;

                return (
                    <div className="flex justify-end gap-2">
                        {(extraction.status === 'failed' || extraction.status === 'completed_with_errors') && (
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => router.post(retry({ current_team: teamSlug, extraction: extraction.id }).url)}
                            >
                                Reintentar
                            </Button>
                        )}
                        {!['completed', 'completed_with_errors', 'failed', 'cancelled'].includes(extraction.status) && (
                            <Button
                                size="sm"
                                variant="destructive"
                                onClick={() => router.post(cancel({ current_team: teamSlug, extraction: extraction.id }).url)}
                            >
                                Cancelar
                            </Button>
                        )}
                    </div>
                );
            },
        }),
    ]);
}