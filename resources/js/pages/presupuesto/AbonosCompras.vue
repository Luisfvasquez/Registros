<script setup lang="ts">
import type {
    BudgetLine,
    BudgetLinePayment,
    BudgetPayable,
    BudgetPeriod,
    BudgetPeriodOption,
} from '@/types';
import AbonosSheet from './AbonosSheet.vue';

defineProps<{
    period: BudgetPeriod;
    periods: BudgetPeriodOption[];
    activePeriodId: number | null;
    payments: BudgetLinePayment[];
    registros: BudgetPayable[];
    contactos: BudgetLine[];
    metodos: string[];
    totals: { abonado: number; bolivares: number };
}>();
</script>

<template>
    <AbonosSheet
        :period="period"
        :periods="periods"
        :active-period-id="activePeriodId"
        tab="purchase-payments"
        title="Abonos Compras"
        tone="pink"
        link-label="Compra vinculada"
        party-label="Proveedor"
        :payments="payments"
        :registros="registros"
        :metodos="metodos"
        :totals="totals"
    />
</template>
