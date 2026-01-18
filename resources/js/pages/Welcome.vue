<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps<{
    canRegister: boolean;
}>();
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-white via-orange-50 to-orange-100 text-gray-900">
        <Head title="Meu Negocio" />

        <header class="max-w-6xl mx-auto px-6 py-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="h-12 w-12 rounded-2xl bg-orange-500 text-white flex items-center justify-center text-2xl font-black shadow-lg">MN</div>
                <div>
                    <p class="text-sm uppercase tracking-[0.2em] text-orange-600">Meu Negocio</p>
                    <p class="text-lg font-semibold text-gray-800">Controle financeiro e comissoes</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <Link
                    href="/login"
                    class="px-4 py-2 rounded-full font-semibold border border-orange-200 text-orange-700 hover:border-orange-400 hover:text-orange-800 transition"
                >
                    Entrar
                </Link>
                <Link
                    v-if="props.canRegister"
                    href="/register"
                    class="px-5 py-2 rounded-full font-semibold bg-orange-500 text-white shadow-md hover:bg-orange-600 transition"
                >
                    Criar conta
                </Link>
            </div>
        </header>

        <main class="max-w-6xl mx-auto px-6 pb-16">
            <section class="grid lg:grid-cols-2 gap-10 items-center">
                <div class="space-y-6">
                    <p class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white shadow text-sm font-semibold text-orange-700">
                        Plataforma SaaS multi-tenant
                    </p>
                    <h1 class="text-4xl sm:text-5xl font-black text-gray-900 leading-tight">
                        Domine entradas, saidas e comissoes em uma so tela
                    </h1>
                    <p class="text-lg text-gray-700 leading-relaxed">
                        Painel financeiro, calculo de comissoes, escalas de trabalho e isolamento por empresa. Tudo pronto para crescer com seguranca.
                    </p>
                    <div class="flex flex-wrap gap-3">
                        <Link
                            href="/login"
                            class="px-6 py-3 rounded-xl bg-orange-500 text-white font-semibold shadow-lg hover:bg-orange-600 transition"
                        >
                            Acessar painel
                        </Link>
                        <Link
                            v-if="props.canRegister"
                            href="/register"
                            class="px-6 py-3 rounded-xl bg-white text-orange-700 font-semibold border border-orange-200 hover:border-orange-400 transition"
                        >
                            Criar conta root
                        </Link>
                    </div>
                    <div class="grid sm:grid-cols-3 gap-4 pt-4">
                        <div class="p-4 bg-white rounded-xl shadow-sm border border-orange-100">
                            <p class="text-sm text-orange-600 font-semibold">Multi-empresa</p>
                            <p class="text-base font-bold text-gray-900">Isolamento por company_id</p>
                        </div>
                        <div class="p-4 bg-white rounded-xl shadow-sm border border-orange-100">
                            <p class="text-sm text-orange-600 font-semibold">Comissoes</p>
                            <p class="text-base font-bold text-gray-900">Calculo por servico e turno</p>
                        </div>
                        <div class="p-4 bg-white rounded-xl shadow-sm border border-orange-100">
                            <p class="text-sm text-orange-600 font-semibold">Dashboards</p>
                            <p class="text-base font-bold text-gray-900">Entradas, saidas e lucro</p>
                        </div>
                    </div>
                </div>
                <div class="relative">
                    <div class="absolute -inset-6 bg-gradient-to-br from-orange-200/60 via-white to-white rounded-3xl blur-3xl"></div>
                    <div class="relative bg-white border border-orange-100 rounded-3xl shadow-xl p-6 space-y-4">
                        <div class="flex items-center justify-between pb-2 border-b border-orange-100">
                            <p class="text-sm font-semibold text-orange-700">Resumo diario</p>
                            <span class="px-3 py-1 text-xs font-bold bg-orange-100 text-orange-700 rounded-full">Demo</span>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="p-4 rounded-xl bg-orange-50 border border-orange-100">
                                <p class="text-sm text-gray-600">Entradas</p>
                                <p class="text-2xl font-black text-orange-600">R$ 2.035,10</p>
                            </div>
                            <div class="p-4 rounded-xl bg-orange-50 border border-orange-100">
                                <p class="text-sm text-gray-600">Saidas</p>
                                <p class="text-2xl font-black text-orange-600">R$ 849,80</p>
                            </div>
                            <div class="p-4 rounded-xl bg-orange-50 border border-orange-100">
                                <p class="text-sm text-gray-600">Lucro</p>
                                <p class="text-2xl font-black text-orange-600">R$ 1.185,30</p>
                            </div>
                            <div class="p-4 rounded-xl bg-orange-50 border border-orange-100">
                                <p class="text-sm text-gray-600">Comissoes</p>
                                <p class="text-2xl font-black text-orange-600">R$ 320,00</p>
                            </div>
                        </div>
                        <div class="p-4 rounded-xl border border-orange-100 bg-gradient-to-r from-orange-50 to-white flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600">Turno atual</p>
                                <p class="text-lg font-semibold text-gray-900">Manha - 3 colaboradores</p>
                            </div>
                            <div class="h-10 px-4 flex items-center rounded-full bg-orange-500 text-white font-semibold">Escala</div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</template>
