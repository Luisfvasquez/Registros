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
        tab="sale-payments"
        title="Abonos Ventas"
        tone="sky"
        link-label="Venta vinculada"
        party-label="Cliente"
        :payments="payments"
        :registros="registros"
        :metodos="metodos"
        :totals="totals"
    />
</template>
