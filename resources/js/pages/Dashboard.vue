<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import { AlertTriangle, Building2, CalendarDays, ChevronLeft, ChevronRight, RefreshCw } from 'lucide-vue-next';
import Sidebar from '@/components/Sidebar.vue';
import Footer from '@/components/Footer.vue';
import FixedPageHeaderTitle from '@/components/FixedPageHeaderTitle.vue';
import { useAuth } from '@/composables/useAuth';
import { usePrivacy } from '@/composables/usePrivacy';

type DashboardDay = {
    date: string;
    day: number;
    entries: number;
    exits: number;
    net: number;
    status: 'empty' | 'entry' | 'exit' | 'positive' | 'negative' | 'zero';
    has_entries: boolean;
    has_exits: boolean;
    is_today: boolean;
};

type DashboardSummary = {
    company: {
        name: string | null;
    };
    month: number;
    year: number;
    month_label: string;
    totals: {
        entries: number;
        exits: number;
        commissions: number;
        profit: number;
        days_with_activity: number;
    };
    today: {
        date: string;
        entries: number;
        exits: number;
        net: number;
        has_activity: boolean;
    };
    days: DashboardDay[];
};

const auth = useAuth();
const { isPrivacyMode } = usePrivacy();

const monthOptions = [
    { value: 1, label: 'Janeiro' },
    { value: 2, label: 'Fevereiro' },
    { value: 3, label: 'Março' },
    { value: 4, label: 'Abril' },
    { value: 5, label: 'Maio' },
    { value: 6, label: 'Junho' },
    { value: 7, label: 'Julho' },
    { value: 8, label: 'Agosto' },
    { value: 9, label: 'Setembro' },
    { value: 10, label: 'Outubro' },
    { value: 11, label: 'Novembro' },
    { value: 12, label: 'Dezembro' },
];

const currentYear = new Date().getFullYear();
const selectedMonth = ref(new Date().getMonth() + 1);
const selectedYear = ref(currentYear);
const selectedDayKey = ref<string | null>(null);
const isLoading = ref(true);
const isRefreshing = ref(false);
const loadError = ref('');
const dashboardData = ref<DashboardSummary | null>(null);
const isReady = ref(false);

const yearOptions = computed(() => {
    const years: number[] = [];

    for (let year = currentYear - 5; year <= currentYear + 1; year += 1) {
        years.push(year);
    }

    return years;
});

const companyName = computed(() => dashboardData.value?.company?.name || auth.getCompanyName.value || 'Empresa');
const monthLabel = computed(() => monthOptions.find((item) => item.value === selectedMonth.value)?.label || 'Mês');
const monthTitle = computed(() => `${monthLabel.value} de ${selectedYear.value}`);

const getCsrfToken = (): string => {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
};

const formatCurrency = (value: number): string => {
    if (isPrivacyMode.value) {
        return 'R$ •••••';
    }

    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(value);
};

const formatCurrencyCompact = (value: number): string => {
    if (isPrivacyMode.value) {
        return 'R$ •••••';
    }

    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(value);
};

const formatSignedCurrency = (value: number): string => {
    if (isPrivacyMode.value) {
        return 'R$ •••••';
    }

    if (value > 0) {
        return `+${formatCurrency(value)}`;
    }

    if (value < 0) {
        return `-${formatCurrency(Math.abs(value))}`;
    }

    return formatCurrency(0);
};

const updateQueryString = () => {
    if (typeof window === 'undefined') {
        return;
    }

    const params = new URLSearchParams(window.location.search);
    params.set('month', String(selectedMonth.value));
    params.set('year', String(selectedYear.value));

    const query = params.toString();
    window.history.replaceState({}, '', `${window.location.pathname}${query ? `?${query}` : ''}`);
};

const loadDashboard = async (withSpinner = false) => {
    if (withSpinner) {
        isRefreshing.value = true;
    } else {
        isLoading.value = true;
    }

    loadError.value = '';

    try {
        const response = await fetch(`/api/dashboard/summary?month=${selectedMonth.value}&year=${selectedYear.value}`, {
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'include',
        });

        const payload = await response.json();

        if (!response.ok) {
            throw new Error(payload?.message || 'Não foi possível carregar o dashboard.');
        }

        dashboardData.value = payload.data;
        selectedDayKey.value = null;
        updateQueryString();
    } catch (error) {
        loadError.value = error instanceof Error ? error.message : 'Não foi possível carregar o dashboard.';
    } finally {
        isLoading.value = false;
        isRefreshing.value = false;
    }
};

const previousMonth = () => {
    if (selectedMonth.value === 1) {
        selectedMonth.value = 12;
        selectedYear.value -= 1;
        selectedDayKey.value = null;
        return;
    }

    selectedMonth.value -= 1;
    selectedDayKey.value = null;
};

const nextMonth = () => {
    if (selectedMonth.value === 12) {
        selectedMonth.value = 1;
        selectedYear.value += 1;
        selectedDayKey.value = null;
        return;
    }

    selectedMonth.value += 1;
    selectedDayKey.value = null;
};

const goToCurrentMonth = async () => {
    const now = new Date();
    const currentMonthValue = now.getMonth() + 1;
    const currentYearValue = now.getFullYear();

    if (selectedMonth.value === currentMonthValue && selectedYear.value === currentYearValue) {
        selectedDayKey.value = null;
        await loadDashboard(true);
        return;
    }

    selectedMonth.value = currentMonthValue;
    selectedYear.value = currentYearValue;
    selectedDayKey.value = null;
};

const calendarCells = computed<Array<DashboardDay | null>>(() => {
    if (!dashboardData.value) {
        return [];
    }

    const totalDays = new Date(selectedYear.value, selectedMonth.value, 0).getDate();
    const firstWeekday = new Date(selectedYear.value, selectedMonth.value - 1, 1).getDay();
    const dayMap = new Map(dashboardData.value.days.map((day) => [day.date, day]));
    const cells: Array<DashboardDay | null> = [];

    for (let index = 0; index < firstWeekday; index += 1) {
        cells.push(null);
    }

    for (let day = 1; day <= totalDays; day += 1) {
        const year = selectedYear.value;
        const month = String(selectedMonth.value).padStart(2, '0');
        const dayNumber = String(day).padStart(2, '0');
        const key = `${year}-${month}-${dayNumber}`;

        cells.push(
            dayMap.get(key) || {
                date: key,
                day,
                entries: 0,
                exits: 0,
                net: 0,
                status: 'empty',
                has_entries: false,
                has_exits: false,
                is_today: false,
            },
        );
    }

    return cells;
});

const statusLabel = (day: DashboardDay) => {
    if (day.status === 'entry') {
        return 'Entrada';
    }

    if (day.status === 'exit') {
        return 'Saída';
    }

    if (day.status === 'positive' || day.status === 'negative') {
        return 'Saldo';
    }

    if (day.status === 'zero') {
        return 'Empate';
    }

    return 'Sem lançamento';
};

const tooltipValueLabel = (day: DashboardDay) => {
    if (day.status === 'entry') {
        return formatSignedCurrency(day.entries);
    }

    if (day.status === 'exit') {
        return formatSignedCurrency(-day.exits);
    }

    return formatSignedCurrency(day.net);
};

const isSelectedDay = (day: DashboardDay) => selectedDayKey.value === day.date;

const toggleDayDetails = (day: DashboardDay) => {
    selectedDayKey.value = selectedDayKey.value === day.date ? null : day.date;
};

const dayValueLabel = (day: DashboardDay) => {
    if (day.status === 'empty') {
        return '';
    }

    if (day.status === 'entry') {
        return `+${formatCurrencyCompact(day.entries)}`;
    }

    if (day.status === 'exit') {
        return `-${formatCurrencyCompact(day.exits)}`;
    }

    if (day.net > 0) {
        return `+${formatCurrencyCompact(day.net)}`;
    }

    if (day.net < 0) {
        return `-${formatCurrencyCompact(Math.abs(day.net))}`;
    }

    return formatCurrencyCompact(0);
};

const cellStyle = (day: DashboardDay) => {
    if (day.status === 'empty') {
        return 'border border-dashed border-slate-200 bg-white/70 text-slate-300';
    }

    if (day.status === 'entry' || day.status === 'positive') {
        return 'border border-emerald-200 bg-emerald-50 text-emerald-950 shadow-sm';
    }

    if (day.status === 'exit' || day.status === 'negative') {
        return 'border border-rose-200 bg-rose-50 text-rose-950 shadow-sm';
    }

    return 'border border-amber-200 bg-amber-50 text-amber-950 shadow-sm';
};

watch([selectedMonth, selectedYear], async () => {
    if (!isReady.value) {
        return;
    }

    await loadDashboard(true);
});

onMounted(async () => {
    const params = new URLSearchParams(window.location.search);
    const queryMonth = Number(params.get('month'));
    const queryYear = Number(params.get('year'));

    if (Number.isInteger(queryMonth) && queryMonth >= 1 && queryMonth <= 12) {
        selectedMonth.value = queryMonth;
    }

    if (Number.isInteger(queryYear) && queryYear >= 2000) {
        selectedYear.value = queryYear;
    }

    selectedDayKey.value = null;

    const currentUser = auth.user.value || (await auth.fetchCurrentUser());

    if (!currentUser) {
        window.location.href = '/login';
        return;
    }

    if (!currentUser.company?.name) {
        window.location.href = '/setup-empresa';
        return;
    }

    await loadDashboard(false);
    isReady.value = true;
});
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-amber-50 to-orange-100 flex overflow-x-hidden">
        <Head title="Dashboard - MEU NEGÓCIO" />

        <Sidebar activeRoute="dashboard" />

        <div class="flex-1 min-w-0 lg:ml-64 transition-all duration-300 overflow-x-hidden flex flex-col">
            <FixedPageHeaderTitle
                title="Dashboard"
                :subtitle="`Bem-vindo, ${auth.getUserName.value} · ${companyName}`"
            />

            <main class="w-full min-w-0 max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-6 sm:py-8 flex-1 flex flex-col gap-4 sm:gap-6 overflow-x-hidden">

                <!-- Period filter -->
                <div class="bg-white rounded-xl shadow-lg p-4 sm:p-6">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex items-center gap-2 shrink-0">
                            <svg class="w-6 h-6 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <h2 class="text-xl font-bold text-gray-800">{{ monthTitle }}</h2>
                        </div>

                        <div class="flex flex-wrap items-end gap-3">
                            <button
                                type="button"
                                class="inline-flex items-center justify-center gap-2 px-4 py-2 border-2 border-amber-300 text-amber-700 rounded-lg font-medium text-sm hover:bg-amber-50 transition-all"
                                @click="previousMonth"
                            >
                                <ChevronLeft class="h-4 w-4" />
                                Mês anterior
                            </button>

                            <button
                                type="button"
                                class="inline-flex items-center justify-center gap-2 px-4 py-2 border-2 border-amber-300 text-amber-700 rounded-lg font-medium text-sm hover:bg-amber-50 transition-all"
                                @click="nextMonth"
                            >
                                Próximo mês
                                <ChevronRight class="h-4 w-4" />
                            </button>

                            <button
                                type="button"
                                class="inline-flex items-center justify-center gap-2 px-4 py-2 border-2 border-orange-300 text-orange-700 rounded-lg font-medium text-sm hover:bg-orange-50 transition-all"
                                @click="goToCurrentMonth"
                            >
                                Mês atual
                            </button>

                            <div class="flex items-end gap-3">
                                <div class="flex flex-col gap-1">
                                    <span class="text-xs font-semibold text-gray-500">Mês</span>
                                    <select
                                        v-model.number="selectedMonth"
                                        class="h-10 px-3 border-2 border-amber-200 rounded-lg text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent"
                                    >
                                        <option v-for="month in monthOptions" :key="month.value" :value="month.value">{{ month.label }}</option>
                                    </select>
                                </div>

                                <div class="flex flex-col gap-1">
                                    <span class="text-xs font-semibold text-gray-500">Ano</span>
                                    <select
                                        v-model.number="selectedYear"
                                        class="h-10 px-3 border-2 border-amber-200 rounded-lg text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent"
                                    >
                                        <option v-for="year in yearOptions" :key="year" :value="year">{{ year }}</option>
                                    </select>
                                </div>
                            </div>

                            <button
                                type="button"
                                class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-gradient-to-r from-amber-400 to-orange-500 text-white rounded-lg font-medium text-sm hover:shadow-lg transition-all disabled:opacity-50"
                                :disabled="isLoading || isRefreshing"
                                @click="loadDashboard(true)"
                            >
                                <RefreshCw class="h-4 w-4" :class="isRefreshing ? 'animate-spin' : ''" />
                                Atualizar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Today alert -->
                <div
                    v-if="dashboardData && !dashboardData.today.has_activity"
                    class="bg-amber-50 border border-amber-200 rounded-xl p-4 flex items-start gap-3"
                >
                    <AlertTriangle class="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-500" />
                    <div>
                        <p class="text-sm font-semibold text-amber-900">Você ainda não registrou entrada ou saída hoje.</p>
                        <p class="mt-1 text-xs text-amber-700">Quando houver lançamentos, o dia atual ficará colorido no calendário abaixo.</p>
                    </div>
                </div>

                <!-- Error -->
                <div v-if="loadError" class="bg-red-50 border border-red-200 rounded-xl p-4">
                    <p class="text-sm font-semibold text-red-800">Não foi possível carregar o dashboard.</p>
                    <p class="mt-1 text-xs text-red-700">{{ loadError }}</p>
                </div>

                <!-- Calendar + Aside -->
                <div class="grid gap-4 sm:gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">

                    <!-- Calendar -->
                    <div class="bg-white rounded-xl shadow-lg p-4 sm:p-6">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-4 sm:mb-5">
                            <div class="flex items-center gap-2">
                                <CalendarDays class="w-6 h-6 text-amber-500" />
                                <h2 class="text-xl font-bold text-gray-800">Movimentação diária</h2>
                            </div>
                        </div>

                        <div class="grid grid-cols-7 gap-1 sm:gap-1 lg:gap-1.5 xl:gap-2">
                            <div
                                v-for="weekday in ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb']"
                                :key="weekday"
                                class="rounded-lg bg-gray-100 px-1 py-1.5 text-center text-[8px] font-bold uppercase tracking-wide text-gray-500 sm:px-2 sm:py-2 sm:text-[10px]"
                            >
                                {{ weekday }}
                            </div>

                            <template v-for="(day, index) in calendarCells" :key="`${day?.date || 'empty'}-${index}`">
                                <div
                                    v-if="day"
                                    class="group relative min-h-[4rem] rounded-xl p-1 transition-shadow cursor-pointer sm:min-h-[4.5rem] sm:p-2 lg:min-h-[4.75rem] xl:min-h-[6rem]"
                                    :class="[cellStyle(day), day.is_today ? 'ring-2 ring-orange-400 ring-offset-1' : '']"
                                    role="button"
                                    tabindex="0"
                                    @click="toggleDayDetails(day)"
                                    @keyup.enter.prevent="toggleDayDetails(day)"
                                    @keyup.space.prevent="toggleDayDetails(day)"
                                >
                                    <div class="flex flex-col gap-1 overflow-hidden">
                                        <span class="text-[10px] font-semibold sm:text-sm lg:text-base">{{ day.day }}</span>
                                        <p v-if="day.status !== 'empty'" class="truncate text-[9px] font-semibold leading-tight sm:text-[10px] lg:text-sm">
                                            {{ dayValueLabel(day) }}
                                        </p>
                                    </div>

                                    <transition
                                        enter-active-class="transition-all duration-150 ease-out"
                                        leave-active-class="transition-all duration-120 ease-in"
                                        enter-from-class="opacity-0 translate-y-1 scale-95"
                                        enter-to-class="opacity-100 translate-y-0 scale-100"
                                        leave-from-class="opacity-100 translate-y-0 scale-100"
                                        leave-to-class="opacity-0 translate-y-1 scale-95"
                                    >
                                        <div
                                            v-if="isSelectedDay(day)"
                                            class="absolute inset-x-0.5 bottom-0.5 z-10 rounded-lg border border-gray-200 bg-white px-2 py-1.5 shadow-lg"
                                        >
                                            <p class="text-[7px] font-bold uppercase tracking-wide text-gray-500 sm:text-[8px]">
                                                {{ statusLabel(day) }}
                                            </p>
                                            <p v-if="day.status !== 'empty'" class="mt-0.5 text-[9px] font-semibold leading-tight text-gray-900 sm:text-[10px]">
                                                {{ tooltipValueLabel(day) }}
                                            </p>
                                        </div>
                                    </transition>
                                </div>

                                <div
                                    v-else
                                    class="min-h-[4rem] rounded-xl border border-dashed border-gray-200 bg-transparent sm:min-h-[4.5rem] lg:min-h-[4.75rem] xl:min-h-[6rem]"
                                />
                            </template>
                        </div>
                    </div>

                    <!-- Aside -->
                    <aside class="space-y-4 sm:space-y-6">

                        <!-- Company stats -->
                        <div class="bg-white rounded-xl shadow-lg p-4 sm:p-6">
                            <div class="flex items-center gap-2 mb-4">
                                <Building2 class="w-6 h-6 text-amber-500" />
                                <h2 class="text-xl font-bold text-gray-800">{{ companyName }}</h2>
                            </div>
                            <div class="space-y-3">
                                <div class="rounded-lg bg-gray-50 px-4 py-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Dias com movimento</p>
                                    <p class="mt-1 text-xl font-bold text-gray-800">{{ dashboardData?.totals.days_with_activity || 0 }}</p>
                                </div>
                                <div class="rounded-lg bg-gray-50 px-4 py-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Comissões do mês</p>
                                    <p class="mt-1 text-xl font-bold text-gray-800">{{ formatCurrency(dashboardData?.totals.commissions || 0) }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Legend -->
                        <div class="bg-white rounded-xl shadow-lg p-4 sm:p-6">
                            <h2 class="text-base font-bold text-gray-800 mb-4">Legenda</h2>
                            <div class="space-y-3 text-sm text-gray-600">
                                <div class="flex items-start gap-3">
                                    <span class="mt-1 h-3.5 w-3.5 flex-shrink-0 rounded-full bg-emerald-500"></span>
                                    <div>
                                        <p class="font-semibold text-gray-800">Verde</p>
                                        <p class="text-xs">Entrada apenas, ou saldo positivo.</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3">
                                    <span class="mt-1 h-3.5 w-3.5 flex-shrink-0 rounded-full bg-red-500"></span>
                                    <div>
                                        <p class="font-semibold text-gray-800">Vermelho</p>
                                        <p class="text-xs">Saída apenas, ou saldo negativo.</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3">
                                    <span class="mt-1 h-3.5 w-3.5 flex-shrink-0 rounded-full bg-amber-400"></span>
                                    <div>
                                        <p class="font-semibold text-gray-800">Amarelo</p>
                                        <p class="text-xs">Entrada e saída com resultado zerado.</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3">
                                    <span class="mt-1 h-3.5 w-3.5 flex-shrink-0 rounded-full border border-gray-300 bg-white"></span>
                                    <div>
                                        <p class="font-semibold text-gray-800">Em branco</p>
                                        <p class="text-xs">Dia sem qualquer lançamento.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Summary -->
                        <div class="bg-white rounded-xl shadow-lg p-4 sm:p-6">
                            <h2 class="text-base font-bold text-gray-800 mb-4">Resumo</h2>
                            <div class="space-y-3 text-sm">
                                <div class="flex items-center justify-between rounded-lg bg-emerald-50 border border-emerald-100 px-4 py-3">
                                    <span class="font-semibold text-emerald-900">Lucro acumulado</span>
                                    <span class="font-bold text-emerald-700">{{ formatSignedCurrency(dashboardData?.totals.profit || 0) }}</span>
                                </div>
                                <div class="flex items-center justify-between rounded-lg bg-red-50 border border-red-100 px-4 py-3">
                                    <span class="font-semibold text-red-900">Saídas acumuladas</span>
                                    <span class="font-bold text-red-700">{{ formatCurrency(dashboardData?.totals.exits || 0) }}</span>
                                </div>
                                <div class="flex items-center justify-between rounded-lg bg-gray-50 border border-gray-100 px-4 py-3">
                                    <span class="font-semibold text-gray-700">Entradas acumuladas</span>
                                    <span class="font-bold text-gray-800">{{ formatCurrency(dashboardData?.totals.entries || 0) }}</span>
                                </div>
                            </div>
                        </div>

                    </aside>
                </div>

                <!-- Loading toast -->
                <div v-if="isLoading || isRefreshing" class="pointer-events-none fixed inset-x-0 bottom-6 z-30 flex justify-center px-4">
                    <div class="pointer-events-auto inline-flex items-center gap-3 rounded-full border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-700 shadow-lg">
                        <RefreshCw class="h-4 w-4 animate-spin text-amber-500" />
                        Processando dados do calendário...
                    </div>
                </div>

                <Footer />
            </main>
        </div>
    </div>
</template>