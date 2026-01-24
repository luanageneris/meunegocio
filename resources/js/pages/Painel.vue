<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import { useAuth } from '@/composables/useAuth';
import { usePrivacy } from '@/composables/usePrivacy';
import Sidebar from '@/components/Sidebar.vue';
import PageHeaderTitle from '@/components/PageHeaderTitle.vue';
import LoadingSpinner from '@/components/LoadingSpinner.vue';

const auth = useAuth();
const { isPrivacyMode } = usePrivacy();
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

const dailyBreakdown = ref<Array<{ date: string; entradas: number; saidas: number; lucro: number }>>([]);

const monthlyBreakdown = ref<Array<{ month: string; monthNum: number; entradas: number; saidas: number; lucro: number }>>([]);

const formatCurrency = (value: number) => {
    if (isPrivacyMode.value) {
        return 'R$ •••••';
    }
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

const maxValueDaily = computed(() => {
    if (!dailyBreakdown.value.length) return 0;
    return Math.max(...dailyBreakdown.value.flatMap(d => [d.entradas, d.saidas, d.lucro]));
});

const maxValueMonthly = computed(() => {
    if (!monthlyBreakdown.value.length) return 0;
    return Math.max(...monthlyBreakdown.value.flatMap(m => [m.entradas, m.saidas, m.lucro]));
});

const formatDayLabel = (dateStr: string) => {
    const date = new Date(dateStr + 'T00:00:00');
    return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
};

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
    // mensal (current year)
    const first = new Date(today.getFullYear(), 0, 1);
    const last = new Date(today.getFullYear(), 11, 31);
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

        // For últimos 7 dias, calculate daily breakdown
        if (range === 'ultimos7dias') {
            const dailyMap = new Map<string, { entradas: number; saidas: number }>();
            items.forEach((r: any) => {
                const date = r.date?.split('T')[0] || r.date;
                if (!date) return;
                if (!dailyMap.has(date)) {
                    dailyMap.set(date, { entradas: 0, saidas: 0 });
                }
                const day = dailyMap.get(date)!;
                if (r.type === 'entry' || r.entry_type_id) {
                    day.entradas += Number(r.amount || 0);
                } else {
                    day.saidas += Number(r.amount || 0);
                }
            });
            
            // Generate array for last 7 days
            const breakdown: Array<{ date: string; entradas: number; saidas: number; lucro: number }> = [];
            const today = new Date();
            for (let i = 6; i >= 0; i--) {
                const d = new Date(today);
                d.setDate(today.getDate() - i);
                const dateStr = d.toISOString().split('T')[0];
                const data = dailyMap.get(dateStr) || { entradas: 0, saidas: 0 };
                breakdown.push({
                    date: dateStr,
                    entradas: data.entradas,
                    saidas: data.saidas,
                    lucro: data.entradas - data.saidas,
                });
            }
            dailyBreakdown.value = breakdown;
        }

        // For mensal, calculate monthly breakdown (all 12 months)
        if (range === 'mensal') {
            const monthlyMap = new Map<number, { entradas: number; saidas: number }>();
            
            // Initialize all 12 months with 0
            for (let m = 0; m < 12; m++) {
                monthlyMap.set(m, { entradas: 0, saidas: 0 });
            }
            
            items.forEach((r: any) => {
                const dateStr = r.date?.split('T')[0] || r.date;
                if (!dateStr) return;
                const date = new Date(dateStr + 'T00:00:00');
                const monthNum = date.getMonth();
                
                if (!monthlyMap.has(monthNum)) {
                    monthlyMap.set(monthNum, { entradas: 0, saidas: 0 });
                }
                const month = monthlyMap.get(monthNum)!;
                
                if (r.type === 'entry' || r.entry_type_id) {
                    month.entradas += Number(r.amount || 0);
                } else {
                    month.saidas += Number(r.amount || 0);
                }
            });
            
            // Generate array for 12 months
            const monthNames = ['Jan', 'Fev', 'Mar', 'Abr', 'Maio', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
            const breakdown: Array<{ month: string; monthNum: number; entradas: number; saidas: number; lucro: number }> = [];
            
            for (let m = 0; m < 12; m++) {
                const data = monthlyMap.get(m) || { entradas: 0, saidas: 0 };
                breakdown.push({
                    month: monthNames[m],
                    monthNum: m,
                    entradas: data.entradas,
                    saidas: data.saidas,
                    lucro: data.entradas - data.saidas,
                });
            }
            monthlyBreakdown.value = breakdown;
        }
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
    <div class="min-h-screen bg-gradient-to-br from-amber-50 to-orange-100 flex overflow-x-hidden">
        <Head title="Painel - MEU NEGÓCIO" />

        <!-- Sidebar -->
        <Sidebar active-route="painel" />

        <!-- Main Content with margin for sidebar -->
        <div class="flex-1 lg:ml-64 transition-all duration-300 overflow-x-hidden">
            <PageHeaderTitle 
                title="Painel" 
                subtitle="Acompanhe o desempenho financeiro do seu negócio"
            />

            <!-- Main Content -->
            <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <LoadingSpinner v-if="isLoading" />

            <div v-else>
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
                            <p class="text-sm text-gray-600">Comissões</p>
                        </div>
                    </div>

                    <!-- Comissões Card -->
                    <div class="bg-white rounded-lg p-6 shadow-md">
                        <h3 class="text-xl font-bold text-orange-600 text-center mb-6">
                            {{ formatCurrency(commissionsTotal) }}
                        </h3>
                        <p class="text-center text-gray-600 mb-6">Comissões</p>
                        
                        <!-- Aviso quando não há registros -->
                        <div v-if="currentCommissions.length === 0" class="flex items-center justify-center h-64">
                            <div class="text-center text-gray-400">
                                <svg class="mx-auto h-12 w-12 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <p class="text-lg font-medium">Nenhum registro hoje</p>
                                <p class="text-sm mt-1">Não há comissões registradas para este período</p>
                            </div>
                        </div>
                        
                        <!-- Gráfico de Barras Verticais -->
                        <div v-else class="flex items-end justify-center gap-8 h-64">
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
                            <p class="text-2xl font-bold text-orange-500">{{ formatCurrency(commissionsTotal) }}</p>
                            <p class="text-sm text-gray-600">Comissões</p>
                        </div>
                    </div>

                    <!-- Gráficos Diários -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Entradas vs Saídas por Dia -->
                        <div class="bg-white rounded-lg p-6 shadow-md">
                            <h3 class="text-lg font-bold text-gray-800 mb-4">Entradas vs Saídas</h3>
                            <div class="flex justify-end gap-4 mb-4 text-sm">
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 bg-yellow-400 rounded"></div>
                                    <span class="text-gray-600">Entradas</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 bg-orange-500 rounded"></div>
                                    <span class="text-gray-600">Saídas</span>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <div v-for="day in dailyBreakdown" :key="day.date" class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm text-gray-600 w-16 text-right">{{ formatDayLabel(day.date) }}</span>
                                        <div class="flex-1 flex items-center gap-1">
                                            <div 
                                                class="h-6 bg-yellow-400 rounded"
                                                :style="{ width: `${maxValueDaily > 0 ? (day.entradas / maxValueDaily) * 100 : 0}%` }"
                                            ></div>
                                            <span class="text-xs text-gray-500 whitespace-nowrap">{{ formatCurrency(day.entradas) }}</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="w-16"></span>
                                        <div class="flex-1 flex items-center gap-1">
                                            <div 
                                                class="h-6 bg-orange-500 rounded"
                                                :style="{ width: `${maxValueDaily > 0 ? (day.saidas / maxValueDaily) * 100 : 0}%` }"
                                            ></div>
                                            <span class="text-xs text-gray-500 whitespace-nowrap">{{ formatCurrency(day.saidas) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Lucros vs Investimentos por Dia -->
                        <div class="bg-white rounded-lg p-6 shadow-md">
                            <h3 class="text-lg font-bold text-gray-800 mb-4">Lucros vs Investimentos</h3>
                            <div class="flex justify-end gap-4 mb-4 text-sm">
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 bg-green-500 rounded"></div>
                                    <span class="text-gray-600">Lucros</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 bg-blue-500 rounded"></div>
                                    <span class="text-gray-600">Investimentos</span>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <div v-for="day in dailyBreakdown" :key="day.date" class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm text-gray-600 w-16 text-right">{{ formatDayLabel(day.date) }}</span>
                                        <div class="flex-1 flex items-center gap-1">
                                            <div 
                                                class="h-6 bg-green-500 rounded"
                                                :style="{ width: `${maxValueDaily > 0 ? (Math.max(day.lucro, 0) / maxValueDaily) * 100 : 0}%` }"
                                            ></div>
                                            <span class="text-xs text-gray-500 whitespace-nowrap">{{ formatCurrency(day.lucro) }}</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="w-16"></span>
                                        <div class="flex-1 flex items-center gap-1">
                                            <div 
                                                class="h-6 bg-blue-500 rounded"
                                                :style="{ width: `${maxValueDaily > 0 ? (day.saidas / maxValueDaily) * 100 : 0}%` }"
                                            ></div>
                                            <span class="text-xs text-gray-500 whitespace-nowrap">{{ formatCurrency(day.saidas) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Comissões -->
                    <div class="bg-white rounded-lg p-6 shadow-md">
                        <h3 class="text-xl font-bold text-orange-600 text-center mb-2">
                            {{ formatCurrency(commissionsTotal) }}</h3>
                        <p class="text-center text-gray-600 mb-6">Comissões</p>
                        
                        <!-- Aviso quando não há registros -->
                        <div v-if="currentCommissions.length === 0" class="flex items-center justify-center h-48">
                            <div class="text-center text-gray-400">
                                <svg class="mx-auto h-12 w-12 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <p class="text-lg font-medium">Nenhum registro</p>
                                <p class="text-sm mt-1">Não há comissões registradas para este período</p>
                            </div>
                        </div>
                        
                        <div v-else class="flex items-end justify-center gap-8 h-48">
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

                    <!-- Gráfico de Barras Agrupadas: Entradas vs Saídas por Mês -->
                    <div class="bg-white rounded-lg p-6 shadow-md">
                        <div class="flex flex-col md:flex-row justify-between items-center mb-6">
                            <div>
                                <h3 class="text-xl font-bold text-orange-600">{{ formatCurrency(currentTotals.entradas) }}</h3>
                                <p class="text-sm text-gray-600">Entrada Anual</p>
                            </div>
                            <div class="text-right">
                                <h3 class="text-xl font-bold text-orange-600">{{ formatCurrency(currentTotals.saidas) }}</h3>
                                <p class="text-sm text-gray-600">Saída Anual</p>
                            </div>
                        </div>
                        
                        <div class="flex justify-end gap-4 mb-4 text-sm">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 bg-yellow-400 rounded"></div>
                                <span class="text-gray-600">Entrada</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 bg-orange-500 rounded"></div>
                                <span class="text-gray-600">Saída</span>
                            </div>
                        </div>
                        
                        <!-- Gráfico -->
                        <div class="flex items-end justify-around gap-1 h-64 px-4">
                            <div v-for="month in monthlyBreakdown" :key="month.monthNum" class="flex flex-col items-center gap-1 flex-1">
                                <!-- Barras Agrupadas -->
                                <div class="flex items-end gap-0.5 h-48">
                                    <!-- Entrada (amarelo) -->
                                    <div 
                                        class="flex-1 bg-yellow-400 rounded-t transition-all hover:bg-yellow-500"
                                        :style="{ height: `${maxValueMonthly > 0 ? (month.entradas / maxValueMonthly) * 100 : 0}%` }"
                                    ></div>
                                    <!-- Saída (laranja) -->
                                    <div 
                                        class="flex-1 bg-orange-500 rounded-t transition-all hover:bg-orange-600"
                                        :style="{ height: `${maxValueMonthly > 0 ? (month.saidas / maxValueMonthly) * 100 : 0}%` }"
                                    ></div>
                                </div>
                                <span class="text-xs text-gray-600 mt-2">{{ month.month }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Gráfico de Linhas: Lucro vs Saídas/Investimentos por Mês -->
                    <div class="bg-white rounded-lg p-6 shadow-md">
                        <div class="flex flex-col md:flex-row justify-between items-center mb-6">
                            <div>
                                <h3 class="text-xl font-bold text-green-600">{{ formatCurrency(currentTotals.lucro) }}</h3>
                                <p class="text-sm text-gray-600">Lucro Anual</p>
                            </div>
                            <div class="text-right">
                                <h3 class="text-xl font-bold text-blue-600">{{ formatCurrency(currentTotals.saidas) }}</h3>
                                <p class="text-sm text-gray-600">Investimentos Anual</p>
                            </div>
                        </div>
                        
                        <div class="flex justify-end gap-4 mb-6 text-sm">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 bg-yellow-400 rounded-full"></div>
                                <span class="text-gray-600">Lucro</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 bg-orange-500 rounded-full"></div>
                                <span class="text-gray-600">Investimento</span>
                            </div>
                        </div>
                        
                        <!-- Gráfico de Linhas SVG -->
                        <svg class="w-full h-64" viewBox="0 0 1200 250" preserveAspectRatio="xMidYMid meet">
                            <!-- Grid -->
                            <defs>
                                <pattern id="grid" width="100" height="50" patternUnits="userSpaceOnUse">
                                    <path d="M 100 0 L 0 0 0 50" fill="none" stroke="#f3f4f6" stroke-width="1"/>
                                </pattern>
                            </defs>
                            <rect width="1200" height="250" fill="url(#grid)" />
                            
                            <!-- Linhas de Lucro (amarelo) e Investimentos (laranja) -->
                            <polyline 
                                :points="monthlyBreakdown.map((m, i) => `${50 + i * 95},${250 - (maxValueMonthly > 0 ? (m.lucro / maxValueMonthly) * 200 : 0)}`).join(' ')" 
                                fill="none" 
                                stroke="#facc15" 
                                stroke-width="3" 
                                stroke-linecap="round" 
                                stroke-linejoin="round"
                            />
                            <polyline 
                                :points="monthlyBreakdown.map((m, i) => `${50 + i * 95},${250 - (maxValueMonthly > 0 ? (m.saidas / maxValueMonthly) * 200 : 0)}`).join(' ')" 
                                fill="none" 
                                stroke="#f97316" 
                                stroke-width="3" 
                                stroke-linecap="round" 
                                stroke-linejoin="round"
                            />
                            
                            <!-- Pontos na linha de Lucro -->
                            <circle 
                                v-for="(m, i) in monthlyBreakdown" 
                                :key="`lucro-${i}`"
                                :cx="50 + i * 95" 
                                :cy="250 - (maxValueMonthly > 0 ? (m.lucro / maxValueMonthly) * 200 : 0)" 
                                r="5" 
                                fill="#facc15" 
                                stroke="white" 
                                stroke-width="2"
                            />
                            
                            <!-- Pontos na linha de Investimentos -->
                            <circle 
                                v-for="(m, i) in monthlyBreakdown" 
                                :key="`investimento-${i}`"
                                :cx="50 + i * 95" 
                                :cy="250 - (maxValueMonthly > 0 ? (m.saidas / maxValueMonthly) * 200 : 0)" 
                                r="5" 
                                fill="#f97316" 
                                stroke="white" 
                                stroke-width="2"
                            />
                            
                            <!-- Labels dos meses -->
                            <text 
                                v-for="(m, i) in monthlyBreakdown" 
                                :key="`label-${i}`"
                                :x="50 + i * 95" 
                                y="240" 
                                text-anchor="middle" 
                                font-size="12" 
                                fill="#666"
                            >
                                {{ m.month }}
                            </text>
                        </svg>
                    </div>

                    <!-- Comissões -->
                    <div class="bg-white rounded-lg p-6 shadow-md">
                        <h3 class="text-xl font-bold text-orange-600 text-center mb-2">
                            {{ formatCurrency(commissionsTotal) }}
                        </h3>
                        <p class="text-center text-gray-600 mb-6">Comissões</p>
                        
                        <!-- Aviso quando não há registros -->
                        <div v-if="currentCommissions.length === 0" class="flex items-center justify-center h-48">
                            <div class="text-center text-gray-400">
                                <svg class="mx-auto h-12 w-12 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <p class="text-lg font-medium">Nenhum registro</p>
                                <p class="text-sm mt-1">Não há comissões registradas para este período</p>
                            </div>
                        </div>
                        
                        <div v-else class="flex items-end justify-center gap-12 h-48">
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
