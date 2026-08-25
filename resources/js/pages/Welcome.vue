<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { dashboard, login, register } from '@/routes';

const features = [
    {
        title: 'Contactos',
        description: 'Administra clientes y proveedores en un solo lugar.',
    },
    {
        title: 'Productos',
        description: 'Controla tu catálogo, precios y categorías fácilmente.',
    },
    {
        title: 'Documentos',
        description: 'Genera cotizaciones, facturas y conviértelas en un clic.',
    },
    {
        title: 'Pagos',
        description: 'Registra pagos y lleva el control de tus cobros.',
    },
];
</script>

<template>
    <Head title="Bienvenido">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>

    <div
        class="flex min-h-screen flex-col bg-linear-to-b from-white to-slate-50 text-slate-900 dark:from-[#0a0a0a] dark:to-[#111113] dark:text-slate-100"
    >
        <header class="mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-6">
            <div class="flex items-center gap-3">
                <img
                    src="/images/logo-icon.png"
                    alt="Logo"
                    class="h-10 w-10 rounded-lg bg-white object-contain p-1 shadow-sm ring-1 ring-black/5"
                />
                <span class="text-lg font-semibold tracking-tight">{{ $page.props.name }}</span>
            </div>
            <nav class="flex items-center gap-3">
                <Link
                    v-if="$page.props.auth.user"
                    :href="dashboard()"
                    class="inline-block rounded-md bg-slate-900 px-5 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-slate-700 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200"
                >
                    Ir al Dashboard
                </Link>
                <template v-else>
                    <Link
                        :href="login()"
                        class="inline-block rounded-md px-4 py-2 text-sm font-medium text-slate-700 transition hover:text-slate-900 dark:text-slate-300 dark:hover:text-white"
                    >
                        Iniciar sesión
                    </Link>
                    <Link
                        :href="register()"
                        class="inline-block rounded-md bg-slate-900 px-5 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-slate-700 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200"
                    >
                        Crear cuenta
                    </Link>
                </template>
            </nav>
        </header>

        <main class="mx-auto flex w-full max-w-6xl flex-1 flex-col items-center justify-center gap-16 px-6 py-12 text-center">
            <div class="flex flex-col items-center gap-6">
                <img
                    src="/images/logo-icon.png"
                    alt="Logo"
                    class="h-20 w-20 rounded-2xl bg-white object-contain p-3 shadow-lg ring-1 ring-black/5 dark:ring-white/10"
                />
                <h1 class="max-w-2xl text-4xl font-bold tracking-tight text-balance sm:text-5xl">
                    Gestiona tu negocio de forma simple y ordenada
                </h1>
                <p class="max-w-xl text-lg text-slate-600 dark:text-slate-400">
                    Contactos, productos, documentos y pagos, todo en un mismo sistema, pensado
                    para que tu operación diaria sea más rápida.
                </p>
                <div class="flex flex-wrap items-center justify-center gap-4">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="inline-block rounded-md bg-slate-900 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-700 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200"
                    >
                        Ir al Panel
                    </Link>
                    <Link
                        v-else
                        :href="login()"
                        class="inline-block rounded-md bg-slate-900 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-700 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200"
                    >
                        Comenzar ahora
                    </Link>
                </div>
            </div>

            <div class="grid w-full grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="feature in features"
                    :key="feature.title"
                    class="rounded-xl border border-slate-200 bg-white p-6 text-left shadow-sm transition hover:shadow-md dark:border-slate-800 dark:bg-slate-900/40"
                >
                    <h3 class="mb-2 text-base font-semibold">{{ feature.title }}</h3>
                    <p class="text-sm text-slate-600 dark:text-slate-400">{{ feature.description }}</p>
                </div>
            </div>
        </main>

        <footer class="border-t border-slate-200 py-6 text-center text-sm text-slate-500 dark:border-slate-800 dark:text-slate-500">
            &copy; {{ new Date().getFullYear() }} {{ $page.props.name }}. Todos los derechos reservados.
        </footer>
    </div>
</template>
