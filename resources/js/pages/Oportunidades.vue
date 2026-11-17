<script setup lang="ts">
import AppSelect from '@/components/AppSelect.vue';
import CuriosityCalculationInfo from '@/components/CuriosityCalculationInfo.vue';
import FixedPageHeaderTitle from '@/components/FixedPageHeaderTitle.vue';
import Footer from '@/components/Footer.vue';
import Sidebar from '@/components/Sidebar.vue';
import { useAuth } from '@/composables/useAuth';
import { usePrivacy } from '@/composables/usePrivacy';
import { Head } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowDownRight,
    ArrowUpRight,
    CalendarDays,
    RefreshCw,
    TrendingUp,
    Wallet,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

type CategoryOption = {
    value: string;
    label: string;
};

type ImpactRange = {
    minimum: number;
    maximum: number;
    basis: string;
} | null;

type RevenueTrend = {
    current_entries: number;
    previous_entries: number;
    difference: number;
    change_percent: number | null;
    direction: 'up' | 'down' | 'stable';
    recommendation: string;
    estimated_impact: ImpactRange;
};

type WeekdayAverage = {
    day: number;
    label: string;
    average_entries: number;
};

type WeekdayOpportunity = {
    weakest_weekday: WeekdayAverage;
    strongest_weekday: WeekdayAverage;
    weekday_averages: WeekdayAverage[];
    recommendation: string;
    estimated_impact: ImpactRange;
};

type CashFlow = {
    entries: number;
    exits: number;
    net: number;
    previous_net: number;
    recommendation: string;
    estimated_impact: ImpactRange;
};

type OpportunityAnalysis = {
    eligible: boolean;
    eligibility: {
        active_days: number;
        required_active_days: number;
        window_days: number;
    };
    period: {
        start_date: string;
        end_date: string;
    };
    selected_category: {
        type: 'all' | 'entry' | 'exit';
        id: number | null;
    };
    categories: CategoryOption[];
    period_has_activity: boolean;
    insights: {
        revenue_trend: RevenueTrend;
        weekday_opportunity: WeekdayOpportunity;
        cash_flow: CashFlow;
    };
};

const props = defineProps<{ today: string }>();

const auth = useAuth();
const { isPrivacyMode } = usePrivacy();
const todayInput = props.today;
const currentMonthStart = `${todayInput.slice(0, 7)}-01`;
const startDate = ref(currentMonthStart);
const endDate = ref(todayInput);
const selectedCategory = ref('all');
const categoryOptions = ref<CategoryOption[]>([
    { value: 'all', label: 'Todas as categorias' },
]);
const analysis = ref<OpportunityAnalysis | null>(null);
const isLoading = ref(false);
const loadError = ref('');
let requestId = 0;

const companyName = computed(
    () => auth.getCompanyName.value || 'a sua empresa',
);
const maxEndDate = computed(() => {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(startDate.value)) {
        return todayInput;
    }

    const [year, month, day] = startDate.value.split('-').map(Number);
    const twoYearsAfterStart = formatDate(new Date(year + 2, month - 1, day));

    return twoYearsAfterStart < todayInput
        ? twoYearsAfterStart
        : todayInput;
});
const periodError = computed(() => {
    if (!startDate.value || !endDate.value) {
        return 'Selecione as datas inicial e final.';
    }
    if (startDate.value > todayInput) {
        return 'A data inicial não pode ser posterior a hoje.';
    }
    if (startDate.value > endDate.value) {
        return 'A data final deve ser igual ou posterior à data inicial.';
    }
    if (endDate.value > todayInput) {
        return 'A data final não pode ser posterior a hoje.';
    }
    if (endDate.value > maxEndDate.value) {
        return 'O intervalo entre as datas não pode ser superior a 2 anos.';
    }

    return '';
});
const isPeriodValid = computed(() =>
    Boolean(startDate.value && endDate.value && !periodError.value),
);
const maxWeekdayAverage = computed(() =>
    Math.max(
        0,
        ...(analysis.value?.insights.weekday_opportunity.weekday_averages.map(
            (item) => item.average_entries,
        ) ?? []),
    ),
);

const calculationExplanations = computed(() => {
    const data = analysis.value;
    if (!data) return { revenue: [], weekday: [], cashFlow: [] };

    const dayMilliseconds = 86400000;
    const start = new Date(`${data.period.start_date}T00:00:00Z`);
    const end = new Date(`${data.period.end_date}T00:00:00Z`);
    const periodDays = Math.round((end.getTime() - start.getTime()) / dayMilliseconds) + 1;
    const previousEnd = new Date(start.getTime() - dayMilliseconds);
    const previousStart = new Date(start.getTime() - periodDays * dayMilliseconds);
    const dateLabel = (date: Date) => date.toLocaleDateString('pt-BR', { timeZone: 'UTC' });
    const category = data.selected_category;
    const categoryLabel = data.categories.find((option) => option.value === `${category.type}:${category.id}`)?.label;
    const common = [
        `Período analisado: ${dateLabel(start)} a ${dateLabel(end)}, incluindo as duas datas (${periodDays} dias). As explicações usam o período do resultado exibido, mesmo que os filtros tenham sido alterados sem atualizar a análise.`,
        `Usamos somente registos financeiros da sua empresa que não foram excluídos. Cada lançamento vale (valor × quantidade) − desconto, com mínimo de zero. Valores monetários são arredondados para duas casas decimais.`,
        `A análise exige registos em pelo menos ${data.eligibility.required_active_days} dias diferentes nos últimos ${data.eligibility.window_days} dias até hoje, no fuso da aplicação. A sua empresa tem ${data.eligibility.active_days} dias nessa janela. Vários lançamentos na mesma data contam uma vez, e dias com apenas saídas também contam. Essa regra é independente do período e da categoria selecionados.`,
        category.type === 'all'
            ? 'Categoria: todas. Consideramos entradas e saídas de todas as categorias.'
            : `Categoria: ${categoryLabel ?? (category.type === 'entry' ? 'tipo de entrada selecionado' : 'tipo de saída selecionado')}. O filtro é aplicado somente às ${category.type === 'entry' ? 'entradas; as saídas continuam incluindo todas as categorias' : 'saídas; as entradas continuam incluindo todas as categorias'}.`,
    ];
    const comparison = `O período anterior é ${dateLabel(previousStart)} a ${dateLabel(previousEnd)}: os ${periodDays} dias imediatamente anteriores, com a mesma duração. Não é necessariamente o mês anterior nem o mesmo período de outro ano. Aplicamos o mesmo filtro de categoria aos dois intervalos.`;

    return {
        revenue: [
            ...common,
            comparison,
            'Somamos somente as entradas de cada período. Saídas e comissões não são descontadas: este indicador mede entradas, não lucro.',
            'Variação em reais = entradas do período selecionado − entradas do período anterior. A porcentagem é essa diferença dividida pelas entradas anteriores, multiplicada por 100 e arredondada para uma casa decimal. Se as entradas anteriores forem zero, não calculamos porcentagem.',
            'Diferença positiva indica crescimento; negativa indica queda; zero indica estabilidade. A recomendação acompanha essa direção.',
            '“Possível impacto” só aparece quando as entradas diminuem: a faixa é de 25% a 50% do valor da queda observada. É uma referência para avaliar ações de melhoria, não uma previsão nem recuperação garantida.',
        ],
        weekday: [
            ...common,
            'Usamos somente entradas do período analisado e exibimos apenas os dias de funcionamento configurados em Minha Conta. Essa configuração limita este gráfico e a escolha dos dias mais fortes e mais fracos; não remove movimentos dos totais de Entradas ou de Fluxo de caixa.',
            'Para cada dia da semana, dividimos a soma das entradas desse dia pelo número de vezes que ele ocorre no intervalo. Dias sem entradas contam com zero. Se um dia de funcionamento não ocorrer no intervalo, sua média será zero. A barra usa como referência a maior média entre os dias exibidos.',
            'A recomendação sugere uma ação para o dia de menor média, comparando-o com o de maior média. Em empate, a ordem de domingo a sábado desempata: o primeiro empatado é escolhido como menor e o último como maior. Se todas as médias forem iguais, informamos que são parecidas.',
            `Para “Possível impacto”, calculamos a diferença entre a maior e a menor média e multiplicamos pela quantidade de ocorrências do dia mais fraco nos ${periodDays} dias seguintes ao fim do intervalo analisado. Exibimos de 25% a 50% desse valor de referência quando a diferença é positiva. Essa janela pode estar no passado e não significa previsão a partir de hoje.`,
            'As médias não descontam gastos, e as estimativas não garantem resultados futuros. Este gráfico não usa médias dos meses equivalentes em anos anteriores, ao contrário das Curiosidades do Dashboard.',
        ],
        cashFlow: [
            ...common,
            comparison,
            'Somamos as entradas e as saídas separadamente. Fluxo de caixa = entradas − saídas do período selecionado. O saldo anterior, usado na comparação interna, é calculado da mesma forma no intervalo anterior. Não é o saldo bancário acumulado desde o início da empresa.',
            'As saídas incluem comissões que possuem lançamento financeiro de saída. Não somamos novamente a lista de comissões; uma comissão sem saída financeira não é descontada aqui.',
            'Se o saldo for negativo, sugerimos rever despesas e pagamentos. Se for zero ou positivo, informamos que as entradas cobriram as saídas.',
            '“Possível impacto” só aparece quando as saídas atuais superam as anteriores. Calculamos o aumento das saídas e mostramos uma faixa de 25% a 50% desse aumento como referência de revisão de despesas, não como economia garantida.',
        ],
    };
});

function formatDate(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function formatCurrency(value: number): string {
    if (isPrivacyMode.value) {
        return 'R$ •••••';
    }

    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(value);
}

function formatChange(value: number | null): string {
    if (value === null) {
        return 'Sem valor anterior para comparar';
    }

    return `${value > 0 ? '+' : ''}${value.toLocaleString('pt-BR', { maximumFractionDigits: 1 })}%`;
}

function getErrorMessage(payload: Record<string, unknown>): string {
    const errors = payload.errors;
    if (errors && typeof errors === 'object') {
        const firstError = Object.values(
            errors as Record<string, unknown>,
        ).flat()[0];
        if (typeof firstError === 'string') {
            return firstError;
        }
    }

    if (typeof payload.message === 'string') {
        return payload.message;
    }

    return 'Não foi possível carregar a análise. Tente novamente.';
}

async function loadAnalysis(): Promise<void> {
    if (!isPeriodValid.value) {
        requestId += 1;
        isLoading.value = false;
        analysis.value = null;
        loadError.value = periodError.value;
        return;
    }

    const currentRequest = ++requestId;
    isLoading.value = true;
    loadError.value = '';
    analysis.value = null;

    const params = new URLSearchParams({
        start_date: startDate.value,
        end_date: endDate.value,
        category: selectedCategory.value,
    });

    try {
        const response = await fetch(
            `/api/opportunities?${params.toString()}`,
            {
                credentials: 'include',
                headers: { Accept: 'application/json' },
            },
        );
        const payload = (await response.json()) as {
            data?: OpportunityAnalysis;
            message?: string;
            errors?: Record<string, unknown>;
        };
        if (!response.ok) {
            throw new Error(getErrorMessage(payload));
        }
        if (currentRequest !== requestId) return;

        if (!payload.data) {
            throw new Error(
                'A resposta da análise veio incompleta. Tente novamente.',
            );
        }

        analysis.value = payload.data;
        categoryOptions.value = payload.data.categories;
        if (
            !categoryOptions.value.some(
                (option) => option.value === selectedCategory.value,
            )
        ) {
            selectedCategory.value = 'all';
        }
    } catch (error) {
        if (currentRequest === requestId) {
            loadError.value =
                error instanceof Error
                    ? error.message
                    : 'Não foi possível carregar a análise. Tente novamente.';
        }
    } finally {
        if (currentRequest === requestId) {
            isLoading.value = false;
        }
    }
}

onMounted(() => {
    void loadAnalysis();
});
</script>

<template>
    <div
        class="flex min-h-screen overflow-x-hidden bg-gradient-to-br from-amber-50 to-orange-100 dark:from-slate-950 dark:via-slate-900 dark:to-slate-950"
    >
        <Head title="Oportunidades - MEU NEGÓCIO" />
        <Sidebar activeRoute="oportunidades" />

        <div
            class="flex min-w-0 flex-1 flex-col overflow-x-hidden transition-all duration-300 lg:ml-64"
        >
            <FixedPageHeaderTitle
                title="Oportunidades"
                :subtitle="`Análises simples para ${companyName}`"
            />

            <main
                class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-5 overflow-x-hidden px-3 py-6 sm:gap-6 sm:px-6 sm:py-8 lg:px-8"
            >
                <section
                    class="rounded-xl border border-transparent bg-white p-4 shadow-lg sm:p-6 dark:border-slate-800 dark:bg-slate-900/90 dark:shadow-none"
                >
                    <div class="mb-5 flex items-start gap-3">
                        <CalendarDays
                            class="mt-0.5 h-6 w-6 shrink-0 text-orange-500"
                            aria-hidden="true"
                        />
                        <div>
                            <h2
                                class="text-lg font-bold text-gray-800 dark:text-slate-100"
                            >
                                Escolha o período da análise
                            </h2>
                            <p
                                class="mt-1 text-sm text-gray-600 dark:text-slate-300"
                            >
                                Compare os seus registos e veja sugestões
                                baseadas no que já aconteceu.
                            </p>
                        </div>
                    </div>

                    <form
                        class="grid gap-4 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_minmax(15rem,1.5fr)_auto]"
                        @submit.prevent="loadAnalysis"
                    >
                        <label
                            class="flex flex-col gap-1.5 text-sm font-medium text-gray-700 dark:text-slate-200"
                        >
                            Data inicial
                            <input
                                v-model="startDate"
                                type="date"
                                :max="todayInput"
                                class="app-select-control w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950/80 dark:text-slate-100"
                            />
                        </label>
                        <label
                            class="flex flex-col gap-1.5 text-sm font-medium text-gray-700 dark:text-slate-200"
                        >
                            Data final
                            <input
                                v-model="endDate"
                                type="date"
                                :min="startDate"
                                :max="maxEndDate"
                                class="app-select-control w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950/80 dark:text-slate-100"
                            />
                        </label>
                        <label
                            class="flex flex-col gap-1.5 text-sm font-medium text-gray-700 dark:text-slate-200"
                        >
                            Categoria
                            <AppSelect
                                v-model="selectedCategory"
                                label="Categoria"
                                :options="categoryOptions"
                                class="w-full"
                            />
                        </label>
                        <button
                            type="submit"
                            :disabled="isLoading || !isPeriodValid"
                            class="inline-flex min-h-10 cursor-pointer items-center justify-center gap-2 self-end rounded-lg bg-gradient-to-r from-orange-500 to-amber-500 px-5 py-2 text-sm font-semibold text-white transition-all hover:shadow-md active:scale-95 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <RefreshCw
                                class="h-4 w-4"
                                :class="isLoading ? 'animate-spin' : ''"
                                aria-hidden="true"
                            />
                            Analisar
                        </button>
                    </form>
                    <p
                        v-if="periodError"
                        role="alert"
                        class="mt-3 text-xs text-rose-700 dark:text-rose-300"
                    >
                        {{ periodError }}
                    </p>
                    <p class="mt-3 text-xs text-gray-500 dark:text-slate-400">
                        A análise compara períodos do mesmo tamanho e, neste
                        mês, considera os registos até hoje. A categoria
                        selecionada filtra entradas ou saídas desse tipo; o
                        outro lado continua completo para contextualizar o
                        caixa.
                    </p>
                </section>

                <div
                    v-if="loadError"
                    role="alert"
                    class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800/70 dark:bg-red-950/35 dark:text-red-200"
                >
                    <p class="font-semibold">
                        Não foi possível concluir a análise.
                    </p>
                    <p class="mt-1">{{ loadError }}</p>
                </div>

                <section
                    v-if="isLoading"
                    role="status"
                    class="rounded-xl border border-orange-200 bg-white p-6 text-sm font-medium text-gray-700 shadow-sm dark:border-slate-800 dark:bg-slate-900/90 dark:text-slate-200"
                >
                    <div class="flex items-center gap-3">
                        <RefreshCw
                            class="h-5 w-5 animate-spin text-orange-500"
                            aria-hidden="true"
                        />
                        A analisar os registos do período...
                    </div>
                </section>

                <section
                    v-else-if="analysis && !analysis.eligible"
                    class="rounded-xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-800/70 dark:bg-amber-950/35"
                >
                    <div class="flex items-start gap-3">
                        <AlertTriangle
                            class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-300"
                            aria-hidden="true"
                        />
                        <div>
                            <h2
                                class="font-bold text-amber-950 dark:text-amber-100"
                            >
                                Ainda precisamos de mais registos
                            </h2>
                            <p
                                class="mt-1 text-sm text-amber-900 dark:text-amber-200"
                            >
                                Encontrámos atividade em
                                {{ analysis.eligibility.active_days }} de pelo
                                menos
                                {{ analysis.eligibility.required_active_days }}
                                dias diferentes nos últimos
                                {{ analysis.eligibility.window_days }} dias.
                                Continue a registar entradas e saídas para
                                receber sugestões mais úteis.
                            </p>
                        </div>
                    </div>
                </section>

                <section
                    v-else-if="
                        analysis?.eligible && !analysis.period_has_activity
                    "
                    class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-gray-700 shadow-sm dark:border-slate-800 dark:bg-slate-900/90 dark:text-slate-200"
                >
                    Não encontrámos entradas ou saídas no período e categoria
                    selecionados. Ajuste os filtros para consultar outro
                    intervalo.
                </section>

                <template v-else-if="analysis?.eligible">
                    <section class="grid gap-4 lg:grid-cols-3">
                        <article
                            class="rounded-xl border border-transparent bg-white p-5 shadow-lg sm:p-6 dark:border-slate-800 dark:bg-slate-900/90 dark:shadow-none"
                        >
                            <div
                                class="mb-4 flex items-center justify-between gap-3"
                            >
                                <div class="flex items-center gap-2">
                                    <ArrowUpRight
                                        v-if="
                                            analysis.insights.revenue_trend
                                                .direction === 'up'
                                        "
                                        class="h-5 w-5 text-emerald-600"
                                        aria-label="Em crescimento"
                                    />
                                    <ArrowDownRight
                                        v-else-if="
                                            analysis.insights.revenue_trend
                                                .direction === 'down'
                                        "
                                        class="h-5 w-5 text-rose-600"
                                        aria-label="Em queda"
                                    />
                                    <TrendingUp
                                        v-else
                                        class="h-5 w-5 text-orange-500"
                                        aria-hidden="true"
                                    />
                                    <h2
                                        class="font-bold text-gray-800 dark:text-slate-100"
                                    >
                                        Entradas
                                    </h2>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span
                                        v-if="analysis.insights.revenue_trend.direction === 'stable'"
                                        class="text-xs font-semibold text-gray-500 dark:text-slate-400"
                                    >Estáveis</span>
                                    <CuriosityCalculationInfo title="Entradas" :paragraphs="calculationExplanations.revenue" />
                                </div>
                            </div>
                            <p
                                class="text-xs text-gray-500 dark:text-slate-400"
                            >
                                Período selecionado
                            </p>
                            <p
                                class="mt-1 text-2xl font-bold text-gray-900 dark:text-white"
                            >
                                {{
                                    formatCurrency(
                                        analysis.insights.revenue_trend
                                            .current_entries,
                                    )
                                }}
                            </p>
                            <div
                                class="mt-4 rounded-lg bg-gray-50 p-3 text-sm dark:bg-slate-800"
                            >
                                <div
                                    class="flex justify-between gap-3 text-gray-600 dark:text-slate-300"
                                >
                                    <span>Período anterior</span>
                                    <span class="font-semibold">{{
                                        formatCurrency(
                                            analysis.insights.revenue_trend
                                                .previous_entries,
                                        )
                                    }}</span>
                                </div>
                                <div
                                    class="mt-2 flex justify-between gap-3 font-semibold text-gray-800 dark:text-slate-100"
                                >
                                    <span>Variação</span>
                                    <span
                                        >{{
                                            formatCurrency(
                                                analysis.insights.revenue_trend
                                                    .difference,
                                            )
                                        }}
                                        ({{
                                            formatChange(
                                                analysis.insights.revenue_trend
                                                    .change_percent,
                                            )
                                        }})</span
                                    >
                                </div>
                            </div>
                            <p
                                class="mt-4 text-sm leading-relaxed text-gray-700 dark:text-slate-200"
                            >
                                {{
                                    analysis.insights.revenue_trend
                                        .recommendation
                                }}
                            </p>
                            <div
                                v-if="
                                    analysis.insights.revenue_trend
                                        .estimated_impact
                                "
                                class="mt-4 rounded-lg border border-orange-100 bg-orange-50 p-3 dark:border-orange-900/60 dark:bg-orange-950/30"
                            >
                                <p
                                    class="text-xs font-semibold tracking-wide text-orange-800 uppercase dark:text-orange-200"
                                >
                                    Possível impacto
                                </p>
                                <p
                                    class="mt-1 font-bold text-orange-900 dark:text-orange-100"
                                >
                                    {{
                                        formatCurrency(
                                            analysis.insights.revenue_trend
                                                .estimated_impact.minimum,
                                        )
                                    }}
                                    a
                                    {{
                                        formatCurrency(
                                            analysis.insights.revenue_trend
                                                .estimated_impact.maximum,
                                        )
                                    }}
                                </p>
                                <p
                                    class="mt-1 text-xs text-orange-800 dark:text-orange-200"
                                >
                                    {{
                                        analysis.insights.revenue_trend
                                            .estimated_impact.basis
                                    }}
                                </p>
                            </div>
                        </article>

                        <article
                            class="rounded-xl border border-transparent bg-white p-5 shadow-lg sm:p-6 dark:border-slate-800 dark:bg-slate-900/90 dark:shadow-none"
                        >
                            <div class="mb-4 flex items-center gap-2">
                                <CalendarDays
                                    class="h-5 w-5 text-orange-500"
                                    aria-hidden="true"
                                />
                                <h2
                                    class="font-bold text-gray-800 dark:text-slate-100"
                                >
                                    Dias da semana
                                </h2>
                                <div class="ml-auto">
                                    <CuriosityCalculationInfo title="Dias da semana" :paragraphs="calculationExplanations.weekday" />
                                </div>
                            </div>
                            <p
                                class="text-sm text-gray-600 dark:text-slate-300"
                            >
                                Média de entradas por dia
                            </p>
                            <div class="mt-4 space-y-3">
                                <div
                                    v-for="weekday in analysis.insights
                                        .weekday_opportunity.weekday_averages"
                                    :key="weekday.day"
                                >
                                    <div
                                        class="mb-1 flex justify-between gap-3 text-xs text-gray-600 dark:text-slate-300"
                                    >
                                        <span>{{ weekday.label }}</span>
                                        <span class="font-semibold">{{
                                            formatCurrency(
                                                weekday.average_entries,
                                            )
                                        }}</span>
                                    </div>
                                    <div
                                        class="h-2.5 overflow-hidden rounded-full bg-gray-100 dark:bg-slate-800"
                                    >
                                        <div
                                            class="h-full rounded-full bg-gradient-to-r from-orange-400 to-amber-400"
                                            :style="{
                                                width: `${maxWeekdayAverage > 0 ? (weekday.average_entries / maxWeekdayAverage) * 100 : 0}%`,
                                            }"
                                        />
                                    </div>
                                </div>
                            </div>
                            <p
                                class="mt-4 text-sm leading-relaxed text-gray-700 dark:text-slate-200"
                            >
                                {{
                                    analysis.insights.weekday_opportunity
                                        .recommendation
                                }}
                            </p>
                            <div
                                v-if="
                                    analysis.insights.weekday_opportunity
                                        .estimated_impact
                                "
                                class="mt-4 rounded-lg border border-orange-100 bg-orange-50 p-3 dark:border-orange-900/60 dark:bg-orange-950/30"
                            >
                                <p
                                    class="text-xs font-semibold tracking-wide text-orange-800 uppercase dark:text-orange-200"
                                >
                                    Possível impacto
                                </p>
                                <p
                                    class="mt-1 font-bold text-orange-900 dark:text-orange-100"
                                >
                                    {{
                                        formatCurrency(
                                            analysis.insights
                                                .weekday_opportunity
                                                .estimated_impact.minimum,
                                        )
                                    }}
                                    a
                                    {{
                                        formatCurrency(
                                            analysis.insights
                                                .weekday_opportunity
                                                .estimated_impact.maximum,
                                        )
                                    }}
                                </p>
                                <p
                                    class="mt-1 text-xs text-orange-800 dark:text-orange-200"
                                >
                                    {{
                                        analysis.insights.weekday_opportunity
                                            .estimated_impact.basis
                                    }}
                                </p>
                            </div>
                        </article>

                        <article
                            class="rounded-xl border border-transparent bg-white p-5 shadow-lg sm:p-6 dark:border-slate-800 dark:bg-slate-900/90 dark:shadow-none"
                        >
                            <div class="mb-4 flex items-center gap-2">
                                <Wallet
                                    class="h-5 w-5 text-orange-500"
                                    aria-hidden="true"
                                />
                                <h2
                                    class="font-bold text-gray-800 dark:text-slate-100"
                                >
                                    Fluxo de caixa
                                </h2>
                                <div class="ml-auto">
                                    <CuriosityCalculationInfo title="Fluxo de caixa" :paragraphs="calculationExplanations.cashFlow" />
                                </div>
                            </div>
                            <p
                                class="text-xs text-gray-500 dark:text-slate-400"
                            >
                                Entradas menos saídas registadas
                            </p>
                            <p
                                class="mt-1 text-2xl font-bold"
                                :class="
                                    analysis.insights.cash_flow.net >= 0
                                        ? 'text-emerald-700 dark:text-emerald-300'
                                        : 'text-rose-700 dark:text-rose-300'
                                "
                            >
                                {{
                                    formatCurrency(
                                        analysis.insights.cash_flow.net,
                                    )
                                }}
                            </p>
                            <div
                                class="mt-4 space-y-2 rounded-lg bg-gray-50 p-3 text-sm dark:bg-slate-800"
                            >
                                <div
                                    class="flex justify-between gap-3 text-gray-600 dark:text-slate-300"
                                >
                                    <span>Entradas</span>
                                    <span class="font-semibold">{{
                                        formatCurrency(
                                            analysis.insights.cash_flow.entries,
                                        )
                                    }}</span>
                                </div>
                                <div
                                    class="flex justify-between gap-3 text-gray-600 dark:text-slate-300"
                                >
                                    <span>Saídas</span>
                                    <span class="font-semibold">{{
                                        formatCurrency(
                                            analysis.insights.cash_flow.exits,
                                        )
                                    }}</span>
                                </div>
                            </div>
                            <p
                                class="mt-4 text-sm leading-relaxed text-gray-700 dark:text-slate-200"
                            >
                                {{ analysis.insights.cash_flow.recommendation }}
                            </p>
                            <div
                                v-if="
                                    analysis.insights.cash_flow.estimated_impact
                                "
                                class="mt-4 rounded-lg border border-orange-100 bg-orange-50 p-3 dark:border-orange-900/60 dark:bg-orange-950/30"
                            >
                                <p
                                    class="text-xs font-semibold tracking-wide text-orange-800 uppercase dark:text-orange-200"
                                >
                                    Possível impacto
                                </p>
                                <p
                                    class="mt-1 font-bold text-orange-900 dark:text-orange-100"
                                >
                                    {{
                                        formatCurrency(
                                            analysis.insights.cash_flow
                                                .estimated_impact.minimum,
                                        )
                                    }}
                                    a
                                    {{
                                        formatCurrency(
                                            analysis.insights.cash_flow
                                                .estimated_impact.maximum,
                                        )
                                    }}
                                </p>
                                <p
                                    class="mt-1 text-xs text-orange-800 dark:text-orange-200"
                                >
                                    {{
                                        analysis.insights.cash_flow
                                            .estimated_impact.basis
                                    }}
                                </p>
                            </div>
                        </article>
                    </section>

                    <p
                        class="text-xs leading-relaxed text-gray-500 dark:text-slate-400"
                    >
                        As estimativas são referências baseadas nas diferenças
                        encontradas nos seus registos. Não garantem resultados
                        futuros.
                    </p>
                </template>
            </main>
            <Footer />
        </div>
    </div>
</template>
