<script setup lang="ts">
import Footer from '@/components/Footer.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps<{
    canRegister: boolean;
}>();
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-white via-orange-50 to-orange-100 text-gray-900">
        <Head title="Meu Negocio - Gestão Financeira e Comissões" />

        <header class="max-w-6xl mx-auto px-6 py-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="h-12 w-12 rounded-2xl bg-orange-500 text-white flex items-center justify-center text-2xl font-black shadow-lg">MN</div>
                <div>
                    <p class="text-sm uppercase tracking-[0.2em] text-orange-600">Meu Negocio</p>
                    <p class="text-lg font-semibold text-gray-800">Gestão financeira multi-empresa</p>
                </div>
            </div>
        </header>

        <main class="w-full min-w-0 max-w-6xl mx-auto px-6 pb-8 overflow-x-hidden">
            <section class="grid lg:grid-cols-2 gap-10 items-center">
                <div class="space-y-6">
                    <h1 class="text-4xl sm:text-5xl font-black text-gray-900 leading-tight">
                        Controle financeiro e comissões para seu negócio
                    </h1>
                    <p class="text-lg text-gray-700 leading-relaxed">
                        Registre entradas, saídas, calcule comissões por colaborador, visualize lucro diário, mensal e anual. Com organização e segurança.
                    </p>
                    <div class="flex flex-wrap gap-3">
                        <Link
                            href="/login"
                            class="px-6 py-3 rounded-xl bg-orange-500 text-white font-semibold shadow-lg hover:bg-orange-600 transition"
                        >
                            Acessar plataforma
                        </Link>
                        <Link
                            v-if="props.canRegister"
                            href="/register"
                            class="px-6 py-3 rounded-xl bg-white text-orange-700 font-semibold border border-orange-200 hover:border-orange-400 transition"
                        >
                            Criar primeira conta
                        </Link>
                    </div>
                    <div class="grid sm:grid-cols-3 gap-4 pt-4">
                        <div class="p-4 bg-white rounded-xl shadow-sm border border-orange-100">
                            <p class="text-sm text-orange-600 font-semibold flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6m4 6V7m4 10v-3M5 20h14a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v14a1 1 0 001 1z" />
                                </svg>
                                Registros
                            </p>
                            <p class="text-base font-bold text-gray-900">Entradas e saídas</p>
                        </div>
                        <div class="p-4 bg-white rounded-xl shadow-sm border border-orange-100">
                            <p class="text-sm text-orange-600 font-semibold flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V6m0 10v2m8-6a8 8 0 11-16 0 8 8 0 0116 0z" />
                                </svg>
                                Comissões
                            </p>
                            <p class="text-base font-bold text-gray-900">Cálculo por colaborador</p>
                        </div>
                        <div class="p-4 bg-white rounded-xl shadow-sm border border-orange-100">
                            <p class="text-sm text-orange-600 font-semibold flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l4-4 3 3 5-6M5 20h14" />
                                </svg>
                                Lucro
                            </p>
                            <p class="text-base font-bold text-gray-900">Diário, mensal, anual</p>
                        </div>
                    </div>
                </div>
                <div class="relative">
                    <div class="absolute -inset-6 bg-gradient-to-br from-orange-200/60 via-white to-white rounded-3xl blur-3xl"></div>
                    <div class="relative bg-white border border-orange-100 rounded-3xl shadow-xl p-6 space-b-4">
                        <div class="pb-4 border-b border-orange-100">
                            <p class="text-sm font-semibold text-orange-700 flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3l1.9 4.9L19 10l-5.1 2.1L12 17l-1.9-4.9L5 10l5.1-2.1L12 3z" />
                                </svg>
                                O que você consegue
                            </p>
                        </div>
                        <ul class="space-y-3 text-gray-800">
                            <li class="flex items-start gap-3">
                                <span class="text-orange-500 font-bold mt-1">✓</span>
                                <div>
                                    <p class="font-semibold">Controle total de finanças</p>
                                    <p class="text-sm text-gray-600">Registre cada entrada e saída com categoria e observações</p>
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="text-orange-500 font-bold mt-1">✓</span>
                                <div>
                                    <p class="font-semibold">Gestão de comissões</p>
                                    <p class="text-sm text-gray-600">Calcule e acompanhe comissões por colaborador e período</p>
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="text-orange-500 font-bold mt-1">✓</span>
                                <div>
                                    <p class="font-semibold">Relatórios e gráficos</p>
                                    <p class="text-sm text-gray-600">Visualize lucro, comissões e desempenho em tempo real</p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>
        </main>
    </div>
    <Footer />
</template>

