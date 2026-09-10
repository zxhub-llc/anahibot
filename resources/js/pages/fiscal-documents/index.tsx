
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    xml as downloadXml,
    pdf as downloadPdf,
    json as downloadJson
} from '@/routes/documents/download';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { FileDown, FileJson, FileText } from 'lucide-react';
import { fiscalDocumentNavigation, fiscalDocumentTypeLabels, navItemQuery } from '@/types/fiscal-navigation';
import type { FiscalDocument, Paginated } from '@/types/fiscal-extraction';

interface Props {
    documents: Paginated<FiscalDocument>;
    filters: { direction?: string; type?: string; voided?: string };
}

export default function Index({ documents, filters }: Props) {
    const { current_team } = usePage().props as unknown as { current_team: { slug: string } };

    function isActive(direction: string, type?: string, voided?: boolean) {
        if (filters.direction !== direction) return false;
        if (voided) return filters.voided === '1';
        return filters.type === type && filters.voided !== '1';
    }

    function goTo(query: Record<string, string>) {
        router.get(`/${current_team.slug}/documents`, query, { preserveState: true, preserveScroll: true });
    }

    return (
        <>
            <Head title="Documentos Fiscales" />

            <div className="grid grid-cols-[240px_1fr] gap-6 p-6">
                <aside className="space-y-6">
                    {fiscalDocumentNavigation.map((group) => (
                        <div key={group.label}>
                            <h3 className="mb-2 text-xs font-semibold uppercase text-muted-foreground">{group.label}</h3>
                            <ul className="space-y-1">
                                {group.items.map((item) => (
                                    <li key={`${item.direction}-${item.type ?? 'voided'}`}>
                                        <button
                                            onClick={() => goTo(navItemQuery(item))}
                                            className={`w-full rounded px-2 py-1 text-left text-sm hover:bg-accent ${isActive(item.direction, item.type, item.voided) ? 'bg-accent font-medium' : ''
                                                }`}
                                        >
                                            {item.label}
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}
                </aside>

                <div className="space-y-4">
                    <h1 className="text-2xl font-semibold">Documentos Fiscales</h1>

                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Clave de acceso</TableHead>
                                <TableHead>Tipo</TableHead>
                                <TableHead>Dirección</TableHead>
                                <TableHead>Emisor / Receptor</TableHead>
                                <TableHead>Fecha</TableHead>
                                <TableHead className="text-right">Total</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead className="text-right">Descargas</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {documents.data.map((document) => (
                                <TableRow key={document.id}>
                                    <TableCell>
                                        <Link
                                            href={`/${current_team.slug}/documents/${document.id}`}
                                            className="font-mono text-xs underline underline-offset-4"
                                        >
                                            {document.access_key.slice(0, 14)}…
                                        </Link>
                                    </TableCell>
                                    <TableCell>{fiscalDocumentTypeLabels[document.type]}</TableCell>
                                    <TableCell className="capitalize">
                                        {document.direction === 'received' ? 'Recibido' : 'Emitido'}
                                    </TableCell>
                                    <TableCell className="text-sm">
                                        {document.direction === 'received' ? document.issuer_name : document.receiver_name}
                                    </TableCell>
                                    <TableCell>{document.issued_at ?? '—'}</TableCell>
                                    <TableCell className="text-right">
                                        {document.total !== null ? `$${document.total.toFixed(2)}` : '—'}
                                    </TableCell>
                                    <TableCell>
                                        {document.is_voided ? (
                                            <Badge variant="destructive">Anulado</Badge>
                                        ) : (
                                            <Badge variant={document.status === 'processed' ? 'outline' : 'secondary'}>
                                                {document.status}
                                            </Badge>
                                        )}
                                    </TableCell>
                                    <TableCell className="flex justify-end gap-1">
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            disabled={!document.has_xml}
                                            title="Descargar XML"
                                            onClick={() =>
                                                window.location.assign(
                                                    downloadXml({ current_team: current_team.slug, document: document.id }).url,
                                                )
                                            }
                                        >
                                            <FileText className="h-4 w-4" />
                                        </Button>
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            disabled={!document.has_pdf}
                                            title="Descargar PDF"
                                            onClick={() =>
                                                window.location.assign(
                                                    downloadPdf({ current_team: current_team.slug, document: document.id }).url,
                                                )
                                            }
                                        >
                                            <FileDown className="h-4 w-4" />
                                        </Button>
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            disabled={!document.has_json}
                                            title="Descargar JSON"
                                            onClick={() =>
                                                window.location.assign(
                                                    downloadJson({ current_team: current_team.slug, document: document.id }).url,
                                                )
                                            }
                                        >
                                            <FileJson className="h-4 w-4" />
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </>
    );
}