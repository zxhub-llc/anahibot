import { useMemo, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    xml as downloadXml,
    pdf as downloadPdf,
    json as downloadJson,
} from '@/routes/documents/download';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { ChevronLeft, ChevronRight, FileDown, FileJson, FileText, Search } from 'lucide-react';
import { fiscalDocumentTypeLabels } from '@/types/fiscal-navigation';
import type { FiscalDocument, Paginated } from '@/types/fiscal-extraction';
import Heading from '@/components/heading';
import { SectionCards, type FiscalSummary } from '@/components/cards/section-cards';
import { ChartAreaInteractive, type FiscalChartPoint } from '@/components/cards/chart-area-interactive';

interface Props {
    documents: Paginated<FiscalDocument>;
    filters: { direction?: string; type?: string; voided?: string };
    documentTypes: string[];
    directions: string[];
    summary: FiscalSummary;
    chartSeries: FiscalChartPoint[];
}

type DirectionTab = 'all' | 'issued' | 'received';

const VOIDED_ALL = 'all';
const VOIDED_ONLY = '1';
const TYPE_ALL = 'all';

export default function Index({ documents, filters, documentTypes, summary, chartSeries }: Props) {
    const { current_team } = usePage().props as unknown as { current_team: { slug: string } };

    const [search, setSearch] = useState('');

    const activeTab: DirectionTab = (filters.direction as DirectionTab) ?? 'all';
    const activeType = filters.type ?? TYPE_ALL;
    const activeVoided = filters.voided === '1' ? VOIDED_ONLY : VOIDED_ALL;

    function updateFilters(partial: Record<string, string | undefined>) {
        const next: Record<string, string> = {
            ...(filters.direction ? { direction: filters.direction } : {}),
            ...(filters.type ? { type: filters.type } : {}),
            ...(filters.voided ? { voided: filters.voided } : {}),
            ...partial,
        };

        Object.keys(next).forEach((key) => {
            if (!next[key] || next[key] === 'all') delete next[key];
        });

        router.get(`/${current_team.slug}/documents`, next, {
            preserveState: true,
            preserveScroll: true,
        });
    }

    const filteredData = useMemo(() => {
        if (!search.trim()) return documents.data;

        const term = search.trim().toLowerCase();

        return documents.data.filter((document) => {
            return (
                document.access_key.toLowerCase().includes(term) ||
                document.issuer_name?.toLowerCase().includes(term) ||
                document.receiver_name?.toLowerCase().includes(term)
            );
        });
    }, [documents.data, search]);

    function goToPage(url: string | null) {
        if (!url) return;
        router.get(url, {}, { preserveState: true, preserveScroll: true });
    }

    return (
        <>
            <Head title="Documentos Fiscales" />

            <div className="@container/main flex flex-col gap-6 p-4 sm:p-6">
                <Heading
                    variant="small"
                    title="Documentos Fiscales"
                    description="Consulta y descarga los comprobantes electrónicos emitidos y recibidos."
                />

                <SectionCards summary={summary} />

                <ChartAreaInteractive data={chartSeries} />

                <Tabs
                    value={activeTab}
                    onValueChange={(value) =>
                        updateFilters({ direction: value === 'all' ? undefined : value })
                    }
                    className="flex w-full items-center"
                >
                    <TabsList className="grid w-full grid-cols-3 sm:w-auto sm:inline-grid">
                        <TabsTrigger value="all">Todos</TabsTrigger>
                        <TabsTrigger value="issued">Emitidos</TabsTrigger>
                        <TabsTrigger value="received">Recibidos</TabsTrigger>
                    </TabsList>
                </Tabs>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="relative w-full sm:max-w-xs">
                        <Search className="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Buscar por clave, emisor o receptor…"
                            className="pl-8"
                        />
                    </div>

                    <div className="flex flex-col gap-3 sm:flex-row">
                        <Select
                            value={activeType}
                            onValueChange={(value) =>
                                updateFilters({ type: value === TYPE_ALL ? undefined : value })
                            }
                        >
                            <SelectTrigger className="w-full sm:w-[180px]">
                                <SelectValue placeholder="Tipo de documento" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={TYPE_ALL}>Todos los tipos</SelectItem>
                                {documentTypes.map((type) => (
                                    <SelectItem key={type} value={type}>
                                        {fiscalDocumentTypeLabels[type as keyof typeof fiscalDocumentTypeLabels] ?? type}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Select
                            value={activeVoided}
                            onValueChange={(value) =>
                                updateFilters({ voided: value === VOIDED_ONLY ? '1' : undefined })
                            }
                        >
                            <SelectTrigger className="w-full sm:w-[160px]">
                                <SelectValue placeholder="Estado" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={VOIDED_ALL}>Todos los estados</SelectItem>
                                <SelectItem value={VOIDED_ONLY}>Solo anulados</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div className="overflow-hidden rounded-md border">
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Clave de acceso</TableHead>
                                    <TableHead>Tipo</TableHead>
                                    <TableHead>Dirección</TableHead>
                                    <TableHead className="hidden md:table-cell">Emisor / Receptor</TableHead>
                                    <TableHead className="hidden sm:table-cell">Fecha</TableHead>
                                    <TableHead className="text-right">Total</TableHead>
                                    <TableHead>Estado</TableHead>
                                    <TableHead className="text-right">Descargas</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {filteredData.length ? (
                                    filteredData.map((document) => (
                                        <TableRow key={document.id}>
                                            <TableCell>
                                                <Link
                                                    href={`/${current_team.slug}/documents/${document.id}`}
                                                    className="font-mono text-xs underline underline-offset-4"
                                                >
                                                    {document.access_key.slice(0, 14)}…
                                                </Link>
                                            </TableCell>
                                            <TableCell className="whitespace-nowrap">
                                                {fiscalDocumentTypeLabels[document.type]}
                                            </TableCell>
                                            <TableCell className="capitalize">
                                                {document.direction === 'received' ? 'Recibido' : 'Emitido'}
                                            </TableCell>
                                            <TableCell className="hidden max-w-[220px] truncate text-sm md:table-cell">
                                                {document.direction === 'received'
                                                    ? document.issuer_name
                                                    : document.receiver_name}
                                            </TableCell>
                                            <TableCell className="hidden whitespace-nowrap sm:table-cell">
                                                {document.issued_at ?? '—'}
                                            </TableCell>
                                            <TableCell className="text-right whitespace-nowrap">
                                                {document.total !== null && document.total !== undefined
                                                    ? `$${Number(document.total).toFixed(2)}`
                                                    : '—'}
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
                                            <TableCell>
                                                <div className="flex justify-end gap-1">
                                                    <Button
                                                        size="icon"
                                                        variant="ghost"
                                                        disabled={!document.has_xml}
                                                        title="Descargar XML"
                                                        onClick={() =>
                                                            window.location.assign(
                                                                downloadXml({
                                                                    current_team: current_team.slug,
                                                                    document: document.id,
                                                                }).url,
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
                                                                downloadPdf({
                                                                    current_team: current_team.slug,
                                                                    document: document.id,
                                                                }).url,
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
                                                                downloadJson({
                                                                    current_team: current_team.slug,
                                                                    document: document.id,
                                                                }).url,
                                                            )
                                                        }
                                                    >
                                                        <FileJson className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                ) : (
                                    <TableRow>
                                        <TableCell colSpan={8} className="h-24 text-center text-muted-foreground">
                                            No se encontraron documentos.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                </div>

                <div className="flex flex-col items-center justify-between gap-3 sm:flex-row">
                    <p className="text-sm text-muted-foreground">
                        Mostrando {documents.from ?? 0} a {documents.to ?? 0} de {documents.total} documentos
                    </p>

                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => goToPage(documents.prev_page_url)}
                            disabled={!documents.prev_page_url}
                        >
                            <ChevronLeft className="h-4 w-4" />
                            Anterior
                        </Button>
                        <span className="px-2 text-sm text-muted-foreground">
                            Página {documents.current_page} de {documents.last_page}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => goToPage(documents.next_page_url)}
                            disabled={!documents.next_page_url}
                        >
                            Siguiente
                            <ChevronRight className="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </div>
        </>
    );
}

Index.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Documentos',
            href: props.currentTeam ? '/tenant/' + props.currentTeam.slug + '/fiscal-documents' : '/',
        },
    ],
});