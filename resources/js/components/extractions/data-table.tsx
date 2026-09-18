import { FlexRender, useTable } from '@tanstack/react-table';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { extractionTableFeatures } from './columns';
import type { Extraction } from '@/types/fiscal-extraction';

interface DataTableProps {
    columns: ReturnType<typeof import('./columns').buildColumns>;
    data: Extraction[];
}

export function DataTable({ columns, data }: DataTableProps) {
    const table = useTable({
        features: extractionTableFeatures,
        data,
        columns,
        getRowId: (row) => row.id.toString(),
    });

    return (
        <div className="rounded-md border">
            <Table>
                <TableHeader>
                    {table.getHeaderGroups().map((headerGroup) => (
                        <TableRow key={headerGroup.id}>
                            {headerGroup.headers.map((header) => (
                                <TableHead key={header.id} colSpan={header.colSpan}>
                                    {header.isPlaceholder ? null : <FlexRender header={header} />}
                                </TableHead>
                            ))}
                        </TableRow>
                    ))}
                </TableHeader>
                <TableBody>
                    {table.getRowModel().rows?.length ? (
                        table.getRowModel().rows.map((row) => (
                            <TableRow key={row.id}>
                                {row.getVisibleCells().map((cell) => (
                                    <TableCell key={cell.id}>
                                        <FlexRender cell={cell} />
                                    </TableCell>
                                ))}
                            </TableRow>
                        ))
                    ) : (
                        <TableRow>
                            <TableCell colSpan={columns.length} className="h-24 text-center">
                                Sin resultados.
                            </TableCell>
                        </TableRow>
                    )}
                </TableBody>
            </Table>
        </div>
    );
}