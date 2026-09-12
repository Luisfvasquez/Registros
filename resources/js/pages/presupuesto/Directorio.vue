<script setup lang="ts">
import type { BudgetLine, BudgetPeriod, BudgetPeriodOption } from '@/types';
import { useLines } from './sheet';
import SheetLayout from './SheetLayout.vue';
import SheetTable from './SheetTable.vue';
import type { SheetColumn, SheetRow } from './SheetTable.vue';

/**
 * Directorio: se escribe cada proveedor y cada cliente una sola vez y aparece
 * en los selects de Compras y Ventas. Es el mismo para todos los períodos.
 */
const props = defineProps<{
    period: BudgetPeriod;
    periods: BudgetPeriodOption[];
    activePeriodId: number | null;
    proveedores: BudgetLine[];
    clientes: BudgetLine[];
}>();

const providers = useLines(props.period.id, 'contacto', props.proveedores);
const clients = useLines(props.period.id, 'contacto', props.clientes);

const columns: SheetColumn[] = [
    { key: 'party_name', label: 'Nombre', type: 'text', width: '14rem' },
    { key: 'telefono', label: 'Teléfono', type: 'text', width: '10rem' },
    { key: 'notas', label: 'Notas', type: 'text', width: '18rem' },
];
</script>

<template>
    <SheetLayout
        :period="period"
        :periods="periods"
        :active-period-id="activePeriodId"
        tab="directory"
        title="Directorio"
        subtitle="Proveedores y clientes, compartidos por todos los períodos"
    >
        <div class="grid gap-3 lg:grid-cols-2">
            <section>
                <h2
                    class="mb-1.5 text-sm font-semibold text-pink-700 dark:text-pink-300"
                >
                    Proveedores
                </h2>
                <SheetTable
                    :columns="columns"
                    :rows="providers.rows.value as unknown as SheetRow[]"
                    tone="pink"
                    add-label="Agregar proveedor"
                    empty-text="Sin proveedores cargados."
                    :totals="false"
                    max-height="calc(100vh - 21rem)"
                    @add="providers.addRow({ tipo: 'proveedor' })"
                    @update="
                        (row, patch) =>
                            providers.patchRow(
                                row as unknown as BudgetLine,
                                patch,
                            )
                    "
                    @remove="
                        (row) =>
                            providers.removeRow(row as unknown as BudgetLine)
                    "
                />
            </section>

            <section>
                <h2
                    class="mb-1.5 text-sm font-semibold text-sky-700 dark:text-sky-300"
                >
                    Clientes
                </h2>
                <SheetTable
                    :columns="columns"
                    :rows="clients.rows.value as unknown as SheetRow[]"
                    tone="sky"
                    add-label="Agregar cliente"
                    empty-text="Sin clientes cargados."
                    :totals="false"
                    max-height="calc(100vh - 21rem)"
                    @add="clients.addRow({ tipo: 'cliente' })"
                    @update="
                        (row, patch) =>
                            clients.patchRow(
                                row as unknown as BudgetLine,
                                patch,
                            )
                    "
                    @remove="
                        (row) => clients.removeRow(row as unknown as BudgetLine)
                    "
                />
            </section>
        </div>
    </SheetLayout>
</template>
