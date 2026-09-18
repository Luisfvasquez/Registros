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
    /** En una compra o venta: la factura que la agrupa. */
    invoice_line_id: number | null;
    party_name: string | null;
    telefono: string | null;
    categoria: string | null;
    producto: string | null;
    descripcion: string | null;
    cantidad: string | null;
    unit_price: string | null;
    /** Precio unitario en bolivares: el espejo de `unit_price`. */
    unit_price_bs: string | null;
    /** Bs por unidad de la moneda del periodo con que se convirtio la fila. */
    exchange_rate: string | null;
    /** Costo de la operacion, en ganancias y perdidas. */
    costo: string | null;
    /** Flete de la operacion, en ganancias y perdidas. */
    flete: string | null;
    /** Monto de compra de la operacion, en ganancias y perdidas. */
    monto_compra: string | null;
    /** Monto de venta de la operacion, en ganancias y perdidas. */
    monto_venta: string | null;
    /** Monto del gasto, solo en la seccion gasto. */
    monto: string | null;
    /** Cargo extra de una factura: flete, envio o lo que se sume aparte. */
    monto_adicional: string | null;
    payment_status: string | null;
    payment_method: string | null;
    invoice_number: string | null;
    gastos_personales: string | null;
    perdidas_mercancia: string | null;
    notas: string | null;
    position: number;
    /** Calculado en el servidor: cantidad x precio unitario. */
    precio_total: number;
    /** Calculado en el servidor: cantidad x precio unitario en bolivares. */
    precio_total_bs: number;
    /** Calculado en el servidor: venta - compra - costo - flete. */
    utilidad: number;
    /** Calculado en el servidor: utilidad - gastos personales - perdidas. */
    total_utilidad: number;
    /** Calculado en el servidor: suma de los abonos de la fila. */
    abonado: number;
    /** Calculado en el servidor: precio total - abonado. */
    restante: number;
    payments?: BudgetLinePayment[];
    created_at: string;
    updated_at: string;
};

/**
 * Un abono de la factura tal como se hizo: si se cargo contra la factura entera
 * se repartio entre sus movimientos, pero aca vuelve a ser uno solo.
 */
export type BudgetInvoicePayment = {
    /** El `batch_id` del reparto, o el id del abono suelto. */
    id: string;
    fecha: string | null;
    method: string | null;
    notes: string | null;
    amount: number;
    amount_bs: number | null;
    exchange_rate: string | null;
    /** Entre cuantos movimientos se repartio. */
    movimientos: number;
};

/** Una factura con los movimientos que agrupa y los totales que salen de ellos. */
export type BudgetInvoice = BudgetLine & {
    items: BudgetLine[];
    abonos: BudgetInvoicePayment[];
    totales: {
        movimientos: number;
        cantidad: number;
        /** Lo que suman los movimientos, sin el cargo extra. */
        subtotal: number;
        /** El cargo extra cargado en la factura. */
        adicional: number;
        /** Lo que falta del cargo extra: se abona despues de los movimientos. */
        adicional_restante: number;
        /** Subtotal + adicional: lo que se le cobra al contacto. */
        total: number;
        abonado: number;
        restante: number;
        estado: string;
    };
};

/** Factura tal como la ve la celda "Factura" de compras y ventas. */
export type BudgetInvoiceOption = {
    id: number;
    label: string;
    contact_line_id: number | null;
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
    ganancia_bruta: number;
    pagado_a_proveedores: number;
    cobrado_a_clientes: number;
    cuentas_por_pagar: number;
    cuentas_por_cobrar: number;
    gastos: number;
    gastos_personales: number;
    perdidas_mercancia: number;
    /** Suma de la columna Total de la hoja de ganancias y perdidas. */
    resultado_utilidad: number;
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
