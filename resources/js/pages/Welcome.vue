<script setup lang="ts">
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
                        ✓ Isolamento de dados por empresa
                    </p>
                    <h1 class="text-4xl sm:text-5xl font-black text-gray-900 leading-tight">
                        Controle financeiro e comissões em um só lugar
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
                            <p class="text-sm text-orange-600 font-semibold">📊 Registros</p>
                            <p class="text-base font-bold text-gray-900">Entradas e saídas</p>
                        </div>
                        <div class="p-4 bg-white rounded-xl shadow-sm border border-orange-100">
                            <p class="text-sm text-orange-600 font-semibold">💰 Comissões</p>
                            <p class="text-base font-bold text-gray-900">Cálculo por colaborador</p>
                        </div>
                        <div class="p-4 bg-white rounded-xl shadow-sm border border-orange-100">
                            <p class="text-sm text-orange-600 font-semibold">📈 Lucro</p>
                            <p class="text-base font-bold text-gray-900">Diário, mensal, anual</p>
                        </div>
                    </div>
                </div>
                <div class="relative">
                    <div class="absolute -inset-6 bg-gradient-to-br from-orange-200/60 via-white to-white rounded-3xl blur-3xl"></div>
                    <div class="relative bg-white border border-orange-100 rounded-3xl shadow-xl p-6 space-y-4">
                        <div class="pb-4 border-b border-orange-100">
                            <p class="text-sm font-semibold text-orange-700">✨ O que você consegue</p>
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
                                    <p class="font-semibold">Escalas de trabalho</p>
                                    <p class="text-sm text-gray-600">Organize turnos e colaboradores por dia</p>
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="text-orange-500 font-bold mt-1">✓</span>
                                <div>
                                    <p class="font-semibold">Relatórios e gráficos</p>
                                    <p class="text-sm text-gray-600">Visualize lucro, comissões e desempenho em tempo real</p>
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="text-orange-500 font-bold mt-1">✓</span>
                                <div>
                                    <p class="font-semibold">Multi-empresa segura</p>
                                    <p class="text-sm text-gray-600">Dados isolados por empresa, sem mistura</p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>
        </main>

        <footer class="max-w-6xl mx-auto px-6 pb-10 text-sm text-gray-600 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div class="flex flex-wrap gap-4">
                <Link href="/politica-de-privacidade" class="font-semibold hover:text-orange-700 transition">Política de Privacidade</Link>
                <Link href="/suporte" class="font-semibold hover:text-orange-700 transition">Ajuda e Suporte</Link>
            </div>
            <span class="text-xs text-gray-500">© {{ new Date().getFullYear() }} Meu Negócio</span>
        </footer>
    </div>
</template>

