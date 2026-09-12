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
- Una `factura` no repite importes: apunta con `linked_line_id` a la compra o venta de origen y muestra sus datos.
- `payment_status` lo recalcula `syncPaymentStatus()` solo cuando la fila tiene abonos; si nunca tuvo, respeta lo que el usuario eligió a mano.
