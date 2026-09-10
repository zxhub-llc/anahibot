import { Head, usePage } from '@inertiajs/react';
import {
    xml as downloadXml,
    pdf as downloadPdf,
    json as downloadJson
} from '@/routes/documents/download';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { fiscalDocumentTypeLabels } from '@/types/fiscal-navigation';
import type { FiscalDocument } from '@/types/fiscal-extraction';

// Responsabilidad: detalle de un FiscalDocument individual con sus 3 descargas disponibles.

export default function Show({ document }: { document: FiscalDocument }) {
    const { current_team } = usePage().props as unknown as { current_team: { slug: string } };

    return (
        <>
            <Head title={`Documento ${document.access_key}`} />

            <div className="space-y-6 p-6">
                <div className="flex items-center gap-3">
                    <h1 className="text-2xl font-semibold">{fiscalDocumentTypeLabels[document.type]}</h1>
                    {document.is_voided && <Badge variant="destructive">Anulado</Badge>}
                    <Badge variant="outline">{document.direction === 'received' ? 'Recibido' : 'Emitido'}</Badge>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Detalle</CardTitle>
                    </CardHeader>
                    <CardContent className="grid grid-cols-2 gap-4 text-sm">
                        <div className="col-span-2">
                            <div className="text-muted-foreground">Clave de acceso</div>
                            <div className="font-mono text-xs">{document.access_key}</div>
                        </div>
                        <div>
                            <div className="text-muted-foreground">Emisor</div>
                            <div>{document.issuer_name ?? '—'} ({document.issuer_ruc ?? '—'})</div>
                        </div>
                        <div>
                            <div className="text-muted-foreground">Receptor</div>
                            <div>{document.receiver_name ?? '—'} ({document.receiver_ruc ?? '—'})</div>
                        </div>
                        <div>
                            <div className="text-muted-foreground">Fecha de emisión</div>
                            <div>{document.issued_at ?? '—'}</div>
                        </div>
                        <div>
                            <div className="text-muted-foreground">Estado de procesamiento</div>
                            <div>{document.status}</div>
                        </div>
                        <div>
                            <div className="text-muted-foreground">Subtotal</div>
                            <div>{document.subtotal !== null ? `$${document.subtotal.toFixed(2)}` : '—'}</div>
                        </div>
                        <div>
                            <div className="text-muted-foreground">Impuestos</div>
                            <div>{document.tax_amount !== null ? `$${document.tax_amount.toFixed(2)}` : '—'}</div>
                        </div>
                        <div>
                            <div className="text-muted-foreground">Total</div>
                            <div className="font-semibold">{document.total !== null ? `$${document.total.toFixed(2)}` : '—'}</div>
                        </div>
                    </CardContent>
                </Card>

                <div className="flex gap-3">
                    <Button
                        variant="outline"
                        disabled={!document.has_xml}
                        onClick={() => window.location.assign(downloadXml({ current_team: current_team.slug, document: document.id }).url)}
                    >
                        Descargar XML
                    </Button>
                    <Button
                        variant="outline"
                        disabled={!document.has_pdf}
                        onClick={() => window.location.assign(downloadPdf({ current_team: current_team.slug, document: document.id }).url)}
                    >
                        Descargar PDF
                    </Button>
                    <Button
                        variant="outline"
                        disabled={!document.has_json}
                        onClick={() => window.location.assign(downloadJson({ current_team: current_team.slug, document: document.id }).url)}
                    >
                        Descargar JSON
                    </Button>
                </div>
            </div>
        </>
    );
}