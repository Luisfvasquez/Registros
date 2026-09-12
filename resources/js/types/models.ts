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

export type BudgetSection =
    'contacto' | 'compra' | 'venta' | 'gasto' | 'resultado' | 'factura';

export type BudgetContactType = 'proveedor' | 'cliente';

export type BudgetLinePayment = {
    id: number;
    budget_line_id: number;
    fecha: string;
    method: string | null;
    /** Monto entregado en bolivares, cuando el abono se cargo en Bs. */
    amount_bs: string | null;
    /** Tasa usada para convertir `amount_bs` a `amount`. */
    exchange_rate: string | null;
    /** Monto en la moneda del periodo: es el que manda para saldos y reportes. */
    amount: string;
    notes: string | null;
    created_at: string;
    updated_at: string;
};

export type BudgetPeriodStatus = 'abierto' | 'cerrado';

export type BudgetPeriod = {
    id: number;
    year: number;
    month: number;
    currency: string;
    status: BudgetPeriodStatus;
    available_money: string;
    notes: string | null;
    created_at: string;
    updated_at: string;
};

export type BudgetPeriodOption = Pick<
    BudgetPeriod,
    'id' | 'year' | 'month' | 'currency' | 'status'
>;

/**
 * Una fila de cualquier hoja de /presupuesto. `section` dice de que hoja es y,
 * con eso, que columnas de esta fila ancha tienen sentido.
 */
export type BudgetLine = {
    id: number;
    /** Null en el Directorio: proveedores y clientes se comparten entre periodos. */
    budget_period_id: number | null;
    section: BudgetSection;
    /** Directorio: proveedor | cliente. Factura: venta | compra. */
    tipo: string | null;
    fecha: string | null;
    /** Fila del Directorio elegida como proveedor o cliente. */
    contact_line_id: number | null;
    party_name: string | null;
    telefono: string | null;
    categoria: string | null;
    producto: string | null;
    descripcion: string | null;
    cantidad: string | null;
    unit_price: string | null;
    /** Costo de la mercancia vendida, solo en ventas. */
    costo: string | null;
    /** Monto del gasto, solo en la seccion gasto. */
    monto: string | null;
    payment_status: string | null;
    payment_method: string | null;
    invoice_number: string | null;
    ganancia: string | null;
    gastos_personales: string | null;
    perdidas_mercancia: string | null;
    /** En una factura: la compra o venta de la que salio. */
    linked_line_id: number | null;
    notas: string | null;
    position: number;
    /** Calculado en el servidor: cantidad x precio unitario. */
    precio_total: number;
    /** Calculado en el servidor: ganancia - gastos personales - perdidas. */
    total_utilidad: number;
    /** Calculado en el servidor: suma de los abonos de la fila. */
    abonado: number;
    /** Calculado en el servidor: precio total - abonado. */
    restante: number;
    payments?: BudgetLinePayment[];
    source_line?: BudgetLine | null;
    created_at: string;
    updated_at: string;
};

/** Compra o venta tal como la ve el select de "registro origen" de Facturas. */
export type BudgetInvoiceSource = {
    id: number;
    tipo: 'compra' | 'venta';
    label: string;
    fecha: string | null;
    party_name: string | null;
    producto: string | null;
    cantidad: string | null;
    unit_price: string | null;
    precio_total: number;
    payment_method: string | null;
    payment_status: string | null;
    abonado: number;
    restante: number;
};

/** Compra o venta tal como la ve el select de las hojas de abonos. */
export type BudgetPayable = {
    id: number;
    label: string;
    party_name: string | null;
    contact_line_id: number | null;
    precio_total: number;
    abonado: number;
    restante: number;
};

export type BudgetSummary = {
    total_compras: number;
    total_ventas: number;
    costo_ventas: number;
    ganancia_bruta: number;
    pagado_a_proveedores: number;
    cobrado_a_clientes: number;
    cuentas_por_pagar: number;
    cuentas_por_cobrar: number;
    gastos: number;
    ganancia_registrada: number;
    gastos_personales: number;
    perdidas_mercancia: number;
    utilidad_neta: number;
    estado: 'ganancia' | 'perdida';
    compras: number;
    ventas: number;
};

/** Un punto de las series del tablero (semanal, mensual o anual). */
export type BudgetSeriesPoint = {
    label: string;
    ventas: number;
    compras: number;
    costo: number;
    gastos: number;
    utilidad: number;
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
