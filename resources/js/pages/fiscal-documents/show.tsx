import { useEffect, useState } from 'react';
import { Head } from '@inertiajs/react';
import {
    xml as downloadXml,
    pdf as downloadPdf,
    json as downloadJson,
    zip as downloadZip,
} from '@/routes/documents/download';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Download, FileArchive, Loader2 } from 'lucide-react';
import { fiscalDocumentTypeLabels } from '@/types/fiscal-navigation';
import type { FiscalDocument } from '@/types/fiscal-extraction';
import Heading from '@/components/heading';

type FormatKey = 'xml' | 'pdf' | 'json';

interface ShowProps {
    document: FiscalDocument;
    current_team: { slug: string };
}

type RouteFn = (params: { current_team: string; document: number }) => { url: string };

const FORMAT_CONFIG: Record<FormatKey, { label: string; getUrl: RouteFn }> = {
    xml: { label: 'XML', getUrl: downloadXml },
    pdf: { label: 'PDF', getUrl: downloadPdf },
    json: { label: 'JSON', getUrl: downloadJson },
};

export default function Show({ document, current_team }: ShowProps) {
    const availableFormats = (['xml', 'pdf', 'json'] as FormatKey[]).filter((format) => {
        if (format === 'xml') return document.has_xml;
        if (format === 'pdf') return document.has_pdf;
        return document.has_json;
    });

    const [activeFormat, setActiveFormat] = useState<FormatKey | null>(availableFormats[0] ?? null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);
    const [previewText, setPreviewText] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!activeFormat) return;

        let cancelled = false;
        let objectUrl: string | null = null;

        setLoading(true);
        setError(null);
        setPreviewUrl(null);
        setPreviewText(null);

        const { url } = FORMAT_CONFIG[activeFormat].getUrl({
            current_team: current_team.slug,
            document: document.id,
        });

        fetch(url, { credentials: 'same-origin' })
            .then(async (response) => {
                if (!response.ok) throw new Error('No se pudo cargar el archivo.');

                if (activeFormat === 'pdf') {
                    const blob = await response.blob();
                    if (cancelled) return;
                    objectUrl = URL.createObjectURL(blob);
                    setPreviewUrl(objectUrl);
                    return;
                }

                const text = await response.text();
                if (cancelled) return;

                if (activeFormat === 'json') {
                    try {
                        setPreviewText(JSON.stringify(JSON.parse(text), null, 2));
                    } catch {
                        setPreviewText(text);
                    }
                } else {
                    setPreviewText(text);
                }
            })
            .catch((err: Error) => {
                if (!cancelled) setError(err.message || 'Ocurrió un error al cargar la vista previa.');
            })
            .finally(() => {
                if (!cancelled) setLoading(false);
            });

        return () => {
            cancelled = true;
            if (objectUrl) URL.revokeObjectURL(objectUrl);
        };
    }, [activeFormat, current_team.slug, document.id]);

    function handleDownload(format: FormatKey) {
        const { url } = FORMAT_CONFIG[format].getUrl({ current_team: current_team.slug, document: document.id });
        window.location.assign(url);
    }

    function handleDownloadZip() {
        window.location.assign(downloadZip({ current_team: current_team.slug, document: document.id }).url);
    }

    return (
        <>
            <Head title={`Documento ${document.access_key}`} />

            <div className="flex flex-col gap-6 p-4 sm:p-6">
                <Heading
                    variant="small"
                    title="Documentos Fiscales"
                    description="Consulta y descarga los comprobantes electrónicos emitidos y recibidos."
                />

                <div className="flex flex-wrap items-center gap-3">
                    <h2 className="text-xl font-semibold">{fiscalDocumentTypeLabels[document.type]}</h2>
                    {document.is_voided && <Badge variant="destructive">Anulado</Badge>}
                    <Badge variant="outline">{document.direction === 'received' ? 'Recibido' : 'Emitido'}</Badge>
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2 lg:items-start">
                    {/* Izquierda: datos del comprobante */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Detalle</CardTitle>
                        </CardHeader>
                        <CardContent className="grid grid-cols-1 gap-4 text-sm">
                            <div className="grid grid-cols-3 gap-4">
                                <div className="col-span-1">
                                    <div className="text-muted-foreground">Fecha de emisión</div>
                                    <div>{document.issued_at ?? '—'}</div>
                                </div>
                                <div className="col-span-2">
                                    <div className="text-muted-foreground">Clave de acceso</div>
                                    <div className="break-all font-mono text-xs">{document.access_key}</div>
                                </div>
                            </div>
                            <div>
                                <div className="text-muted-foreground">Emisor</div>
                                <div>
                                    {document.issuer_name ?? '—'} ({document.issuer_ruc ?? '—'})
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <div className="text-muted-foreground">Receptor</div>
                                    <div>
                                        {document.receiver_name ?? '—'} ({document.receiver_ruc ?? '—'})
                                    </div>
                                </div>
                                <div>
                                    <div className="text-muted-foreground">Estado de procesamiento</div>
                                    <div>{document.status}</div>
                                </div>
                            </div>
                            <div className="grid grid-cols-3 gap-4">
                                <div>
                                    <div className="text-muted-foreground">Subtotal</div>
                                    <div>{document.subtotal !== null ? `$${Number(document.subtotal).toFixed(2)}` : '—'}</div>
                                </div>
                                <div>
                                    <div className="text-muted-foreground">Impuestos</div>
                                    <div>{document.tax_amount !== null ? `$${Number(document.tax_amount).toFixed(2)}` : '—'}</div>
                                </div>
                                <div>
                                    <div className="text-muted-foreground">Total</div>
                                    <div className="font-semibold">
                                        {document.total !== null ? `$${Number(document.total).toFixed(2)}` : '—'}
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Derecha: vista previa del documento renderizado */}
                    <Card className="flex flex-col">
                        <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <CardTitle>Vista previa</CardTitle>
                            {availableFormats.length > 0 && (
                                <Tabs
                                    value={activeFormat ?? undefined}
                                    onValueChange={(value) => setActiveFormat(value as FormatKey)}
                                >
                                    <TabsList>
                                        {availableFormats.map((format) => (
                                            <TabsTrigger key={format} value={format}>
                                                {FORMAT_CONFIG[format].label}
                                            </TabsTrigger>
                                        ))}
                                    </TabsList>
                                </Tabs>
                            )}
                        </CardHeader>

                        <CardContent className="flex flex-1 flex-col gap-4">
                            <div className="min-h-[420px] flex-1 overflow-hidden rounded-md border bg-muted/30">
                                {!activeFormat && (
                                    <div className="flex h-full min-h-[420px] items-center justify-center p-6 text-center text-sm text-muted-foreground">
                                        No hay archivos disponibles para este comprobante.
                                    </div>
                                )}

                                {activeFormat && loading && (
                                    <div className="flex h-full min-h-[420px] items-center justify-center gap-2 p-6 text-sm text-muted-foreground">
                                        <Loader2 className="h-4 w-4 animate-spin" />
                                        Cargando {FORMAT_CONFIG[activeFormat].label}…
                                    </div>
                                )}

                                {activeFormat && !loading && error && (
                                    <div className="flex h-full min-h-[420px] items-center justify-center p-6 text-center text-sm text-destructive">
                                        {error}
                                    </div>
                                )}

                                {activeFormat === 'pdf' && !loading && !error && previewUrl && (
                                    <iframe
                                        title="Vista previa PDF"
                                        src={previewUrl}
                                        className="h-full min-h-[420px] w-full"
                                    />
                                )}

                                {(activeFormat === 'xml' || activeFormat === 'json') && !loading && !error && previewText && (
                                    <pre className="h-full max-h-[420px] overflow-auto whitespace-pre-wrap break-all p-4 font-mono text-xs">
                                        {previewText}
                                    </pre>
                                )}
                            </div>

                            <div className="flex flex-col gap-2 sm:flex-row">
                                <Button
                                    className="flex-1"
                                    disabled={!activeFormat}
                                    onClick={() => activeFormat && handleDownload(activeFormat)}
                                >
                                    <Download className="h-4 w-4" />
                                    Descargar {activeFormat ? FORMAT_CONFIG[activeFormat].label : ''}
                                </Button>
                                <Button
                                    variant="outline"
                                    className="flex-1"
                                    disabled={availableFormats.length < 2}
                                    onClick={handleDownloadZip}
                                >
                                    <FileArchive className="h-4 w-4" />
                                    Descargar ZIP
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

Show.layout = (props: { current_team?: { slug: string } | null; document?: { access_key: string } }) => ({
    breadcrumbs: [
        {
            title: 'Documentos',
            href: props.current_team ? `/${props.current_team.slug}/documents` : '/',
        },
        {
            title: props.document?.access_key ?? 'Detalle',
            href: '#',
        },
    ],
});