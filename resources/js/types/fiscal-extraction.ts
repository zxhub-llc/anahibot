export type ExtractionStatus =
    | 'pending'
    | 'queued'
    | 'running'
    | 'completed'
    | 'completed_with_errors'
    | 'failed'
    | 'cancelled';

export type FiscalDocumentType =
    | 'invoice'
    | 'credit_note'
    | 'debit_note'
    | 'retention'
    | 'purchase_settlement'
    | 'remission_guide';

export type FiscalDocumentDirection = 'received' | 'issued';

export interface Extraction {
    id: number;
    company_ruc: string;
    date_from: string;
    date_to: string;
    trigger: string;
    status: 'pending' | 'queued' | 'running' | 'completed' | 'completed_with_errors' | 'failed' | 'cancelled';
    attempts: number;
    completed_at: string | null;
    last_error: string | null;
    created_at: string;
    documents_found: number;
    documents_processed: number;
    documents_failed: number;
    progress: number;
}

export interface FiscalDocument {
    id: number;
    company_ruc: string;
    access_key: string;
    type: FiscalDocumentType;
    direction: FiscalDocumentDirection;
    status: 'downloaded' | 'processed' | 'failed';
    is_voided: boolean;
    issuer_ruc: string | null;
    issuer_name: string | null;
    receiver_ruc: string | null;
    receiver_name: string | null;
    issued_at: string | null;
    subtotal: number | null;
    tax_amount: number | null;
    total: number | null;
    has_xml: boolean;
    has_pdf: boolean;
    has_json: boolean;
}

export interface SriCredential {
    id: number;
    company_ruc: string;
    environment: 'production' | 'test';
    last_authenticated_at: string | null;
    last_authentication_error: string | null;
    is_connected: boolean;
}

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    first_page_url: string;
    last_page_url: string;
    next_page_url: string | null;
    prev_page_url: string | null;
    path: string;
}