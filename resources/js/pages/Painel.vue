<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import { useAuth } from '@/composables/useAuth';
import Sidebar from '@/components/Sidebar.vue';
import PageHeader from '@/components/PageHeader.vue';

const auth = useAuth();
const company = ref<any>(null);
const isLoading = ref(true);
const activeTab = ref<'hoje' | 'ultimos7dias' | 'mensal'>('hoje');

// Real data state
type RangeKey = 'hoje' | 'ultimos7dias' | 'mensal';
const rangeTotals = ref<Record<RangeKey, { entradas: number; saidas: number; lucro: number }>>({
    hoje: { entradas: 0, saidas: 0, lucro: 0 },
    ultimos7dias: { entradas: 0, saidas: 0, lucro: 0 },
    mensal: { entradas: 0, saidas: 0, lucro: 0 },
});

const commissionsByRange = ref<Record<RangeKey, Array<{ collaborator_id: number; collaborator_name: string; total: number }>>>({
    hoje: [],
    ultimos7dias: [],
    mensal: [],
});

const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(value);
};

const currentTotals = computed(() => rangeTotals.value[activeTab.value]);
const currentCommissions = computed(() => commissionsByRange.value[activeTab.value]);
const maxValueComissoes = computed(() => {
    const values = currentCommissions.value.map((c) => Number(c.total || 0));
    return values.length ? Math.max(...values) : 0;
});

const commissionsTotal = computed(() => currentCommissions.value.reduce((sum, c) => sum + Number(c.total || 0), 0));

const makeRange = (range: RangeKey) => {
    const today = new Date();
    const formatYMD = (d: Date) => d.toISOString().split('T')[0];
    if (range === 'hoje') {
        const ymd = formatYMD(today);
        return { start: ymd, end: ymd };
    }
    if (range === 'ultimos7dias') {
        const past = new Date(today);
        past.setDate(today.getDate() - 6);
        return { start: formatYMD(past), end: formatYMD(today) };
    }
    // mensal (current month)
    const first = new Date(today.getFullYear(), today.getMonth(), 1);
    const last = new Date(today.getFullYear(), today.getMonth() + 1, 0);
    return { start: formatYMD(first), end: formatYMD(last) };
};

const fetchRangeData = async (range: RangeKey) => {
    const { start, end } = makeRange(range);
    try {
        // Financial records totals
        const recordsRes = await fetch('/api/registros/financial-records/between-dates', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${auth.token.value}`,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ start_date: start, end_date: end }),
        });
        const recordsJson = await recordsRes.json();
        const items = Array.isArray(recordsJson?.data) ? recordsJson.data : [];
        const entradas = items.filter((r: any) => r.type === 'entry' || r.entry_type_id).reduce((s: number, r: any) => s + Number(r.amount || 0), 0);
        const saidas = items.filter((r: any) => r.type === 'exit' || r.exit_type_id).reduce((s: number, r: any) => s + Number(r.amount || 0), 0);
        rangeTotals.value[range] = { entradas, saidas, lucro: entradas - saidas };

        // Commissions summary by collaborator
        const commRes = await fetch('/api/registros/commissions/summary-by-collaborator', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${auth.token.value}`,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ start_date: start, end_date: end }),
        });
        const commJson = await commRes.json();
        commissionsByRange.value[range] = Array.isArray(commJson) ? commJson.map((c: any) => ({
            collaborator_id: c.collaborator_id,
            collaborator_name: c.collaborator_name,
            total: Number(c.total || 0),
        })) : [];
    } catch (e) {
        console.error('Erro ao carregar dados do painel:', e);
    }
};

const maxValueLucroInvestimento = computed(() => {
    return Math.max(...mockDataUltimos7Dias.graficoLucroInvestimento.flatMap(item => [item.lucro, item.investimento]));
});

const maxValueEntradasSaidas = computed(() => {
    return Math.max(...mockDataMensal.graficoEntradasSaidas.flatMap(item => [item.entradas, item.saidas]));
});

const maxValueLinha = computed(() => {
    return Math.max(...mockDataMensal.graficoLinha.flatMap(item => [item.lucro, item.investimento]));
});

onMounted(async () => {
    // Verificar autenticação
    if (!auth.isAuthenticated.value) {
        window.location.href = '/login';
        return;
    }
    
    try {
        const response = await fetch('/api/company', {
            headers: {
                'Authorization': `Bearer ${auth.token.value}`,
            },
        });

        if (response.ok) {
            const data = await response.json();
            company.value = data.data;
        }
        // Fetch real dashboard data for all ranges
        await Promise.all([
            fetchRangeData('hoje'),
            fetchRangeData('ultimos7dias'),
            fetchRangeData('mensal'),
        ]);
    } catch (error) {
        console.error('Erro ao carregar empresa:', error);
    } finally {
        isLoading.value = false;
    }
});
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-amber-50 to-orange-100 flex">
        <Head title="Painel - MEU NEGÓCIO" />

        <!-- Sidebar -->
        <Sidebar active-route="painel" />

        <!-- Main Content with margin for sidebar -->
        <div class="flex-1 lg:ml-64 transition-all duration-300">
            <PageHeader title-class="ml-12 lg:ml-0" />

            <!-- Main Content -->
            <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div v-if="isLoading" class="flex items-center justify-center min-h-96">
                <div class="text-center">
                    <svg class="animate-spin h-12 w-12 text-orange-500 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="text-gray-600">Carregando dados...</p>
                </div>
            </div>

            <div v-else>
                <!-- Title -->
                <h2 class="text-2xl font-bold text-center text-orange-600 mb-6">Painel</h2>

                <!-- Tabs -->
                <div class="flex justify-center gap-2 mb-8">
                    <button
                        @click="activeTab = 'hoje'"
                        :class="[
                            'px-6 py-2 rounded-full font-medium transition-all',
                            activeTab === 'hoje' 
                                ? 'bg-orange-500 text-white shadow-lg' 
                                : 'bg-orange-100 text-orange-600 hover:bg-orange-200'
                        ]"
                    >
                        Hoje
                    </button>
                    <button
                        @click="activeTab = 'ultimos7dias'"
                        :class="[
                            'px-6 py-2 rounded-full font-medium transition-all',
                            activeTab === 'ultimos7dias' 
                                ? 'bg-orange-500 text-white shadow-lg' 
                                : 'bg-orange-100 text-orange-600 hover:bg-orange-200'
                        ]"
                    >
                        Últimos 7 dias
                    </button>
                    <button
                        @click="activeTab = 'mensal'"
                        :class="[
                            'px-6 py-2 rounded-full font-medium transition-all',
                            activeTab === 'mensal' 
                                ? 'bg-orange-500 text-white shadow-lg' 
                                : 'bg-orange-100 text-orange-600 hover:bg-orange-200'
                        ]"
                    >
                        Mensal
                    </button>
                </div>

                <!-- Hoje View -->
                <div v-if="activeTab === 'hoje'" class="space-y-6">
                    <!-- Cards de Resumo -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="bg-white rounded-lg p-4 text-center shadow-md">
                            <p class="text-2xl font-bold text-orange-500">{{ formatCurrency(currentTotals.entradas) }}</p>
                            <p class="text-sm text-gray-600">Entradas</p>
                        </div>
                        <div class="bg-white rounded-lg p-4 text-center shadow-md">
                            <p class="text-2xl font-bold text-orange-500">{{ formatCurrency(currentTotals.saidas) }}</p>
                            <p class="text-sm text-gray-600">Saídas</p>
                        </div>
                        <div class="bg-white rounded-lg p-4 text-center shadow-md">
                            <p class="text-2xl font-bold text-orange-500">{{ formatCurrency(currentTotals.lucro) }}</p>
                            <p class="text-sm text-gray-600">Lucro</p>
                        </div>
                        <div class="bg-white rounded-lg p-4 text-center shadow-md">
                            <p class="text-2xl font-bold text-orange-500">{{ formatCurrency(commissionsTotal) }}</p>
                            <p class="text-sm text-gray-600">Investimentos</p>
                        </div>
                    </div>

                    <!-- Comissões Card -->
                    <div class="bg-white rounded-lg p-6 shadow-md">
                        <h3 class="text-xl font-bold text-orange-600 text-center mb-6">
                            {{ formatCurrency(commissionsTotal) }}
                        </h3>
                        <p class="text-center text-gray-600 mb-6">Comissões</p>
                        
                        <!-- Gráfico de Barras Verticais -->
                        <div class="flex items-end justify-center gap-8 h-64">
                            <div 
                                v-for="item in currentCommissions" 
                                :key="item.collaborator_id"
                                class="flex flex-col items-center gap-2"
                            >
                                <div class="flex items-end h-48">
                                    <div 
                                        class="w-16 bg-orange-500 rounded-t-lg transition-all hover:bg-orange-600"
                                        :style="{ height: `${(item.total / maxValueComissoes) * 100}%` }"
                                    ></div>
                                </div>
                                <span class="text-sm text-gray-600">{{ item.collaborator_name }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Últimos 7 Dias View -->
                <div v-if="activeTab === 'ultimos7dias'" class="space-y-6">
                    <!-- Cards de Resumo -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="bg-white rounded-lg p-4 text-center shadow-md">
                            <p class="text-2xl font-bold text-orange-500">{{ formatCurrency(currentTotals.entradas) }}</p>
                            <p class="text-sm text-gray-600">Entradas</p>
                        </div>
                        <div class="bg-white rounded-lg p-4 text-center shadow-md">
                            <p class="text-2xl font-bold text-orange-500">{{ formatCurrency(currentTotals.saidas) }}</p>
                            <p class="text-sm text-gray-600">Saídas</p>
                        </div>
                        <div class="bg-white rounded-lg p-4 text-center shadow-md">
                            <p class="text-2xl font-bold text-orange-500">{{ formatCurrency(currentTotals.lucro) }}</p>
                            <p class="text-sm text-gray-600">Lucro</p>
                        </div>
                        <div class="bg-white rounded-lg p-4 text-center shadow-md">
                            <p class="text-sm text-gray-600">Comissões</p>
                        </div>
                    </div>

                    <!-- Comissões -->
                    <div class="bg-white rounded-lg p-6 shadow-md">
                        <h3 class="text-xl font-bold text-orange-600 text-center mb-2">
                            {{ formatCurrency(commissionsTotal) }}
                        </h3>
                        <p class="text-center text-gray-600 mb-6">Comissões</p>
                        
                        <div class="flex items-end justify-center gap-8 h-48">
                            <div 
                                v-for="item in currentCommissions" 
                                :key="item.collaborator_id"
                                class="flex flex-col items-center gap-2"
                            >
                                <div class="flex items-end h-32">
                                    <div 
                                        class="w-16 bg-orange-500 rounded-t-lg transition-all hover:bg-orange-600"
                                        :style="{ height: `${(item.total / maxValueComissoes) * 100}%` }"
                                    ></div>
                                </div>
                                <span class="text-sm text-gray-600">{{ item.collaborator_name }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Comissões -->
                    <div class="bg-white rounded-lg p-6 shadow-md">
                        <h3 class="text-xl font-bold text-orange-600 text-center mb-2">
                            {{ formatCurrency(commissionsTotal) }}
                        </h3>
                        <p class="text-center text-gray-600 mb-6">Comissões</p>
                        
                        <div class="flex items-end justify-center gap-8 h-48">
                            <div 
                                v-for="item in currentCommissions" 
                                :key="item.collaborator_id"
                                class="flex flex-col items-center gap-2"
                            >
                                <div class="flex items-end h-32">
                                    <div 
                                        class="w-16 bg-orange-500 rounded-t-lg transition-all hover:bg-orange-600"
                                        :style="{ height: `${(item.total / maxValueComissoes) * 100}%` }"
                                    ></div>
                                </div>
                                <span class="text-sm text-gray-600">{{ item.collaborator_name }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mensal View -->
                <div v-if="activeTab === 'mensal'" class="space-y-6">
                    <!-- Cards de Resumo -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="bg-white rounded-lg p-4 text-center shadow-md">
                            <p class="text-2xl font-bold text-orange-500">{{ formatCurrency(currentTotals.entradas) }}</p>
                            <p class="text-sm text-gray-600">Entradas</p>
                        </div>
                        <div class="bg-white rounded-lg p-4 text-center shadow-md">
                            <p class="text-2xl font-bold text-orange-500">{{ formatCurrency(currentTotals.saidas) }}</p>
                            <p class="text-sm text-gray-600">Saídas</p>
                        </div>
                        <div class="bg-white rounded-lg p-4 text-center shadow-md">
                            <p class="text-2xl font-bold text-orange-500">{{ formatCurrency(currentTotals.lucro) }}</p>
                            <p class="text-sm text-gray-600">Lucro</p>
                        </div>
                        <div class="bg-white rounded-lg p-4 text-center shadow-md">
                            <p class="text-2xl font-bold text-orange-500">{{ formatCurrency(commissionsTotal) }}</p>
                            <p class="text-sm text-gray-600">Comissões</p>
                        </div>
                    </div>

                    <!-- (Gráficos mensais removidos por enquanto; serão substituídos por Chart.js com dados reais) -->

                    <!-- Comissões -->
                    <div class="bg-white rounded-lg p-6 shadow-md">
                        <h3 class="text-xl font-bold text-orange-600 text-center mb-2">
                            {{ formatCurrency(commissionsTotal) }}
                        </h3>
                        <p class="text-center text-gray-600 mb-6">Comissões</p>
                        
                        <div class="flex items-end justify-center gap-12 h-48">
                            <div 
                                v-for="item in currentCommissions" 
                                :key="item.collaborator_id"
                                class="flex flex-col items-center gap-2"
                            >
                                <div class="flex items-end h-32">
                                    <div 
                                        class="w-20 bg-orange-500 rounded-t-lg transition-all hover:bg-orange-600"
                                        :style="{ height: `${(item.total / maxValueComissoes) * 100}%` }"
                                    ></div>
                                </div>
                                <span class="text-sm text-gray-600">{{ item.collaborator_name }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        </div>
    </div>
</template>
