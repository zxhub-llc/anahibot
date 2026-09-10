import { Head, Link, router, usePage } from '@inertiajs/react';
import { store, retry, cancel } from '@/routes/extractions';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Input } from '@/components/ui/input';
import { useState } from 'react';
import type { Extraction, Paginated } from '@/types/fiscal-extraction';

const statusVariant: Record<Extraction['status'], 'default' | 'secondary' | 'destructive' | 'outline'> = {
    pending: 'secondary',
    queued: 'secondary',
    running: 'default',
    completed: 'outline',
    completed_with_errors: 'destructive',
    failed: 'destructive',
    cancelled: 'secondary',
};

export default function Index({ extractions }: { extractions: Paginated<Extraction> }) {
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';

    const [companyRuc, setCompanyRuc] = useState('');

    function handleCreate(event: React.FormEvent) {
        event.preventDefault();
        if (!teamSlug) return;

        router.visit(store({ current_team: teamSlug }), {
            method: 'post',
            data: { company_ruc: companyRuc },
            preserveScroll: true,
        });
    }

    return (
        <>
            <Head title="Extracciones" />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">Extracciones</h1>
                </div>

                <form onSubmit={handleCreate} className="flex items-end gap-3">
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
                    <Button type="submit" disabled={!teamSlug}>Nueva extracción</Button>
                </form>

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>RUC</TableHead>
                            <TableHead>Origen</TableHead>
                            <TableHead>Estado</TableHead>
                            <TableHead>Intentos</TableHead>
                            <TableHead>Creada</TableHead>
                            <TableHead className="text-right">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {extractions.data.map((extraction) => (
                            <TableRow key={extraction.id}>
                                <TableCell>
                                    <Link href={`/${teamSlug}/extractions/${extraction.id}`} className="underline underline-offset-4">
                                        {extraction.company_ruc}
                                    </Link>
                                </TableCell>
                                <TableCell className="capitalize">{extraction.trigger.replace('_', ' ')}</TableCell>
                                <TableCell>
                                    <Badge variant={statusVariant[extraction.status]}>{extraction.status.replace(/_/g, ' ')}</Badge>
                                </TableCell>
                                <TableCell>{extraction.attempts}</TableCell>
                                <TableCell>{new Date(extraction.created_at).toLocaleString()}</TableCell>
                                <TableCell className="flex justify-end gap-2">
                                    {(extraction.status === 'failed' || extraction.status === 'completed_with_errors') && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            disabled={!teamSlug}
                                            onClick={() =>
                                                router.post(
                                                    retry({ current_team: teamSlug, extraction: extraction.id }).url,
                                                )
                                            }
                                        >
                                            Reintentar
                                        </Button>
                                    )}
                                    {!['completed', 'completed_with_errors', 'failed', 'cancelled'].includes(extraction.status) && (
                                        <Button
                                            size="sm"
                                            variant="destructive"
                                            disabled={!teamSlug}
                                            onClick={() =>
                                                router.post(
                                                    cancel({ current_team: teamSlug, extraction: extraction.id }).url,
                                                )
                                            }
                                        >
                                            Cancelar
                                        </Button>
                                    )}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
        </>
    );
}