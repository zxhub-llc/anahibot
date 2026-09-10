import type { FiscalDocumentDirection, FiscalDocumentType } from '@/types/fiscal-extraction';

export interface FiscalDocumentNavItem {
    label: string;
    direction: FiscalDocumentDirection;
    type?: FiscalDocumentType;
    voided?: true;
}

export interface FiscalDocumentNavGroup {
    label: string;
    direction: FiscalDocumentDirection;
    items: FiscalDocumentNavItem[];
}

export const fiscalDocumentNavigation: FiscalDocumentNavGroup[] = [
    {
        label: 'Documentos Recibidos',
        direction: 'received',
        items: [
            { label: 'Facturas de Compra', direction: 'received', type: 'invoice' },
            { label: 'Retenciones', direction: 'received', type: 'retention' },
            { label: 'Notas de Crédito', direction: 'received', type: 'credit_note' },
            { label: 'Notas de Débito', direction: 'received', type: 'debit_note' },
            { label: 'Liquidaciones de Compra', direction: 'received', type: 'purchase_settlement' },
            { label: 'Documentos Anulados', direction: 'received', voided: true },
        ],
    },
    {
        label: 'Documentos Emitidos',
        direction: 'issued',
        items: [
            { label: 'Facturas de Venta', direction: 'issued', type: 'invoice' },
            { label: 'Retenciones', direction: 'issued', type: 'retention' },
            { label: 'Notas de Crédito', direction: 'issued', type: 'credit_note' },
            { label: 'Liquidaciones de Venta', direction: 'issued', type: 'purchase_settlement' },
            { label: 'Documentos Anulados', direction: 'issued', voided: true },
        ],
    },
];

export const fiscalDocumentTypeLabels: Record<FiscalDocumentType, string> = {
    invoice: 'Factura',
    credit_note: 'Nota de Crédito',
    debit_note: 'Nota de Débito',
    retention: 'Retención',
    purchase_settlement: 'Liquidación de Compra/Venta',
    remission_guide: 'Guía de Remisión',
};

export function navItemQuery(item: FiscalDocumentNavItem): Record<string, string> {
    const query: Record<string, string> = { direction: item.direction };

    if (item.type) {
        query.type = item.type;
    }

    if (item.voided) {
        query.voided = '1';
    }

    return query;
}