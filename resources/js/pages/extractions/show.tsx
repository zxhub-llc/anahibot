import { Head } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { Extraction } from '@/types/fiscal-extraction';

interface ExtractionRun {
    id: number;
    attempt_number: number;
    status: string;
    documents_found: number;
    documents_processed: number;
    documents_failed: number;
    error_message: string | null;
}

export default function Show({ extraction }: { extraction: Extraction & { runs?: ExtractionRun[] } }) {
    return (
        <>
            <Head title={`Extracción #${extraction.id}`} />

            <div className="space-y-6 p-6">
                <div className="flex items-center gap-3">
                    <h1 className="text-2xl font-semibold">Extracción #{extraction.id}</h1>
                    <Badge variant="outline">{extraction.status.replace(/_/g, ' ')}</Badge>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Detalle</CardTitle>
                    </CardHeader>
                    <CardContent className="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <div className="text-muted-foreground">RUC</div>
                            <div>{extraction.company_ruc}</div>
                        </div>
                        <div>
                            <div className="text-muted-foreground">Origen</div>
                            <div className="capitalize">{extraction.trigger.replace('_', ' ')}</div>
                        </div>
                        <div>
                            <div className="text-muted-foreground">Intentos</div>
                            <div>{extraction.attempts}</div>
                        </div>
                        <div>
                            <div className="text-muted-foreground">Completada</div>
                            <div>{extraction.completed_at ? new Date(extraction.completed_at).toLocaleString() : '—'}</div>
                        </div>
                        {extraction.last_error && (
                            <div className="col-span-2">
                                <div className="text-muted-foreground">Último error</div>
                                <div className="text-destructive">{extraction.last_error}</div>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {extraction.runs && extraction.runs.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Historial de ejecuciones</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {extraction.runs.map((run) => (
                                <div key={run.id} className="flex items-center justify-between border-b py-2 text-sm last:border-0">
                                    <span>Intento #{run.attempt_number}</span>
                                    <Badge variant="outline">{run.status}</Badge>
                                    <span className="text-muted-foreground">
                                        {run.documents_processed}/{run.documents_found} procesados · {run.documents_failed} fallidos
                                    </span>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}