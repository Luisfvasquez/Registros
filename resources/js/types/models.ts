export type ContactType = 'cliente' | 'proveedor' | 'ambos';

export type Contact = {
    id: number;
    type: ContactType;
    name: string;
    document: string | null;
    phone_country_code: string | null;
    phone: string | null;
    email: string | null;
    address: string | null;
    created_at: string;
    updated_at: string;
};

export type Category = {
    id: number;
    name: string;
    created_at: string;
    updated_at: string;
};

export type PaymentMethod = {
    id: number;
    name: string;
    created_at: string;
    updated_at: string;
};

export type Product = {
    id: number;
    category_id: number | null;
    category?: Category | null;
    name: string;
    sku: string | null;
    sale_price: string;
    purchase_cost: string;
    created_at: string;
    updated_at: string;
};

export type OperationType = 'venta' | 'compra';
export type DocumentType = 'presupuesto' | 'factura';
export type DocumentStatus =
    'pendiente' | 'parcial' | 'pagado' | 'convertido' | 'anulado';

export type DocumentItem = {
    id: number;
    document_id: number;
    product_id: number | null;
    product?: Product | null;
    description: string;
    quantity: string;
    unit_price: string;
    tax_rate: string;
    subtotal: string;
    sort_order: number;
};

export type Expense = {
    id: number;
    document_id: number;
    description: string;
    amount: string;
};

export type Payment = {
    id: number;
    document_id: number;
    payment_method_id: number;
    payment_method?: PaymentMethod;
    amount: string;
    reference: string | null;
    paid_at: string;
    created_at: string;
};

export type Document = {
    id: number;
    number: string;
    operation_type: OperationType;
    document_type: DocumentType;
    status: DocumentStatus;
    contact_id: number;
    contact?: Contact;
    converted_from_id: number | null;
    issue_date: string;
    subtotal: string;
    tax_total: string;
    total: string;
    exchange_rate: string | null;
    notes: string | null;
    items?: DocumentItem[];
    expenses?: Expense[];
    payments?: Payment[];
    balance?: number;
    paid_total?: number;
    created_at: string;
    updated_at: string;
};

export type PaginatedData<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
};
