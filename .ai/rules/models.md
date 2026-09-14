---
paths:
  - 'app/Models/**'
---

# Models

## No crees tablas nuevas para compras, ventas, facturas, proveedores ni clientes
`purchases`, `sales`, `invoices`, `providers` y `clients` ya tienen equivalente. En facturación: `documents` (con `operation_type` y `document_type`; `status` lo recalcula `Document::syncPaymentStatus()`) y `contacts` (`type`: cliente|proveedor|ambos). En /presupuesto: filas de `budget_lines` por sección.

## Los dos módulos son independientes: no los mezcles
El proyecto tiene dos módulos paralelos, a propósito:

1. Facturación (sistema principal): `contacts`, `documents` (operation_type venta|compra, document_type presupuesto|factura), `document_items`, `payments`, `expenses`. Es lo que se ve en el sidebar.

2. /presupuesto: `budget_periods` + `budget_lines` (una sección por hoja) + `budget_line_payments`. Es la réplica autocontenida de la planilla del cliente, con su propia pantalla full-screen y sus pestañas.

Toda la lógica de la planilla (abonos Bs/USD, estados de cuenta, ventas del día, gastos, tablero, gráficos) vive en /presupuesto y se calcula sobre `budget_lines`. NO leas `documents` desde ahí ni agregues pantallas del presupuesto al sidebar principal.

## Las hojas de /presupuesto son secciones de budget_lines
`budget_lines.section` dice a qué hoja pertenece la fila: contacto | compra | venta | gasto | resultado | factura. No crees tablas nuevas para proveedores, clientes, compras, ventas ni facturas.

- `contacto` es el Directorio y tiene `budget_period_id` NULL a propósito: proveedores y clientes se comparten entre todos los períodos. El resto de las secciones sí cuelgan de un período.
- Una compra o venta apunta al contacto con `contact_line_id` y guarda copia de `party_name` / `telefono`; esa copia la mantiene al día `Budget\LineController` (no la escribas desde el front).
- `payment_status` lo recalcula `syncPaymentStatus()` solo cuando la fila tiene abonos; si nunca tuvo, respeta lo que el usuario eligió a mano.

## Una factura agrupa varias compras o ventas (invoice_line_id)
La relación va de la compra/venta hacia la factura: `budget_lines.invoice_line_id` apunta a la fila `factura`. Una factura no repite importes — sus totales salen de `toInvoiceArray()`, que suma los movimientos que agrupa.

- `App\Concerns\ResolvesBudgetInvoices` decide a qué factura va una fila: la última abierta de ese contacto y tipo en el período, o una nueva. Lo usan `Budget\LineController`, `Budget\InvoiceController` y el seeder, para que el enlace sea igual venga de donde venga.
- `Budget\InvoiceController::storePayment` reparte un abono entre los movimientos pendientes, del más viejo al más nuevo. Si el pago vino en bolívares se reparten los bolívares y el último movimiento se queda con el resto, así la suma entregada queda exacta.
- En la hoja de ganancias y pérdidas cada fila lleva `monto_compra`, `monto_venta` y `costo`; `utilidad` y `total_utilidad` son accesores, no columnas. Esa hoja se carga a mano y solo baja la utilidad neta del período por gastos personales y pérdidas, para no contar dos veces lo que ya está en Compras y Ventas.
