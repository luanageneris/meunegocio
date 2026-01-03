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

// Mock Data
const mockDataHoje = {
    entradas: 2035.10,
    saidas: 849.80,
    lucro: 2035.10,
    investimentos: 849.80,
    comissoes: 849.80,
    graficoComissoes: [
        { colaborador: 'João', valor: 200 },
        { colaborador: 'Maria', valor: 300 },
        { colaborador: 'Pedro', valor: 150 },
        { colaborador: 'Ana', valor: 100 },
    ]
};

const mockDataUltimos7Dias = {
    entradas: 2035.10,
    saidas: 849.80,
    lucro: 2035.10,
    investimentos: 849.80,
    comissoes: 849.80,
    graficoLucroSaida: [
        { colaborador: 'João', lucro: 450, saida: 200 },
        { colaborador: 'Maria', lucro: 520, saida: 180 },
        { colaborador: 'Pedro', lucro: 380, saida: 150 },
        { colaborador: 'Ana', lucro: 290, saida: 120 },
        { colaborador: 'Carlos', lucro: 180, saida: 80 },
        { colaborador: 'Fernanda', lucro: 215, saida: 120 },
    ],
    graficoLucroInvestimento: [
        { colaborador: 'João', lucro: 450, investimento: 150 },
        { colaborador: 'Maria', lucro: 520, investimento: 180 },
        { colaborador: 'Pedro', lucro: 380, investimento: 120 },
        { colaborador: 'Ana', lucro: 290, investimento: 90 },
        { colaborador: 'Carlos', lucro: 180, investimento: 60 },
        { colaborador: 'Fernanda', lucro: 215, investimento: 80 },
    ],
    graficoComissoes: [
        { dia: 'Seg', valor: 150 },
        { dia: 'Ter', valor: 200 },
        { dia: 'Qua', valor: 280 },
        { dia: 'Qui', valor: 120 },
    ]
};

const mockDataMensal = {
    entradas: 2035.10,
    saidas: 849.80,
    lucroAtual: 2035.10,
    investimentoAtual: 849.80,
    comissoes: 849.80,
    graficoEntradasSaidas: [
        { mes: 'Jan', entradas: 1200, saidas: 800 },
        { mes: 'Fev', entradas: 1500, saidas: 900 },
        { mes: 'Mar', entradas: 1800, saidas: 700 },
        { mes: 'Abr', entradas: 1100, saidas: 600 },
        { mes: 'Mai', entradas: 1400, saidas: 750 },
        { mes: 'Jun', entradas: 1600, saidas: 850 },
        { mes: 'Jul', entradas: 1900, saidas: 950 },
        { mes: 'Ago', entradas: 1700, saidas: 800 },
        { mes: 'Set', entradas: 1550, saidas: 720 },
        { mes: 'Out', entradas: 1820, saidas: 880 },
        { mes: 'Nov', entradas: 1650, saidas: 790 },
        { mes: 'Dez', entradas: 1480, saidas: 690 },
    ],
    graficoLinha: [
        { mes: 'Jan', lucro: 400, investimento: 200 },
        { mes: 'Fev', lucro: 600, investimento: 300 },
        { mes: 'Mar', lucro: 1100, investimento: 400 },
        { mes: 'Abr', lucro: 500, investimento: 250 },
        { mes: 'Mai', lucro: 650, investimento: 350 },
        { mes: 'Jun', lucro: 750, investimento: 320 },
        { mes: 'Jul', lucro: 950, investimento: 450 },
        { mes: 'Ago', lucro: 900, investimento: 400 },
        { mes: 'Set', lucro: 830, investimento: 380 },
        { mes: 'Out', lucro: 940, investimento: 420 },
        { mes: 'Nov', lucro: 860, investimento: 390 },
        { mes: 'Dez', lucro: 790, investimento: 360 },
    ],
    graficoComissoes: [
        { mes: 'Set', valor: 200 },
        { mes: 'Out', valor: 280 },
        { mes: 'Nov', valor: 180 },
        { mes: 'Dez', valor: 150 },
    ]
};

const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(value);
};

const maxValueComissoes = computed(() => {
    if (activeTab.value === 'hoje') {
        return Math.max(...mockDataHoje.graficoComissoes.map(item => item.valor));
    } else if (activeTab.value === 'ultimos7dias') {
        return Math.max(...mockDataUltimos7Dias.graficoComissoes.map(item => item.valor));
    } else {
        return Math.max(...mockDataMensal.graficoComissoes.map(item => item.valor));
    }
});

const maxValueLucroSaida = computed(() => {
    return Math.max(...mockDataUltimos7Dias.graficoLucroSaida.flatMap(item => [item.lucro, item.saida]));
});

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
                            <p class="text-2xl font-bold text-orange-500">{{ formatCurrency(mockDataHoje.entradas) }}</p>
                            <p class="text-sm text-gray-600">Entradas</p>
                        </div>
                        <div class="bg-white rounded-lg p-4 text-center shadow-md">
                            <p class="text-2xl font-bold text-orange-500">{{ formatCurrency(mockDataHoje.saidas) }}</p>
                            <p class="text-sm text-gray-600">Saídas</p>
                        </div>
                        <div class="bg-white rounded-lg p-4 text-center shadow-md">
                            <p class="text-2xl font-bold text-orange-500">{{ formatCurrency(mockDataHoje.lucro) }}</p>
                            <p class="text-sm text-gray-600">Lucro</p>
                        </div>
                        <div class="bg-white rounded-lg p-4 text-center shadow-md">
                            <p class="text-2xl font-bold text-orange-500">{{ formatCurrency(mockDataHoje.investimentos) }}</p>
                            <p class="text-sm text-gray-600">Investimentos</p>
                        </div>
                    </div>

                    <!-- Comissões Card -->
                    <div class="bg-white rounded-lg p-6 shadow-md">
                        <h3 class="text-xl font-bold text-orange-600 text-center mb-6">
                            {{ formatCurrency(mockDataHoje.comissoes) }}
                        </h3>
                        <p class="text-center text-gray-600 mb-6">Comissões</p>
                        
                        <!-- Gráfico de Barras Verticais -->
                        <div class="flex items-end justify-center gap-8 h-64">
                            <div 
                                v-for="item in mockDataHoje.graficoComissoes" 
                                :key="item.colaborador"
                                class="flex flex-col items-center gap-2"
                            >
                                <div class="flex items-end h-48">
                                    <div 
                                        class="w-16 bg-orange-500 rounded-t-lg transition-all hover:bg-orange-600"
                                        :style="{ height: `${(item.valor / maxValueComissoes) * 100}%` }"
                                    ></div>
                                </div>
                                <span class="text-sm text-gray-600">{{ item.colaborador }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Últimos 7 Dias View -->
                <div v-if="activeTab === 'ultimos7dias'" class="space-y-6">
                    <!-- Cards de Resumo -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="bg-white rounded-lg p-4 text-center shadow-md">
                            <p class="text-2xl font-bold text-orange-500">{{ formatCurrency(mockDataUltimos7Dias.entradas) }}</p>
                            <p class="text-sm text-gray-600">Entradas</p>
                        </div>
                        <div class="bg-white rounded-lg p-4 text-center shadow-md">
                            <p class="text-2xl font-bold text-orange-500">{{ formatCurrency(mockDataUltimos7Dias.saidas) }}</p>
                            <p class="text-sm text-gray-600">Saídas</p>
                        </div>
                        <div class="bg-white rounded-lg p-4 text-center shadow-md">
                            <p class="text-2xl font-bold text-orange-500">{{ formatCurrency(mockDataUltimos7Dias.lucro) }}</p>
                            <p class="text-sm text-gray-600">Lucro</p>
                        </div>
                        <div class="bg-white rounded-lg p-4 text-center shadow-md">
                            <p class="text-2xl font-bold text-orange-500">{{ formatCurrency(mockDataUltimos7Dias.investimentos) }}</p>
                            <p class="text-sm text-gray-600">Investimentos</p>
                        </div>
                    </div>

                    <!-- Gráficos Horizontais -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Lucro vs Saída -->
                        <div class="bg-white rounded-lg p-6 shadow-md">
                            <div class="flex justify-end gap-4 mb-4 text-sm">
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 bg-yellow-400 rounded"></div>
                                    <span class="text-gray-600">Lucro</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 bg-orange-500 rounded"></div>
                                    <span class="text-gray-600">Saída</span>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <div v-for="item in mockDataUltimos7Dias.graficoLucroSaida" :key="item.colaborador" class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm text-gray-600 w-20 text-right">{{ item.colaborador }}</span>
                                        <div class="flex-1 flex items-center gap-1">
                                            <div 
                                                class="h-6 bg-yellow-400 rounded"
                                                :style="{ width: `${(item.lucro / maxValueLucroSaida) * 100}%` }"
                                            ></div>
                                            <span class="text-xs text-gray-500">{{ formatCurrency(item.lucro) }}</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="w-20"></span>
                                        <div class="flex-1 flex items-center gap-1">
                                            <div 
                                                class="h-6 bg-orange-500 rounded"
                                                :style="{ width: `${(item.saida / maxValueLucroSaida) * 100}%` }"
                                            ></div>
                                            <span class="text-xs text-gray-500">{{ formatCurrency(item.saida) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Lucro vs Investimento -->
                        <div class="bg-white rounded-lg p-6 shadow-md">
                            <div class="flex justify-end gap-4 mb-4 text-sm">
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 bg-yellow-400 rounded"></div>
                                    <span class="text-gray-600">Lucro</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 bg-orange-500 rounded"></div>
                                    <span class="text-gray-600">Investimento</span>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <div v-for="item in mockDataUltimos7Dias.graficoLucroInvestimento" :key="item.colaborador" class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm text-gray-600 w-20 text-right">{{ item.colaborador }}</span>
                                        <div class="flex-1 flex items-center gap-1">
                                            <div 
                                                class="h-6 bg-yellow-400 rounded"
                                                :style="{ width: `${(item.lucro / maxValueLucroInvestimento) * 100}%` }"
                                            ></div>
                                            <span class="text-xs text-gray-500">{{ formatCurrency(item.lucro) }}</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="w-20"></span>
                                        <div class="flex-1 flex items-center gap-1">
                                            <div 
                                                class="h-6 bg-orange-500 rounded"
                                                :style="{ width: `${(item.investimento / maxValueLucroInvestimento) * 100}%` }"
                                            ></div>
                                            <span class="text-xs text-gray-500">{{ formatCurrency(item.investimento) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Comissões -->
                    <div class="bg-white rounded-lg p-6 shadow-md">
                        <h3 class="text-xl font-bold text-orange-600 text-center mb-2">
                            {{ formatCurrency(mockDataUltimos7Dias.comissoes) }}
                        </h3>
                        <p class="text-center text-gray-600 mb-6">Comissões</p>
                        
                        <div class="flex items-end justify-center gap-8 h-48">
                            <div 
                                v-for="item in mockDataUltimos7Dias.graficoComissoes" 
                                :key="item.dia"
                                class="flex flex-col items-center gap-2"
                            >
                                <div class="flex items-end h-32">
                                    <div 
                                        class="w-16 bg-orange-500 rounded-t-lg transition-all hover:bg-orange-600"
                                        :style="{ height: `${(item.valor / maxValueComissoes) * 100}%` }"
                                    ></div>
                                </div>
                                <span class="text-sm text-gray-600">{{ item.dia }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mensal View -->
                <div v-if="activeTab === 'mensal'" class="space-y-6">
                    <!-- Cards de Resumo -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="bg-white rounded-lg p-4 text-center shadow-md">
                            <p class="text-2xl font-bold text-orange-500">{{ formatCurrency(mockDataMensal.entradas) }}</p>
                            <p class="text-sm text-gray-600">Entradas Atual</p>
                        </div>
                        <div class="bg-white rounded-lg p-4 text-center shadow-md">
                            <p class="text-2xl font-bold text-orange-500">{{ formatCurrency(mockDataMensal.saidas) }}</p>
                            <p class="text-sm text-gray-600">Saídas Atual</p>
                        </div>
                        <div class="bg-white rounded-lg p-4 text-center shadow-md">
                            <p class="text-2xl font-bold text-orange-500">{{ formatCurrency(mockDataMensal.lucroAtual) }}</p>
                            <p class="text-sm text-gray-600">Lucro Atual</p>
                        </div>
                        <div class="bg-white rounded-lg p-4 text-center shadow-md">
                            <p class="text-2xl font-bold text-orange-500">{{ formatCurrency(mockDataMensal.investimentoAtual) }}</p>
                            <p class="text-sm text-gray-600">Investimento Atual</p>
                        </div>
                    </div>

                    <!-- Gráfico de Barras Duplas (Entradas e Saídas) -->
                    <div class="bg-white rounded-lg p-6 shadow-md">
                        <div class="flex justify-center gap-4 mb-6 text-sm">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 bg-yellow-400 rounded"></div>
                                <span class="text-gray-600">Entradas</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 bg-orange-500 rounded"></div>
                                <span class="text-gray-600">Saídas</span>
                            </div>
                        </div>
                        
                        <div class="flex items-end justify-center gap-4 h-64 overflow-x-auto">
                            <div 
                                v-for="item in mockDataMensal.graficoEntradasSaidas" 
                                :key="item.mes"
                                class="flex flex-col items-center gap-2 flex-shrink-0"
                            >
                                <div class="flex items-end gap-1 h-48">
                                    <div 
                                        class="w-8 bg-yellow-400 rounded-t-lg"
                                        :style="{ height: `${(item.entradas / maxValueEntradasSaidas) * 100}%` }"
                                    ></div>
                                    <div 
                                        class="w-8 bg-orange-500 rounded-t-lg"
                                        :style="{ height: `${(item.saidas / maxValueEntradasSaidas) * 100}%` }"
                                    ></div>
                                </div>
                                <span class="text-xs text-gray-600">{{ item.mes }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Gráfico de Linha (Lucro e Investimento) -->
                    <div class="bg-white rounded-lg p-6 shadow-md">
                        <div class="flex justify-center gap-4 mb-6 text-sm">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 bg-yellow-400 rounded-full"></div>
                                <span class="text-gray-600">Lucro</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 bg-orange-500 rounded-full"></div>
                                <span class="text-gray-600">Investimento</span>
                            </div>
                        </div>
                        
                        <div class="relative h-64">
                            <svg class="w-full h-full" viewBox="0 0 800 200" preserveAspectRatio="none">
                                <!-- Grid lines -->
                                <line v-for="i in 5" :key="`grid-${i}`" 
                                    :x1="0" :y1="i * 40" :x2="800" :y2="i * 40" 
                                    stroke="#e5e7eb" stroke-width="1" />
                                
                                <!-- Linha Lucro -->
                                <polyline 
                                    :points="mockDataMensal.graficoLinha.map((item, i) => 
                                        `${i * (800 / (mockDataMensal.graficoLinha.length - 1))},${200 - (item.lucro / maxValueLinha) * 180}`
                                    ).join(' ')"
                                    fill="none"
                                    stroke="#fbbf24"
                                    stroke-width="3"
                                />
                                
                                <!-- Linha Investimento -->
                                <polyline 
                                    :points="mockDataMensal.graficoLinha.map((item, i) => 
                                        `${i * (800 / (mockDataMensal.graficoLinha.length - 1))},${200 - (item.investimento / maxValueLinha) * 180}`
                                    ).join(' ')"
                                    fill="none"
                                    stroke="#f97316"
                                    stroke-width="3"
                                />
                                
                                <!-- Pontos Lucro -->
                                <circle 
                                    v-for="(item, i) in mockDataMensal.graficoLinha" 
                                    :key="`lucro-${i}`"
                                    :cx="i * (800 / (mockDataMensal.graficoLinha.length - 1))"
                                    :cy="200 - (item.lucro / maxValueLinha) * 180"
                                    r="5"
                                    fill="#fbbf24"
                                />
                                
                                <!-- Pontos Investimento -->
                                <circle 
                                    v-for="(item, i) in mockDataMensal.graficoLinha" 
                                    :key="`inv-${i}`"
                                    :cx="i * (800 / (mockDataMensal.graficoLinha.length - 1))"
                                    :cy="200 - (item.investimento / maxValueLinha) * 180"
                                    r="5"
                                    fill="#f97316"
                                />
                            </svg>
                            
                            <!-- Labels dos meses -->
                            <div class="flex justify-between mt-2 text-xs text-gray-600">
                                <span v-for="item in mockDataMensal.graficoLinha" :key="item.mes">
                                    {{ item.mes }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Comissões -->
                    <div class="bg-white rounded-lg p-6 shadow-md">
                        <h3 class="text-xl font-bold text-orange-600 text-center mb-2">
                            {{ formatCurrency(mockDataMensal.comissoes) }}
                        </h3>
                        <p class="text-center text-gray-600 mb-6">Comissões</p>
                        
                        <div class="flex items-end justify-center gap-12 h-48">
                            <div 
                                v-for="item in mockDataMensal.graficoComissoes" 
                                :key="item.mes"
                                class="flex flex-col items-center gap-2"
                            >
                                <div class="flex items-end h-32">
                                    <div 
                                        class="w-20 bg-orange-500 rounded-t-lg transition-all hover:bg-orange-600"
                                        :style="{ height: `${(item.valor / maxValueComissoes) * 100}%` }"
                                    ></div>
                                </div>
                                <span class="text-sm text-gray-600">{{ item.mes }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        </div>
    </div>
</template>
