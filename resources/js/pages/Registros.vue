<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { ref, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue';
import { Eye, EyeOff, Info } from 'lucide-vue-next';
import { useAuth } from '@/composables/useAuth';
import { usePrivacy } from '@/composables/usePrivacy';
import { useInputMasks } from '@/composables/useInputMasks';
import Sidebar from '@/components/Sidebar.vue';
import FixedPageHeaderTitle from '@/components/FixedPageHeaderTitle.vue';
import Footer from '@/components/Footer.vue';
import AppInlineNotice from '@/components/AppInlineNotice.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

const page = usePage();
const auth = useAuth();
const { isPrivacyMode, togglePrivacy } = usePrivacy();
const { formatCurrencyInput, parseCurrencyInput } = useInputMasks();
const registrationSuccessToastKey = 'registration_success_toast';
const entryCreateDraftStorageKey = 'draft:registros.entry.create';
const exitCreateDraftStorageKey = 'draft:registros.exit.create';
const registrationSuccessMessage = ref('');
const company = ref<any>(null);
const isLoading = ref(false);
const isListLoading = ref(false);
const isLoadingMore = ref(false);
const activeTab = ref<'hoje' | 'ultimos7dias' | 'mensal' | 'anual' | 'todos'>('hoje');
const isChartsView = ref(false);
const selectedYear = ref<number>(new Date().getFullYear());
const selectedMonth = ref<number>(new Date().getMonth() + 1); // 1-12
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
const searchQuery = ref('');
const financialTypeFilter = ref<'entrada' | 'saida' | 'comissao' | null>(null);
const searchInputRef = ref<HTMLInputElement | null>(null);
const currentPage = ref(1);
const pagination = ref<{ total: number; per_page: number; current_page: number }>({
    total: 0,
    per_page: 7,
    current_page: 1,
});
const resumoTotais = ref<{ entries: number; exits: number; commissions: number; final_total: number; entry_discounts: number }>({
    entries: 0,
    exits: 0,
    commissions: 0,
    final_total: 0,
    entry_discounts: 0,
});

// Inline form state
const showNewEntryInline = ref(false);
const showNewExitInline = ref(false);
const editingRegistroId = ref<number | null>(null);
const editingRegistroType = ref<'entrada' | 'saida' | null>(null);
const selectedRecordId = ref<number | null>(null);
const deletingRecordId = ref<number | null>(null);
const isDeleteLoading = ref(false);
const isRestoreLoading = ref(false);
const restoringRecordId = ref<number | null>(null);
const showRemoved = ref(false);
const isMobileNavVisible = ref(true);

// Dropdown options
const tiposEntrada = ref<any[]>([]);
const tiposSaida = ref<any[]>([]);
const tiposServico = ref<any[]>([]);
const colaboradoresOptions = ref<any[]>([]);
const optionsLoaded = ref(false);
const importFileInput = ref<HTMLInputElement | null>(null);
const showImportPanel = ref(false);
const showImportHelpModal = ref(false);
const showUnsavedCreateChangesModal = ref(false);
const pendingCreateFormAction = ref<'entry' | 'exit' | null>(null);
const isExportLoading = ref(false);
const exportError = ref('');
const importTemplateError = ref('');
const selectedImportFile = ref<File | null>(null);
const importError = ref('');
const importSuccess = ref('');
const isImportLoading = ref(false);
const importSummary = ref<any | null>(null);
const showEntryCalculationTooltip = ref(false);
const showExitCalculationTooltip = ref(false);
const importDefaults = ref({
    default_entry_type_id: null as number | null,
    default_exit_type_id: null as number | null,
    default_collaborator_id: null as number | null,
});
const entryAmountInput = ref<HTMLInputElement | null>(null);
const exitAmountInput = ref<HTMLInputElement | null>(null);

interface EntryForm {
    entry_type_id: number | null;
    service_type_id: number | null;
    collaborator_id: number | null;
    date: string;
    amount: number | string;
    quantity: number;
    discount: number | string;
    description: string;
}

interface ExitForm {
    exit_type_id: number | null;
    collaborator_id: number | null;
    date: string;
    amount: number | string;
    quantity: number;
    discount: number | string;
    description: string;
}

const createEmptyEntryForm = (): EntryForm => ({
    entry_type_id: null,
    service_type_id: null,
    collaborator_id: null,
    date: new Date().toISOString().split('T')[0],
    amount: '',
    quantity: 1,
    discount: '',
    description: '',
});

const createEmptyExitForm = (): ExitForm => ({
    exit_type_id: null,
    collaborator_id: null,
    date: new Date().toISOString().split('T')[0],
    amount: '',
    quantity: 1,
    discount: '',
    description: '',
});

const restoreEntryCreateDraft = (): EntryForm => {
    if (typeof window === 'undefined') {
        return createEmptyEntryForm();
    }

    const empty = createEmptyEntryForm();
    const raw = window.sessionStorage.getItem(entryCreateDraftStorageKey);

    if (!raw) {
        return empty;
    }

    try {
        const parsed = JSON.parse(raw);
        return {
            ...empty,
            ...parsed,
            quantity: Number(parsed?.quantity ?? empty.quantity) || 1,
            date: typeof parsed?.date === 'string' && parsed.date ? parsed.date : empty.date,
            amount: parsed?.amount ?? empty.amount,
            discount: parsed?.discount ?? empty.discount,
            description: typeof parsed?.description === 'string' ? parsed.description : empty.description,
        };
    } catch {
        window.sessionStorage.removeItem(entryCreateDraftStorageKey);
        return empty;
    }
};

const restoreExitCreateDraft = (): ExitForm => {
    if (typeof window === 'undefined') {
        return createEmptyExitForm();
    }

    const empty = createEmptyExitForm();
    const raw = window.sessionStorage.getItem(exitCreateDraftStorageKey);

    if (!raw) {
        return empty;
    }

    try {
        const parsed = JSON.parse(raw);
        return {
            ...empty,
            ...parsed,
            quantity: Number(parsed?.quantity ?? empty.quantity) || 1,
            date: typeof parsed?.date === 'string' && parsed.date ? parsed.date : empty.date,
            amount: parsed?.amount ?? empty.amount,
            discount: parsed?.discount ?? empty.discount,
            description: typeof parsed?.description === 'string' ? parsed.description : empty.description,
        };
    } catch {
        window.sessionStorage.removeItem(exitCreateDraftStorageKey);
        return empty;
    }
};

const persistEntryCreateDraft = () => {
    if (typeof window === 'undefined') {
        return;
    }

    window.sessionStorage.setItem(entryCreateDraftStorageKey, JSON.stringify(entryForm.value));
};

const persistExitCreateDraft = () => {
    if (typeof window === 'undefined') {
        return;
    }

    window.sessionStorage.setItem(exitCreateDraftStorageKey, JSON.stringify(exitForm.value));
};

const clearEntryCreateDraft = () => {
    if (typeof window === 'undefined') {
        return;
    }

    window.sessionStorage.removeItem(entryCreateDraftStorageKey);
};

const clearExitCreateDraft = () => {
    if (typeof window === 'undefined') {
        return;
    }

    window.sessionStorage.removeItem(exitCreateDraftStorageKey);
};

const entryForm = ref<EntryForm>(createEmptyEntryForm());
const exitForm = ref<ExitForm>(createEmptyExitForm());
const entryFormErrors = ref<Record<string, string[]>>({});
const exitFormErrors = ref<Record<string, string[]>>({});
const isEntryLoading = ref(false);
const isExitLoading = ref(false);

const handleMobileNavVisibilityChanged = (event: Event) => {
    const customEvent = event as CustomEvent<{ visible?: boolean }>;
    if (typeof customEvent.detail?.visible === 'boolean') {
        isMobileNavVisible.value = customEvent.detail.visible;
    }
};

const useServiceTypes = computed(() => company.value?.use_service_types !== false);

// Helper function to get CSRF token from Inertia props, fallback to meta tag
const getCsrfToken = (): string => {
    // Try to get from Inertia props first
    const csrfFromProps = (page.props as any).csrf_token;
    if (csrfFromProps) return csrfFromProps;
    
    // Fallback to meta tag
    if (typeof window === 'undefined') return '';
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
};

const registros = ref<any[]>([]);
const sortKey = ref<'valor' | 'categoria' | 'colaborador' | 'descricao' | 'dataOperacao' | 'registradoEm' | 'tipo'>('registradoEm');
const sortDir = ref<'asc' | 'desc'>('desc');

const validTabs = ['hoje', 'ultimos7dias', 'mensal', 'anual', 'todos'] as const;
const validSortKeys = ['valor', 'categoria', 'colaborador', 'descricao', 'dataOperacao', 'registradoEm', 'tipo'] as const;
const validFinancialFilters = ['entrada', 'saida', 'comissao'] as const;
const yearOptions = computed(() => Array.from({ length: 5 }, (_, index) => selectedYear.value - 2 + index));

const normalizeNullableString = (value: unknown): string => {
    if (typeof value !== 'string') {
        return '';
    }

    const trimmed = value.trim();
    const lowered = trimmed.toLowerCase();

    if (lowered === 'null' || lowered === 'undefined') {
        return '';
    }

    return value;
};

const sanitizeFilterState = () => {
    const safeSearch = normalizeNullableString(searchQuery.value);
    if (searchQuery.value !== safeSearch) {
        searchQuery.value = safeSearch;
    }

    if (!validSortKeys.includes(sortKey.value)) {
        sortKey.value = 'registradoEm';
    }

    if (sortDir.value !== 'asc' && sortDir.value !== 'desc') {
        sortDir.value = 'desc';
    }

    if (!financialTypeFilter.value || !validFinancialFilters.includes(financialTypeFilter.value as typeof validFinancialFilters[number])) {
        financialTypeFilter.value = null;
    }
};

const applyFiltersFromQueryString = () => {
    if (typeof window === 'undefined') return;

    const params = new URLSearchParams(window.location.search);

    const tab = params.get('tab');
    if (tab && validTabs.includes(tab as typeof validTabs[number])) {
        activeTab.value = tab as 'hoje' | 'ultimos7dias' | 'mensal' | 'anual' | 'todos';
    }

    const month = Number(params.get('month'));
    if (Number.isInteger(month) && month >= 1 && month <= 12) {
        selectedMonth.value = month;
    }

    const year = Number(params.get('year'));
    if (Number.isInteger(year) && year >= 2000 && year <= 2100) {
        selectedYear.value = year;
    }

    const page = Number(params.get('page'));
    if (Number.isInteger(page) && page >= 1) {
        currentPage.value = page;
    }

    const search = params.get('search');
    if (search !== null) {
        searchQuery.value = normalizeNullableString(search);
    }

    const sortKeyParam = params.get('sort_key');
    if (sortKeyParam && validSortKeys.includes(sortKeyParam as typeof validSortKeys[number])) {
        sortKey.value = sortKeyParam as typeof sortKey.value;
    }

    const sortDirParam = params.get('sort_dir');
    if (sortDirParam === 'asc' || sortDirParam === 'desc') {
        sortDir.value = sortDirParam;
    }

    const financialTypeParam = normalizeNullableString(params.get('financial_type'));
    if (financialTypeParam && validFinancialFilters.includes(financialTypeParam as typeof validFinancialFilters[number])) {
        financialTypeFilter.value = financialTypeParam as typeof financialTypeFilter.value;
    }

    showRemoved.value = params.get('removed') === '1';
    isChartsView.value = params.get('charts') === '1';
};

const syncFiltersToQueryString = () => {
    if (typeof window === 'undefined') return;

    sanitizeFilterState();

    const params = new URLSearchParams();

    if (activeTab.value !== 'hoje') {
        params.set('tab', activeTab.value);
    }

    if (selectedMonth.value !== new Date().getMonth() + 1) {
        params.set('month', String(selectedMonth.value));
    }

    if (selectedYear.value !== new Date().getFullYear()) {
        params.set('year', String(selectedYear.value));
    }

    if (currentPage.value > 1) {
        params.set('page', String(currentPage.value));
    }

    const safeSearch = normalizeNullableString(searchQuery.value).trim();
    if (safeSearch) {
        params.set('search', safeSearch);
    }

    if (sortKey.value !== 'registradoEm') {
        params.set('sort_key', sortKey.value);
    }

    if (sortDir.value !== 'desc') {
        params.set('sort_dir', sortDir.value);
    }

    if (financialTypeFilter.value) {
        params.set('financial_type', financialTypeFilter.value);
    }

    if (showRemoved.value) {
        params.set('removed', '1');
    }

    if (isChartsView.value) {
        params.set('charts', '1');
    }

    const queryString = params.toString();
    const nextUrl = queryString ? `${window.location.pathname}?${queryString}` : window.location.pathname;
    window.history.replaceState({}, '', nextUrl);
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

type RangeKey = 'hoje' | 'ultimos7dias' | 'mensal' | 'anual';

const rangeTotals = ref<Record<RangeKey, { entradas: number; saidas: number; lucro: number }>>({
    hoje: { entradas: 0, saidas: 0, lucro: 0 },
    ultimos7dias: { entradas: 0, saidas: 0, lucro: 0 },
    mensal: { entradas: 0, saidas: 0, lucro: 0 },
    anual: { entradas: 0, saidas: 0, lucro: 0 },
});

const commissionsByRange = ref<Record<RangeKey, Array<{ collaborator_id: number; collaborator_name: string; total: number }>>>({
    hoje: [],
    ultimos7dias: [],
    mensal: [],
    anual: [],
});

const dailyBreakdown = ref<Array<{ date: string; entradas: number; saidas: number; lucro: number }>>([]);
const monthlyDailyBreakdown = ref<Array<{ date: string; entradas: number; saidas: number; lucro: number }>>([]);

const monthlyBreakdown = ref<Array<{ month: string; monthNum: number; entradas: number; saidas: number; lucro: number; comissoes: number }>>([]);

const formatCurrency = (value: number) => {
    if (isPrivacyMode.value) {
        return 'R$ •••••';
    }
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(value);
};

const formatDateTime = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    }).replace(',', ' às');
};

const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
};

const formatFileSize = (size: number) => {
    if (size < 1024) return `${size} B`;
    if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`;
    return `${(size / (1024 * 1024)).toFixed(2)} MB`;
};

const chartValueTitle = (label: string, value: number) => `${label}: ${formatCurrency(Number(value || 0))}`;

const chartMonthlyTitle = (month: string, label: string, value: number) => `${month} - ${label}: ${formatCurrency(Number(value || 0))}`;

const chartBarHeight = (value: number, max: number): string => {
    const safeValue = toFiniteAmount(value);
    const safeMax = toFiniteAmount(max);

    if (safeValue <= 0 || safeMax <= 0) {
        return '0%';
    }

    const percentage = (safeValue / safeMax) * 100;
    return `${Math.max(percentage, 2)}%`;
};

const toFiniteAmount = (value: unknown): number => {
    if (value === null || value === undefined) return 0;

    if (typeof value === 'number') {
        return Number.isFinite(value) ? value : 0;
    }

    if (typeof value === 'string') {
        const trimmed = value.trim();
        if (!trimmed) return 0;

        const raw = trimmed.replace(/[R$\s]/g, '');
        const normalized = raw.includes(',')
            ? raw.replace(/\./g, '').replace(',', '.')
            : raw;

        const parsed = Number(normalized);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : 0;
};

const calculateRecordTotal = (amount: unknown, quantity: unknown, discount: unknown): number => {
    const safeAmount = Math.max(0, toFiniteAmount(amount));
    const safeQuantity = Math.max(1, toFiniteAmount(quantity) || 1);
    const safeDiscount = Math.max(0, toFiniteAmount(discount));

    return Math.max(0, (safeAmount * safeQuantity) - safeDiscount);
};

const entryCalculatedTotal = computed(() => calculateRecordTotal(
    entryForm.value.amount,
    entryForm.value.quantity,
    entryForm.value.discount,
));

const exitCalculatedTotal = computed(() => calculateRecordTotal(
    exitForm.value.amount,
    exitForm.value.quantity,
    exitForm.value.discount,
));

const findDefaultOptionId = (items: any[]) => {
    const normalized = (v: string) => (v || '').trim().toLowerCase();
    const found = items.find((item: any) => {
        const name = normalized(item?.name || '');
        return name === 'não definido' || name === 'nao definido';
    });

    return found?.id ?? null;
};

const getRecordDeleteLabel = (record: { categoria?: string; descricao?: string; colaborador?: string; tipo?: string }) => {
    const normalizedValue = (value?: string) => {
        const trimmed = (value || '').trim();
        return trimmed && trimmed !== '-' ? trimmed : '';
    };

    return normalizedValue(record.categoria)
        || normalizedValue(record.descricao)
        || normalizedValue(record.colaborador)
        || (record.tipo === 'entrada' ? 'esta entrada' : 'esta saída');
};

const privacyOptionLabel = computed(() => (isPrivacyMode.value ? 'Mostrar valores sensíveis' : 'Ocultar valores sensíveis'));
const privacyStatusLabel = computed(() => (isPrivacyMode.value ? 'Ocultos' : 'Visíveis'));

const normalizedSearchQuery = computed({
    get: () => {
        return normalizeNullableString(searchQuery.value);
    },
    set: (value: string) => {
        searchQuery.value = normalizeNullableString(value);
    },
});

const activeFinancialFilterLabel = computed(() => {
    if (financialTypeFilter.value === 'entrada') return 'Entradas';
    if (financialTypeFilter.value === 'saida') return 'Saidas';
    if (financialTypeFilter.value === 'comissao') return 'Comissoes';
    return '';
});

const normalizeFilterText = (value: string) => value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim();

const isCommissionRecord = (record: { tipo: string; categoria?: string; descricao?: string }) => {
    if (record.tipo !== 'saida') {
        return false;
    }

    const category = normalizeFilterText(record.categoria || '');
    const description = normalizeFilterText(record.descricao || '');

    return category.includes('comissao') || description.includes('comissao');
};

const viewRegistros = computed(() => registros.value.map((r: any) => ({
    id: r.id,
    // Determina tipo com base no campo type do banco de dados
    tipo: r.type === 'entry' ? 'entrada' : 'saida',
    valor: calculateRecordTotal(r.amount, r.quantity, r.discount),
    categoria: r.entry_type?.name ?? r.exit_type?.name ?? '-',
    descricao: r.description ?? '',
    colaborador: r.collaborator?.name ?? '',
    // Data do movimento (informada pelo usuário) e momento do registro
    dataOperacao: r.date ?? r.created_at ?? r.updated_at,
    registradoEm: r.created_at ?? r.updated_at,
    deleted: Boolean(r.deleted_at),
})));

const filteredRegistros = computed(() => {
    // Backend já filtra por deleted status, então não precisamos filtrar novamente aqui
    // Apenas aplicamos o filtro de busca se houver
    let filtered = viewRegistros.value;

    if (financialTypeFilter.value === 'entrada') {
        filtered = filtered.filter((r) => r.tipo === 'entrada');
    } else if (financialTypeFilter.value === 'saida') {
        filtered = filtered.filter((r) => r.tipo === 'saida');
    } else if (financialTypeFilter.value === 'comissao') {
        filtered = filtered.filter((r) => isCommissionRecord(r));
    }

    if (searchQuery.value && !showRemoved.value) {
        const query = searchQuery.value.toLowerCase();
        filtered = filtered.filter(r =>
            (r.categoria || '').toLowerCase().includes(query) ||
            (r.descricao || '').toLowerCase().includes(query) ||
            formatCurrency(r.valor).includes(query)
        );
    }

    // Sorting logic by selected key and direction
    const key = sortKey.value;
    const dir = sortDir.value;
    const compare = (a: any, b: any): number => {
        let av = a[key];
        let bv = b[key];
        // Normalize based on key type
        if (key === 'valor') {
            av = Number(av ?? 0);
            bv = Number(bv ?? 0);
        } else if (key === 'dataOperacao' || key === 'registradoEm') {
            av = av ? new Date(av).getTime() : 0;
            bv = bv ? new Date(bv).getTime() : 0;
        } else {
            av = (av ?? '').toString().toLowerCase();
            bv = (bv ?? '').toString().toLowerCase();
        }
        const res = av < bv ? -1 : av > bv ? 1 : 0;
        return dir === 'asc' ? res : -res;
    };

    return filtered.slice().sort(compare);
});

// Já vem paginado do backend; usamos filtragem apenas no que está carregado
const paginatedRegistros = computed(() => filteredRegistros.value);

const totalPages = computed(() => {
    const total = pagination.value?.total ?? filteredRegistros.value.length;
    const perPage = (pagination.value?.per_page ?? filteredRegistros.value.length) || 1;
    return Math.max(1, Math.ceil(total / perPage));
});

const totalRegistros = computed(() => pagination.value?.total ?? filteredRegistros.value.length);

const hasOpenForm = computed(() => showNewEntryInline.value || showNewExitInline.value || editingRegistroId.value !== null);

const isCreateEntryFormDirty = computed(() => {
    if (!showNewEntryInline.value) return false;

    const form = entryForm.value;
    const defaultDate = new Date().toISOString().split('T')[0];

    return Boolean(
        form.entry_type_id !== null
        || form.service_type_id !== null
        || form.collaborator_id !== null
        || form.date !== defaultDate
        || String(form.amount ?? '').trim() !== ''
        || Number(form.quantity ?? 1) !== 1
        || String(form.discount ?? '').trim() !== ''
        || String(form.description ?? '').trim() !== ''
    );
});

const isCreateExitFormDirty = computed(() => {
    if (!showNewExitInline.value) return false;

    const form = exitForm.value;
    const defaultDate = new Date().toISOString().split('T')[0];

    return Boolean(
        form.exit_type_id !== null
        || form.collaborator_id !== null
        || form.date !== defaultDate
        || String(form.amount ?? '').trim() !== ''
        || Number(form.quantity ?? 1) !== 1
        || String(form.discount ?? '').trim() !== ''
        || String(form.description ?? '').trim() !== ''
    );
});

const hasUnsavedCreateFormChanges = computed(() => isCreateEntryFormDirty.value || isCreateExitFormDirty.value);

const handleUnsavedCreateChangesModalOpenChange = (isOpen: boolean) => {
    showUnsavedCreateChangesModal.value = isOpen;

    if (!isOpen) {
        pendingCreateFormAction.value = null;
    }
};

const cancelUnsavedCreateChanges = () => {
    showUnsavedCreateChangesModal.value = false;
    pendingCreateFormAction.value = null;
};

const openCreateForm = async (type: 'entry' | 'exit') => {
    if (hasUnsavedCreateFormChanges.value) {
        pendingCreateFormAction.value = type;
        showUnsavedCreateChangesModal.value = true;
        return;
    }

    closeAllForms();
    await fetchOptions();

    if (type === 'entry') {
        entryForm.value = restoreEntryCreateDraft();
        showNewEntryInline.value = true;
        await nextTick();
        entryAmountInput.value?.focus();
        return;
    }

    exitForm.value = restoreExitCreateDraft();
    showNewExitInline.value = true;
    await nextTick();
    exitAmountInput.value?.focus();
};

const confirmUnsavedCreateChanges = async () => {
    const action = pendingCreateFormAction.value;

    const shouldClearEntryDraft = isCreateEntryFormDirty.value;
    const shouldClearExitDraft = isCreateExitFormDirty.value;

    showUnsavedCreateChangesModal.value = false;
    pendingCreateFormAction.value = null;

    if (!action) {
        return;
    }

    if (shouldClearEntryDraft) {
        clearEntryCreateDraft();
    }

    if (shouldClearExitDraft) {
        clearExitCreateDraft();
    }

    closeAllForms();
    await fetchOptions();

    if (action === 'entry') {
        entryForm.value = restoreEntryCreateDraft();
        showNewEntryInline.value = true;
        await nextTick();
        entryAmountInput.value?.focus();
        return;
    }

    exitForm.value = restoreExitCreateDraft();
    showNewExitInline.value = true;
    await nextTick();
    exitAmountInput.value?.focus();
};

const hasMorePages = computed(() => currentPage.value < totalPages.value);

const infiniteScrollTrigger = ref<HTMLElement | null>(null);
let infiniteScrollObserver: IntersectionObserver | null = null;

const totalEntradas = computed(() => Number(resumoTotais.value.entries ?? 0));

const totalSaidas = computed(() => Number(resumoTotais.value.exits ?? 0));

const totalComissoes = computed(() => Number(resumoTotais.value.commissions ?? 0));

const totalDescontosEntradas = computed(() => Number(resumoTotais.value.entry_discounts ?? 0));

const totalEntradasBrutas = computed(() => totalEntradas.value + totalDescontosEntradas.value);

const totalFinal = computed(() => Number(resumoTotais.value.final_total ?? 0));

const chartRangeKey = computed<RangeKey>(() => {
    if (activeTab.value === 'hoje' || activeTab.value === 'ultimos7dias' || activeTab.value === 'mensal' || activeTab.value === 'anual') {
        return activeTab.value;
    }

    return 'anual';
});

const canExportPeriodData = computed(() => {
    return (activeTab.value === 'mensal' || activeTab.value === 'anual')
        && !showRemoved.value
        && !isChartsView.value;
});

const currentChartTotals = computed(() => rangeTotals.value[chartRangeKey.value]);
const currentCommissions = computed(() => commissionsByRange.value[chartRangeKey.value]);

const commissionsTotal = computed(() => currentCommissions.value.reduce((sum, c) => sum + toFiniteAmount(c.total), 0));

const maxValueComissoes = computed(() => {
    const values = currentCommissions.value.map((c) => toFiniteAmount(c.total));
    return values.length ? Math.max(...values) : 0;
});

const maxValueDaily = computed(() => {
    if (!dailyBreakdown.value.length) return 0;
    const values = dailyBreakdown.value
        .flatMap((d) => [toFiniteAmount(d.entradas), toFiniteAmount(d.saidas), toFiniteAmount(d.lucro)])
        .filter((v) => Number.isFinite(v));

    return values.length ? Math.max(...values) : 0;
});

const maxValueMonthlyDaily = computed(() => {
    if (!monthlyDailyBreakdown.value.length) return 0;
    const values = monthlyDailyBreakdown.value
        .flatMap((d) => [toFiniteAmount(d.entradas), toFiniteAmount(d.saidas), toFiniteAmount(d.lucro)])
        .filter((v) => Number.isFinite(v));

    return values.length ? Math.max(...values) : 0;
});

const maxValueMonthly = computed(() => {
    if (!monthlyBreakdown.value.length) return 0;
    const values = monthlyBreakdown.value
        .flatMap((m) => [toFiniteAmount(m.entradas), toFiniteAmount(m.saidas), toFiniteAmount(m.lucro), toFiniteAmount(m.comissoes)])
        .filter((v) => Number.isFinite(v));

    return values.length ? Math.max(...values) : 0;
});

const formatDayLabel = (dateStr: string) => {
    const date = new Date(dateStr + 'T00:00:00');
    return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
};

const formatDateForApi = (date: Date): string => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const parseRecordDate = (rawDate: unknown): Date | null => {
    if (!rawDate) return null;

    const raw = String(rawDate).trim();
    if (!raw) return null;

    const normalized = raw.includes('T') ? raw : raw.replace(' ', 'T');
    const parsed = new Date(normalized);
    if (!Number.isNaN(parsed.getTime())) {
        return parsed;
    }

    const dateOnly = raw.split(' ')[0]?.split('T')[0];
    if (!dateOnly || !dateOnly.includes('-')) {
        return null;
    }

    const [y, m, d] = dateOnly.split('-').map(Number);
    if (!y || !m || !d) {
        return null;
    }

    const fallback = new Date(y, m - 1, d);
    return Number.isNaN(fallback.getTime()) ? null : fallback;
};

const makeRange = (range: RangeKey) => {
    const today = new Date();

    if (range === 'hoje') {
        const ymd = formatDateForApi(today);
        return { start: ymd, end: ymd };
    }

    if (range === 'ultimos7dias') {
        const past = new Date(today);
        past.setDate(today.getDate() - 6);
        return { start: formatDateForApi(past), end: formatDateForApi(today) };
    }

    if (range === 'mensal') {
        const first = new Date(selectedYear.value, selectedMonth.value - 1, 1);
        const last = new Date(selectedYear.value, selectedMonth.value, 0);
        return { start: formatDateForApi(first), end: formatDateForApi(last) };
    }

    const first = new Date(selectedYear.value, 0, 1);
    const last = new Date(selectedYear.value, 11, 31);
    return { start: formatDateForApi(first), end: formatDateForApi(last) };
};

const fetchRangeData = async (range: RangeKey) => {
    const { start, end } = makeRange(range);

    try {
        const fetchRangePage = async (page: number) => {
            const res = await fetch(`/api/registros/financial-records/between-dates?page=${page}&only_deleted=${showRemoved.value ? 1 : 0}`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${auth.token.value}`,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({ start_date: start, end_date: end }),
            });

            if (!res.ok) {
                throw new Error('Erro ao carregar registros para gráficos');
            }

            return res.json();
        };

        const firstPageJson = await fetchRangePage(1);
        const firstPageItems = Array.isArray(firstPageJson?.data) ? firstPageJson.data : [];
        const total = Number(firstPageJson?.pagination?.total ?? firstPageItems.length ?? 0);
        const perPage = Number((firstPageJson?.pagination?.per_page ?? firstPageItems.length) || 1);
        const totalPages = Math.max(1, Math.ceil(total / Math.max(1, perPage)));

        const items = [...firstPageItems];

        for (let page = 2; page <= totalPages; page++) {
            const pageJson = await fetchRangePage(page);
            const pageItems = Array.isArray(pageJson?.data) ? pageJson.data : [];
            items.push(...pageItems);
        }

        const entradasFromApi = toFiniteAmount(firstPageJson?.totals?.entries ?? 0);
        const saidasFromApi = toFiniteAmount(firstPageJson?.totals?.exits ?? 0);
        const entradas = Number.isFinite(entradasFromApi)
            ? entradasFromApi
            : items
                .filter((r: any) => r.type === 'entry' || r.entry_type_id)
                .reduce((s: number, r: any) => s + calculateRecordTotal(r.amount, r.quantity, r.discount), 0);
        const saidas = Number.isFinite(saidasFromApi)
            ? saidasFromApi
            : items
                .filter((r: any) => r.type === 'exit' || r.exit_type_id)
                .reduce((s: number, r: any) => s + calculateRecordTotal(r.amount, r.quantity, r.discount), 0);

        rangeTotals.value[range] = { entradas, saidas, lucro: entradas - saidas };

        const commRes = await fetch('/api/registros/commissions/summary-by-collaborator', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${auth.token.value}`,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({ start_date: start, end_date: end }),
        });

        if (!commRes.ok) {
            throw new Error('Erro ao carregar comissões para gráficos');
        }

        const commJson = await commRes.json();
        const commissionsRaw = Array.isArray(commJson?.data)
            ? commJson.data
            : Array.isArray(commJson)
                ? commJson
                : [];

        commissionsByRange.value[range] = commissionsRaw.map((c: any) => ({
            collaborator_id: c.collaborator_id,
            collaborator_name: c.collaborator_name,
            total: toFiniteAmount(c.total),
        }));

        if (range === 'ultimos7dias') {
            const dailyMap = new Map<string, { entradas: number; saidas: number }>();

            items.forEach((r: any) => {
                const parsedDate = parseRecordDate(r.date);
                if (!parsedDate) return;
                const date = formatDateForApi(parsedDate);

                if (!dailyMap.has(date)) {
                    dailyMap.set(date, { entradas: 0, saidas: 0 });
                }

                const day = dailyMap.get(date)!;
                if (r.type === 'entry' || r.entry_type_id) {
                    day.entradas += calculateRecordTotal(r.amount, r.quantity, r.discount);
                } else {
                    day.saidas += calculateRecordTotal(r.amount, r.quantity, r.discount);
                }
            });

            const breakdown: Array<{ date: string; entradas: number; saidas: number; lucro: number }> = [];
            const today = new Date();

            for (let i = 6; i >= 0; i--) {
                const d = new Date(today);
                d.setDate(today.getDate() - i);
                const dateStr = formatDateForApi(d);
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

        if (range === 'mensal') {
            const dailyMap = new Map<string, { entradas: number; saidas: number }>();

            items.forEach((r: any) => {
                const parsedDate = parseRecordDate(r.date);
                if (!parsedDate) return;

                const date = formatDateForApi(parsedDate);
                if (!dailyMap.has(date)) {
                    dailyMap.set(date, { entradas: 0, saidas: 0 });
                }

                const day = dailyMap.get(date)!;
                if (r.type === 'entry' || r.entry_type_id) {
                    day.entradas += calculateRecordTotal(r.amount, r.quantity, r.discount);
                } else {
                    day.saidas += calculateRecordTotal(r.amount, r.quantity, r.discount);
                }
            });

            const breakdown: Array<{ date: string; entradas: number; saidas: number; lucro: number }> = [];
            const lastDay = new Date(selectedYear.value, selectedMonth.value, 0).getDate();

            for (let day = 1; day <= lastDay; day++) {
                const date = new Date(selectedYear.value, selectedMonth.value - 1, day);
                const dateStr = formatDateForApi(date);
                const data = dailyMap.get(dateStr) || { entradas: 0, saidas: 0 };

                breakdown.push({
                    date: dateStr,
                    entradas: data.entradas,
                    saidas: data.saidas,
                    lucro: data.entradas - data.saidas,
                });
            }

            monthlyDailyBreakdown.value = breakdown;
        }

        if (range === 'anual') {
            const monthlyMap = new Map<number, { entradas: number; saidas: number; comissoes: number }>();

            for (let m = 0; m < 12; m++) {
                monthlyMap.set(m, { entradas: 0, saidas: 0, comissoes: 0 });
            }

            // Agregar entradas e saídas por mês
            items.forEach((r: any) => {
                const date = parseRecordDate(r.date);
                if (!date) return;
                const monthNum = date.getMonth();
                const month = monthlyMap.get(monthNum)!;

                if (r.type === 'entry' || r.entry_type_id) {
                    month.entradas += calculateRecordTotal(r.amount, r.quantity, r.discount);
                } else {
                    month.saidas += calculateRecordTotal(r.amount, r.quantity, r.discount);
                }
            });

            // Carregar comissões agregadas por mês
            try {
                const commMonthRes = await fetch('/api/registros/commissions/summary-by-month', {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${auth.token.value}`,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken(),
                    },
                    body: JSON.stringify({ 
                        start_date: `${selectedYear.value}-01-01`, 
                        end_date: `${selectedYear.value}-12-31` 
                    }),
                });

                if (commMonthRes.ok) {
                    const commMonthData = await commMonthRes.json();
                    const commissionsMonthly = Array.isArray(commMonthData?.data) ? commMonthData.data : (Array.isArray(commMonthData) ? commMonthData : []);
                    
                    commissionsMonthly.forEach((cm: any) => {
                        const month = monthlyMap.get(cm.monthNum);
                        if (month) {
                            month.comissoes = toFiniteAmount(cm.total);
                        }
                    });
                }
            } catch (error) {
                console.error('Erro ao carregar comissões por mês:', error);
            }

            const monthNames = ['Jan', 'Fev', 'Mar', 'Abr', 'Maio', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
            const breakdown: Array<{ month: string; monthNum: number; entradas: number; saidas: number; lucro: number; comissoes: number }> = [];

            for (let m = 0; m < 12; m++) {
                const data = monthlyMap.get(m) || { entradas: 0, saidas: 0, comissoes: 0 };
                breakdown.push({
                    month: monthNames[m],
                    monthNum: m,
                    entradas: data.entradas,
                    saidas: data.saidas,
                    lucro: data.entradas - data.saidas,
                    comissoes: data.comissoes,
                });
            }

            monthlyBreakdown.value = breakdown;
        }
    } catch (error) {
        console.error('Erro ao carregar dados de gráficos:', error);
    }
};

const toggleChartsView = async () => {
    isChartsView.value = !isChartsView.value;

    if (isChartsView.value) {
        if (activeTab.value !== 'todos') {
            await fetchRangeData(chartRangeKey.value);
        }
    } else if (activeTab.value === 'todos') {
        currentPage.value = 1;
        await fetchRegistros(1);
    }
};

const fetchRegistros = async (page = 1, showPageLoading = false, append = false) => {
    if (append && (isLoading.value || isListLoading.value || isLoadingMore.value)) {
        return;
    }

    if (showPageLoading) {
        isLoading.value = true;
    } else if (append) {
        isLoadingMore.value = true;
    } else {
        isListLoading.value = true;
    }
    try {
        // Decide endpoint based on activeTab
        const today = new Date();
        let response: Response;

        if (activeTab.value === 'hoje' || activeTab.value === 'ultimos7dias' || activeTab.value === 'mensal' || activeTab.value === 'anual') {
            let start: string;
            let end: string;

            if (activeTab.value === 'hoje') {
                end = formatDateForApi(today);
                start = end;
            } else if (activeTab.value === 'ultimos7dias') {
                const past = new Date(today);
                past.setDate(today.getDate() - 6);
                start = formatDateForApi(past);
                end = formatDateForApi(today);
            } else if (activeTab.value === 'mensal') {
                const year = selectedYear.value;
                const monthIdx = selectedMonth.value - 1; // 0-based
                const first = new Date(year, monthIdx, 1);
                const last = new Date(year, monthIdx + 1, 0);
                start = formatDateForApi(first);
                end = formatDateForApi(last);
            } else {
                const first = new Date(selectedYear.value, 0, 1);
                const last = new Date(selectedYear.value, 11, 31);
                start = formatDateForApi(first);
                end = formatDateForApi(last);
            }

            response = await fetch(`/api/registros/financial-records/between-dates?page=${page}&only_deleted=${showRemoved.value ? 1 : 0}` , {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${auth.token.value}`,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({ start_date: start, end_date: end }),
            });
        } else {
            response = await fetch(`/api/registros/financial-records?page=${page}&only_deleted=${showRemoved.value ? 1 : 0}`, {
                headers: {
                    'Authorization': `Bearer ${auth.token.value}`,
                    'Accept': 'application/json',
                },
            });
        }

        if (!response.ok) {
            throw new Error('Erro ao buscar registros');
        }

        const json = await response.json();
        const incomingData = (json?.data) ?? [];
        console.log('Registros recebidos:', { showRemoved: showRemoved.value, activeTab: activeTab.value, incomingData, total: json.pagination?.total });
        registros.value = append ? [...registros.value, ...incomingData] : incomingData;
        pagination.value = json.pagination || {
            total: json.data?.length ?? 0,
            per_page: json.data?.length || 1,
            current_page: page,
        };
        resumoTotais.value = {
            entries: Number(json?.totals?.entries ?? 0),
            exits: Number(json?.totals?.exits ?? 0),
            commissions: Number(json?.totals?.commissions ?? 0),
            final_total: Number(json?.totals?.final_total ?? 0),
            entry_discounts: Number(json?.totals?.entry_discounts ?? 0),
        };
        currentPage.value = pagination.value.current_page || page;

        if (activeTab.value !== 'todos') {
            await fetchRangeData(activeTab.value as RangeKey);
        }
    } catch (error) {
        console.error('Erro ao carregar registros:', error);
    } finally {
        if (showPageLoading) {
            isLoading.value = false;
        } else if (append) {
            isLoadingMore.value = false;
        } else {
            isListLoading.value = false;
        }
    }
};

const loadMoreRegistros = async () => {
    if (activeTab.value !== 'todos') return;
    if (!hasMorePages.value) return;
    await fetchRegistros(currentPage.value + 1, false, true);
};

const setupInfiniteScroll = () => {
    if (typeof window === 'undefined') return;

    if (!infiniteScrollObserver) {
        infiniteScrollObserver = new IntersectionObserver(
            (entries) => {
                if (entries[0]?.isIntersecting) {
                    loadMoreRegistros();
                }
            },
            {
                root: null,
                rootMargin: '220px 0px',
                threshold: 0,
            }
        );
    }

    if (infiniteScrollTrigger.value) {
        infiniteScrollObserver.observe(infiniteScrollTrigger.value);
    }
};

const cleanupInfiniteScroll = () => {
    if (infiniteScrollObserver) {
        infiniteScrollObserver.disconnect();
    }
};

const changeTab = (tab: 'hoje' | 'ultimos7dias' | 'mensal' | 'anual' | 'todos') => {
    if (tab === 'todos' && isChartsView.value) {
        isChartsView.value = false;
    }

    activeTab.value = tab;
    currentPage.value = 1;
    fetchRegistros(1);
};

const handleDelete = (id: number) => {
    deletingRecordId.value = id;
};

const cancelDelete = () => {
    deletingRecordId.value = null;
};

const selectRecord = (id: number) => {
    selectedRecordId.value = id;
};

const shouldIgnoreGlobalKeydown = (event: KeyboardEvent) => {
    const target = event.target as HTMLElement | null;
    if (!target) return false;

    const tagName = target.tagName?.toLowerCase();
    return tagName === 'input'
        || tagName === 'textarea'
        || tagName === 'select'
        || Boolean(target.isContentEditable);
};

const handleGlobalDeleteShortcut = (event: KeyboardEvent) => {
    if (event.key !== 'Delete') {
        return;
    }

    if (showRemoved.value || isDeleteLoading.value || deletingRecordId.value !== null) {
        return;
    }

    if (shouldIgnoreGlobalKeydown(event)) {
        return;
    }

    if (!selectedRecordId.value) {
        return;
    }

    const exists = viewRegistros.value.some((record) => record.id === selectedRecordId.value);
    if (!exists) {
        return;
    }

    event.preventDefault();
    handleDelete(selectedRecordId.value);
};

const handleGlobalConfirmDeleteShortcut = (event: KeyboardEvent) => {
    const isEnterKey = event.key === 'Enter' || event.key === 'NumpadEnter';

    if (!isEnterKey) {
        return;
    }

    if (!deletingRecordId.value || isDeleteLoading.value) {
        return;
    }

    if (shouldIgnoreGlobalKeydown(event)) {
        return;
    }

    event.preventDefault();
    confirmDelete();
};

const handleGlobalListNavigationShortcut = (event: KeyboardEvent) => {
    const isArrowDown = event.key === 'ArrowDown';
    const isArrowUp = event.key === 'ArrowUp';
    const isEnterKey = event.key === 'Enter' || event.key === 'NumpadEnter';

    if (!isArrowDown && !isArrowUp && !isEnterKey) {
        return;
    }

    if (shouldIgnoreGlobalKeydown(event)) {
        return;
    }

    if (deletingRecordId.value !== null || hasOpenForm.value || isLoading.value || isListLoading.value) {
        return;
    }

    const records = paginatedRegistros.value;

    if (!records.length) {
        return;
    }

    if (isEnterKey) {
        if (showRemoved.value || !selectedRecordId.value) {
            return;
        }

        const exists = records.some((record) => record.id === selectedRecordId.value);
        if (!exists) {
            return;
        }

        event.preventDefault();
        openEditRecord(selectedRecordId.value);
        return;
    }

    event.preventDefault();

    const currentIndex = records.findIndex((record) => record.id === selectedRecordId.value);

    if (isArrowDown) {
        const nextIndex = currentIndex === -1 ? 0 : Math.min(currentIndex + 1, records.length - 1);
        selectedRecordId.value = records[nextIndex].id;
        return;
    }

    const previousIndex = currentIndex === -1 ? records.length - 1 : Math.max(currentIndex - 1, 0);
    selectedRecordId.value = records[previousIndex].id;
};

const confirmDelete = async () => {
    if (!deletingRecordId.value) return;

    const id = deletingRecordId.value;
    isDeleteLoading.value = true;
    try {
        const response = await fetch(`/api/registros/financial-records/${id}`, {
            method: 'DELETE',
            headers: {
                'Authorization': `Bearer ${auth.token.value}`,
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
        });

        if (!response.ok) {
            throw new Error('Erro ao remover registro');
        }

        if (showRemoved.value) {
            registros.value = registros.value.map(r =>
                r.id === id ? { ...r, deleted_at: new Date().toISOString() } : r
            );
        } else {
            registros.value = registros.value.filter(r => r.id !== id);
        }
    } catch (error) {
        console.error('Erro ao remover registro:', error);
    } finally {
        isDeleteLoading.value = false;
        deletingRecordId.value = null;
        if (!showRemoved.value) {
            selectedRecordId.value = null;
        }
    }
};

const handleRestore = async (id: number) => {
    if (isRestoreLoading.value) return;
    isRestoreLoading.value = true;
    restoringRecordId.value = id;
    try {
        const response = await fetch(`/api/registros/financial-records/${id}/restore`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${auth.token.value}`,
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
        });

        if (!response.ok) {
            const err = await response.text();
            throw new Error('Erro ao restaurar registro: ' + err);
        }

        registros.value = registros.value.filter(r => r.id !== id);
    } catch (error) {
        console.error(error);
    } finally {
        isRestoreLoading.value = false;
        restoringRecordId.value = null;
        if (selectedRecordId.value === id) {
            selectedRecordId.value = null;
        }
    }
};

const fetchOptions = async (includeDefaults = false) => {
    if (optionsLoaded.value && !includeDefaults) return;
    try {
        const headers: HeadersInit = {
            'Authorization': `Bearer ${auth.token.value}`,
            'Accept': 'application/json',
        };
        const query = includeDefaults ? '?include_defaults=1' : '';
        const [entryRes, exitRes, collabRes, serviceRes] = await Promise.all([
            fetch(`/api/personalizar/entry-types${query}`, { headers }),
            fetch(`/api/personalizar/exit-types${query}`, { headers }),
            fetch(`/api/personalizar/collaborators${query}`, { headers }),
            fetch('/api/personalizar/service-types', { headers }),
        ]);
        const [entryData, exitData, collabData, serviceData] = await Promise.all([
            entryRes.json(),
            exitRes.json(),
            collabRes.json(),
            serviceRes.json(),
        ]);
        tiposEntrada.value = entryData?.data ?? entryData ?? [];
        tiposSaida.value = exitData?.data ?? exitData ?? [];
        colaboradoresOptions.value = collabData?.data ?? collabData ?? [];
        tiposServico.value = serviceData?.data ?? serviceData ?? [];
        if (!includeDefaults) {
            optionsLoaded.value = true;
        }
    } catch (e) {
        console.error('Erro ao carregar opções:', e);
    }
};

const openImportPicker = async () => {
    await fetchOptions(true);
    showImportPanel.value = true;
    importError.value = '';
    importSuccess.value = '';
    importSummary.value = null;
    selectedImportFile.value = null;
    importDefaults.value = {
        default_entry_type_id: findDefaultOptionId(tiposEntrada.value),
        default_exit_type_id: findDefaultOptionId(tiposSaida.value),
        default_collaborator_id: findDefaultOptionId(colaboradoresOptions.value),
    };
    importFileInput.value?.click();
};

const openImportHelpModal = () => {
    importTemplateError.value = '';
    showImportHelpModal.value = true;
};

const closeImportHelpModal = () => {
    showImportHelpModal.value = false;
};

const downloadImportTemplate = async () => {
    importTemplateError.value = '';

    try {
        const downloadResponse = await axios.get('/api/registros/template/download', {
            responseType: 'blob',
            withCredentials: true,
            headers: {
                'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            },
        });

        const blob = new Blob([downloadResponse.data], {
            type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        });
        const downloadUrl = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = downloadUrl;
        link.download = 'template-importacao-entradas-saidas.xlsx';
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(downloadUrl);
    } catch (error) {
        importTemplateError.value = 'Não foi possível baixar o template. Verifique seu acesso e tente novamente.';
        console.error('Erro ao baixar template:', error);
    }
};

const exportPeriodToExcel = async () => {
    if (!canExportPeriodData.value || isExportLoading.value) {
        return;
    }

    isExportLoading.value = true;
    exportError.value = '';

    try {
        const { start, end } = makeRange(chartRangeKey.value);

        const response = await axios.get('/api/registros/financial-records/export', {
            params: {
                start_date: start,
                end_date: end,
                period: activeTab.value,
            },
            responseType: 'blob',
            withCredentials: true,
            headers: {
                Authorization: `Bearer ${auth.token.value}`,
                Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
        });

        const rawContentType = response.headers['content-type'];
        const contentType = Array.isArray(rawContentType)
            ? rawContentType.join(';')
            : String(rawContentType ?? '');
        if (contentType.includes('application/json')) {
            const text = await new Response(response.data).text();
            const json = JSON.parse(text);
            throw new Error(json?.message || 'Erro ao exportar dados.');
        }

        const blob = new Blob([response.data], {
            type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        });

        const suggestedName =
            response.headers['content-disposition']
                ?.split('filename=')[1]
                ?.replace(/"/g, '')
            || `registros-${activeTab.value}.xlsx`;

        const downloadUrl = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = downloadUrl;
        link.download = suggestedName;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(downloadUrl);
    } catch (error: any) {
        console.error('Erro ao exportar dados:', error);
        exportError.value = error?.message || 'Nao foi possivel exportar os dados. Tente novamente.';
    } finally {
        isExportLoading.value = false;
    }
};

const onImportFileSelected = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0] ?? null;
    selectedImportFile.value = file;
    importError.value = '';
    importSuccess.value = '';
};

const cancelImport = () => {
    showImportPanel.value = false;
    selectedImportFile.value = null;
    importError.value = '';
    importSuccess.value = '';
    importSummary.value = null;
    importDefaults.value = {
        default_entry_type_id: null,
        default_exit_type_id: null,
        default_collaborator_id: null,
    };
    if (importFileInput.value) {
        importFileInput.value.value = '';
    }
};

const clearImportFile = () => {
    selectedImportFile.value = null;
    importError.value = '';
    importSuccess.value = '';
    importSummary.value = null;
    if (importFileInput.value) {
        importFileInput.value.value = '';
    }
};

const clearSearchQuery = async () => {
    if (!searchQuery.value) {
        return;
    }

    searchQuery.value = '';
    await nextTick();
    searchInputRef.value?.focus();
};

const applyFinancialTypeFilter = (type: 'entrada' | 'saida' | 'comissao') => {
    financialTypeFilter.value = type;
    selectedRecordId.value = null;
};

const clearFinancialTypeFilter = () => {
    financialTypeFilter.value = null;
    selectedRecordId.value = null;
};

const handleEntryAmountInput = (event: Event) => {
    const target = event.target as HTMLInputElement;
    entryForm.value.amount = formatCurrencyInput(target.value);
};

const handleExitAmountInput = (event: Event) => {
    const target = event.target as HTMLInputElement;
    exitForm.value.amount = formatCurrencyInput(target.value);
};

const handleEntryDiscountInput = (event: Event) => {
    const target = event.target as HTMLInputElement;
    entryForm.value.discount = formatCurrencyInput(target.value);
};

const handleExitDiscountInput = (event: Event) => {
    const target = event.target as HTMLInputElement;
    exitForm.value.discount = formatCurrencyInput(target.value);
};

const captureScrollPosition = () => {
    if (typeof window === 'undefined') {
        return { x: 0, y: 0 };
    }

    return {
        x: window.scrollX,
        y: window.scrollY,
    };
};

const restoreScrollPosition = async (position: { x: number; y: number }) => {
    if (typeof window === 'undefined') {
        return;
    }

    await nextTick();
    window.requestAnimationFrame(() => {
        window.scrollTo({
            left: position.x,
            top: position.y,
            behavior: 'auto',
        });
    });
};

const confirmImport = async () => {
    if (!selectedImportFile.value) {
        importError.value = 'Selecione um arquivo Excel para continuar.';
        return;
    }

    if (!importDefaults.value.default_entry_type_id || !importDefaults.value.default_exit_type_id || !importDefaults.value.default_collaborator_id) {
        importError.value = 'Selecione os valores padrão para entrada, saída e comissão.';
        return;
    }

    isImportLoading.value = true;
    importError.value = '';
    importSuccess.value = '';
    importSummary.value = null;

    try {
        const formData = new FormData();
        formData.append('file', selectedImportFile.value);
        formData.append('default_entry_type_id', String(importDefaults.value.default_entry_type_id));
        formData.append('default_exit_type_id', String(importDefaults.value.default_exit_type_id));
        formData.append('default_collaborator_id', String(importDefaults.value.default_collaborator_id));

        const response = await fetch('/api/import', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${auth.token.value}`,
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: formData,
        });

        const json = await response.json();
        if (!response.ok) {
            // Mostrar erro com detalhes
            let errorMessage = json?.message || 'Erro ao importar o arquivo.';
            if (json?.error) {
                errorMessage += ` (${json.error})`;
            }
            importError.value = errorMessage;
            return;
        }

        // Sucesso - mostrar mensagem e resumo
        importSuccess.value = json?.message || 'Importação concluída com sucesso.';
        importSummary.value = json?.summary ?? null;
        
        // Aguardar 2 segundos e recarregar registros
        setTimeout(async () => {
            await fetchRegistros(1, false, false);
        }, 2000);
    } catch (error) {
        console.error(error);
        importError.value = 'Não foi possível concluir a importação. Verifique sua conexão e tente novamente.';
    } finally {
        isImportLoading.value = false;
    }
};

const closeAllForms = (options?: { clearCreateDrafts?: boolean }) => {
    const shouldClearCreateDrafts = options?.clearCreateDrafts === true;

    if (shouldClearCreateDrafts) {
        if (showNewEntryInline.value && editingRegistroId.value === null) {
            clearEntryCreateDraft();
        }

        if (showNewExitInline.value && editingRegistroId.value === null) {
            clearExitCreateDraft();
        }
    }

    showNewEntryInline.value = false;
    showNewExitInline.value = false;
    editingRegistroId.value = null;
    editingRegistroType.value = null;
    showEntryCalculationTooltip.value = false;
    showExitCalculationTooltip.value = false;
    entryForm.value = createEmptyEntryForm();
    exitForm.value = createEmptyExitForm();
    entryFormErrors.value = {};
    exitFormErrors.value = {};
};

const cancelCreateForms = () => {
    closeAllForms({ clearCreateDrafts: true });
};

const handleGlobalSaveShortcut = (event: KeyboardEvent) => {
    const isSaveShortcut = event.ctrlKey && event.key.toLowerCase() === 's';

    if (!isSaveShortcut) {
        return;
    }

    if (showNewEntryInline.value || editingRegistroType.value === 'entrada') {
        event.preventDefault();
        saveEntry();
        return;
    }

    if (showNewExitInline.value || editingRegistroType.value === 'saida') {
        event.preventDefault();
        saveExit();
    }
};

const handleGlobalEnterToSave = (event: KeyboardEvent) => {
    const isEnterKey = event.key === 'Enter' || event.key === 'NumpadEnter';

    if (!isEnterKey) {
        return;
    }

    if (event.ctrlKey || event.altKey || event.metaKey) {
        return;
    }

    if (deletingRecordId.value !== null || isDeleteLoading.value) {
        return;
    }

    const target = event.target as HTMLElement | null;
    const tagName = target?.tagName?.toLowerCase();

    // Preserve native behavior for multiline/content-editable controls.
    if (tagName === 'textarea' || Boolean(target?.isContentEditable)) {
        return;
    }

    if (showNewEntryInline.value || editingRegistroType.value === 'entrada') {
        event.preventDefault();
        saveEntry();
        return;
    }

    if (showNewExitInline.value || editingRegistroType.value === 'saida') {
        event.preventDefault();
        saveExit();
    }
};

const handleGlobalCreateShortcut = async (event: KeyboardEvent) => {
    if (shouldIgnoreGlobalKeydown(event)) {
        return;
    }

    if (!event.shiftKey || event.ctrlKey || event.altKey || event.metaKey) {
        return;
    }

    if (showRemoved.value || isChartsView.value || isLoading.value || isListLoading.value) {
        return;
    }

    const key = event.key.toLowerCase();

    if (key === 'e') {
        event.preventDefault();
        await openCreateEntry();
        return;
    }

    if (key === 's') {
        event.preventDefault();
        await openCreateExit();
    }
};

const handleGlobalEscapeToCancel = (event: KeyboardEvent) => {
    if (event.key !== 'Escape') {
        return;
    }

    sanitizeFilterState();

    const target = event.target as HTMLElement | null;
    const isSearchField = target === searchInputRef.value;

    if (isSearchField && !hasOpenForm.value) {
        searchQuery.value = normalizeNullableString(searchQuery.value);
        return;
    }

    const isCreateFormActive = (showNewEntryInline.value || showNewExitInline.value) && editingRegistroId.value === null;
    const isEditFormActive = editingRegistroId.value !== null;

    if (!isCreateFormActive && !isEditFormActive) {
        return;
    }

    event.preventDefault();

    if (isCreateFormActive) {
        cancelCreateForms();
        return;
    }

    closeAllForms();
};

const openCreateEntry = async () => {
    await openCreateForm('entry');
};

const openCreateExit = async () => {
    await openCreateForm('exit');
};

const openEditRecord = async (id: number) => {
    const raw = registros.value.find((r: any) => r.id === id);
    if (!raw) return;
    closeAllForms();
    await fetchOptions();
    editingRegistroId.value = id;
    const isEntry = raw.type === 'entry';
    editingRegistroType.value = isEntry ? 'entrada' : 'saida';
    if (isEntry) {
        const [datePart] = (raw.date || '').split('T');
        entryForm.value = {
            entry_type_id: raw.entry_type_id ?? null,
            service_type_id: raw.service_type_id ?? null,
            collaborator_id: raw.collaborator_id ?? null,
            date: datePart || raw.date,
            amount: formatCurrencyInput(raw.amount || 0),
            quantity: raw.quantity || 1,
            discount: formatCurrencyInput(raw.discount || 0),
            description: raw.description || '',
        };
    } else {
        const [datePart] = (raw.date || '').split('T');
        exitForm.value = {
            exit_type_id: raw.exit_type_id ?? null,
            collaborator_id: raw.collaborator_id ?? null,
            date: datePart || raw.date,
            amount: formatCurrencyInput(raw.amount || 0),
            quantity: raw.quantity || 1,
            discount: formatCurrencyInput(raw.discount || 0),
            description: raw.description || '',
        };
    }
};

const saveEntry = async () => {
    if (isEntryLoading.value) return;
    entryFormErrors.value = {};
    const scrollPosition = captureScrollPosition();
    const payload = {
        date: entryForm.value.date,
        amount: parseCurrencyInput(entryForm.value.amount),
        quantity: entryForm.value.quantity,
        discount: parseCurrencyInput(entryForm.value.discount),
        description: entryForm.value.description,
        type: 'entry',
        entry_type_id: entryForm.value.entry_type_id,
        service_type_id: entryForm.value.service_type_id,
        collaborator_id: entryForm.value.collaborator_id,
    };
    const isEdit = editingRegistroId.value !== null;
    const url = isEdit
        ? `/api/registros/financial-records/${editingRegistroId.value}`
        : '/api/registros/financial-records';
    const method = isEdit ? 'PUT' : 'POST';
    isEntryLoading.value = true;
    try {
        const response = await fetch(url, {
            method,
            headers: {
                'Authorization': `Bearer ${auth.token.value}`,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify(payload),
        });
        if (!response.ok) {
            let errData: any = null;
            try { errData = await response.json(); } catch {}
            entryFormErrors.value = errData?.errors || { _: [errData?.message || 'Erro ao salvar entrada'] };
            return;
        }

        if (!isEdit) {
            clearEntryCreateDraft();
        }

        await fetchRegistros(currentPage.value);
        closeAllForms();
        await restoreScrollPosition(scrollPosition);
    } catch (error) {
        console.error(error);
    } finally {
        isEntryLoading.value = false;
    }
};

const saveExit = async () => {
    if (isExitLoading.value) return;
    exitFormErrors.value = {};
    const scrollPosition = captureScrollPosition();
    const payload = {
        date: exitForm.value.date,
        amount: parseCurrencyInput(exitForm.value.amount),
        quantity: exitForm.value.quantity,
        discount: parseCurrencyInput(exitForm.value.discount),
        description: exitForm.value.description,
        type: 'exit',
        exit_type_id: exitForm.value.exit_type_id,
        collaborator_id: exitForm.value.collaborator_id,
    };
    const isEdit = editingRegistroId.value !== null;
    const url = isEdit
        ? `/api/registros/financial-records/${editingRegistroId.value}`
        : '/api/registros/financial-records';
    const method = isEdit ? 'PUT' : 'POST';
    isExitLoading.value = true;
    try {
        const response = await fetch(url, {
            method,
            headers: {
                'Authorization': `Bearer ${auth.token.value}`,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify(payload),
        });
        if (!response.ok) {
            let errData: any = null;
            try { errData = await response.json(); } catch {}
            exitFormErrors.value = errData?.errors || { _: [errData?.message || 'Erro ao salvar saída'] };
            return;
        }

        if (!isEdit) {
            clearExitCreateDraft();
        }

        await fetchRegistros(currentPage.value);
        closeAllForms();
        await restoreScrollPosition(scrollPosition);
    } catch (error) {
        console.error(error);
    } finally {
        isExitLoading.value = false;
    }
};

const handleVerRemovidos = () => {
    showRemoved.value = !showRemoved.value;
    selectedRecordId.value = null;
    financialTypeFilter.value = null;
    currentPage.value = 1;
    searchQuery.value = '';
    fetchRegistros(1);
};

const goToPage = (page: number) => {
    if (activeTab.value === 'todos') return;
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
        fetchRegistros(page);
    }
};

watch(infiniteScrollTrigger, (newEl, oldEl) => {
    if (!infiniteScrollObserver) return;
    if (oldEl) {
        infiniteScrollObserver.unobserve(oldEl);
    }
    if (newEl) {
        infiniteScrollObserver.observe(newEl);
    }
});

watch(
    [activeTab, selectedMonth, selectedYear, searchQuery, financialTypeFilter, sortKey, sortDir, showRemoved, currentPage, isChartsView],
    () => {
        syncFiltersToQueryString();
    }
);

watch(
    () => entryForm.value,
    () => {
        if (showNewEntryInline.value && editingRegistroId.value === null) {
            persistEntryCreateDraft();
        }
    },
    { deep: true }
);

watch(
    () => exitForm.value,
    () => {
        if (showNewExitInline.value && editingRegistroId.value === null) {
            persistExitCreateDraft();
        }
    },
    { deep: true }
);

onMounted(async () => {
    if (typeof window !== 'undefined') {
        window.addEventListener('keydown', handleGlobalSaveShortcut);
        window.addEventListener('keydown', handleGlobalEnterToSave);
        window.addEventListener('keydown', handleGlobalCreateShortcut);
        window.addEventListener('keydown', handleGlobalEscapeToCancel);
        window.addEventListener('keydown', handleGlobalDeleteShortcut);
        window.addEventListener('keydown', handleGlobalConfirmDeleteShortcut);
        window.addEventListener('keydown', handleGlobalListNavigationShortcut);
        window.addEventListener('mobile-nav-visibility-changed', handleMobileNavVisibilityChanged);

        const savedMobileNavState = localStorage.getItem('mobileNavVisible');
        if (savedMobileNavState !== null) {
            isMobileNavVisible.value = savedMobileNavState === 'true';
        }
    }

    if (!auth.isAuthenticated.value) {
        window.location.href = '/login';
        return;
    }

    showRegistrationSuccessMessageIfNeeded();
    
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

    applyFiltersFromQueryString();

    await fetchRegistros(currentPage.value, true);

    syncFiltersToQueryString();

    setupInfiniteScroll();

    await nextTick();
    searchInputRef.value?.focus();
});

onBeforeUnmount(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('keydown', handleGlobalSaveShortcut);
        window.removeEventListener('keydown', handleGlobalEnterToSave);
        window.removeEventListener('keydown', handleGlobalCreateShortcut);
        window.removeEventListener('keydown', handleGlobalEscapeToCancel);
        window.removeEventListener('keydown', handleGlobalDeleteShortcut);
        window.removeEventListener('keydown', handleGlobalConfirmDeleteShortcut);
        window.removeEventListener('keydown', handleGlobalListNavigationShortcut);
        window.removeEventListener('mobile-nav-visibility-changed', handleMobileNavVisibilityChanged);
    }

    cleanupInfiniteScroll();
});
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-amber-50 to-orange-100 flex overflow-x-hidden">
        <Head title="Registros - MEU NEGÓCIO" />

        <!-- Sidebar -->
        <Sidebar active-route="registros" />

        <!-- Main Content -->
        <div class="flex-1 min-w-0 lg:ml-64 transition-all duration-300 overflow-x-hidden flex flex-col">
            <FixedPageHeaderTitle
                title="Registros"
                subtitle="Gerencie suas entradas, saídas e comissões"
            />

            <!-- Main Content -->
            <main class="w-full min-w-0 max-w-full sm:max-w-6xl mx-auto px-3 sm:px-6 lg:px-8 py-6 sm:py-8 flex-1 flex flex-col overflow-x-hidden">
                <AppInlineNotice
                    v-if="registrationSuccessMessage"
                    class="mb-6"
                    variant="success"
                    title="Conta criada"
                    :message="registrationSuccessMessage"
                    @dismiss="dismissRegistrationSuccessMessage"
                />

                <!-- Tabs -->
                <div class="mb-8 -mx-4 px-4 sm:-mx-0 sm:px-0 overflow-x-auto sm:overflow-visible scroll-smooth scrollbar-hide">
                    <div class="flex min-w-max justify-start sm:justify-center gap-2 pb-2 sm:pb-1">
                    <button
                        @click="changeTab('hoje')"
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
                        @click="changeTab('ultimos7dias')"
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
                        @click="changeTab('mensal')"
                        :class="[
                            'px-6 py-2 rounded-full font-medium transition-all',
                            activeTab === 'mensal' 
                                ? 'bg-orange-500 text-white shadow-lg' 
                                : 'bg-orange-100 text-orange-600 hover:bg-orange-200'
                        ]"
                    >
                        Mensal
                    </button>
                    <button
                        @click="changeTab('anual')"
                        :class="[
                            'px-6 py-2 rounded-full font-medium transition-all',
                            activeTab === 'anual'
                                ? 'bg-orange-500 text-white shadow-lg'
                                : 'bg-orange-100 text-orange-600 hover:bg-orange-200'
                        ]"
                    >
                        Anual
                    </button>
                    <button
                        v-if="!isChartsView"
                        @click="changeTab('todos')"
                        :class="[
                            'px-6 py-2 rounded-full font-medium transition-all',
                            activeTab === 'todos'
                                ? 'bg-orange-500 text-white shadow-lg'
                                : 'bg-orange-100 text-orange-600 hover:bg-orange-200'
                        ]"
                    >
                        Todos
                    </button>
                    </div>
                </div>

                <!-- Period Selectors -->
                <div v-if="activeTab === 'mensal' || activeTab === 'anual'" class="mb-3 -mx-4 px-4 sm:-mx-0 sm:px-0 overflow-x-auto sm:overflow-visible scrollbar-hide">
                    <div class="flex flex-wrap gap-2 pb-2 sm:pb-1 justify-center items-center">
                        <select
                            v-if="activeTab === 'mensal'"
                            v-model.number="selectedMonth"
                            @change="(currentPage = 1, fetchRegistros(1))"
                            class="px-3 py-2 border border-orange-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent bg-white text-sm"
                        >
                            <option v-for="m in monthOptions" :key="m.value" :value="m.value">
                                {{ m.label }}
                            </option>
                        </select>
                        <select
                            v-model.number="selectedYear"
                            @change="(currentPage = 1, fetchRegistros(1))"
                            class="px-3 py-2 border border-orange-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent bg-white text-sm"
                        >
                            <option v-for="y in yearOptions" :key="y" :value="y">
                                {{ y }}
                            </option>
                        </select>
                        <button
                            v-if="canExportPeriodData"
                            type="button"
                            @click="exportPeriodToExcel"
                            :disabled="isExportLoading"
                            class="inline-flex items-center gap-2 px-3 py-2 border border-orange-300 rounded-lg bg-white text-orange-700 text-sm font-medium hover:bg-orange-50 transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                        >
                            <svg v-if="!isExportLoading" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 16V4m0 12l-4-4m4 4l4-4M4 20h16" />
                            </svg>
                            <svg v-else class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <span>{{ isExportLoading ? 'Exportando...' : 'Exportar Excel' }}</span>
                        </button>
                    </div>
                    <p v-if="exportError" class="mt-1 text-center text-xs text-red-600">{{ exportError }}</p>
                </div>

                <!-- Content Card -->
                <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 w-full max-w-full overflow-hidden">
                    <!-- Removed Items Mode Banner -->
                    <div v-if="showRemoved" class="mb-6 p-3 sm:p-4 border-l-4 border-red-500 bg-red-50 rounded-lg">
                        <div class="flex items-start gap-3">
                            <svg class="w-5 h-5 sm:w-6 sm:h-6 text-red-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4v2m0-6a4 4 0 110-8 4 4 0 010 8zm0 6a6 6 0 110-12 6 6 0 010 12z" />
                            </svg>
                            <div class="flex-1">
                                <p class="text-sm font-semibold text-red-800">Visualizando itens removidos</p>
                                <p class="text-xs text-red-700 mt-1">Você está vendo apenas registros deletados. Somente restauração é permitida neste modo.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="mb-4 flex flex-col sm:flex-row gap-2 sm:gap-4 sm:justify-end">
                        <input
                            ref="importFileInput"
                            type="file"
                            accept=".xlsx,.xls"
                            class="hidden"
                            @change="onImportFileSelected"
                        />

                        <div class="self-end sm:self-auto">
                            <!-- Botão de troca para sair do modo gráfico -->
                            <button
                                v-if="isChartsView"
                                @click="toggleChartsView"
                                class="w-full sm:w-auto px-4 py-2 h-10 inline-flex items-center justify-center rounded-lg border-2 border-orange-200 text-orange-600 hover:bg-orange-50 transition-colors"
                                title="Voltar para listagem"
                                aria-label="Voltar para listagem"
                            >
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m10 0v12m0 0l4-4m-4 4l-4-4" />
                                </svg>
                                <span class="ml-2 text-sm">Voltar</span>
                            </button>

                            <!-- Menu de 3 pontos para outras opções (normal mode) -->
                            <DropdownMenu v-else-if="!showRemoved">
                                <DropdownMenuTrigger :as-child="true">
                                    <button
                                        type="button"
                                        class="w-full sm:w-auto px-4 py-2 h-10 inline-flex items-center justify-center gap-2 rounded-lg border-2 border-orange-200 text-orange-600 hover:bg-orange-50 transition-colors text-sm"
                                        aria-label="Abrir configurações"
                                    >
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                            <circle cx="12" cy="5" r="1.75" />
                                            <circle cx="12" cy="12" r="1.75" />
                                            <circle cx="12" cy="19" r="1.75" />
                                        </svg>
                                        <span>Opções</span>
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" class="w-48 sm:w-56">
                                    <DropdownMenuItem :as-child="true">
                                        <button
                                            type="button"
                                            class="w-full text-left inline-flex items-center justify-between gap-2"
                                            @click="togglePrivacy"
                                        >
                                            <span>{{ privacyOptionLabel }}</span>
                                            <EyeOff v-if="isPrivacyMode" class="h-4 w-4 text-orange-500" />
                                            <Eye v-else class="h-4 w-4 text-gray-500" />
                                        </button>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem :as-child="true">
                                        <button
                                            type="button"
                                            class="w-full text-left"
                                            @click="openImportPicker"
                                        >
                                            Importar Entradas e Saídas
                                        </button>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem :as-child="true">
                                        <button
                                            type="button"
                                            class="w-full text-left"
                                            @click="handleVerRemovidos"
                                        >
                                            Ver removidos
                                        </button>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem v-if="activeTab !== 'todos'" :as-child="true">
                                        <button
                                            type="button"
                                            class="w-full text-left"
                                            @click="toggleChartsView"
                                        >
                                            Visualizar gráficos
                                        </button>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>

                            <!-- Simplified button for removed mode -->
                            <button v-else
                                @click="handleVerRemovidos"
                                class="w-full sm:w-auto px-4 py-2 h-10 inline-flex items-center justify-center gap-2 rounded-lg border-2 border-red-400 text-red-600 hover:bg-red-50 transition-colors text-sm"
                                title="Voltar para itens ativos"
                                aria-label="Voltar para itens ativos"
                            >
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                <span>Ver ativos</span>
                            </button>
                        </div>
                    </div>

                    <!-- Import Panel (hidden when viewing removed items) -->
                    <div v-if="showImportPanel && !showRemoved" class="mb-4 border-2 border-orange-200 rounded-lg p-3 sm:p-4 bg-orange-50/60">
                        <div class="flex items-center justify-between gap-3 mb-3 sm:mb-4">
                            <div class="flex items-center gap-2 min-w-0">
                                <svg class="w-5 h-5 sm:w-6 sm:h-6 text-orange-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <h3 class="text-xs sm:text-sm font-semibold text-orange-700">Importar Entradas e Saídas</h3>
                            </div>
                            <button
                                type="button"
                                @click="openImportHelpModal"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-orange-300 bg-white text-orange-700 transition-colors hover:bg-orange-100"
                                aria-label="Abrir instruções de importação"
                                title="Ver instruções"
                            >
                                <Info class="h-4 w-4" />
                            </button>
                        </div>

                        <div v-if="selectedImportFile" class="mb-3 p-2 sm:p-3 bg-white border border-orange-200 rounded-lg flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2 min-w-0">
                                <svg class="w-5 h-5 sm:w-6 sm:h-6 text-orange-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <div class="min-w-0">
                                    <p class="text-xs sm:text-sm font-medium text-gray-700 truncate">{{ selectedImportFile.name }}</p>
                                    <p class="text-xs text-gray-500">{{ formatFileSize(selectedImportFile.size) }}</p>
                                </div>
                            </div>
                            <button @click="clearImportFile" class="px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 rounded-lg transition-colors flex-shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                            </button>
                        </div>

                        <div v-else class="mb-3 p-3 sm:p-4 bg-white border-2 border-dashed border-orange-300 rounded-lg text-center">
                            <p class="text-xs sm:text-sm text-gray-600">Nenhum arquivo selecionado</p>
                            <button @click="openImportPicker" class="mt-1 text-xs text-orange-600 hover:text-orange-700 font-medium underline">
                                Escolher Excel
                            </button>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 sm:gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Tipo de Entrada</label>
                                <select v-model="importDefaults.default_entry_type_id" class="w-full px-2 sm:px-3 py-1 sm:py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400 bg-white text-xs sm:text-sm">
                                    <option :value="null">Selecione</option>
                                    <option v-for="t in tiposEntrada" :key="t.id" :value="t.id">{{ t.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Tipo de Saída</label>
                                <select v-model="importDefaults.default_exit_type_id" class="w-full px-2 sm:px-3 py-1 sm:py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400 bg-white text-xs sm:text-sm">
                                    <option :value="null">Selecione</option>
                                    <option v-for="t in tiposSaida" :key="t.id" :value="t.id">{{ t.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Colaborador</label>
                                <select v-model="importDefaults.default_collaborator_id" class="w-full px-2 sm:px-3 py-1 sm:py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400 bg-white text-xs sm:text-sm">
                                    <option :value="null">Selecione</option>
                                    <option v-for="c in colaboradoresOptions" :key="c.id" :value="c.id">{{ c.name }}</option>
                                </select>
                            </div>
                        </div>

                        <!-- Loading State -->
                        <div v-if="isImportLoading" class="mt-3 p-3 sm:p-4 bg-white border border-blue-200 rounded-lg text-center">
                            <div class="inline-block mb-2">
                                <svg class="animate-spin h-6 sm:h-8 w-6 sm:w-8 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                            <p class="text-xs sm:text-sm font-medium text-gray-700">Processando arquivo...</p>
                            <p class="text-xs text-gray-500 mt-1">Pode levar alguns segundos</p>
                        </div>

                        <!-- Error State -->
                        <div v-if="importError" class="mt-3 p-3 sm:p-4 bg-red-50 border border-red-300 rounded-lg">
                            <div class="flex items-center gap-2 mb-1 sm:mb-2">
                                <svg class="w-4 h-4 sm:w-5 sm:h-5 text-red-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                                <p class="text-xs sm:text-sm font-medium text-red-700">Erro na importação</p>
                            </div>
                            <p class="text-xs sm:text-sm text-red-600">{{ importError }}</p>
                        </div>

                        <!-- Success State -->
                        <div v-if="importSuccess && !isImportLoading" class="mt-3 p-3 sm:p-4 bg-orange-50 border border-orange-300 rounded-lg">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 sm:w-5 sm:h-5 text-orange-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                <p class="text-xs sm:text-sm font-medium text-orange-700">{{ importSuccess }}</p>
                            </div>
                        </div>

                        <!-- Summary Cards -->
                        <div v-if="importSummary && !isImportLoading" class="mt-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-2">
                            <div class="bg-white border border-orange-200 rounded-lg p-2 text-center">
                                <p class="text-lg sm:text-xl font-bold text-orange-600">{{ importSummary.sheets_processed }}</p>
                                <p class="text-xs text-gray-600 mt-0.5">Abas</p>
                            </div>
                            <div class="bg-white border border-blue-200 rounded-lg p-2 text-center">
                                <p class="text-lg sm:text-xl font-bold text-blue-600">{{ importSummary.rows_processed }}</p>
                                <p class="text-xs text-gray-600 mt-0.5">Linhas</p>
                            </div>
                            <div class="bg-white border border-yellow-200 rounded-lg p-2 text-center">
                                <p class="text-lg sm:text-xl font-bold text-yellow-600">{{ importSummary.entries_created }}</p>
                                <p class="text-xs text-gray-600 mt-0.5">Entradas</p>
                            </div>
                            <div class="bg-white border border-orange-300 rounded-lg p-2 text-center">
                                <p class="text-lg sm:text-xl font-bold text-orange-700">{{ importSummary.exits_created }}</p>
                                <p class="text-xs text-gray-600 mt-0.5">Saídas</p>
                            </div>
                            <div class="bg-white border border-purple-200 rounded-lg p-2 text-center">
                                <p class="text-lg sm:text-xl font-bold text-purple-600">{{ importSummary.commissions_created }}</p>
                                <p class="text-xs text-gray-600 mt-0.5">Comissões</p>
                            </div>
                        </div>

                        <div v-if="!importSuccess || importError" class="flex flex-col sm:flex-row justify-end gap-2 mt-3 sm:mt-4">
                            <button @click="cancelImport" :disabled="isImportLoading" class="px-3 sm:px-4 py-2 text-xs sm:text-sm text-gray-700 hover:bg-gray-100 rounded-lg transition-colors disabled:opacity-50">
                                Cancelar
                            </button>
                            <button @click="confirmImport" :disabled="isImportLoading || !selectedImportFile" class="px-4 sm:px-6 py-2 text-xs sm:text-sm font-medium text-white bg-gradient-to-r from-orange-500 to-amber-500 rounded-lg hover:shadow-md transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                                <svg v-if="!isImportLoading" class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                <span v-if="!isImportLoading">Confirmar</span>
                                <span v-else class="flex items-center gap-1">
                                    <svg class="animate-spin h-3 w-3" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Importando...
                                </span>
                            </button>
                        </div>
                        <div v-if="importSuccess && !importError" class="flex justify-end mt-3 sm:mt-4">
                            <button @click="cancelImport" class="px-4 sm:px-6 py-2 text-xs sm:text-sm font-medium text-white bg-gradient-to-r from-orange-500 to-amber-500 rounded-lg hover:shadow-md transition-all flex items-center justify-center gap-2">
                                <span>Fechar importação</span>
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>

                        <Dialog :open="showImportHelpModal" @update:open="showImportHelpModal = $event">
                            <DialogContent class="sm:max-w-2xl">
                                <DialogHeader>
                                    <DialogTitle>Instruções para importação</DialogTitle>
                                    <DialogDescription>
                                        Siga estas orientações para evitar falhas durante o processamento do arquivo Excel.
                                    </DialogDescription>
                                </DialogHeader>

                                <div class="space-y-3 text-sm text-gray-700">
                                    <button
                                        type="button"
                                        @click="downloadImportTemplate"
                                        class="inline-flex items-center gap-2 rounded-lg border border-orange-300 bg-white px-3 py-2 text-sm font-medium text-orange-700 transition-colors hover:bg-orange-50"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v12m0 0l-4-4m4 4l4-4m-7 8h6" />
                                        </svg>
                                        Baixar template
                                    </button>

                                    <p v-if="importTemplateError" class="text-sm text-red-600">
                                        {{ importTemplateError }}
                                    </p>

                                    <ul class="list-disc space-y-2 pl-5">
                                        <li>Use um arquivo Excel nos formatos <strong>.xlsx</strong> ou <strong>.xls</strong>.</li>
                                        <li>Confira se cada aba segue a estrutura com 6 colunas: Data, Total de Entrada, Total de Comissões, Pagamentos (Saída), Total Final e motivo da saída.</li>
                                        <li>Garanta que a coluna A tenha datas no formato <strong>d/m/Y</strong>.</li>
                                        <li>Não inclua linhas vazias antes dos dados e mantenha a linha final com <strong>Total</strong> na coluna A.</li>
                                        <li>Valores iguais a zero, vazios ou inválidos nas colunas B, C e D serão ignorados.</li>
                                        <li>Se uma linha tiver apenas comissão, a comissão será importada normalmente.</li>
                                        <li>Quando não houver identificação específica, revise os valores padrão selecionados antes de confirmar.</li>
                                        <li>Se houver saída, a coluna F será usada como descrição do registro.</li>
                                        <li>O arquivo pode conter várias abas e todas serão processadas.</li>
                                    </ul>

                                    <div class="rounded-lg border border-orange-200 bg-orange-50 p-3 text-orange-900">
                                        <p class="font-medium">Dica prática</p>
                                        <p class="mt-1">Antes de importar, confirme se os itens "Não definido" estão selecionados para entrada, saída e colaborador, caso a planilha não informe esses dados.</p>
                                    </div>
                                </div>
                            </DialogContent>
                        </Dialog>

                    </div>

                    <Dialog :open="showUnsavedCreateChangesModal" @update:open="handleUnsavedCreateChangesModalOpenChange">
                        <DialogContent class="sm:max-w-md">
                            <DialogHeader>
                                <DialogTitle>Descartar alterações?</DialogTitle>
                                <DialogDescription>
                                    Você tem dados não salvos no formulário atual. Se continuar, essas alterações serão perdidas.
                                </DialogDescription>
                            </DialogHeader>

                            <div class="mt-2 flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                                <button
                                    type="button"
                                    @click="cancelUnsavedCreateChanges"
                                    class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="button"
                                    @click="confirmUnsavedCreateChanges"
                                    class="px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-orange-500 to-amber-500 rounded-lg hover:shadow-md transition-all"
                                >
                                    Continuar e descartar
                                </button>
                            </div>
                        </DialogContent>
                    </Dialog>

                    <template v-if="!isChartsView">
                    <!-- Summary (hidden when viewing removed items) -->
                    <div v-if="!showRemoved" class="mb-4 space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-2">
                            <div class="rounded-lg border border-slate-200 bg-white p-3 text-center">
                                <p class="text-xs font-medium text-slate-500">Total</p>
                                <p class="mt-1 text-lg font-bold text-slate-800">{{ totalRegistros }}</p>
                            </div>
                            <div class="rounded-lg border border-yellow-200 bg-yellow-50/70 p-3 text-center">
                                <p class="text-xs font-medium text-yellow-700">Entradas</p>
                                <p class="mt-1 text-lg font-bold text-yellow-700">{{ formatCurrency(totalEntradas) }}</p>
                            </div>
                            <div class="rounded-lg border border-amber-200 bg-amber-50/70 p-3 text-center">
                                <p class="text-xs font-medium text-amber-700">Descontos</p>
                                <p class="mt-1 text-lg font-bold text-amber-700">{{ formatCurrency(totalDescontosEntradas) }}</p>
                            </div>
                            <div class="rounded-lg border border-orange-200 bg-orange-50/70 p-3 text-center">
                                <p class="text-xs font-medium text-orange-700">Saídas</p>
                                <p class="mt-1 text-lg font-bold text-orange-700">{{ formatCurrency(totalSaidas) }}</p>
                            </div>
                            <div class="rounded-lg border border-blue-200 bg-blue-50/70 p-3 text-center">
                                <p class="text-xs font-medium text-blue-700">Comissões</p>
                                <p class="mt-1 text-lg font-bold text-blue-700">{{ formatCurrency(totalComissoes) }}</p>
                            </div>
                            <div class="rounded-lg border border-emerald-200 bg-emerald-50/70 p-3 text-center">
                                <p class="text-xs font-medium text-emerald-700">Total final</p>
                                <p class="mt-1 text-lg font-bold text-emerald-700">{{ formatCurrency(totalFinal) }}</p>
                            </div>
                        </div>

                        <div class="rounded-lg border border-slate-200 bg-white overflow-hidden hidden sm:block">
                            <div class="px-3 sm:px-4 py-2 sm:py-3 border-b border-slate-100 bg-slate-50/70">
                                <p class="text-xs sm:text-sm font-semibold text-slate-700">Conferência financeira</p>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-xs sm:text-sm">
                                    <thead class="bg-slate-50 text-slate-600">
                                        <tr>
                                            <th class="text-left px-3 sm:px-4 py-2 font-semibold">Métrica</th>
                                            <th class="text-right px-3 sm:px-4 py-2 font-semibold">Valor bruto</th>
                                            <th class="text-right px-3 sm:px-4 py-2 font-semibold">Descontos</th>
                                            <th class="text-right px-3 sm:px-4 py-2 font-semibold">Valor líquido</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <tr
                                            @click="applyFinancialTypeFilter('entrada')"
                                            class="cursor-pointer transition-colors"
                                            :class="financialTypeFilter === 'entrada' ? 'bg-yellow-50/70' : 'hover:bg-yellow-50/40'"
                                        >
                                            <td class="px-3 sm:px-4 py-2 text-slate-700">Entradas</td>
                                            <td class="px-3 sm:px-4 py-2 text-right font-semibold text-slate-700">{{ formatCurrency(totalEntradasBrutas) }}</td>
                                            <td class="px-3 sm:px-4 py-2 text-right font-semibold text-amber-700">{{ formatCurrency(totalDescontosEntradas) }}</td>
                                            <td class="px-3 sm:px-4 py-2 text-right font-semibold text-yellow-700">{{ formatCurrency(totalEntradas) }}</td>
                                        </tr>
                                        <tr
                                            @click="applyFinancialTypeFilter('saida')"
                                            class="cursor-pointer transition-colors"
                                            :class="financialTypeFilter === 'saida' ? 'bg-orange-50/70' : 'hover:bg-orange-50/40'"
                                        >
                                            <td class="px-3 sm:px-4 py-2 text-slate-700">Saídas</td>
                                            <td class="px-3 sm:px-4 py-2 text-right font-semibold text-orange-700">{{ formatCurrency(totalSaidas) }}</td>
                                            <td class="px-3 sm:px-4 py-2 text-right text-slate-400">-</td>
                                            <td class="px-3 sm:px-4 py-2 text-right font-semibold text-orange-700">{{ formatCurrency(totalSaidas) }}</td>
                                        </tr>
                                        <tr
                                            @click="applyFinancialTypeFilter('comissao')"
                                            class="cursor-pointer transition-colors"
                                            :class="financialTypeFilter === 'comissao' ? 'bg-blue-50/70' : 'hover:bg-blue-50/40'"
                                        >
                                            <td class="px-3 sm:px-4 py-2 text-slate-700">Comissões</td>
                                            <td class="px-3 sm:px-4 py-2 text-right font-semibold text-blue-700">{{ formatCurrency(totalComissoes) }}</td>
                                            <td class="px-3 sm:px-4 py-2 text-right text-slate-400">-</td>
                                            <td class="px-3 sm:px-4 py-2 text-right font-semibold text-blue-700">{{ formatCurrency(totalComissoes) }}</td>
                                        </tr>
                                        <tr class="bg-emerald-50/60">
                                            <td class="px-3 sm:px-4 py-2 font-semibold text-emerald-800">Total final</td>
                                            <td class="px-3 sm:px-4 py-2 text-right text-slate-400">-</td>
                                            <td class="px-3 sm:px-4 py-2 text-right text-slate-400">-</td>
                                            <td class="px-3 sm:px-4 py-2 text-right font-bold text-emerald-700">{{ formatCurrency(totalFinal) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Search (hidden when viewing removed items) -->
                    <div v-if="!showRemoved" class="mb-4">
                        <div class="relative">
                            <input
                                ref="searchInputRef"
                                v-model="normalizedSearchQuery"
                                type="text"
                                placeholder="Pesquisar"
                                class="w-full px-3 sm:px-4 py-2 sm:py-3 pr-10 text-sm border-2 border-orange-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent"
                            />
                            <button
                                v-if="searchQuery"
                                type="button"
                                @click="clearSearchQuery"
                                class="absolute inset-y-0 right-2 my-auto h-7 w-7 inline-flex items-center justify-center rounded-md text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors"
                                aria-label="Limpar busca"
                                title="Limpar busca"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div v-if="!showRemoved && financialTypeFilter" class="mb-4">
                        <div class="inline-flex items-center gap-2 rounded-full border border-orange-200 bg-orange-50 px-3 py-1.5 text-xs sm:text-sm text-orange-800">
                            <span>Filtro ativo: apenas {{ activeFinancialFilterLabel }}</span>
                            <button
                                type="button"
                                @click="clearFinancialTypeFilter"
                                class="inline-flex h-5 w-5 items-center justify-center rounded-full text-orange-700 hover:bg-orange-100"
                                aria-label="Remover filtro financeiro"
                                title="Remover filtro"
                            >
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Sorting Controls (hidden when viewing removed items) -->
                    <div v-if="!showRemoved" class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 sm:gap-3">
                        <div class="flex flex-wrap items-center gap-1 sm:gap-2">
                            <div class="flex items-center gap-1 sm:gap-2">
                                <label class="text-xs text-gray-600 whitespace-nowrap">Ordenar</label>
                                <select
                                    v-model="sortKey"
                                    class="px-2 py-1 border border-orange-300 rounded-lg bg-white text-xs focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent"
                                >
                                    <option value="registradoEm">Registrado</option>
                                    <option value="dataOperacao">Data</option>
                                    <option value="valor">Valor</option>
                                    <option value="categoria">Categoria</option>
                                    <option value="colaborador">Colaborador</option>
                                    <option value="descricao">Descrição</option>
                                    <option value="tipo">Tipo</option>
                                </select>
                            </div>
                            <select
                                v-model="sortDir"
                                class="px-2 py-1 border border-orange-300 rounded-lg bg-white text-xs focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent"
                            >
                                <option value="asc">A-Z</option>
                                <option value="desc">Z-A</option>
                            </select>
                        </div>

                        <div class="flex gap-1 sm:gap-2"></div>
                    </div>

                    <!-- Inline Create Entry Form (hidden when viewing removed items) -->
                    <div v-if="showNewEntryInline && !showRemoved" class="mb-4 border-2 border-yellow-300 rounded-lg p-3 sm:p-4 bg-yellow-50/50">
                        <h3 class="text-xs sm:text-sm font-semibold text-yellow-700 mb-3 sm:mb-4">Nova Entrada</h3>
                        <div v-if="Object.keys(entryFormErrors).length" class="mb-3 p-2 sm:p-3 bg-red-50 border border-red-200 rounded-lg">
                            <p v-for="(errs, field) in entryFormErrors" :key="field" class="text-xs sm:text-sm text-red-600">{{ Array.isArray(errs) ? errs.join(', ') : errs }}</p>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 sm:gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Tipo</label>
                                <select v-model="entryForm.entry_type_id" class="w-full px-2 sm:px-3 py-1 sm:py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 bg-white text-xs sm:text-sm">
                                    <option :value="null">Selecione</option>
                                    <option v-for="t in tiposEntrada" :key="t.id" :value="t.id">{{ t.name }}</option>
                                </select>
                            </div>
                            <div v-if="useServiceTypes">
                                <label class="block text-xs font-medium text-gray-500 mb-1">Serviço</label>
                                <select v-model="entryForm.service_type_id" class="w-full px-2 sm:px-3 py-1 sm:py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 bg-white text-xs sm:text-sm">
                                    <option :value="null">Selecione</option>
                                    <option v-for="s in tiposServico" :key="s.id" :value="s.id">{{ s.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Responsável</label>
                                <select v-model="entryForm.collaborator_id" class="w-full px-2 sm:px-3 py-1 sm:py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 bg-white text-xs sm:text-sm">
                                    <option :value="null">Selecione</option>
                                    <option v-for="c in colaboradoresOptions" :key="c.id" :value="c.id">{{ c.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Data</label>
                                <input type="date" v-model="entryForm.date" class="w-full px-2 sm:px-3 py-1 sm:py-2 border border-gray-300 rounded-lg font-normal leading-normal [&::-webkit-date-and-time-value]:p-0 [&::-webkit-date-and-time-value]:m-0 [&::-webkit-date-and-time-value]:leading-normal focus:outline-none focus:ring-2 focus:ring-yellow-400 text-xs sm:text-sm" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Valor (R$)</label>
                                <input ref="entryAmountInput" type="text" inputmode="numeric" v-model="entryForm.amount" @input="handleEntryAmountInput" class="w-full px-2 sm:px-3 py-1 sm:py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 text-xs sm:text-sm" placeholder="0,00" />
                            </div>
                            <div v-if="useServiceTypes">
                                <label class="block text-xs font-medium text-gray-500 mb-1">Qtd</label>
                                <input type="number" min="1" v-model="entryForm.quantity" class="w-full px-2 sm:px-3 py-1 sm:py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 text-xs sm:text-sm" />
                            </div>
                            <div v-if="useServiceTypes">
                                <label class="block text-xs font-medium text-gray-500 mb-1">Desconto (R$)</label>
                                <input type="text" inputmode="numeric" v-model="entryForm.discount" @input="handleEntryDiscountInput" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 text-sm" placeholder="0,00" />
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-medium text-gray-500 mb-1">Descrição</label>
                                <input type="text" v-model="entryForm.description" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 text-sm" placeholder="Opcional" />
                            </div>
                            <div class="sm:col-span-2">
                                <div class="rounded-lg border border-orange-300 bg-gradient-to-r from-orange-500 to-amber-500 px-4 py-4 text-center shadow-sm">
                                    <p class="text-xs font-medium uppercase tracking-wide text-orange-100">Valor total</p>
                                    <p class="mt-1 text-2xl font-bold text-white">{{ formatCurrency(entryCalculatedTotal) }}</p>
                                    <div v-if="useServiceTypes" class="mt-1 flex items-center justify-center gap-2">
                                        <span class="text-xs text-orange-50">Fórmula</span>
                                        <div class="relative inline-flex">
                                            <button
                                                type="button"
                                                @click="showEntryCalculationTooltip = !showEntryCalculationTooltip"
                                                class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-orange-200/60 bg-white/20 text-orange-50 transition-colors hover:bg-white/30"
                                                aria-label="Mostrar fórmula do cálculo"
                                                title="Mostrar fórmula"
                                            >
                                                <Info class="h-3.5 w-3.5" />
                                            </button>
                                            <div
                                                v-if="showEntryCalculationTooltip"
                                                class="absolute left-1/2 top-7 z-20 w-64 -translate-x-1/2 rounded-md border border-orange-200 bg-white px-3 py-2 text-left text-xs text-orange-900 shadow-lg"
                                            >
                                                Cálculo: valor x quantidade - desconto
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-end gap-2 mt-4">
                            <button @click="cancelCreateForms" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">Cancelar</button>
                            <button @click="saveEntry" :disabled="isEntryLoading" class="px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-yellow-400 to-amber-500 rounded-lg hover:shadow-md transition-all disabled:opacity-50">
                                {{ isEntryLoading ? 'Salvando...' : 'Salvar' }}
                            </button>
                        </div>
                    </div>

                    <!-- Inline Create Exit Form (hidden when viewing removed items) -->
                    <div v-if="showNewExitInline && !showRemoved" class="mb-4 border-2 border-orange-300 rounded-lg p-3 sm:p-4 bg-orange-50/50">
                        <h3 class="text-xs sm:text-sm font-semibold text-orange-700 mb-3 sm:mb-4">Nova Saída</h3>
                        <div v-if="Object.keys(exitFormErrors).length" class="mb-3 p-2 sm:p-3 bg-red-50 border border-red-200 rounded-lg">
                            <p v-for="(errs, field) in exitFormErrors" :key="field" class="text-xs sm:text-sm text-red-600">{{ Array.isArray(errs) ? errs.join(', ') : errs }}</p>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 sm:gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Tipo de Saída</label>
                                <select v-model="exitForm.exit_type_id" class="w-full px-2 sm:px-3 py-1 sm:py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400 bg-white text-xs sm:text-sm">
                                    <option :value="null">Selecione</option>
                                    <option v-for="t in tiposSaida" :key="t.id" :value="t.id">{{ t.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Responsável</label>
                                <select v-model="exitForm.collaborator_id" class="w-full px-2 sm:px-3 py-1 sm:py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400 bg-white text-xs sm:text-sm">
                                    <option :value="null">Selecione</option>
                                    <option v-for="c in colaboradoresOptions" :key="c.id" :value="c.id">{{ c.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Data</label>
                                <input type="date" v-model="exitForm.date" class="w-full px-2 sm:px-3 py-1 sm:py-2 border border-gray-300 rounded-lg font-normal leading-normal [&::-webkit-date-and-time-value]:p-0 [&::-webkit-date-and-time-value]:m-0 [&::-webkit-date-and-time-value]:leading-normal focus:outline-none focus:ring-2 focus:ring-orange-400 text-xs sm:text-sm" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Valor (R$)</label>
                                <input ref="exitAmountInput" type="text" inputmode="numeric" v-model="exitForm.amount" @input="handleExitAmountInput" class="w-full px-2 sm:px-3 py-1 sm:py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400 text-xs sm:text-sm" placeholder="0,00" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Quantidade</label>
                                <input type="number" min="1" v-model="exitForm.quantity" class="w-full px-2 sm:px-3 py-1 sm:py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400 text-xs sm:text-sm" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Desconto (R$)</label>
                                <input type="text" inputmode="numeric" v-model="exitForm.discount" @input="handleExitDiscountInput" class="w-full px-2 sm:px-3 py-1 sm:py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400 text-xs sm:text-sm" placeholder="0,00" />
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-medium text-gray-500 mb-1">Descrição</label>
                                <input type="text" v-model="exitForm.description" class="w-full px-2 sm:px-3 py-1 sm:py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400 text-xs sm:text-sm" placeholder="Opcional" />
                            </div>
                            <div class="sm:col-span-2">
                                <div class="rounded-lg border border-orange-300 bg-gradient-to-r from-orange-500 to-amber-500 px-4 py-4 text-center shadow-sm">
                                    <p class="text-xs font-medium uppercase tracking-wide text-orange-100">Valor total</p>
                                    <p class="mt-1 text-2xl font-bold text-white">{{ formatCurrency(exitCalculatedTotal) }}</p>
                                    <div class="mt-1 flex items-center justify-center gap-2">
                                        <span class="text-xs text-orange-50">Fórmula</span>
                                        <div class="relative inline-flex">
                                            <button
                                                type="button"
                                                @click="showExitCalculationTooltip = !showExitCalculationTooltip"
                                                class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-orange-200/60 bg-white/20 text-orange-50 transition-colors hover:bg-white/30"
                                                aria-label="Mostrar fórmula do cálculo"
                                                title="Mostrar fórmula"
                                            >
                                                <Info class="h-3.5 w-3.5" />
                                            </button>
                                            <div
                                                v-if="showExitCalculationTooltip"
                                                class="absolute left-1/2 top-7 z-20 w-64 -translate-x-1/2 rounded-md border border-orange-200 bg-white px-3 py-2 text-left text-xs text-orange-900 shadow-lg"
                                            >
                                                Cálculo: valor x quantidade - desconto
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-end gap-2 mt-4">
                            <button @click="cancelCreateForms" class="px-4 py-2 text-xs sm:text-sm text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">Cancelar</button>
                            <button @click="saveExit" :disabled="isExitLoading" class="px-4 py-2 text-xs sm:text-sm font-medium text-white bg-gradient-to-r from-orange-400 to-orange-500 rounded-lg hover:shadow-md transition-all disabled:opacity-50">
                                {{ isExitLoading ? 'Salvando...' : 'Salvar' }}
                            </button>
                        </div>
                    </div>

                    <!-- Records List -->
                    <div class="space-y-3 min-h-[320px] w-full">
                        <template v-if="isLoading || isListLoading">
                            <div
                                v-for="n in 5"
                                :key="`registro-skeleton-${n}`"
                                class="border border-gray-200 rounded-lg p-3 sm:p-4 bg-gray-50"
                            >
                                <!-- Mobile skeleton -->
                                <div class="lg:hidden space-y-2">
                                    <div class="flex justify-between">
                                        <div class="h-5 w-1/2 bg-gray-200 rounded animate-pulse"></div>
                                        <div class="h-5 w-16 bg-gray-200 rounded animate-pulse"></div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div class="h-4 bg-gray-200 rounded animate-pulse"></div>
                                        <div class="h-4 bg-gray-200 rounded animate-pulse"></div>
                                    </div>
                                    <div class="h-3 w-24 bg-gray-200 rounded animate-pulse"></div>
                                </div>
                                <!-- Desktop skeleton -->
                                <div class="hidden lg:flex lg:items-center gap-4">
                                    <div class="w-24 h-5 bg-gray-200 rounded animate-pulse"></div>
                                    <div class="w-32 h-5 bg-gray-200 rounded animate-pulse"></div>
                                    <div class="w-40 h-5 bg-gray-200 rounded animate-pulse"></div>
                                    <div class="flex-1 h-5 bg-gray-200 rounded animate-pulse"></div>
                                    <div class="w-64 space-y-2">
                                        <div class="h-4 bg-gray-200 rounded animate-pulse"></div>
                                        <div class="h-4 bg-gray-200 rounded animate-pulse"></div>
                                    </div>
                                    <div class="w-24 h-5 bg-gray-200 rounded animate-pulse"></div>
                                </div>
                            </div>
                        </template>

                        <div
                            v-else-if="paginatedRegistros.length === 0"
                            class="border border-gray-200 rounded-lg p-4 text-sm text-gray-500 text-center"
                        >
                            <template v-if="showRemoved">
                                Nenhum registro removido encontrado
                            </template>
                            <template v-else-if="financialTypeFilter">
                                Nenhum registro encontrado para o filtro {{ activeFinancialFilterLabel }}
                            </template>
                            <template v-else>
                                Nenhum registro encontrado
                            </template>
                        </div>

                        <div
                            v-else
                            v-for="registro in paginatedRegistros"
                            :key="registro.id"
                            @click="selectRecord(registro.id)"
                            class="border border-gray-200 rounded-lg transition-shadow"
                            :class="[
                                editingRegistroId === registro.id
                                    ? 'ring-2 ring-yellow-300 shadow-md'
                                    : selectedRecordId === registro.id
                                        ? 'ring-2 ring-orange-200 shadow-sm'
                                        : 'hover:shadow-md'
                            ]"
                        >
                            <!-- Inline edit form for Entry -->
                            <div v-if="editingRegistroId === registro.id && editingRegistroType === 'entrada'" class="p-4">
                                <h3 class="text-sm font-semibold text-yellow-700 mb-4">Editar Entrada</h3>
                                <div v-if="Object.keys(entryFormErrors).length" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                                    <p v-for="(errs, field) in entryFormErrors" :key="field" class="text-sm text-red-600">{{ Array.isArray(errs) ? errs.join(', ') : errs }}</p>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1">Tipo de Entrada</label>
                                        <select v-model="entryForm.entry_type_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 bg-white text-sm">
                                            <option :value="null">Selecione</option>
                                            <option v-for="t in tiposEntrada" :key="t.id" :value="t.id">{{ t.name }}</option>
                                        </select>
                                    </div>
                                    <div v-if="useServiceTypes">
                                        <label class="block text-xs font-medium text-gray-500 mb-1">Tipo de Serviço</label>
                                        <select v-model="entryForm.service_type_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 bg-white text-sm">
                                            <option :value="null">Selecione</option>
                                            <option v-for="s in tiposServico" :key="s.id" :value="s.id">{{ s.name }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1">Responsável</label>
                                        <select v-model="entryForm.collaborator_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 bg-white text-sm">
                                            <option :value="null">Selecione</option>
                                            <option v-for="c in colaboradoresOptions" :key="c.id" :value="c.id">{{ c.name }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1">Data</label>
                                        <input type="date" v-model="entryForm.date" class="w-full px-3 py-2 border border-gray-300 rounded-lg font-normal leading-normal [&::-webkit-date-and-time-value]:p-0 [&::-webkit-date-and-time-value]:m-0 [&::-webkit-date-and-time-value]:leading-normal focus:outline-none focus:ring-2 focus:ring-yellow-400 text-sm" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1">Valor (R$)</label>
                                        <input type="text" inputmode="numeric" v-model="entryForm.amount" @input="handleEntryAmountInput" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 text-sm" placeholder="0,00" />
                                    </div>
                                    <div v-if="useServiceTypes">
                                        <label class="block text-xs font-medium text-gray-500 mb-1">Quantidade</label>
                                        <input type="number" min="1" v-model="entryForm.quantity" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 text-sm" />
                                    </div>
                                    <div v-if="useServiceTypes">
                                        <label class="block text-xs font-medium text-gray-500 mb-1">Desconto (R$)</label>
                                        <input type="text" inputmode="numeric" v-model="entryForm.discount" @input="handleEntryDiscountInput" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 text-sm" placeholder="0,00" />
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="block text-xs font-medium text-gray-500 mb-1">Descrição</label>
                                        <input type="text" v-model="entryForm.description" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 text-sm" placeholder="Opcional" />
                                    </div>
                                    <div class="sm:col-span-2">
                                        <div class="rounded-lg border border-orange-300 bg-gradient-to-r from-orange-500 to-amber-500 px-4 py-4 text-center shadow-sm">
                                            <p class="text-xs font-medium uppercase tracking-wide text-orange-100">Valor total</p>
                                            <p class="mt-1 text-2xl font-bold text-white">{{ formatCurrency(entryCalculatedTotal) }}</p>
                                            <div v-if="useServiceTypes" class="mt-1 flex items-center justify-center gap-2">
                                                <span class="text-xs text-orange-50">Fórmula</span>
                                                <div class="relative inline-flex">
                                                    <button
                                                        type="button"
                                                        @click="showEntryCalculationTooltip = !showEntryCalculationTooltip"
                                                        class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-orange-200/60 bg-white/20 text-orange-50 transition-colors hover:bg-white/30"
                                                        aria-label="Mostrar fórmula do cálculo"
                                                        title="Mostrar fórmula"
                                                    >
                                                        <Info class="h-3.5 w-3.5" />
                                                    </button>
                                                    <div
                                                        v-if="showEntryCalculationTooltip"
                                                        class="absolute left-1/2 top-7 z-20 w-64 -translate-x-1/2 rounded-md border border-orange-200 bg-white px-3 py-2 text-left text-xs text-orange-900 shadow-lg"
                                                    >
                                                        Cálculo: valor x quantidade - desconto
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex justify-end gap-2 mt-4">
                                    <button @click="closeAllForms" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">Cancelar</button>
                                    <button @click="saveEntry" :disabled="isEntryLoading" class="px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-yellow-400 to-amber-500 rounded-lg hover:shadow-md transition-all disabled:opacity-50">
                                        {{ isEntryLoading ? 'Salvando...' : 'Salvar' }}
                                    </button>
                                </div>
                            </div>

                            <!-- Inline edit form for Exit -->
                            <div v-else-if="editingRegistroId === registro.id && editingRegistroType === 'saida'" class="p-4">
                                <h3 class="text-sm font-semibold text-orange-700 mb-4">Editar Saída</h3>
                                <div v-if="Object.keys(exitFormErrors).length" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                                    <p v-for="(errs, field) in exitFormErrors" :key="field" class="text-sm text-red-600">{{ Array.isArray(errs) ? errs.join(', ') : errs }}</p>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1">Tipo de Saída</label>
                                        <select v-model="exitForm.exit_type_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400 bg-white text-sm">
                                            <option :value="null">Selecione</option>
                                            <option v-for="t in tiposSaida" :key="t.id" :value="t.id">{{ t.name }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1">Responsável</label>
                                        <select v-model="exitForm.collaborator_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400 bg-white text-sm">
                                            <option :value="null">Selecione</option>
                                            <option v-for="c in colaboradoresOptions" :key="c.id" :value="c.id">{{ c.name }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1">Data</label>
                                        <input type="date" v-model="exitForm.date" class="w-full px-3 py-2 border border-gray-300 rounded-lg font-normal leading-normal [&::-webkit-date-and-time-value]:p-0 [&::-webkit-date-and-time-value]:m-0 [&::-webkit-date-and-time-value]:leading-normal focus:outline-none focus:ring-2 focus:ring-orange-400 text-sm" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1">Valor (R$)</label>
                                        <input type="text" inputmode="numeric" v-model="exitForm.amount" @input="handleExitAmountInput" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400 text-sm" placeholder="0,00" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1">Quantidade</label>
                                        <input type="number" min="1" v-model="exitForm.quantity" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400 text-sm" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1">Desconto (R$)</label>
                                        <input type="text" inputmode="numeric" v-model="exitForm.discount" @input="handleExitDiscountInput" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400 text-sm" placeholder="0,00" />
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="block text-xs font-medium text-gray-500 mb-1">Descrição</label>
                                        <input type="text" v-model="exitForm.description" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400 text-sm" placeholder="Opcional" />
                                    </div>
                                    <div class="sm:col-span-2">
                                        <div class="rounded-lg border border-orange-300 bg-gradient-to-r from-orange-500 to-amber-500 px-4 py-4 text-center shadow-sm">
                                            <p class="text-xs font-medium uppercase tracking-wide text-orange-100">Valor total</p>
                                            <p class="mt-1 text-2xl font-bold text-white">{{ formatCurrency(exitCalculatedTotal) }}</p>
                                            <div class="mt-1 flex items-center justify-center gap-2">
                                                <span class="text-xs text-orange-50">Fórmula</span>
                                                <div class="relative inline-flex">
                                                    <button
                                                        type="button"
                                                        @click="showExitCalculationTooltip = !showExitCalculationTooltip"
                                                        class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-orange-200/60 bg-white/20 text-orange-50 transition-colors hover:bg-white/30"
                                                        aria-label="Mostrar fórmula do cálculo"
                                                        title="Mostrar fórmula"
                                                    >
                                                        <Info class="h-3.5 w-3.5" />
                                                    </button>
                                                    <div
                                                        v-if="showExitCalculationTooltip"
                                                        class="absolute left-1/2 top-7 z-20 w-64 -translate-x-1/2 rounded-md border border-orange-200 bg-white px-3 py-2 text-left text-xs text-orange-900 shadow-lg"
                                                    >
                                                        Cálculo: valor x quantidade - desconto
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex justify-end gap-2 mt-4">
                                    <button @click="closeAllForms" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">Cancelar</button>
                                    <button @click="saveExit" :disabled="isExitLoading" class="px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-orange-400 to-orange-500 rounded-lg hover:shadow-md transition-all disabled:opacity-50">
                                        {{ isExitLoading ? 'Salvando...' : 'Salvar' }}
                                    </button>
                                </div>
                            </div>

                            <!-- Normal view -->
                            <template v-else>
                            <div class="p-3 sm:p-4">
                                <!-- Mobile layout -->
                                <div class="lg:hidden">
                                    <div class="flex items-start justify-between gap-2 mb-2">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <span :class="['inline-flex text-xs font-semibold px-2 py-0.5 rounded-full flex-shrink-0', registro.tipo === 'entrada' ? 'bg-yellow-100 text-yellow-700' : 'bg-orange-100 text-orange-700']">
                                                {{ registro.tipo === 'entrada' ? 'Entrada' : 'Saída' }}
                                            </span>
                                            <span :class="['font-bold text-base', registro.tipo === 'entrada' ? 'text-yellow-500' : 'text-orange-500']">
                                                {{ registro.tipo === 'entrada' ? '+' : '−' }} {{ formatCurrency(registro.valor) }}
                                            </span>
                                        </div>
                                        <div class="flex gap-1 flex-shrink-0">
                                            <template v-if="showRemoved">
                                                <button
                                                    @click="handleRestore(registro.id)"
                                                    :disabled="isRestoreLoading"
                                                    :class="['p-2 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed', restoringRecordId === registro.id && isRestoreLoading ? 'text-green-600 bg-green-50' : 'text-green-500 hover:bg-green-50']"
                                                    :title="restoringRecordId === registro.id && isRestoreLoading ? 'Restaurando...' : 'Restaurar'"
                                                >
                                                    <svg :class="['w-5 h-5', restoringRecordId === registro.id && isRestoreLoading ? 'animate-spin' : '']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                    </svg>
                                                </button>
                                            </template>
                                            <template v-else>
                                                <button @click="handleDelete(registro.id)" class="p-2 text-red-500 hover:bg-red-50 rounded-lg transition-colors" title="Remover">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                                <button @click="openEditRecord(registro.id)" class="p-2 text-orange-500 hover:bg-orange-50 rounded-lg transition-colors" title="Editar">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-x-3 gap-y-1.5 text-xs">
                                        <div>
                                            <span class="text-gray-400 block">Categoria</span>
                                            <p class="text-gray-700 font-medium truncate">{{ registro.categoria || '-' }}</p>
                                        </div>
                                        <div>
                                            <span class="text-gray-400 block">Colaborador</span>
                                            <p class="text-gray-700 truncate">{{ registro.colaborador || '-' }}</p>
                                        </div>
                                        <div v-if="registro.descricao" class="col-span-2">
                                            <span class="text-gray-400 block">Descrição</span>
                                            <p class="text-gray-600 truncate">{{ registro.descricao }}</p>
                                        </div>
                                        <div class="col-span-2 pt-0.5 text-gray-400">
                                            {{ formatDate(registro.dataOperacao) }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Desktop layout -->
                                <div class="hidden lg:flex lg:items-center gap-4">
                                    <!-- Valor -->
                                    <div class="w-24 flex-shrink-0">
                                        <span :class="['font-bold text-sm', registro.tipo === 'entrada' ? 'text-yellow-500' : 'text-orange-500']">
                                            {{ registro.tipo === 'entrada' ? '+ ' : '- ' }}{{ formatCurrency(registro.valor) }}
                                        </span>
                                    </div>
                                    <!-- Categoria -->
                                    <div class="w-32 flex-shrink-0">
                                        <span class="text-gray-700 text-sm truncate block">{{ registro.categoria }}</span>
                                    </div>
                                    <!-- Colaborador -->
                                    <div class="w-40 flex-shrink-0">
                                        <span class="text-gray-700 text-sm truncate block">{{ registro.colaborador || '-' }}</span>
                                    </div>
                                    <!-- Descrição -->
                                    <div class="flex-1">
                                        <span class="text-gray-600 text-sm block">{{ registro.descricao || '-' }}</span>
                                    </div>
                                    <!-- Datas -->
                                    <div class="w-64 flex-shrink-0 text-right">
                                        <div class="text-gray-700 text-xs">Movimento: {{ formatDate(registro.dataOperacao) }}</div>
                                        <div class="text-gray-500 text-xs">Registrado: {{ formatDateTime(registro.registradoEm) }}</div>
                                    </div>
                                    <!-- Actions -->
                                    <div class="flex gap-2 flex-shrink-0 ml-auto w-24 justify-end">
                                        <template v-if="showRemoved">
                                            <button
                                                @click="handleRestore(registro.id)"
                                                :disabled="isRestoreLoading"
                                                :class="['p-2 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed', restoringRecordId === registro.id && isRestoreLoading ? 'text-green-600 bg-green-50' : 'text-green-500 hover:bg-green-50']"
                                                :title="restoringRecordId === registro.id && isRestoreLoading ? 'Restaurando...' : 'Restaurar'"
                                            >
                                                <svg :class="['w-5 h-5', restoringRecordId === registro.id && isRestoreLoading ? 'animate-spin' : '']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                </svg>
                                            </button>
                                        </template>
                                        <template v-else>
                                            <button @click="handleDelete(registro.id)" class="p-2 text-red-500 hover:bg-red-50 rounded-lg transition-colors" title="Remover">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                            <button @click="openEditRecord(registro.id)" class="p-2 text-orange-500 hover:bg-orange-50 rounded-lg transition-colors" title="Editar">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <!-- Inline delete confirmation -->
                            <div
                                v-if="deletingRecordId === registro.id"
                                class="border-t border-red-200 bg-red-50/80 px-4 py-3 rounded-b-lg flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
                            >
                                <p class="text-sm font-medium text-gray-800">
                                    Tem certeza que deseja remover
                                    <span class="font-semibold text-red-600">{{ getRecordDeleteLabel(registro) }}</span>?
                                </p>
                                <div class="flex gap-2 justify-end">
                                    <button
                                        @click="cancelDelete"
                                        :disabled="isDeleteLoading"
                                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors disabled:opacity-50"
                                    >Cancelar</button>
                                    <button
                                        @click="confirmDelete"
                                        :disabled="isDeleteLoading"
                                        class="px-4 py-2 text-sm font-medium text-white bg-red-500 rounded-lg hover:bg-red-600 transition-colors disabled:opacity-50"
                                    >{{ isDeleteLoading ? 'Removendo...' : 'Remover' }}</button>
                                </div>
                            </div>
                            </template>
                        </div>
                    </div>

                    <div
                        v-if="activeTab === 'todos'"
                        ref="infiniteScrollTrigger"
                        class="h-10"
                        aria-hidden="true"
                    ></div>

                    <div v-if="activeTab === 'todos' && isLoadingMore" class="mt-2 text-sm text-gray-500 text-center">
                        Carregando mais registros...
                    </div>

                    <div
                        v-if="activeTab === 'todos' && !isLoadingMore && !hasMorePages && paginatedRegistros.length > 0"
                        class="mt-2 text-sm text-gray-500 text-center"
                    >
                        Todos os registros foram carregados.
                    </div>

                    <!-- Pagination -->
                    <div v-if="activeTab !== 'todos' && !isLoading && !isListLoading && totalPages > 1" class="flex items-center justify-center gap-2 mt-6">
                        <button
                            @click="goToPage(currentPage - 1)"
                            :disabled="currentPage === 1"
                            class="px-3 py-1 text-sm text-gray-600 hover:bg-gray-100 rounded disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            Anterior
                        </button>

                        <button
                            v-for="page in totalPages"
                            :key="page"
                            @click="goToPage(page)"
                            :class="[
                                'px-3 py-1 text-sm rounded',
                                page === currentPage
                                    ? 'bg-yellow-400 text-white font-semibold'
                                    : 'text-gray-600 hover:bg-gray-100'
                            ]"
                        >
                            {{ page }}
                        </button>

                        <button
                            @click="goToPage(currentPage + 1)"
                            :disabled="currentPage === totalPages"
                            class="px-3 py-1 text-sm text-gray-600 hover:bg-gray-100 rounded disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            Próxima
                        </button>
                    </div>
                    </template>

                    <template v-if="isChartsView && !showRemoved">
                        <div class="space-y-6">
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div class="bg-white border border-orange-100 rounded-lg p-4 text-center shadow-sm">
                                    <p class="text-2xl font-bold text-orange-500">{{ formatCurrency(currentChartTotals.entradas) }}</p>
                                    <p class="text-sm text-gray-600">Entradas</p>
                                </div>
                                <div class="bg-white border border-orange-100 rounded-lg p-4 text-center shadow-sm">
                                    <p class="text-2xl font-bold text-orange-500">{{ formatCurrency(currentChartTotals.saidas) }}</p>
                                    <p class="text-sm text-gray-600">Saídas</p>
                                </div>
                                <div class="bg-white border border-orange-100 rounded-lg p-4 text-center shadow-sm">
                                    <p class="text-2xl font-bold text-orange-500">{{ formatCurrency(currentChartTotals.lucro) }}</p>
                                    <p class="text-sm text-gray-600">Lucro</p>
                                </div>
                                <div class="bg-white border border-orange-100 rounded-lg p-4 text-center shadow-sm">
                                    <p class="text-2xl font-bold text-orange-500">{{ formatCurrency(commissionsTotal) }}</p>
                                    <p class="text-sm text-gray-600">Comissões</p>
                                </div>
                            </div>

                            <div v-if="activeTab === 'hoje'" class="bg-white rounded-lg p-4 sm:p-6 shadow-md border border-orange-100">
                                <h3 class="text-xl font-bold text-orange-600 text-center mb-6">{{ formatCurrency(commissionsTotal) }}</h3>
                                <p class="text-center text-gray-600 mb-6">Comissões</p>

                                <div v-if="currentCommissions.length === 0" class="flex items-center justify-center h-64">
                                    <div class="text-center text-gray-400">
                                        <svg class="mx-auto h-12 w-12 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        <p class="text-lg font-medium">Nenhum registro hoje</p>
                                        <p class="text-sm mt-1">Não há comissões registradas para este período</p>
                                    </div>
                                </div>

                                <div v-else class="overflow-x-auto"><div class="flex items-end justify-center gap-6 sm:gap-8 h-64 min-w-max px-2">
                                    <div v-for="item in currentCommissions" :key="item.collaborator_id" class="flex flex-col items-center gap-2">
                                        <div class="flex items-end h-48">
                                            <div class="w-16 bg-orange-500 rounded-t-lg transition-all hover:bg-orange-600" :style="{ height: `${maxValueComissoes > 0 ? (item.total / maxValueComissoes) * 100 : 0}%` }" :title="chartValueTitle(item.collaborator_name, item.total)"></div>
                                        </div>
                                        <span class="text-sm text-gray-600">{{ item.collaborator_name }}</span>
                                    </div>
                                </div></div>
                            </div>

                            <div v-if="activeTab === 'ultimos7dias'" class="space-y-6">
                                <div class="grid grid-cols-1 gap-6">
                                    <div class="bg-white rounded-lg p-4 sm:p-6 shadow-md border border-orange-100">
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
                                                        <div class="h-6 bg-yellow-400 rounded" :style="{ width: `${maxValueDaily > 0 ? (day.entradas / maxValueDaily) * 100 : 0}%` }" :title="chartMonthlyTitle(formatDayLabel(day.date), 'Entradas', day.entradas)"></div>
                                                        <span class="text-xs text-gray-500 whitespace-nowrap">{{ formatCurrency(day.entradas) }}</span>
                                                    </div>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <span class="w-16"></span>
                                                    <div class="flex-1 flex items-center gap-1">
                                                        <div class="h-6 bg-orange-500 rounded" :style="{ width: `${maxValueDaily > 0 ? (day.saidas / maxValueDaily) * 100 : 0}%` }" :title="chartMonthlyTitle(formatDayLabel(day.date), 'Saídas', day.saidas)"></div>
                                                        <span class="text-xs text-gray-500 whitespace-nowrap">{{ formatCurrency(day.saidas) }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                    <div class="bg-white rounded-lg p-4 sm:p-6 shadow-md border border-orange-100">
                                        <h3 class="text-xl font-bold text-orange-600 text-center mb-2">{{ formatCurrency(commissionsTotal) }}</h3>
                                        <p class="text-center text-gray-600 mb-6">Comissões</p>

                                    <div v-if="currentCommissions.length === 0" class="flex items-center justify-center h-48">
                                        <div class="text-center text-gray-400">
                                            <svg class="mx-auto h-12 w-12 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            <p class="text-lg font-medium">Nenhum registro</p>
                                            <p class="text-sm mt-1">Não há comissões registradas para este período</p>
                                        </div>
                                    </div>

                                    <div v-else class="overflow-x-auto"><div class="flex items-end justify-center gap-6 sm:gap-8 h-48 min-w-max px-2">
                                        <div v-for="item in currentCommissions" :key="`commission-7-${item.collaborator_id}`" class="flex flex-col items-center gap-2">
                                            <div class="flex items-end h-32">
                                                <div class="w-16 bg-orange-500 rounded-t-lg transition-all hover:bg-orange-600" :style="{ height: `${maxValueComissoes > 0 ? (item.total / maxValueComissoes) * 100 : 0}%` }" :title="chartValueTitle(item.collaborator_name, item.total)"></div>
                                            </div>
                                            <span class="text-sm text-gray-600">{{ item.collaborator_name }}</span>
                                        </div>
                                    </div></div>
                                </div>
                            </div>

                            <div v-if="activeTab === 'mensal'" class="space-y-6">
                                <div class="bg-white rounded-lg p-4 sm:p-6 shadow-md border border-orange-100">
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                                        <div class="text-center">
                                            <h3 class="text-lg font-bold text-yellow-600">{{ formatCurrency(currentChartTotals.entradas) }}</h3>
                                            <p class="text-xs text-gray-600 mt-1">Entradas do mês</p>
                                        </div>
                                        <div class="text-center">
                                            <h3 class="text-lg font-bold text-orange-600">{{ formatCurrency(currentChartTotals.saidas) }}</h3>
                                            <p class="text-xs text-gray-600 mt-1">Saídas do mês</p>
                                        </div>
                                        <div class="text-center">
                                            <h3 class="text-lg font-bold text-green-600">{{ formatCurrency(currentChartTotals.lucro) }}</h3>
                                            <p class="text-xs text-gray-600 mt-1">Lucro do mês</p>
                                        </div>
                                        <div class="text-center">
                                            <h3 class="text-lg font-bold text-blue-600">{{ formatCurrency(commissionsTotal) }}</h3>
                                            <p class="text-xs text-gray-600 mt-1">Comissões do mês</p>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap justify-center gap-6 mb-6 text-sm">
                                        <div class="flex items-center gap-2">
                                            <div class="w-3 h-3 bg-yellow-400 rounded"></div>
                                            <span class="text-gray-600">Entrada</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <div class="w-3 h-3 bg-orange-500 rounded"></div>
                                            <span class="text-gray-600">Saída</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <div class="w-3 h-3 bg-green-500 rounded"></div>
                                            <span class="text-gray-600">Lucro</span>
                                        </div>
                                    </div>

                                    <div class="flex gap-2 sm:gap-4">
                                        <div class="relative pt-2 pb-8">
                                            <div class="w-12 sm:w-16 h-48 flex flex-col justify-between text-right">
                                                <span class="text-xs text-gray-500 font-medium">{{ formatCurrency(maxValueMonthlyDaily) }}</span>
                                                <span class="text-xs text-gray-500 font-medium">{{ formatCurrency(maxValueMonthlyDaily * 0.75) }}</span>
                                                <span class="text-xs text-gray-500 font-medium">{{ formatCurrency(maxValueMonthlyDaily * 0.5) }}</span>
                                                <span class="text-xs text-gray-500 font-medium">{{ formatCurrency(maxValueMonthlyDaily * 0.25) }}</span>
                                                <span class="text-xs text-gray-500 font-medium">{{ formatCurrency(0) }}</span>
                                            </div>
                                        </div>

                                        <div class="flex-1 relative overflow-x-auto">
                                            <div class="absolute inset-0 flex flex-col pt-2 pb-8 pointer-events-none">
                                                <div class="flex-1 border-t border-gray-200"></div>
                                                <div class="flex-1 border-t border-gray-200"></div>
                                                <div class="flex-1 border-t border-gray-200"></div>
                                                <div class="flex-1 border-t border-gray-200"></div>
                                                <div class="flex-1 border-t border-gray-200"></div>
                                            </div>

                                            <div class="flex items-end gap-2 h-64 min-w-max px-4 relative z-10">
                                                <div v-for="day in monthlyDailyBreakdown" :key="day.date" class="flex flex-col items-center gap-1 w-8 flex-shrink-0">
                                                    <div class="flex items-end gap-0.5 h-48 w-full">
                                                        <div class="flex-1 bg-yellow-400 rounded-t transition-all hover:bg-yellow-500" :style="{ height: chartBarHeight(day.entradas, maxValueMonthlyDaily) }" :title="chartMonthlyTitle(formatDayLabel(day.date), 'Entrada', day.entradas)"></div>
                                                        <div class="flex-1 bg-orange-500 rounded-t transition-all hover:bg-orange-600" :style="{ height: chartBarHeight(day.saidas, maxValueMonthlyDaily) }" :title="chartMonthlyTitle(formatDayLabel(day.date), 'Saída', day.saidas)"></div>
                                                        <div class="flex-1 bg-green-500 rounded-t transition-all hover:bg-green-600" :style="{ height: chartBarHeight(day.lucro, maxValueMonthlyDaily) }" :title="chartMonthlyTitle(formatDayLabel(day.date), 'Lucro', day.lucro)"></div>
                                                    </div>
                                                    <span class="text-xs text-gray-600 mt-2">{{ formatDayLabel(day.date).slice(0, 2) }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-white rounded-lg p-4 sm:p-6 shadow-md border border-orange-100">
                                    <h3 class="text-xl font-bold text-orange-600 text-center mb-2">{{ formatCurrency(commissionsTotal) }}</h3>
                                    <p class="text-center text-gray-600 mb-6">Comissões por colaborador no mês</p>

                                    <div v-if="currentCommissions.length === 0" class="flex items-center justify-center h-48">
                                        <div class="text-center text-gray-400">
                                            <svg class="mx-auto h-12 w-12 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            <p class="text-lg font-medium">Nenhuma comissão no período</p>
                                            <p class="text-sm mt-1">Não há comissões registradas para este mês</p>
                                        </div>
                                    </div>

                                    <div v-else class="overflow-x-auto"><div class="flex items-end justify-center gap-6 sm:gap-8 h-48 min-w-max px-2">
                                        <div v-for="item in currentCommissions" :key="`commission-month-${item.collaborator_id}`" class="flex flex-col items-center gap-2">
                                            <div class="flex items-end h-32">
                                                <div class="w-16 bg-orange-500 rounded-t-lg transition-all hover:bg-orange-600" :style="{ height: `${maxValueComissoes > 0 ? (item.total / maxValueComissoes) * 100 : 0}%` }" :title="chartValueTitle(item.collaborator_name, item.total)"></div>
                                            </div>
                                            <span class="text-sm text-gray-600">{{ item.collaborator_name }}</span>
                                        </div>
                                    </div></div>
                                </div>
                            </div>

                            <div v-if="activeTab === 'anual'" class="space-y-6">
                                <div class="bg-white rounded-lg p-4 sm:p-6 shadow-md border border-orange-100">
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                                        <div class="text-center">
                                            <h3 class="text-lg font-bold text-yellow-600">{{ formatCurrency(currentChartTotals.entradas) }}</h3>
                                            <p class="text-xs text-gray-600 mt-1">Entrada Anual</p>
                                        </div>
                                        <div class="text-center">
                                            <h3 class="text-lg font-bold text-orange-600">{{ formatCurrency(currentChartTotals.saidas) }}</h3>
                                            <p class="text-xs text-gray-600 mt-1">Saída Anual</p>
                                        </div>
                                        <div class="text-center">
                                            <h3 class="text-lg font-bold text-green-600">{{ formatCurrency(currentChartTotals.lucro) }}</h3>
                                            <p class="text-xs text-gray-600 mt-1">Lucro Anual</p>
                                        </div>
                                        <div class="text-center">
                                            <h3 class="text-lg font-bold text-blue-600">{{ formatCurrency(commissionsTotal) }}</h3>
                                            <p class="text-xs text-gray-600 mt-1">Comissão Anual</p>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap justify-center gap-6 mb-6 text-sm">
                                        <div class="flex items-center gap-2">
                                            <div class="w-3 h-3 bg-yellow-400 rounded"></div>
                                            <span class="text-gray-600">Entrada</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <div class="w-3 h-3 bg-orange-500 rounded"></div>
                                            <span class="text-gray-600">Saída</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <div class="w-3 h-3 bg-green-500 rounded"></div>
                                            <span class="text-gray-600">Lucro</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <div class="w-3 h-3 bg-blue-500 rounded"></div>
                                            <span class="text-gray-600">Comissão</span>
                                        </div>
                                    </div>

                                    <div class="flex gap-2 sm:gap-4">
                                        <div class="relative pt-2 pb-8">
                                            <div class="w-12 sm:w-16 h-48 flex flex-col justify-between text-right">
                                                <span class="text-xs text-gray-500 font-medium">{{ formatCurrency(maxValueMonthly) }}</span>
                                                <span class="text-xs text-gray-500 font-medium">{{ formatCurrency(maxValueMonthly * 0.75) }}</span>
                                                <span class="text-xs text-gray-500 font-medium">{{ formatCurrency(maxValueMonthly * 0.5) }}</span>
                                                <span class="text-xs text-gray-500 font-medium">{{ formatCurrency(maxValueMonthly * 0.25) }}</span>
                                                <span class="text-xs text-gray-500 font-medium">{{ formatCurrency(0) }}</span>
                                            </div>
                                        </div>

                                        <div class="flex-1 relative">
                                            <div class="absolute inset-0 flex flex-col pt-2 pb-8 pointer-events-none">
                                                <div class="flex-1 border-t border-gray-200"></div>
                                                <div class="flex-1 border-t border-gray-200"></div>
                                                <div class="flex-1 border-t border-gray-200"></div>
                                                <div class="flex-1 border-t border-gray-200"></div>
                                                <div class="flex-1 border-t border-gray-200"></div>
                                            </div>

                                            <div class="flex items-end justify-around gap-2 h-64 px-4 relative z-10">
                                                <div v-for="month in monthlyBreakdown" :key="month.monthNum" class="flex flex-col items-center gap-1 flex-1">
                                                    <div class="flex items-end gap-0.5 h-48 w-full max-w-[48px]">
                                                        <div class="flex-1 bg-yellow-400 rounded-t transition-all hover:bg-yellow-500" :style="{ height: chartBarHeight(month.entradas, maxValueMonthly) }" :title="chartMonthlyTitle(month.month, 'Entrada', month.entradas)"></div>
                                                        <div class="flex-1 bg-orange-500 rounded-t transition-all hover:bg-orange-600" :style="{ height: chartBarHeight(month.saidas, maxValueMonthly) }" :title="chartMonthlyTitle(month.month, 'Saída', month.saidas)"></div>
                                                        <div class="flex-1 bg-green-500 rounded-t transition-all hover:bg-green-600" :style="{ height: chartBarHeight(month.lucro, maxValueMonthly) }" :title="chartMonthlyTitle(month.month, 'Lucro', month.lucro)"></div>
                                                        <div class="flex-1 bg-blue-500 rounded-t transition-all hover:bg-blue-600" :style="{ height: chartBarHeight(month.comissoes, maxValueMonthly) }" :title="chartMonthlyTitle(month.month, 'Comissão', month.comissoes)"></div>
                                                    </div>
                                                    <span class="text-xs text-gray-600 mt-2">{{ month.month }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="activeTab === 'todos'" class="bg-amber-50 border border-amber-200 rounded-lg p-5 text-center text-amber-700">
                                Selecione Hoje, Últimos 7 dias, Mensal ou Anual para visualizar os gráficos.
                            </div>
                        </div>
                    </template>
                </div>
            </main>

            <div
                v-if="!showRemoved && !isChartsView"
                :class="[
                    'fixed right-4 lg:right-4 z-40 flex flex-col gap-3 transition-all duration-300 ease-out',
                    isMobileNavVisible ? 'bottom-28 sm:bottom-32' : 'bottom-18 sm:bottom-20',
                    'lg:bottom-6'
                ]"
            >
                <!-- Botão Nova Entrada -->
                <button
                    type="button"
                    class="h-9 w-9 rounded-full shadow-lg bg-gradient-to-r from-orange-400 to-amber-400 text-white inline-flex items-center justify-center cursor-pointer transform hover:scale-110 hover:shadow-xl transition-all duration-200 border-2 border-white"
                    title="Nova Entrada (Shift + E)"
                    aria-label="Nova Entrada (Shift + E)"
                    @click="openCreateEntry"
                >
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 5v14m-7-7h14" />
                    </svg>
                </button>

                <!-- Botão Nova Saída -->
                <button
                    type="button"
                    class="h-9 w-9 rounded-full shadow-lg bg-gradient-to-r from-orange-500 to-amber-500 text-white inline-flex items-center justify-center cursor-pointer transform hover:scale-110 hover:shadow-xl transition-all duration-200 border-2 border-white"
                    title="Nova Saída (Shift + S)"
                    aria-label="Nova Saída (Shift + S)"
                    @click="openCreateExit"
                >
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 12h14" />
                    </svg>
                </button>
            </div>
            <Footer />
        </div>

    </div>
</template>
