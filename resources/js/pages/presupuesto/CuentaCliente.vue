<script setup lang="ts">
import presupuesto from '@/routes/presupuesto';
import type { BudgetLine, BudgetPeriod, BudgetPeriodOption } from '@/types';
import CuentaSheet from './CuentaSheet.vue';

const props = defineProps<{
    period: BudgetPeriod;
    periods: BudgetPeriodOption[];
    activePeriodId: number | null;
    contactos: BudgetLine[];
    contactoId: number | null;
    party: BudgetLine | null;
    lines: BudgetLine[];
    totals: {
        total: number;
        abonado: number;
        restante: number;
        registros: number;
    };
}>();
</script>

<template>
    <CuentaSheet
        :period="period"
        :periods="periods"
        :active-period-id="activePeriodId"
        tab="client-account"
        title="Cuenta por Cliente"
        section="venta"
        tone="sky"
        contact-label="Cliente"
        total-label="Total vendido"
        saldo-label="Saldo por cobrar"
        :base-url="presupuesto.clientAccount.url(props.period.id)"
        :contactos="contactos"
        :contacto-id="contactoId"
        :party="party"
        :lines="lines"
        :totals="totals"
    />
</template>
