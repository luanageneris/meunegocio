<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { AlertTriangle, Building2, CalendarDays, ChevronDown, ChevronLeft, ChevronRight, Download, Info, Lightbulb, MapPin, RefreshCw, RotateCcw } from 'lucide-vue-next';
import Sidebar from '@/components/Sidebar.vue';
import Footer from '@/components/Footer.vue';
import FixedPageHeaderTitle from '@/components/FixedPageHeaderTitle.vue';
import AppInlineNotice from '@/components/AppInlineNotice.vue';
import AppSelect from '@/components/AppSelect.vue';
import CuriosityCalculationInfo from '@/components/CuriosityCalculationInfo.vue';
import HolidayLocationDialog from '@/components/HolidayLocationDialog.vue';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { formatHolidayLocation } from '@/types/holidays';
import type { Holiday, HolidayLocation } from '@/types/holidays';
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

type CuriosityKind =
    | 'monthly-ranking'
    | 'monthly-average'
    | 'best-day'
    | 'weekday-average';

type CuriosityWeekday = {
    day: number;
    label: string;
    average_entries: number;
    average_exits: number;
};

type CuriosityData = {
    eligible: boolean;
    eligibility: {
        active_days: number;
        required_active_days: number;
        window_days: number;
    };
    available: boolean;
    message?: string | null;
    comparison_through_day?: number | null;
    current_month_progress?: {
        entries: number;
        exits: number;
        through_day: number;
        rank: number | null;
        months_compared: number;
        weekday_averages: CuriosityWeekday[];
    } | null;
    years_count?: number;
    years?: number[];
    average_entries?: number;
    average_exits?: number;
    latest_comparable?: {
        year: number;
        entries: number;
        rank: number;
    } | null;
    ranked_history?: Array<{
        year: number;
        entries: number;
        rank: number;
    }>;
    best_day?: {
        date: string;
        entries: number;
    };
    previous_year_month_best_day?: {
        date: string;
        entries: number;
    } | null;
    weekdays?: CuriosityWeekday[];
};

type CuriosityState = {
    loading: boolean;
    error: string;
    data: CuriosityData | null;
};

const auth = useAuth();
const { isPrivacyMode } = usePrivacy();
const registrationSuccessToastKey = 'registration_success_toast';
const dashboardFilterStorageKey = 'dashboard_filters';
const registrationSuccessMessage = ref('');

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
const isLoading = ref(true);
const isRefreshing = ref(false);
const isDownloadingReport = ref(false);
const reportError = ref('');
const loadError = ref('');
const dashboardData = ref<DashboardSummary | null>(null);
const isReady = ref(false);
const holidayLocation = ref<HolidayLocation | null>(null);
const holidayLocationLoaded = ref(false);
const holidayDialogOpen = ref(false);
const holidayItems = ref<Holiday[]>([]);
const holidayLoading = ref(false);
const holidayMonthLoaded = ref(false);
const holidayNotice = ref('');
const holidayPartialCoverage = ref(false);
let holidayRequest = 0;
let holidayLocationRequest = 0;
let curiosityRequest = 0;

const curiosityKinds: CuriosityKind[] = [
    'monthly-ranking',
    'monthly-average',
    'best-day',
    'weekday-average',
];
const curiosityResults = ref<Record<CuriosityKind, CuriosityState>>({
    'monthly-ranking': { loading: true, error: '', data: null },
    'monthly-average': { loading: true, error: '', data: null },
    'best-day': { loading: true, error: '', data: null },
    'weekday-average': { loading: true, error: '', data: null },
});
const curiosityEligibility = computed(() => {
    for (const kind of curiosityKinds) {
        const data = curiosityResults.value[kind].data;
        if (data && !data.eligible) return data.eligibility;
    }

    return null;
});


const holidayTypeLabel = (type: Holiday['type']) => ({
    public: '',
    optional: 'Ponto facultativo',
    unknown: '',
})[type];
const holidayDisplayName = (holiday: Holiday) => {
    const type = holidayTypeLabel(holiday.type);
    return type ? `${holiday.name} (${type})` : holiday.name;
};
const holidaysForDay = (date: string) => holidayItems.value.filter((holiday) => holiday.start_date <= date && holiday.end_date >= date);

async function loadHolidayMonth() {
    const requestId = ++holidayRequest;
    holidayItems.value = [];
    holidayNotice.value = '';
    holidayPartialCoverage.value = false;
    holidayMonthLoaded.value = false;
    if (!holidayLocation.value) {
        holidayLoading.value = false;
        return;
    }
    if (selectedYear.value < 2000 || selectedYear.value > 2100) {
        holidayLoading.value = false;
        holidayNotice.value = 'A consulta de feriados está disponível para anos de 2000 a 2100.';
        return;
    }
    holidayLoading.value = true;
    try {
        const response = await fetch('/api/holidays/month', {
            method: 'POST',
            credentials: 'include',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
            body: JSON.stringify({ month: selectedMonth.value, year: selectedYear.value }),
        });
        const payload = await response.json();
        if (requestId !== holidayRequest) return;
        if (!response.ok) throw new Error(payload.message || 'Não foi possível carregar os feriados.');
        holidayItems.value = payload.data.holidays;
        holidayMonthLoaded.value = true;
        if (payload.data.status === 'failed') holidayNotice.value = 'Não foi possível obter feriados para este período. Tente novamente mais tarde.';
        if (payload.data.status === 'stale') holidayNotice.value = 'Não foi possível atualizar os feriados. Exibindo os últimos dados salvos.';
        holidayPartialCoverage.value = payload.data.status === 'partial';
    } catch (error) {
        if (requestId === holidayRequest) holidayNotice.value = error instanceof Error ? error.message : 'Não foi possível carregar os feriados.';
    } finally {
        if (requestId === holidayRequest) holidayLoading.value = false;
    }
}

async function loadHolidayLocation() {
    const requestId = ++holidayLocationRequest;
    ++holidayRequest;
    holidayItems.value = [];
    holidayMonthLoaded.value = false;
    holidayLoading.value = false;
    holidayPartialCoverage.value = false;
    try {
        const response = await fetch('/api/holidays/location', { credentials: 'include', headers: { Accept: 'application/json' } });
        const payload = await response.json();
        if (requestId !== holidayLocationRequest) return;
        if (!response.ok) throw new Error(payload.message || 'Não foi possível carregar a preferência de feriados.');
        holidayLocation.value = payload.data;
        await loadHolidayMonth();
    } catch (error) {
        if (requestId === holidayLocationRequest) {
            holidayLocation.value = null;
            holidayNotice.value = error instanceof Error ? error.message : 'Não foi possível carregar a preferência de feriados.';
        }
    } finally {
        if (requestId === holidayLocationRequest) holidayLocationLoaded.value = true;
    }
}

function onHolidayLocationSaved(location: HolidayLocation) {
    ++holidayLocationRequest;
    holidayDialogOpen.value = false;
    holidayLocation.value = location;
    void loadHolidayMonth();
}

function onHolidayPreferenceChanged(event: StorageEvent) {
    if (event.key === 'holiday-preference-changed') void loadHolidayLocation();
}

function onHolidayPageFocus() {
    if (document.visibilityState === 'visible') void loadHolidayLocation();
}

const yearOptions = computed(() => {
    const years: number[] = [];

    for (let year = currentYear - 5; year <= currentYear + 1; year += 1) {
        years.push(year);
    }

    return years;
});
const createYearOption = (query: string) => {
    if (!/^\d{4}$/.test(query)) return null;
    const year = Number(query);
    return year >= 2000 && year <= 2100 ? { value: year, label: query } : null;
};

const companyName = computed(() => dashboardData.value?.company?.name || auth.getCompanyName.value || 'Empresa');
const monthLabel = computed(() => monthOptions.find((item) => item.value === selectedMonth.value)?.label || 'Mês');
const monthTitle = computed(() => `${monthLabel.value} de ${selectedYear.value}`);
const previousYearMonthBestDay = computed(() => curiosityResults.value['best-day'].data?.previous_year_month_best_day ?? null);

const showTodayActivityWarning = computed(() => {
    const today = dashboardData.value?.today;
    if (!today || today.has_activity) return false;

    const [year, month] = today.date.split('-').map(Number);
    return selectedYear.value === year && selectedMonth.value === month;
});

const curiosityExplanations = computed<Record<CuriosityKind, string[]>>(() => {
    const shared = (kind: CuriosityKind): string[] => {
        const state = curiosityResults.value[kind];
        const eligibility = state.data?.eligibility;
        return [
            `Período selecionado: ${monthTitle.value}. Usamos somente os registos financeiros da sua empresa que não foram excluídos. Entradas e saídas são calculadas separadamente, em todas as categorias; não subtraímos os gastos das entradas. Os valores de entradas não representam lucro.`,
            `Cada lançamento vale (valor × quantidade) − desconto, com mínimo de zero. Os resultados monetários são arredondados para duas casas decimais. As saídas incluem comissões lançadas como saída, sem somar novamente os valores da lista de comissões.`,
            `Para liberar esta análise, é preciso ter registos em pelo menos ${eligibility?.required_active_days ?? 30} dias diferentes nos últimos ${eligibility?.window_days ?? 90} dias, incluindo hoje, independentemente do mês selecionado. Vários lançamentos no mesmo dia contam como um único dia; um dia com apenas saída também conta.${eligibility ? ` A sua empresa tem ${eligibility.active_days} dias com registos nessa janela.` : ''}`,
            ...(state.loading ? ['Os dados desta seção ainda estão carregando; as regras abaixo descrevem o cálculo que será aplicado.'] : []),
            ...(state.error ? ['Não foi possível carregar os dados desta seção. As regras abaixo continuam disponíveis, mas não há um resultado confirmado para este carregamento.'] : []),
        ];
    };
    const historyRules = (kind: CuriosityKind): string[] => {
        const data = curiosityResults.value[kind].data;
        const years = data?.years ?? data?.ranked_history?.map((item) => item.year);
        const today = new Date();
        const throughDay = data
            ? data.comparison_through_day
            : selectedMonth.value === today.getMonth() + 1 && selectedYear.value === today.getFullYear()
              ? today.getDate()
              : null;
        return [
            `Comparamos somente ${monthLabel.value.toLowerCase()} em anos anteriores a ${selectedYear.value}, sem incluir o ano selecionado nem anos posteriores. Um ano entra na comparação quando há pelo menos um registo de entrada ou saída nesse mês e intervalo. Se não houver lançamentos de um dos tipos, o total desse tipo é zero. Anos sem nenhum registo ficam fora.`,
            throughDay
                ? `Como o mês selecionado é o mês corrente, em cada ano histórico consideramos apenas os dias 1 a ${throughDay}, limitados ao último dia existente naquele mês. Os dias restantes não entram na comparação.`
                : 'Como o mês selecionado não é o mês corrente, consideramos o mês completo de cada ano histórico disponível.',
            ...(years?.length ? [`Anos efetivamente considerados: ${years.join(', ')}.`] : []),
        ];
    };
    const fallbackRules = (kind: CuriosityKind): string[] => {
        const progress = curiosityResults.value[kind].data?.current_month_progress;
        return progress ? [
            `Não há histórico comparável. Como o mês corrente ainda não terminou, mostramos também os dados do dia 1 ao dia ${progress.through_day} deste mês, sem substituir a mensagem de falta de histórico.`,
        ] : [
            'Sem anos históricos comparáveis, não calculamos um resultado histórico. Dados adicionais do mês atual só aparecem quando o mês selecionado é o corrente e ainda não chegou ao último dia.',
        ];
    };
    return {
        'monthly-ranking': [
            ...shared('monthly-ranking'),
            ...historyRules('monthly-ranking'),
            'Somamos as entradas de cada ano para esse mês. A posição é 1 + a quantidade de anos com total maior: empates recebem a mesma posição. O destaque é o ano mais recente com histórico, que pode não ser o ano imediatamente anterior. As barras mostram o total de cada ano em proporção ao maior total da comparação.',
            ...fallbackRules('monthly-ranking'),
            'No ranking adicional do mês corrente, comparamos janeiro até o mês selecionado no mesmo ano, sempre do dia 1 até o número do dia de hoje. Meses sem entradas contam com zero. A posição usa a mesma regra de empate; se o mês atual não tiver entradas positivas, não mostramos posição.',
        ],
        'monthly-average': [
            ...shared('monthly-average'),
            ...historyRules('monthly-average'),
            'Somamos o total de entradas desse mês em cada ano histórico considerado e dividimos pela quantidade desses anos. Repetimos o mesmo cálculo separadamente para as saídas, usando os mesmos anos, inclusive os com zero saídas. Cada ano tem o mesmo peso. Não dividimos pelo número de lançamentos nem pelos dias do mês: são médias de totais mensais ou dos intervalos equivalentes já decorridos.',
            'É uma referência histórica, não uma previsão garantida nem uma projeção para os dias restantes do mês.',
            ...fallbackRules('monthly-average'),
            'Quando exibimos os valores registados neste mês, mostramos as somas de entradas e saídas do mês corrente até hoje separadamente. Não são médias nem estimativas.',
        ],
        'best-day': [
            ...shared('best-day'),
            `Consideramos as entradas de 1 de janeiro de ${selectedYear.value} até o último dia do mês selecionado, sem ultrapassar a data de hoje. Se o ano selecionado ainda não começou, não há período para analisar.`,
            'Agrupamos os lançamentos por data, somamos as entradas de cada dia e escolhemos o maior total diário positivo. Em caso de empate, exibimos a data mais antiga.',
            '“Vendas” aqui significa o total de entradas registadas, de todas as categorias, não o lucro do dia. Dias de meses posteriores ao selecionado e datas futuras não entram neste cálculo. Não é necessário ter dados de anos anteriores.',
            'Se não houver um total diário de entradas maior que zero no intervalo, informamos que ainda não há entradas para apresentar um melhor dia.',
            `Também procuramos o melhor dia de ${monthLabel.value.toLowerCase()} de ${selectedYear.value - 1}, o ano imediatamente anterior ao selecionado. Usamos o mês inteiro, mesmo que o mês corrente ainda esteja em andamento, mas nunca incluímos datas futuras. Aplicamos a mesma soma de entradas e o mesmo desempate pela data mais antiga.`,
            'O destaque do mês do ano anterior aparece somente se houver um total diário positivo nesse mês, mesmo que não haja entradas no ano selecionado. Não substituímos o ano anterior por outro ano mais antigo quando faltam registos.',
        ],
        'weekday-average': [
            ...shared('weekday-average'),
            ...historyRules('weekday-average'),
            'Para cada dia da semana, somamos as entradas das datas correspondentes no histórico e dividimos pelo número de vezes que esse dia da semana ocorre nos intervalos considerados. Fazemos o mesmo para as saídas, com o mesmo divisor. Por exemplo: o total das saídas nas segundas-feiras dividido pela quantidade de segundas-feiras nesses intervalos.',
            'Dias sem entradas ou sem saídas também entram no divisor, com valor zero para o respectivo tipo. Neste gráfico de Curiosidades são considerados todos os dias da semana, de domingo a sábado; a configuração de dias de funcionamento é aplicada ao gráfico de Oportunidades, não a este.',
            'Cada dia tem uma barra com dois segmentos: entradas em verde e saídas em vermelho. Todos os dias usam a mesma escala, baseada na maior soma de média de entradas e média de saídas. Os valores de cada segmento aparecem abaixo. O saldo médio ao lado é a média de entradas menos a média de saídas; não é a soma dos segmentos. Dias sem ocorrências recebem média zero.',
            ...fallbackRules('weekday-average'),
            'No gráfico adicional do mês corrente, usamos somente o dia 1 até hoje deste mês, com a mesma regra: total de entradas ou saídas por dia da semana dividido por todas as ocorrências desse dia no intervalo, inclusive as sem lançamentos.',
        ],
    };
});

const downloadReport = async (type: 'monthly' | 'yearly') => {
    if (isDownloadingReport.value) return;

    const month = selectedMonth.value;
    const year = selectedYear.value;
    isDownloadingReport.value = true;
    reportError.value = '';

    try {
        const periodQuery = type === 'monthly' ? `month=${month}&year=${year}` : `year=${year}`;
        const response = await fetch(`/api/dashboard/${type}-report?${periodQuery}`, {
            headers: {
                Accept: 'application/pdf, application/json',
                Authorization: `Bearer ${auth.token.value}`,
            },
        });

        if (!response.ok) {
            if (!response.headers.get('Content-Type')?.includes('application/json')) {
                throw new Error('Não foi possível gerar o relatório. Tente novamente.');
            }
            const payload = await response.json();
            const validationError = Object.values(payload.errors ?? {}).flat()[0];
            throw new Error(response.status === 422
                ? (typeof validationError === 'string' ? validationError : payload.message || 'Revise o período selecionado.')
                : 'Não foi possível gerar o relatório. Atualize a página e tente novamente.');
        }

        if (!response.headers.get('Content-Type')?.includes('application/pdf')) {
            throw new Error('O servidor não retornou um PDF válido. Atualize a página e tente novamente.');
        }

        const url = window.URL.createObjectURL(await response.blob());
        const link = document.createElement('a');
        link.href = url;
        link.download = type === 'monthly'
            ? `relatorio-mensal-${year}-${String(month).padStart(2, '0')}.pdf`
            : `relatorio-anual-${year}.pdf`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.setTimeout(() => window.URL.revokeObjectURL(url), 1000);
    } catch (error) {
        reportError.value = error instanceof TypeError
            ? 'Não foi possível conectar ao servidor. Verifique sua conexão e tente novamente.'
            : error instanceof Error ? error.message : 'Não foi possível gerar o relatório. Tente novamente.';
    } finally {
        isDownloadingReport.value = false;
    }
};
const averageDailyProfit = computed(() => {
    const totals = dashboardData.value?.totals;
    const daysWithActivity = totals?.days_with_activity ?? 0;

    return totals && daysWithActivity > 0 ? totals.profit / daysWithActivity : 0;
});

const getCsrfToken = (): string => {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
};

const showRegistrationSuccessMessageIfNeeded = () => {
    if (typeof window === 'undefined') {
        return;
    }

    const message = window.sessionStorage.getItem(registrationSuccessToastKey);

    if (!message) {
        return;
    }

    window.sessionStorage.removeItem(registrationSuccessToastKey);
    registrationSuccessMessage.value = message;
};

const dismissRegistrationSuccessMessage = () => {
    registrationSuccessMessage.value = '';
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
    const now = new Date();
    const currentMonth = now.getMonth() + 1;
    const currentYear = now.getFullYear();

    if (selectedMonth.value === currentMonth) params.delete('month');
    else params.set('month', String(selectedMonth.value));

    if (selectedYear.value === currentYear) params.delete('year');
    else params.set('year', String(selectedYear.value));

    const query = params.toString();
    window.history.replaceState({}, '', `${window.location.pathname}${query ? `?${query}` : ''}`);

    if (selectedMonth.value === currentMonth && selectedYear.value === currentYear) {
        window.sessionStorage.removeItem(dashboardFilterStorageKey);
    } else {
        window.sessionStorage.setItem(dashboardFilterStorageKey, JSON.stringify({
            month: selectedMonth.value,
            year: selectedYear.value,
        }));
    }
};

const loadDashboard = async (withSpinner = false) => {
    if (typeof window !== 'undefined') {
        const now = new Date();
        if (selectedMonth.value === now.getMonth() + 1 && selectedYear.value === now.getFullYear()) {
            window.sessionStorage.removeItem(dashboardFilterStorageKey);
        } else {
            window.sessionStorage.setItem(dashboardFilterStorageKey, JSON.stringify({
                month: selectedMonth.value,
                year: selectedYear.value,
            }));
        }
    }

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
        updateQueryString();
    } catch (error) {
        loadError.value = error instanceof Error ? error.message : 'Não foi possível carregar o dashboard.';
    } finally {
        isLoading.value = false;
        isRefreshing.value = false;
    }
};

const loadCuriosities = async () => {
    const requestId = ++curiosityRequest;
    const params = new URLSearchParams({
        month: String(selectedMonth.value),
        year: String(selectedYear.value),
    });

    for (const kind of curiosityKinds) {
        curiosityResults.value[kind] = { loading: true, error: '', data: null };
    }

    await Promise.all(
        curiosityKinds.map(async (kind) => {
            try {
                const response = await fetch(
                    `/api/dashboard/curiosities/${kind}?${params.toString()}`,
                    {
                        credentials: 'include',
                        headers: { Accept: 'application/json' },
                    },
                );
                const payload = await response.json();

                if (!response.ok) {
                    throw new Error(
                        payload?.message ||
                            'Não foi possível carregar esta curiosidade.',
                    );
                }
                if (requestId !== curiosityRequest) return;
                if (!payload.data) {
                    throw new Error(
                        'A resposta desta curiosidade está incompleta.',
                    );
                }

                curiosityResults.value[kind] = {
                    loading: false,
                    error: '',
                    data: payload.data,
                };
            } catch (error) {
                if (requestId === curiosityRequest) {
                    curiosityResults.value[kind] = {
                        loading: false,
                        error:
                            error instanceof Error
                                ? error.message
                                : 'Não foi possível carregar esta curiosidade.',
                        data: null,
                    };
                }
            } finally {
                if (
                    requestId === curiosityRequest &&
                    curiosityResults.value[kind].loading
                ) {
                    curiosityResults.value[kind].loading = false;
                }
            }
        }),
    );
};

const refreshDashboard = async () => {
    await Promise.all([loadDashboard(true), loadCuriosities()]);
};

const curiosityWeekdays = computed(() => {
    const data = curiosityResults.value['weekday-average'].data;
    return data?.weekdays ?? data?.current_month_progress?.weekday_averages ?? [];
});

const curiosityWeekdayMax = computed(() =>
    Math.max(0, ...curiosityWeekdays.value.map((item) => item.average_entries + item.average_exits)),
);

const curiosityWeekdayWidth = (value: number): string => {
    if (value <= 0 || curiosityWeekdayMax.value <= 0) return '0%';
    return `${(value / curiosityWeekdayMax.value) * 100}%`;
};

const curiosityHistoryWidth = (value: number): string => {
    const history =
        curiosityResults.value['monthly-ranking'].data?.ranked_history ?? [];
    const highest = Math.max(0, ...history.map((item) => item.entries));
    if (highest <= 0) return '0%';
    return `${Math.max(4, (value / highest) * 100)}%`;
};

const curiosityRankLabel = (rank: number, total: number): string => {
    if (total === 1) return 'Único ano com registo';
    if (rank === 1) return `Melhor resultado entre ${total} anos`;
    if (rank === total) return `Pior resultado entre ${total} anos`;
    return `${rank}.º melhor entre ${total} anos`;
};

const curiosityDateLabel = (date: string): string => {
    const [year, month, day] = date.split('-');
    return `${day}/${month}/${year}`;
};

const previousMonth = () => {
    if (selectedMonth.value === 1) {
        selectedMonth.value = 12;
        selectedYear.value -= 1;
        return;
    }

    selectedMonth.value -= 1;
};

const nextMonth = () => {
    if (selectedMonth.value === 12) {
        selectedMonth.value = 1;
        selectedYear.value += 1;
        return;
    }

    selectedMonth.value += 1;
};

const clearDashboardFilter = async () => {
    const now = new Date();
    const currentMonth = now.getMonth() + 1;
    const currentYear = now.getFullYear();
    const unchanged = selectedMonth.value === currentMonth && selectedYear.value === currentYear;

    selectedMonth.value = currentMonth;
    selectedYear.value = currentYear;
    window.sessionStorage.removeItem(dashboardFilterStorageKey);
    updateQueryString();

    if (unchanged) {
        await refreshDashboard();
    }
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

const openRecordsForDay = (day: DashboardDay) => {
    const [year, month, dayOfMonth] = day.date.split('-');
    const search = `${dayOfMonth}/${month}/${year}`;
    const params = new URLSearchParams({
        tab: 'todos',
        page: '1',
        search,
        financial_type: '',
        removed: '0',
        charts: '0',
    });
    window.location.assign(`/registros?${params.toString()}`);
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
        return 'border border-dashed border-slate-200 bg-white/70 text-slate-300 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-500';
    }

    if (day.status === 'entry' || day.status === 'positive') {
        return 'border border-emerald-200 bg-emerald-50 text-emerald-950 shadow-sm dark:border-emerald-800/70 dark:bg-emerald-950/35 dark:text-emerald-200';
    }

    if (day.status === 'exit' || day.status === 'negative') {
        return 'border border-rose-200 bg-rose-50 text-rose-950 shadow-sm dark:border-rose-800/70 dark:bg-rose-950/35 dark:text-rose-200';
    }

    return 'border border-amber-200 bg-amber-50 text-amber-950 shadow-sm dark:border-amber-800/70 dark:bg-amber-950/35 dark:text-amber-100';
};

watch([selectedMonth, selectedYear], async () => {
    if (!isReady.value) {
        return;
    }

    await Promise.all([loadDashboard(true), loadHolidayMonth(), loadCuriosities()]);
});

onMounted(async () => {
    showRegistrationSuccessMessageIfNeeded();

    const savedFilters = window.sessionStorage.getItem(dashboardFilterStorageKey);
    if (savedFilters) {
        try {
            const parsed: unknown = JSON.parse(savedFilters);
            if (parsed && typeof parsed === 'object') {
                const filters = parsed as Record<string, unknown>;
                if (typeof filters.month === 'number' && Number.isInteger(filters.month) && filters.month >= 1 && filters.month <= 12) {
                    selectedMonth.value = filters.month;
                }
                if (typeof filters.year === 'number' && Number.isInteger(filters.year) && filters.year >= 2000 && filters.year <= 2100) {
                    selectedYear.value = filters.year;
                }
            }
        } catch (error) {
            console.error('Não foi possível restaurar os filtros do Dashboard:', error);
            window.sessionStorage.removeItem(dashboardFilterStorageKey);
        }
    }

    const params = new URLSearchParams(window.location.search);
    const queryMonth = Number(params.get('month'));
    const queryYear = Number(params.get('year'));

    if (Number.isInteger(queryMonth) && queryMonth >= 1 && queryMonth <= 12) {
        selectedMonth.value = queryMonth;
    }

    if (Number.isInteger(queryYear) && queryYear >= 2000) {
        selectedYear.value = queryYear;
    }

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
    void loadCuriosities();
    await loadHolidayLocation();
    window.addEventListener('storage', onHolidayPreferenceChanged);
    document.addEventListener('visibilitychange', onHolidayPageFocus);
});

onUnmounted(() => {
    curiosityRequest++;
    window.removeEventListener('storage', onHolidayPreferenceChanged);
    document.removeEventListener('visibilitychange', onHolidayPageFocus);
});
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-amber-50 to-orange-100 dark:from-slate-950 dark:via-slate-900 dark:to-slate-950 flex overflow-x-hidden">
        <Head title="Dashboard - MEU NEGÓCIO" />

        <Sidebar activeRoute="dashboard" />

        <div class="flex-1 min-w-0 lg:ml-64 transition-all duration-300 overflow-x-hidden flex flex-col">
            <FixedPageHeaderTitle
                title="Dashboard"
                :subtitle="`Bem-vindo, ${auth.getUserName.value} · ${companyName}`"
            />

            <main class="w-full min-w-0 max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-6 sm:py-8 flex-1 flex flex-col gap-4 sm:gap-6 overflow-x-hidden">

                <AppInlineNotice
                    v-if="registrationSuccessMessage"
                    variant="success"
                    title="Conta criada"
                    :message="registrationSuccessMessage"
                    @dismiss="dismissRegistrationSuccessMessage"
                />

                <!-- Today alert -->
                <div
                    v-if="showTodayActivityWarning"
                    class="bg-amber-50 dark:bg-amber-950/35 border border-amber-200 dark:border-amber-800/70 rounded-xl p-4 flex items-start gap-3"
                >
                    <AlertTriangle class="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-500 animate-pulse" />
                    <div>
                        <p class="text-sm font-semibold text-amber-900 dark:text-amber-200">Você ainda não registrou entrada ou saída hoje.</p>
                        <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">Quando houver lançamentos, o dia atual ficará colorido no calendário abaixo.</p>
                    </div>
                </div>

                <!-- Error -->
                <div v-if="loadError" class="bg-red-50 dark:bg-red-950/35 border border-red-200 dark:border-red-800/70 rounded-xl p-4">
                    <p class="text-sm font-semibold text-red-800 dark:text-red-200">Não foi possível carregar o dashboard.</p>
                    <p class="mt-1 text-xs text-red-700 dark:text-red-300">{{ loadError }}</p>
                </div>

                <div class="dashboard-reveal flex items-center gap-2">
                    <CalendarDays class="h-6 w-6 text-amber-500" aria-hidden="true" />
                    <h2 class="text-xl font-bold text-gray-800 dark:text-slate-100">Movimentação diária</h2>
                </div>

                <!-- Calendar + Aside -->
                <div class="grid gap-4 sm:gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">

                    <!-- Calendar -->
                    <div class="dashboard-reveal bg-white dark:bg-slate-900/90 border border-transparent dark:border-slate-800 rounded-xl shadow-lg dark:shadow-none p-4 sm:p-6">
                        <div class="mb-4 grid grid-cols-2 gap-3 sm:mb-5 sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] sm:items-end">
                            <button
                                type="button"
                                class="col-start-1 row-start-1 inline-flex cursor-pointer items-center justify-center gap-1.5 justify-self-start rounded-lg border-2 border-amber-300 px-3 py-2 text-sm font-medium text-amber-700 transition-all hover:bg-amber-50 dark:border-amber-700 dark:text-amber-200 dark:hover:bg-amber-950/40 sm:gap-2 sm:px-4"
                                aria-label="Mês anterior"
                                @click="previousMonth"
                            >
                                <ChevronLeft class="h-4 w-4" />
                                <span>Anterior</span>
                            </button>

                            <button
                                type="button"
                                class="col-start-2 row-start-1 inline-flex cursor-pointer items-center justify-center gap-1.5 justify-self-end rounded-lg border-2 border-amber-300 px-3 py-2 text-sm font-medium text-amber-700 transition-all hover:bg-amber-50 dark:border-amber-700 dark:text-amber-200 dark:hover:bg-amber-950/40 sm:col-start-3 sm:gap-2 sm:px-4"
                                aria-label="Próximo mês"
                                @click="nextMonth"
                            >
                                <span>Próximo</span>
                                <ChevronRight class="h-4 w-4" />
                            </button>

                            <div class="col-span-2 row-start-2 grid min-w-0 grid-cols-[minmax(0,1fr)_minmax(5rem,0.6fr)] gap-3 sm:col-span-1 sm:col-start-2 sm:row-start-1 sm:grid-cols-[10rem_6rem]">
                                <label class="flex min-w-0 flex-col gap-1">
                                    <span class="text-xs font-semibold text-gray-500 dark:text-slate-400">Mês</span>
                                    <AppSelect
                                        v-model.number="selectedMonth"
                                        label="Mês"
                                        searchable
                                        class="w-full text-sm"
                                        :options="monthOptions"
                                    />
                                </label>

                                <label class="flex min-w-0 flex-col gap-1">
                                    <span class="text-xs font-semibold text-gray-500 dark:text-slate-400">Ano</span>
                                    <AppSelect
                                        v-model.number="selectedYear"
                                        label="Ano"
                                        searchable
                                        :create-input-option="createYearOption"
                                        class="w-full text-sm"
                                        :options="yearOptions.map((year) => ({ value: year, label: String(year) }))"
                                    />
                                </label>
                            </div>
                        </div>

                        <div class="grid grid-cols-7 gap-1 sm:gap-1 lg:gap-1.5 xl:gap-2">
                            <div
                                v-for="weekday in ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb']"
                                :key="weekday"
                                class="rounded-lg bg-gray-100 dark:bg-slate-800 px-1 py-1.5 text-center text-[8px] font-bold uppercase tracking-wide text-gray-500 dark:text-slate-300 sm:px-2 sm:py-2 sm:text-[10px]"
                            >
                                {{ weekday }}
                            </div>

                            <template v-for="(day, index) in calendarCells" :key="`${day?.date || 'empty'}-${index}`">
                                <div
                                    v-if="day"
                                    class="group relative min-h-[4rem] rounded-xl p-1 transition-shadow cursor-pointer sm:min-h-[4.5rem] sm:p-2 lg:min-h-[4.75rem] xl:min-h-[6rem]"
                                    :class="[cellStyle(day), day.is_today ? 'ring-2 ring-orange-400 ring-offset-1 dark:ring-offset-slate-900' : '']"
                                    role="button"
                                    tabindex="0"
                                    :title="holidaysForDay(day.date).map(holidayDisplayName).join('\n') || 'Clique para ver detalhes'"
                                    :aria-label="`${day.day}: ${holidaysForDay(day.date).map(holidayDisplayName).join('; ')}. Clique para ver detalhes`"
                                    @click="openRecordsForDay(day)"
                                    @keyup.enter.prevent="openRecordsForDay(day)"
                                    @keyup.space.prevent="openRecordsForDay(day)"
                                >
                                    <div class="flex flex-col gap-1 overflow-hidden">
                                        <span class="text-[10px] font-semibold sm:text-sm lg:text-base" :class="holidaysForDay(day.date).length ? 'text-red-600 dark:text-red-400' : ''">{{ day.day }}</span>
                                        <p v-if="day.status !== 'empty'" class="truncate text-[9px] font-semibold leading-tight sm:text-[10px] lg:text-sm">
                                            {{ dayValueLabel(day) }}
                                        </p>
                                    </div>

                                </div>

                                <div
                                    v-else
                                    class="min-h-[4rem] rounded-xl border border-dashed border-gray-200 dark:border-slate-700 bg-transparent sm:min-h-[4.5rem] lg:min-h-[4.75rem] xl:min-h-[6rem]"
                                />
                            </template>
                        </div>
                        <div class="mt-4 flex flex-col gap-4 border-t border-gray-100 pt-4 dark:border-slate-800 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0 flex-1 text-sm">
                            <button v-if="holidayLocationLoaded && !holidayLocation && !holidayNotice" type="button" class="inline-flex cursor-pointer items-center gap-2 font-semibold text-orange-700 transition-colors hover:text-orange-600 dark:text-orange-300 dark:hover:text-orange-200" title="Clique aqui e selecione sua localização para que possamos encontrar os feriados de onde você está." @click="holidayDialogOpen = true">
                                <MapPin class="h-5 w-5 motion-safe:animate-pulse" aria-hidden="true" />
                                Sabia que podemos mostrar os feriados?
                            </button>
                            <TooltipProvider v-if="holidayLocation" :delay-duration="0">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <button type="button" class="inline-flex cursor-pointer items-center gap-2 text-primary transition-colors hover:text-primary/80 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary dark:text-primary" :aria-label="`Alterar localização: ${formatHolidayLocation(holidayLocation)}`" @click="holidayDialogOpen = true">
                                            <MapPin class="h-5 w-5 shrink-0" aria-hidden="true" />
                                            <span>{{ formatHolidayLocation(holidayLocation) }}</span>
                                        </button>
                                    </TooltipTrigger>
                                    <TooltipContent class="max-w-64 space-y-2">
                                        <p v-if="holidayPartialCoverage">Cobertura parcial: apenas datas nacionais disponíveis, com classificação não informada.</p>
                                        <p>Feriados e pontos facultativos disponíveis para {{ formatHolidayLocation(holidayLocation) }}. A cobertura pode ser incompleta.</p>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                            <p v-if="holidayLoading" role="status">Carregando feriados...</p>
                            <p v-if="holidayNotice" role="status" class="text-amber-700 dark:text-amber-300">{{ holidayNotice }}</p>
                            <p v-if="holidayLocation && holidayMonthLoaded && !holidayLoading && !holidayNotice && !holidayItems.length" class="text-gray-600 dark:text-slate-400">Não há datas disponíveis para este mês nas fontes consultadas.</p>
                            <ul v-if="holidayItems.length" class="mt-2 space-y-1">
                                <li v-for="holiday in holidayItems" :key="`${holiday.start_date}-${holiday.name}`" class="text-gray-700 dark:text-slate-300">
                                    {{ holiday.start_date.split('-').reverse().join('/') }}<template v-if="holiday.end_date !== holiday.start_date"> a {{ holiday.end_date.split('-').reverse().join('/') }}</template> — {{ holidayDisplayName(holiday) }}
                                </li>
                            </ul>
                            <p v-if="holidayLocation" class="mt-3 text-xs text-gray-500 dark:text-slate-400">Fontes: <a class="underline" href="https://www.openholidaysapi.org/" target="_blank" rel="noopener noreferrer">OpenHolidays</a> (dados <a class="underline" href="https://opendatacommons.org/licenses/odbl/" target="_blank" rel="noopener noreferrer">ODbL</a>) e <a class="underline" href="https://brasilapi.com.br/" target="_blank" rel="noopener noreferrer">BrasilAPI</a>.</p>
                            </div>
                            <div class="flex w-full shrink-0 flex-row gap-2 sm:w-auto">
                                <button
                                    type="button"
                                    class="inline-flex flex-1 cursor-pointer items-center justify-center gap-2 rounded-lg border border-orange-300 px-2 py-2 text-xs font-medium text-orange-700 transition-all hover:bg-orange-50 dark:border-orange-700 dark:text-orange-300 dark:hover:bg-orange-950/40 sm:flex-none sm:px-4 sm:text-sm"
                                    @click="clearDashboardFilter"
                                >
                                    <RotateCcw class="h-4 w-4" />
                                    Limpar filtro
                                </button>

                                <button
                                    type="button"
                                    class="inline-flex flex-1 cursor-pointer items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-amber-400 to-orange-500 px-2 py-2 text-xs font-medium text-white transition-all hover:shadow-lg disabled:cursor-not-allowed disabled:opacity-50 sm:flex-none sm:px-4 sm:text-sm"
                                    :disabled="isLoading || isRefreshing"
                                    @click="loadDashboard(true)"
                                >
                                    <RefreshCw class="h-4 w-4" :class="isRefreshing ? 'animate-spin' : ''" />
                                    Atualizar
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Aside -->
                    <aside class="dashboard-stagger space-y-4 sm:space-y-6">

                        <!-- Company stats -->
                        <div class="bg-white dark:bg-slate-900/90 border border-transparent dark:border-slate-800 rounded-xl shadow-lg dark:shadow-none p-4 sm:p-6">
                            <div class="flex items-center gap-2 mb-4">
                                <Building2 class="w-6 h-6 text-amber-500" />
                                <h2 class="text-xl font-bold text-gray-800 dark:text-slate-100">{{ companyName }}</h2>
                            </div>
                            <div class="space-y-3">
                                <div class="rounded-lg bg-gray-50 dark:bg-slate-800 px-4 py-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-slate-400">Dias com movimento</p>
                                    <p class="mt-1 text-xl font-bold text-gray-800 dark:text-slate-100">{{ dashboardData?.totals.days_with_activity || 0 }}</p>
                                </div>
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <button
                                            type="button"
                                            class="inline-flex w-fit max-w-full cursor-pointer items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground transition enabled:hover:opacity-90 enabled:active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50 xl:w-full"
                                            :disabled="isDownloadingReport || isLoading"
                                            :aria-busy="isDownloadingReport"
                                        >
                                            <RefreshCw v-if="isDownloadingReport" class="h-4 w-4 animate-spin" aria-hidden="true" />
                                            <Download v-else class="h-4 w-4" aria-hidden="true" />
                                            {{ isDownloadingReport ? 'Gerando relatório...' : 'Gerar relatório PDF' }}
                                            <ChevronDown class="ml-auto h-4 w-4" aria-hidden="true" />
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" class="w-(--reka-dropdown-menu-trigger-width) min-w-56 border-gray-200 bg-white text-gray-800 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                                        <DropdownMenuItem class="cursor-pointer focus:bg-orange-50 dark:focus:bg-slate-800" :disabled="isDownloadingReport || isLoading" @select="downloadReport('monthly')">
                                            <span>Mensal</span>
                                            <span class="ml-auto text-xs text-gray-500 dark:text-slate-400">{{ monthTitle }}</span>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem class="cursor-pointer focus:bg-orange-50 dark:focus:bg-slate-800" :disabled="isDownloadingReport || isLoading" @select="downloadReport('yearly')">
                                            <span>Anual</span>
                                            <span class="ml-auto text-xs text-gray-500 dark:text-slate-400">{{ selectedYear }}</span>
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                                <p class="text-xs text-gray-500 dark:text-slate-400">
                                    Mensal de {{ monthTitle }} ou anual de {{ selectedYear }}, iguais aos enviados por e-mail. Use o PIN do PDF configurado em
                                    <Link href="/minha-conta" class="font-semibold text-primary underline underline-offset-2 hover:opacity-80">Minha Conta</Link>.
                                </p>
                                <p v-if="reportError" role="alert" class="text-sm text-red-600 dark:text-red-400">{{ reportError }}</p>
                            </div>
                        </div>

                        <!-- Summary -->
                        <div class="bg-white dark:bg-slate-900/90 border border-transparent dark:border-slate-800 rounded-xl shadow-lg dark:shadow-none p-4 sm:p-6">
                            <h2 class="text-base font-bold text-gray-800 dark:text-slate-100 mb-4">Resumo</h2>
                            <div class="space-y-3 text-sm">
                                <div class="flex items-center justify-between rounded-lg bg-emerald-50 dark:bg-emerald-950/35 border border-emerald-100 dark:border-emerald-800/70 px-4 py-3">
                                    <span class="font-semibold text-emerald-900 dark:text-emerald-200">Lucro acumulado</span>
                                    <span class="font-bold text-emerald-700 dark:text-emerald-300">{{ formatSignedCurrency(dashboardData?.totals.profit || 0) }}</span>
                                </div>
                                <div class="flex items-center justify-between rounded-lg bg-orange-50 dark:bg-orange-950/35 border border-orange-100 dark:border-orange-800/70 px-4 py-3">
                                    <span class="font-semibold text-orange-900 dark:text-orange-200" title="Lucro acumulado dividido pelos dias com movimento no período filtrado">Lucro médio diário</span>
                                    <span class="font-bold text-orange-700 dark:text-orange-300">{{ formatSignedCurrency(averageDailyProfit) }}</span>
                                </div>
                                <div class="flex items-center justify-between rounded-lg bg-red-50 dark:bg-red-950/35 border border-red-100 dark:border-red-800/70 px-4 py-3">
                                    <span class="font-semibold text-red-900 dark:text-red-200">Saídas acumuladas</span>
                                    <span class="font-bold text-red-700 dark:text-red-300">{{ formatCurrency(dashboardData?.totals.exits || 0) }}</span>
                                </div>
                                <div class="rounded-lg bg-amber-50 dark:bg-amber-950/35 border border-amber-100 dark:border-amber-800/70 px-4 py-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="inline-flex items-center gap-1.5 font-semibold text-amber-900 dark:text-amber-200">
                                            Comissões do mês
                                            <TooltipProvider :delay-duration="0">
                                                <Tooltip>
                                                    <TooltipTrigger as-child>
                                                        <button type="button" class="inline-flex shrink-0 cursor-pointer rounded-full text-amber-700 hover:text-amber-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary dark:text-amber-300 dark:hover:text-amber-100" aria-label="Informações sobre comissões do mês">
                                                            <Info class="h-4 w-4" aria-hidden="true" />
                                                        </button>
                                                    </TooltipTrigger>
                                                    <TooltipContent class="max-w-64">
                                                        Inclui comissões pagas e pendentes. As que foram lançadas como saída já fazem parte das saídas acumuladas.
                                                    </TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
                                        </span>
                                        <span class="font-bold text-amber-700 dark:text-amber-300">{{ formatCurrency(dashboardData?.totals.commissions || 0) }}</span>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between rounded-lg bg-gray-50 dark:bg-slate-800 border border-gray-100 dark:border-slate-700 px-4 py-3">
                                    <span class="font-semibold text-gray-700 dark:text-slate-300">Entradas acumuladas</span>
                                    <span class="font-bold text-gray-800 dark:text-slate-100">{{ formatCurrency(dashboardData?.totals.entries || 0) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Legend -->
                        <div class="bg-white dark:bg-slate-900/90 border border-transparent dark:border-slate-800 rounded-xl shadow-lg dark:shadow-none p-4 sm:p-6">
                            <h2 class="text-base font-bold text-gray-800 dark:text-slate-100 mb-4">Legenda</h2>
                            <div class="space-y-3 text-sm text-gray-600 dark:text-slate-300">
                                <div class="flex items-start gap-3">
                                    <span class="mt-1 h-3.5 w-3.5 flex-shrink-0 rounded-full bg-emerald-500"></span>
                                    <div>
                                        <p class="font-semibold text-gray-800 dark:text-slate-100">Verde</p>
                                        <p class="text-xs">Entrada apenas, ou saldo positivo.</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3">
                                    <span class="mt-1 h-3.5 w-3.5 flex-shrink-0 rounded-full bg-red-500"></span>
                                    <div>
                                        <p class="font-semibold text-gray-800 dark:text-slate-100">Vermelho</p>
                                        <p class="text-xs">Saída apenas, ou saldo negativo.</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3">
                                    <span class="mt-1 h-3.5 w-3.5 flex-shrink-0 rounded-full bg-amber-400"></span>
                                    <div>
                                        <p class="font-semibold text-gray-800 dark:text-slate-100">Amarelo</p>
                                        <p class="text-xs">Entrada e saída com resultado zerado.</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3">
                                    <span class="mt-1 h-3.5 w-3.5 flex-shrink-0 rounded-full border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900"></span>
                                    <div>
                                        <p class="font-semibold text-gray-800 dark:text-slate-100">Em branco</p>
                                        <p class="text-xs">Dia sem qualquer lançamento.</p>
                                    </div>
                                </div>
                                <div v-if="holidayLocation" class="flex items-start gap-3">
                                    <span class="w-3.5 flex-shrink-0 text-center font-bold text-red-600 dark:text-red-400">1</span>
                                    <div>
                                        <p class="font-semibold text-gray-800 dark:text-slate-100">Número vermelho</p>
                                        <p class="text-xs">Feriado ou ponto facultativo.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </aside>
                </div>

                <!-- Loading toast -->
                <div v-if="isLoading || isRefreshing" class="pointer-events-none fixed inset-x-0 bottom-6 z-30 flex justify-center px-4">
                    <div class="pointer-events-auto inline-flex items-center gap-3 rounded-full border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-4 py-3 text-sm font-semibold text-gray-700 dark:text-slate-200 shadow-lg">
                        <RefreshCw class="h-4 w-4 animate-spin text-amber-500" />
                        Processando dados do calendário...
                    </div>
                </div>

                <section
                    class="space-y-4 sm:space-y-6"
                    aria-labelledby="curiosities-title"
                >
                    <div class="dashboard-reveal flex items-start gap-3">
                        <Lightbulb
                            class="mt-0.5 h-6 w-6 shrink-0 text-amber-500"
                            aria-hidden="true"
                        />
                        <div>
                            <h2
                                id="curiosities-title"
                                class="text-xl font-bold text-gray-800 dark:text-slate-100"
                            >
                                Curiosidades
                            </h2>
                            <p
                                class="mt-1 text-sm text-gray-600 dark:text-slate-300"
                            >
                                Informações simples sobre {{ monthTitle }}, com
                                base nos registos históricos disponíveis.
                            </p>
                            <p
                                v-if="
                                    curiosityResults['monthly-average'].data
                                        ?.comparison_through_day
                                "
                                class="mt-1 text-xs text-gray-500 dark:text-slate-400"
                            >
                                Como o mês está em andamento, as comparações
                                históricas consideram apenas os dias 1 a
                                {{
                                    curiosityResults['monthly-average'].data
                                        ?.comparison_through_day
                                }}.
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="curiosityEligibility"
                        role="status"
                        class="dashboard-reveal rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-800/70 dark:bg-amber-950/35 dark:text-amber-100"
                    >
                        Ainda precisamos de mais registos para mostrar estas
                        curiosidades. Há atividade em
                        {{ curiosityEligibility.active_days }} de pelo menos
                        {{ curiosityEligibility.required_active_days }} dias
                        diferentes nos últimos
                        {{ curiosityEligibility.window_days }} dias.
                    </div>

                    <div
                        v-else
                        class="dashboard-stagger grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
                    >
                        <article
                            class="rounded-xl border border-transparent bg-white p-4 shadow-lg sm:p-5 dark:border-slate-800 dark:bg-slate-900/90 dark:shadow-none"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="font-bold text-gray-800 dark:text-slate-100">
                                    Ranking deste mês
                                </h3>
                                <CuriosityCalculationInfo title="Ranking deste mês" :paragraphs="curiosityExplanations['monthly-ranking']" />
                            </div>
                            <div
                                v-if="
                                    curiosityResults['monthly-ranking'].loading
                                "
                                role="status"
                                class="mt-4 flex items-center gap-2 text-sm text-gray-500 dark:text-slate-400"
                            >
                                <RefreshCw class="h-4 w-4 animate-spin" /> A
                                carregar...
                            </div>
                            <p
                                v-else-if="
                                    curiosityResults['monthly-ranking'].error
                                "
                                role="alert"
                                class="mt-3 text-sm text-rose-700 dark:text-rose-300"
                            >
                                {{ curiosityResults['monthly-ranking'].error }}
                            </p>
                            <template
                                v-else-if="
                                    curiosityResults['monthly-ranking'].data
                                        ?.available
                                "
                            >
                                <p
                                    class="mt-3 text-sm text-gray-600 dark:text-slate-300"
                                >
                                    {{
                                        curiosityResults['monthly-ranking'].data
                                            ?.latest_comparable?.year
                                    }}
                                    foi o
                                    <strong
                                        class="text-gray-900 dark:text-white"
                                        >{{
                                            curiosityResults['monthly-ranking']
                                                .data?.latest_comparable &&
                                            curiosityRankLabel(
                                                curiosityResults[
                                                    'monthly-ranking'
                                                ].data.latest_comparable.rank,
                                                curiosityResults[
                                                    'monthly-ranking'
                                                ].data.years_count || 0,
                                            )
                                        }}</strong
                                    >
                                    neste mês, comparando os anos anteriores.
                                </p>
                                <div class="mt-4 space-y-3">
                                    <div
                                        v-for="item in curiosityResults[
                                            'monthly-ranking'
                                        ].data?.ranked_history"
                                        :key="item.year"
                                    >
                                        <div
                                            class="mb-1 flex justify-between text-xs text-gray-600 dark:text-slate-300"
                                        >
                                            <span>{{ item.year }}</span>
                                            <span class="font-semibold">{{
                                                formatCurrency(item.entries)
                                            }}</span>
                                        </div>
                                        <div
                                            class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-slate-800"
                                        >
                                            <div
                                                class="h-full rounded-full bg-gradient-to-r from-amber-400 to-orange-500"
                                                :style="{
                                                    width: curiosityHistoryWidth(
                                                        item.entries,
                                                    ),
                                                }"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <p
                                v-else
                                class="mt-3 text-sm text-gray-600 dark:text-slate-300"
                            >
                                {{
                                    curiosityResults['monthly-ranking'].data
                                        ?.message ||
                                    'Ainda não há histórico suficiente para esta comparação.'
                                }}
                            </p>
                            <div
                                v-if="
                                    curiosityResults['monthly-ranking'].data
                                        ?.current_month_progress
                                "
                                class="mt-4 rounded-lg bg-orange-50 p-3 dark:bg-orange-950/30"
                            >
                                <p
                                    v-if="
                                        curiosityResults['monthly-ranking'].data
                                            ?.current_month_progress?.rank !==
                                        null
                                    "
                                    class="text-sm font-semibold text-orange-900 dark:text-orange-100"
                                >
                                    Até ao dia
                                    {{
                                        curiosityResults['monthly-ranking'].data
                                            ?.current_month_progress?.through_day
                                    }}, este mês ocupa a posição
                                    {{
                                        curiosityResults['monthly-ranking'].data
                                            ?.current_month_progress?.rank
                                    }} entre
                                    {{
                                        curiosityResults['monthly-ranking'].data
                                            ?.current_month_progress
                                            ?.months_compared
                                    }}
                                    meses considerados no ano.
                                </p>
                                <p
                                    v-else
                                    class="text-sm font-semibold text-orange-900 dark:text-orange-100"
                                >
                                    Ainda não há entradas neste mês para calcular
                                    a posição no ano.
                                </p>
                                <p
                                    class="mt-1 text-xs text-orange-800 dark:text-orange-200"
                                >
                                    Para comparar períodos equivalentes, cada mês
                                    considera os dias 1 a
                                    {{
                                        curiosityResults['monthly-ranking'].data
                                            ?.current_month_progress?.through_day
                                    }}.
                                </p>
                            </div>
                        </article>

                        <article
                            class="rounded-xl border border-transparent bg-white p-4 shadow-lg sm:p-5 dark:border-slate-800 dark:bg-slate-900/90 dark:shadow-none"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="font-bold text-gray-800 dark:text-slate-100">
                                    Média de entradas e saídas
                                </h3>
                                <CuriosityCalculationInfo title="Média de entradas e saídas" :paragraphs="curiosityExplanations['monthly-average']" />
                            </div>
                            <p
                                class="mt-1 text-xs text-gray-500 dark:text-slate-400"
                            >
                                Nos anos anteriores, para
                                {{ monthLabel.toLowerCase() }}
                            </p>
                            <div
                                v-if="
                                    curiosityResults['monthly-average'].loading
                                "
                                role="status"
                                class="mt-4 flex items-center gap-2 text-sm text-gray-500 dark:text-slate-400"
                            >
                                <RefreshCw class="h-4 w-4 animate-spin" /> A
                                carregar...
                            </div>
                            <p
                                v-else-if="
                                    curiosityResults['monthly-average'].error
                                "
                                role="alert"
                                class="mt-3 text-sm text-rose-700 dark:text-rose-300"
                            >
                                {{ curiosityResults['monthly-average'].error }}
                            </p>
                            <template
                                v-else-if="
                                    curiosityResults['monthly-average'].data
                                        ?.available
                                "
                            >
                                <p class="mt-3 text-xs font-semibold text-emerald-700 dark:text-emerald-300">Entradas</p>
                                <p
                                    class="mt-1 text-2xl font-bold text-emerald-700 dark:text-emerald-300"
                                >
                                    {{
                                        formatCurrency(
                                            curiosityResults['monthly-average']
                                                .data?.average_entries || 0,
                                        )
                                    }}
                                </p>
                                <p class="mt-3 text-xs font-semibold text-rose-700 dark:text-rose-300">Saídas</p>
                                <p class="mt-1 text-2xl font-bold text-rose-700 dark:text-rose-300">
                                    {{ formatCurrency(curiosityResults['monthly-average'].data?.average_exits ?? 0) }}
                                </p>
                                <p
                                    class="mt-2 text-xs leading-relaxed text-gray-600 dark:text-slate-300"
                                >
                                    Média dos registos de
                                    {{
                                        curiosityResults['monthly-average'].data
                                            ?.years_count
                                    }}
                                    ano(s) anterior(es). É uma referência
                                    histórica, não uma garantia.
                                </p>
                            </template>
                            <p
                                v-else
                                class="mt-3 text-sm text-gray-600 dark:text-slate-300"
                            >
                                {{
                                    curiosityResults['monthly-average'].data
                                        ?.message ||
                                    'Ainda não há histórico suficiente para esta comparação.'
                                }}
                            </p>
                            <div
                                v-if="
                                    curiosityResults['monthly-average'].data
                                        ?.current_month_progress
                                "
                                class="mt-3 rounded-lg bg-orange-50 p-3 dark:bg-orange-950/30"
                            >
                                <p
                                    class="text-xs text-emerald-900 dark:text-emerald-100"
                                >
                                    Entradas registadas neste mês até ao dia
                                    {{
                                        curiosityResults['monthly-average'].data
                                            ?.current_month_progress?.through_day
                                    }}
                                </p>
                                <p
                                    class="mt-1 text-xl font-bold text-emerald-700 dark:text-emerald-300"
                                >
                                    {{
                                        formatCurrency(
                                            curiosityResults['monthly-average']
                                                .data?.current_month_progress
                                                ?.entries || 0,
                                        )
                                    }}
                                </p>
                                <p class="mt-3 text-xs text-rose-900 dark:text-rose-100">
                                    Saídas registadas neste mês no mesmo intervalo
                                </p>
                                <p class="mt-1 text-xl font-bold text-rose-700 dark:text-rose-300">
                                    {{ formatCurrency(curiosityResults['monthly-average'].data?.current_month_progress?.exits ?? 0) }}
                                </p>
                            </div>
                        </article>

                        <article
                            class="rounded-xl border border-transparent bg-white p-4 shadow-lg sm:p-5 dark:border-slate-800 dark:bg-slate-900/90 dark:shadow-none"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="font-bold text-gray-800 dark:text-slate-100">
                                    Melhor dia de vendas do ano
                                </h3>
                                <CuriosityCalculationInfo title="Melhor dia de vendas do ano" :paragraphs="curiosityExplanations['best-day']" />
                            </div>
                            <p
                                class="mt-1 text-xs text-gray-500 dark:text-slate-400"
                            >
                                Até ao fim do mês selecionado
                            </p>
                            <div
                                v-if="curiosityResults['best-day'].loading"
                                role="status"
                                class="mt-4 flex items-center gap-2 text-sm text-gray-500 dark:text-slate-400"
                            >
                                <RefreshCw class="h-4 w-4 animate-spin" /> A
                                carregar...
                            </div>
                            <p
                                v-else-if="curiosityResults['best-day'].error"
                                role="alert"
                                class="mt-3 text-sm text-rose-700 dark:text-rose-300"
                            >
                                {{ curiosityResults['best-day'].error }}
                            </p>
                            <template
                                v-else-if="
                                    curiosityResults['best-day'].data?.available
                                "
                            >
                                <p
                                    class="mt-3 text-2xl font-bold text-orange-700 dark:text-orange-300"
                                >
                                    {{
                                        curiosityDateLabel(
                                            curiosityResults['best-day'].data
                                                ?.best_day?.date || '',
                                        )
                                    }}
                                </p>
                                <p
                                    class="mt-2 text-sm font-semibold text-emerald-700 dark:text-emerald-300"
                                >
                                    {{
                                        formatCurrency(
                                            curiosityResults['best-day'].data
                                                ?.best_day?.entries || 0,
                                        )
                                    }}
                                    em entradas.
                                </p>
                            </template>
                            <p
                                v-else
                                class="mt-3 text-sm text-gray-600 dark:text-slate-300"
                            >
                                {{
                                    curiosityResults['best-day'].data
                                        ?.message ||
                                    'Ainda não há registos suficientes neste ano.'
                                }}
                            </p>
                            <div
                                v-if="!curiosityResults['best-day'].loading && !curiosityResults['best-day'].error && previousYearMonthBestDay"
                                class="mt-4 border-t border-gray-100 pt-4 dark:border-slate-800"
                            >
                                <p class="text-xs font-semibold text-gray-500 dark:text-slate-400">
                                    Melhor dia de {{ monthLabel.toLowerCase() }} de {{ selectedYear - 1 }}
                                </p>
                                <p class="mt-2 text-lg font-bold text-orange-700 dark:text-orange-300">
                                    {{ curiosityDateLabel(previousYearMonthBestDay.date) }}
                                </p>
                                <p class="mt-1 text-sm font-semibold text-emerald-700 dark:text-emerald-300">
                                    {{ formatCurrency(previousYearMonthBestDay.entries) }} em entradas.
                                </p>
                            </div>
                        </article>

                        <article class="col-span-full rounded-xl border border-transparent bg-white p-4 shadow-lg sm:p-5 dark:border-slate-800 dark:bg-slate-900/90 dark:shadow-none">
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="font-bold text-gray-800 dark:text-slate-100">
                                    Entradas e saídas por dia da semana
                                </h3>
                                <CuriosityCalculationInfo title="Entradas e saídas por dia da semana" :paragraphs="curiosityExplanations['weekday-average']" />
                            </div>
                            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">
                                Média dos meses equivalentes em anos anteriores
                            </p>
                            <div v-if="curiosityResults['weekday-average'].loading" role="status" class="mt-4 flex items-center gap-2 text-sm text-gray-500 dark:text-slate-400">
                                <RefreshCw class="h-4 w-4 animate-spin" /> A carregar...
                            </div>
                            <p v-else-if="curiosityResults['weekday-average'].error" role="alert" class="mt-3 text-sm text-rose-700 dark:text-rose-300">
                                {{ curiosityResults['weekday-average'].error }}
                            </p>
                            <template v-else>
                                <p v-if="!curiosityResults['weekday-average'].data?.available" class="mt-3 text-sm text-gray-600 dark:text-slate-300">
                                    {{ curiosityResults['weekday-average'].data?.message || 'Ainda não há histórico suficiente para esta comparação.' }}
                                </p>
                                <p v-if="curiosityResults['weekday-average'].data?.current_month_progress" class="mt-3 text-xs text-gray-500 dark:text-slate-400">
                                    Média por dia da semana neste mês, até ao dia
                                    {{ curiosityResults['weekday-average'].data?.current_month_progress?.through_day }}
                                </p>
                                <div v-if="curiosityWeekdays.length" class="mt-5 space-y-5">
                                    <div class="flex justify-end">
                                        <TooltipProvider :delay-duration="0">
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <button type="button" class="inline-flex cursor-pointer items-center gap-1.5 rounded text-xs font-medium text-gray-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary dark:text-slate-400" aria-label="Como calculamos o saldo médio">
                                                        Saldo médio
                                                        <Info class="h-4 w-4" aria-hidden="true" />
                                                    </button>
                                                </TooltipTrigger>
                                                <TooltipContent class="max-w-64">
                                                    Média de entradas menos a média de saídas para cada dia da semana, no período considerado.
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    </div>
                                    <div v-for="weekday in curiosityWeekdays" :key="weekday.day" class="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-x-4 gap-y-2 sm:grid-cols-[8rem_minmax(0,1fr)_8rem]">
                                        <span class="text-sm font-semibold text-gray-700 dark:text-slate-200">{{ weekday.label }}</span>
                                        <div class="col-span-2 row-start-2 min-w-0 sm:col-span-1 sm:col-start-2 sm:row-start-1">
                                            <div class="flex h-4 overflow-hidden rounded-full bg-gray-100 dark:bg-slate-800" aria-hidden="true">
                                                <div class="h-full shrink-0 bg-emerald-500" :style="{ width: curiosityWeekdayWidth(weekday.average_entries) }" />
                                                <div class="h-full shrink-0 bg-red-500" :style="{ width: curiosityWeekdayWidth(weekday.average_exits) }" />
                                            </div>
                                            <div class="mt-1.5 flex flex-wrap justify-between gap-x-4 gap-y-1 text-xs">
                                                <span class="font-medium text-emerald-700 dark:text-emerald-300">Entradas: {{ formatCurrency(weekday.average_entries) }}</span>
                                                <span class="font-medium text-red-700 dark:text-red-300">Saídas: {{ formatCurrency(weekday.average_exits) }}</span>
                                            </div>
                                        </div>
                                        <div class="col-start-2 row-start-1 text-right sm:col-start-3">
                                            <p title="Saldo médio" class="text-sm font-bold" :class="weekday.average_entries - weekday.average_exits < 0 ? 'text-red-700 dark:text-red-300' : 'text-emerald-900 dark:text-emerald-400'">
                                                {{ formatSignedCurrency(weekday.average_entries - weekday.average_exits) }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </article>
                    </div>
                </section>

                <!-- Loading toast -->
                <div
                    v-if="isLoading || isRefreshing"
                    class="pointer-events-none fixed inset-x-0 bottom-6 z-30 flex justify-center px-4"
                >
                    <div
                        class="pointer-events-auto inline-flex items-center gap-3 rounded-full border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-700 shadow-lg dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                    >
                        <RefreshCw
                            class="h-4 w-4 animate-spin text-amber-500"
                        />
                        Processando dados do calendário...
                    </div>
                </div>
            </main>
            <Footer />
        </div>
    </div>
    <HolidayLocationDialog v-if="holidayDialogOpen" :location="holidayLocation" :year="selectedYear" :month="selectedMonth" @close="holidayDialogOpen = false" @saved="onHolidayLocationSaved" />
</template>

<style scoped>
@media (prefers-reduced-motion: no-preference) {
    .dashboard-reveal,
    .dashboard-stagger > * {
        animation: dashboard-enter 240ms cubic-bezier(0.16, 1, 0.3, 1) backwards;
    }

    .dashboard-stagger > :nth-child(2) {
        animation-delay: 35ms;
    }

    .dashboard-stagger > :nth-child(3) {
        animation-delay: 70ms;
    }

    .dashboard-stagger > :nth-child(n + 4) {
        animation-delay: 105ms;
    }

    @keyframes dashboard-enter {
        from {
            opacity: 0;
            transform: translateY(8px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
}
</style>