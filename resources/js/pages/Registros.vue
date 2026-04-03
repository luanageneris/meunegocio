<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import { useAuth } from '@/composables/useAuth';
import { usePrivacy } from '@/composables/usePrivacy';
import Sidebar from '@/components/Sidebar.vue';
import PageHeaderTitle from '@/components/PageHeaderTitle.vue';
import Footer from '@/components/Footer.vue';

const auth = useAuth();
const { isPrivacyMode } = usePrivacy();
const company = ref<any>(null);
const isLoading = ref(false);
const isListLoading = ref(false);
const isLoadingMore = ref(false);
const activeTab = ref<'hoje' | 'ultimos7dias' | 'mensal' | 'todos'>('hoje');
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
const currentPage = ref(1);
const pagination = ref<{ total: number; per_page: number; current_page: number }>({
    total: 0,
    per_page: 7,
    current_page: 1,
});

// Inline form state
const showNewEntryInline = ref(false);
const showNewExitInline = ref(false);
const editingRegistroId = ref<number | null>(null);
const editingRegistroType = ref<'entrada' | 'saida' | null>(null);
const deletingRecordId = ref<number | null>(null);
const isDeleteLoading = ref(false);
const isRestoreLoading = ref(false);
const restoringRecordId = ref<number | null>(null);
const showRemoved = ref(false);

// Dropdown options
const tiposEntrada = ref<any[]>([]);
const tiposSaida = ref<any[]>([]);
const tiposServico = ref<any[]>([]);
const colaboradoresOptions = ref<any[]>([]);
const optionsLoaded = ref(false);
const importFileInput = ref<HTMLInputElement | null>(null);
const showImportPanel = ref(false);
const selectedImportFile = ref<File | null>(null);
const importError = ref('');
const importSuccess = ref('');
const isImportLoading = ref(false);
const importSummary = ref<any | null>(null);
const importDefaults = ref({
    default_entry_type_id: null as number | null,
    default_exit_type_id: null as number | null,
    default_collaborator_id: null as number | null,
});

interface EntryForm {
    entry_type_id: number | null;
    service_type_id: number | null;
    collaborator_id: number | null;
    date: string;
    amount: number | string;
    quantity: number;
    discount: number;
    description: string;
}

interface ExitForm {
    exit_type_id: number | null;
    collaborator_id: number | null;
    date: string;
    amount: number | string;
    quantity: number;
    discount: number;
    description: string;
}

const createEmptyEntryForm = (): EntryForm => ({
    entry_type_id: null,
    service_type_id: null,
    collaborator_id: null,
    date: new Date().toISOString().split('T')[0],
    amount: '',
    quantity: 1,
    discount: 0,
    description: '',
});

const createEmptyExitForm = (): ExitForm => ({
    exit_type_id: null,
    collaborator_id: null,
    date: new Date().toISOString().split('T')[0],
    amount: '',
    quantity: 1,
    discount: 0,
    description: '',
});

const entryForm = ref<EntryForm>(createEmptyEntryForm());
const exitForm = ref<ExitForm>(createEmptyExitForm());
const entryFormErrors = ref<Record<string, string[]>>({});
const exitFormErrors = ref<Record<string, string[]>>({});
const isEntryLoading = ref(false);
const isExitLoading = ref(false);

const useServiceTypes = computed(() => company.value?.use_service_types !== false);

const registros = ref<any[]>([]);
const sortKey = ref<'valor' | 'categoria' | 'colaborador' | 'descricao' | 'dataOperacao' | 'registradoEm' | 'tipo'>('registradoEm');
const sortDir = ref<'asc' | 'desc'>('desc');

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

const findDefaultOptionId = (items: any[]) => {
    const normalized = (v: string) => (v || '').trim().toLowerCase();
    const found = items.find((item: any) => {
        const name = normalized(item?.name || '');
        return name === 'não definido' || name === 'nao definido';
    });

    return found?.id ?? null;
};

const viewRegistros = computed(() => registros.value.map((r: any) => ({
    id: r.id,
    // Determina tipo com base em presença de entry/exit
    tipo: (r.entry_type_id || r.entry_type) ? 'entrada' : 'saida',
    valor: Number(r.amount ?? 0),
    categoria: r.entry_type?.name ?? r.exit_type?.name ?? '-',
    descricao: r.description ?? '',
    colaborador: r.collaborator?.name ?? '',
    // Data do movimento (informada pelo usuário) e momento do registro
    dataOperacao: r.date ?? r.created_at ?? r.updated_at,
    registradoEm: r.created_at ?? r.updated_at,
    deleted: Boolean(r.deleted_at),
})));

const filteredRegistros = computed(() => {
    let filtered = viewRegistros.value.filter(r => (showRemoved.value ? r.deleted : !r.deleted));

    if (searchQuery.value) {
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

const hasMorePages = computed(() => currentPage.value < totalPages.value);

const infiniteScrollTrigger = ref<HTMLElement | null>(null);
let infiniteScrollObserver: IntersectionObserver | null = null;

const totalEntradas = computed(() => {
    return filteredRegistros.value
        .filter(r => r.tipo === 'entrada')
        .reduce((sum, r) => sum + r.valor, 0);
});

const totalSaidas = computed(() => {
    return filteredRegistros.value
        .filter(r => r.tipo === 'saida')
        .reduce((sum, r) => sum + r.valor, 0);
});

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
        const formatYMD = (d: Date) => d.toISOString().split('T')[0];
        let response: Response;

        if (activeTab.value === 'hoje' || activeTab.value === 'ultimos7dias' || activeTab.value === 'mensal') {
            let start: string;
            let end: string;

            if (activeTab.value === 'hoje') {
                end = formatYMD(today);
                start = end;
            } else if (activeTab.value === 'ultimos7dias') {
                const past = new Date(today);
                past.setDate(today.getDate() - 6);
                start = formatYMD(past);
                end = formatYMD(today);
            } else { // mensal
                const year = selectedYear.value;
                const monthIdx = selectedMonth.value - 1; // 0-based
                const first = new Date(year, monthIdx, 1);
                const last = new Date(year, monthIdx + 1, 0);
                start = formatYMD(first);
                end = formatYMD(last);
            }

            response = await fetch(`/api/registros/financial-records/between-dates?page=${page}&only_deleted=${showRemoved.value ? 1 : 0}` , {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${auth.token.value}`,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
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
        registros.value = append ? [...registros.value, ...incomingData] : incomingData;
        pagination.value = json.pagination || {
            total: json.data?.length ?? 0,
            per_page: json.data?.length || 1,
            current_page: page,
        };
        currentPage.value = pagination.value.current_page || page;
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

const changeTab = (tab: 'hoje' | 'ultimos7dias' | 'mensal' | 'todos') => {
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
    }
};

const fetchOptions = async () => {
    if (optionsLoaded.value) return;
    try {
        const headers: HeadersInit = {
            'Authorization': `Bearer ${auth.token.value}`,
            'Accept': 'application/json',
        };
        const [entryRes, exitRes, collabRes, serviceRes] = await Promise.all([
            fetch('/api/personalizar/entry-types', { headers }),
            fetch('/api/personalizar/exit-types', { headers }),
            fetch('/api/personalizar/collaborators', { headers }),
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
        optionsLoaded.value = true;
    } catch (e) {
        console.error('Erro ao carregar opções:', e);
    }
};

const openImportPicker = async () => {
    await fetchOptions();
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

const closeAllForms = () => {
    showNewEntryInline.value = false;
    showNewExitInline.value = false;
    editingRegistroId.value = null;
    editingRegistroType.value = null;
    entryForm.value = createEmptyEntryForm();
    exitForm.value = createEmptyExitForm();
    entryFormErrors.value = {};
    exitFormErrors.value = {};
};

const openCreateEntry = async () => {
    closeAllForms();
    await fetchOptions();
    entryForm.value = createEmptyEntryForm();
    showNewEntryInline.value = true;
};

const openCreateExit = async () => {
    closeAllForms();
    await fetchOptions();
    exitForm.value = createEmptyExitForm();
    showNewExitInline.value = true;
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
            amount: Number(raw.amount || 0),
            quantity: raw.quantity || 1,
            discount: Number(raw.discount || 0),
            description: raw.description || '',
        };
    } else {
        const [datePart] = (raw.date || '').split('T');
        exitForm.value = {
            exit_type_id: raw.exit_type_id ?? null,
            collaborator_id: raw.collaborator_id ?? null,
            date: datePart || raw.date,
            amount: Number(raw.amount || 0),
            quantity: raw.quantity || 1,
            discount: Number(raw.discount || 0),
            description: raw.description || '',
        };
    }
};

const saveEntry = async () => {
    if (isEntryLoading.value) return;
    entryFormErrors.value = {};
    const payload = {
        date: entryForm.value.date,
        amount: entryForm.value.amount,
        quantity: entryForm.value.quantity,
        discount: entryForm.value.discount,
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
            },
            body: JSON.stringify(payload),
        });
        if (!response.ok) {
            let errData: any = null;
            try { errData = await response.json(); } catch {}
            entryFormErrors.value = errData?.errors || { _: [errData?.message || 'Erro ao salvar entrada'] };
            return;
        }
        await fetchRegistros(currentPage.value);
        closeAllForms();
    } catch (error) {
        console.error(error);
    } finally {
        isEntryLoading.value = false;
    }
};

const saveExit = async () => {
    if (isExitLoading.value) return;
    exitFormErrors.value = {};
    const payload = {
        date: exitForm.value.date,
        amount: exitForm.value.amount,
        quantity: exitForm.value.quantity,
        discount: exitForm.value.discount,
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
            },
            body: JSON.stringify(payload),
        });
        if (!response.ok) {
            let errData: any = null;
            try { errData = await response.json(); } catch {}
            exitFormErrors.value = errData?.errors || { _: [errData?.message || 'Erro ao salvar saída'] };
            return;
        }
        await fetchRegistros(currentPage.value);
        closeAllForms();
    } catch (error) {
        console.error(error);
    } finally {
        isExitLoading.value = false;
    }
};

const handleVerRemovidos = () => {
    showRemoved.value = !showRemoved.value;
    currentPage.value = 1;
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

onMounted(async () => {
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

    await fetchRegistros(1, true);
    setupInfiniteScroll();
});

onBeforeUnmount(() => {
    cleanupInfiniteScroll();
});
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-amber-50 to-orange-100 flex overflow-x-hidden">
        <Head title="Registros - MEU NEGÓCIO" />

        <!-- Sidebar -->
        <Sidebar active-route="registros" />

        <!-- Main Content -->
        <div class="flex-1 lg:ml-64 transition-all duration-300 overflow-x-hidden flex flex-col">
            <PageHeaderTitle 
                title="Registros" 
                subtitle="Gerencie suas entradas, saídas e comissões"
            />

            <!-- Main Content -->
            <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <!-- Tabs -->
                <div class="flex justify-center gap-2 mb-8">
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

                <!-- Month/Year Selectors for Mensal -->
                <div v-if="activeTab === 'mensal'" class="flex justify-center gap-3 mb-6">
                    <select
                        v-model.number="selectedMonth"
                        @change="(currentPage = 1, fetchRegistros(1))"
                        class="px-4 py-2 border border-yellow-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:border-transparent bg-white"
                    >
                        <option v-for="m in monthOptions" :key="m.value" :value="m.value">
                            {{ m.label }}
                        </option>
                    </select>
                    <select
                        v-model.number="selectedYear"
                        @change="(currentPage = 1, fetchRegistros(1))"
                        class="px-4 py-2 border border-yellow-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:border-transparent bg-white"
                    >
                        <option v-for="y in [selectedYear - 2, selectedYear - 1, selectedYear, selectedYear + 1, selectedYear + 2]" :key="y" :value="y">
                            {{ y }}
                        </option>
                    </select>
                </div>

                <!-- Content Card -->
                <div class="bg-white rounded-lg shadow-lg p-6 ">
                    <!-- Action Buttons -->
                    <div class="flex flex-col sm:flex-row flex-wrap gap-3 mb-6">
                        <input
                            ref="importFileInput"
                            type="file"
                            accept=".xlsx,.xls"
                            class="hidden"
                            @change="onImportFileSelected"
                        />
                        <button
                            @click="openImportPicker"
                            class="w-full sm:w-auto px-6 py-2 bg-gradient-to-r from-emerald-400 to-teal-500 text-white rounded-lg font-medium hover:shadow-lg transition-all"
                        >
                            Importar Entradas e Saídas
                        </button>
                        <button
                            @click="openCreateEntry"
                            class="w-full sm:w-auto px-6 py-2 bg-gradient-to-r from-yellow-400 to-amber-500 text-white rounded-lg font-medium hover:shadow-lg transition-all"
                        >
                            Nova Entrada
                        </button>
                        <button
                            @click="openCreateExit"
                            class="w-full sm:w-auto px-6 py-2 bg-gradient-to-r from-orange-400 to-orange-500 text-white rounded-lg font-medium hover:shadow-lg transition-all"
                        >
                            Nova Saída
                        </button>
                        <button
                            @click="handleVerRemovidos"
                            :class="[
                                'w-full sm:w-auto px-6 py-2 rounded-lg font-medium transition-all border-2',
                                showRemoved
                                    ? 'border-orange-400 text-orange-600 bg-orange-50'
                                    : 'border-yellow-400 text-yellow-600 hover:bg-yellow-50'
                            ]"
                        >
                            {{ showRemoved ? 'Ver ativos' : 'Ver removidos' }}
                        </button>
                    </div>

                    <!-- Import Panel -->
                    <div v-if="showImportPanel" class="mb-6 border-2 border-emerald-200 rounded-lg p-6 bg-emerald-50/60">
                        <div class="flex items-center gap-3 mb-5">
                            <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <h3 class="text-base font-semibold text-emerald-700">Importar Entradas e Saídas</h3>
                        </div>

                        <div v-if="selectedImportFile" class="mb-5 p-4 bg-white border border-emerald-200 rounded-lg flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <div>
                                    <p class="text-sm font-medium text-gray-700">{{ selectedImportFile.name }}</p>
                                    <p class="text-xs text-gray-500">{{ formatFileSize(selectedImportFile.size) }}</p>
                                </div>
                            </div>
                            <button @click="clearImportFile" class="px-3 py-2 text-xs font-medium text-red-600 hover:bg-red-50 rounded-lg transition-colors flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                Trocar arquivo
                            </button>
                        </div>

                        <div v-else class="mb-5 p-4 bg-white border-2 border-dashed border-emerald-300 rounded-lg text-center">
                            <p class="text-sm text-gray-600">Nenhum arquivo selecionado</p>
                            <button @click="openImportPicker" class="mt-2 text-xs text-emerald-600 hover:text-emerald-700 font-medium underline">
                                Clique aqui para escolher um Excel
                            </button>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Tipo de Entrada padrão</label>
                                <select v-model="importDefaults.default_entry_type_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-400 bg-white text-sm">
                                    <option :value="null">Selecione</option>
                                    <option v-for="t in tiposEntrada" :key="t.id" :value="t.id">{{ t.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Tipo de Saída padrão</label>
                                <select v-model="importDefaults.default_exit_type_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-400 bg-white text-sm">
                                    <option :value="null">Selecione</option>
                                    <option v-for="t in tiposSaida" :key="t.id" :value="t.id">{{ t.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Colaborador padrão da Comissão</label>
                                <select v-model="importDefaults.default_collaborator_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-400 bg-white text-sm">
                                    <option :value="null">Selecione</option>
                                    <option v-for="c in colaboradoresOptions" :key="c.id" :value="c.id">{{ c.name }}</option>
                                </select>
                            </div>
                        </div>

                        <!-- Loading State -->
                        <div v-if="isImportLoading" class="mt-5 p-6 bg-white border border-blue-200 rounded-lg text-center">
                            <div class="inline-block mb-3">
                                <svg class="animate-spin h-8 w-8 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                            <p class="text-sm font-medium text-gray-700">Processando arquivo...</p>
                            <p class="text-xs text-gray-500 mt-2">Isto pode levar alguns segundos</p>
                        </div>

                        <!-- Error State -->
                        <div v-if="importError" class="mt-5 p-4 bg-red-50 border border-red-300 rounded-lg">
                            <div class="flex items-center gap-2 mb-2">
                                <svg class="w-5 h-5 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                                <p class="text-sm font-medium text-red-700">Erro na importação</p>
                            </div>
                            <p class="text-sm text-red-600">{{ importError }}</p>
                        </div>

                        <!-- Success State -->
                        <div v-if="importSuccess && !isImportLoading" class="mt-5 p-4 bg-emerald-50 border border-emerald-300 rounded-lg">
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                <p class="text-sm font-medium text-emerald-700">{{ importSuccess }}</p>
                            </div>
                        </div>

                        <!-- Summary Cards -->
                        <div v-if="importSummary && !isImportLoading" class="mt-5 grid grid-cols-2 md:grid-cols-5 gap-3">
                            <div class="bg-white border border-emerald-200 rounded-lg p-3 text-center">
                                <p class="text-2xl font-bold text-emerald-600">{{ importSummary.sheets_processed }}</p>
                                <p class="text-xs text-gray-600 mt-1">Abas</p>
                            </div>
                            <div class="bg-white border border-blue-200 rounded-lg p-3 text-center">
                                <p class="text-2xl font-bold text-blue-600">{{ importSummary.rows_processed }}</p>
                                <p class="text-xs text-gray-600 mt-1">Linhas</p>
                            </div>
                            <div class="bg-white border border-yellow-200 rounded-lg p-3 text-center">
                                <p class="text-2xl font-bold text-yellow-600">{{ importSummary.entries_created }}</p>
                                <p class="text-xs text-gray-600 mt-1">Entradas</p>
                            </div>
                            <div class="bg-white border border-orange-200 rounded-lg p-3 text-center">
                                <p class="text-2xl font-bold text-orange-600">{{ importSummary.exits_created }}</p>
                                <p class="text-xs text-gray-600 mt-1">Saídas</p>
                            </div>
                            <div class="bg-white border border-purple-200 rounded-lg p-3 text-center">
                                <p class="text-2xl font-bold text-purple-600">{{ importSummary.commissions_created }}</p>
                                <p class="text-xs text-gray-600 mt-1">Comissões</p>
                            </div>
                        </div>

                        <div v-if="!importSuccess || importError" class="flex justify-end gap-2 mt-6">
                            <button @click="cancelImport" :disabled="isImportLoading" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg transition-colors disabled:opacity-50">
                                Cancelar
                            </button>
                            <button @click="confirmImport" :disabled="isImportLoading || !selectedImportFile" class="px-6 py-2 text-sm font-medium text-white bg-gradient-to-r from-emerald-500 to-teal-500 rounded-lg hover:shadow-md transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
                                <svg v-if="!isImportLoading" class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                <span v-if="!isImportLoading">Confirmar importação</span>
                                <span v-else class="flex items-center gap-2">
                                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Importando...
                                </span>
                            </button>
                        </div>
                        <div v-if="importSuccess && !importError" class="flex justify-end mt-6">
                            <button @click="cancelImport" class="px-6 py-2 text-sm font-medium text-white bg-gradient-to-r from-blue-500 to-cyan-500 rounded-lg hover:shadow-md transition-all flex items-center gap-2">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                Fechar
                            </button>
                        </div>
                    </div>

                    <!-- Inline Create Entry Form -->
                    <div v-if="showNewEntryInline" class="mb-6 border-2 border-yellow-300 rounded-lg p-4 bg-yellow-50/50">
                        <h3 class="text-sm font-semibold text-yellow-700 mb-4">Nova Entrada</h3>
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
                                <input type="date" v-model="entryForm.date" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 text-sm" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Valor (R$)</label>
                                <input type="number" step="0.01" min="0" v-model="entryForm.amount" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 text-sm" placeholder="0,00" />
                            </div>
                            <div v-if="useServiceTypes">
                                <label class="block text-xs font-medium text-gray-500 mb-1">Quantidade</label>
                                <input type="number" min="1" v-model="entryForm.quantity" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 text-sm" />
                            </div>
                            <div v-if="useServiceTypes">
                                <label class="block text-xs font-medium text-gray-500 mb-1">Desconto (R$)</label>
                                <input type="number" step="0.01" min="0" v-model="entryForm.discount" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 text-sm" placeholder="0,00" />
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-medium text-gray-500 mb-1">Descrição</label>
                                <input type="text" v-model="entryForm.description" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 text-sm" placeholder="Opcional" />
                            </div>
                        </div>
                        <div class="flex justify-end gap-2 mt-4">
                            <button @click="closeAllForms" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">Cancelar</button>
                            <button @click="saveEntry" :disabled="isEntryLoading" class="px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-yellow-400 to-amber-500 rounded-lg hover:shadow-md transition-all disabled:opacity-50">
                                {{ isEntryLoading ? 'Salvando...' : 'Salvar' }}
                            </button>
                        </div>
                    </div>

                    <!-- Inline Create Exit Form -->
                    <div v-if="showNewExitInline" class="mb-6 border-2 border-orange-300 rounded-lg p-4 bg-orange-50/50">
                        <h3 class="text-sm font-semibold text-orange-700 mb-4">Nova Saída</h3>
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
                                <input type="date" v-model="exitForm.date" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400 text-sm" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Valor (R$)</label>
                                <input type="number" step="0.01" min="0" v-model="exitForm.amount" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400 text-sm" placeholder="0,00" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Quantidade</label>
                                <input type="number" min="1" v-model="exitForm.quantity" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400 text-sm" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Desconto (R$)</label>
                                <input type="number" step="0.01" min="0" v-model="exitForm.discount" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400 text-sm" placeholder="0,00" />
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-medium text-gray-500 mb-1">Descrição</label>
                                <input type="text" v-model="exitForm.description" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400 text-sm" placeholder="Opcional" />
                            </div>
                        </div>
                        <div class="flex justify-end gap-2 mt-4">
                            <button @click="closeAllForms" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">Cancelar</button>
                            <button @click="saveExit" :disabled="isExitLoading" class="px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-orange-400 to-orange-500 rounded-lg hover:shadow-md transition-all disabled:opacity-50">
                                {{ isExitLoading ? 'Salvando...' : 'Salvar' }}
                            </button>
                        </div>
                    </div>

                    <!-- Summary -->
                    <div class="mb-6 space-y-1 text-sm text-gray-600">
                        <p>Total de registros: <span class="font-semibold">{{ totalRegistros }}</span></p>
                        <p>Valor Total de Entrada: <span class="font-semibold text-yellow-600">{{ formatCurrency(totalEntradas) }}</span></p>
                        <p>Valor Total de Saída: <span class="font-semibold text-orange-600">{{ formatCurrency(totalSaidas) }}</span></p>
                    </div>

                    <!-- Search -->
                    <div class="mb-6">
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Pesquisar"
                            class="w-full px-4 py-3 border-2 border-yellow-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:border-transparent"
                        />
                    </div>

                    <!-- Sorting Controls -->
                    <div class="mb-4 flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-2">
                            <label class="text-sm text-gray-600">Ordenar por</label>
                            <select
                                v-model="sortKey"
                                class="px-3 py-2 border border-yellow-300 rounded-lg bg-white text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:border-transparent"
                            >
                                <option value="registradoEm">Registrado em</option>
                                <option value="dataOperacao">Data do movimento</option>
                                <option value="valor">Valor</option>
                                <option value="categoria">Categoria</option>
                                <option value="colaborador">Colaborador</option>
                                <option value="descricao">Descrição</option>
                                <option value="tipo">Tipo</option>
                            </select>
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="text-sm text-gray-600">Direção</label>
                            <select
                                v-model="sortDir"
                                class="px-3 py-2 border border-yellow-300 rounded-lg bg-white text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:border-transparent"
                            >
                                <option value="asc">Ascendente</option>
                                <option value="desc">Descendente</option>
                            </select>
                        </div>
                    </div>

                    <!-- Records List -->
                    <div class="space-y-3 min-h-[320px] w-full">
                        <template v-if="isLoading || isListLoading">
                            <div
                                v-for="n in 5"
                                :key="`registro-skeleton-${n}`"
                                class="border border-gray-200 rounded-lg p-4 bg-gray-50"
                            >
                                <div class="flex flex-col md:flex-row md:items-center gap-3 md:gap-4">
                                    <div class="md:w-24 h-5 bg-gray-200 rounded animate-pulse"></div>
                                    <div class="md:w-32 h-5 bg-gray-200 rounded animate-pulse"></div>
                                    <div class="md:w-40 h-5 bg-gray-200 rounded animate-pulse"></div>
                                    <div class="w-full md:flex-1 h-5 bg-gray-200 rounded animate-pulse"></div>
                                    <div class="w-full md:w-64 space-y-2">
                                        <div class="h-4 bg-gray-200 rounded animate-pulse"></div>
                                        <div class="h-4 bg-gray-200 rounded animate-pulse"></div>
                                    </div>
                                    <div class="md:w-24 h-5 bg-gray-200 rounded animate-pulse"></div>
                                </div>
                            </div>
                        </template>

                        <div
                            v-else-if="paginatedRegistros.length === 0"
                            class="border border-gray-200 rounded-lg"
                        >
                            <div class="flex flex-col md:flex-row md:items-center gap-3 md:gap-4 p-4 text-sm text-gray-500">
                                <div class="md:w-24 flex-shrink-0">-</div>
                                <div class="md:w-32 flex-shrink-0">-</div>
                                <div class="md:w-40 flex-shrink-0">-</div>
                                <div class="w-full md:flex-1 font-medium text-gray-500">Nenhum registro encontrado</div>
                                <div class="w-full md:w-24 flex-shrink-0 md:text-right">-</div>
                                <div class="md:w-24 flex-shrink-0"></div>
                            </div>
                        </div>

                        <div
                            v-else
                            v-for="registro in paginatedRegistros"
                            :key="registro.id"
                            class="border border-gray-200 rounded-lg transition-shadow"
                            :class="editingRegistroId === registro.id ? 'ring-2 ring-yellow-300 shadow-md' : 'hover:shadow-md'"
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
                                        <input type="date" v-model="entryForm.date" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 text-sm" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1">Valor (R$)</label>
                                        <input type="number" step="0.01" min="0" v-model="entryForm.amount" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 text-sm" placeholder="0,00" />
                                    </div>
                                    <div v-if="useServiceTypes">
                                        <label class="block text-xs font-medium text-gray-500 mb-1">Quantidade</label>
                                        <input type="number" min="1" v-model="entryForm.quantity" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 text-sm" />
                                    </div>
                                    <div v-if="useServiceTypes">
                                        <label class="block text-xs font-medium text-gray-500 mb-1">Desconto (R$)</label>
                                        <input type="number" step="0.01" min="0" v-model="entryForm.discount" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 text-sm" placeholder="0,00" />
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="block text-xs font-medium text-gray-500 mb-1">Descrição</label>
                                        <input type="text" v-model="entryForm.description" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 text-sm" placeholder="Opcional" />
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
                                        <input type="date" v-model="exitForm.date" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400 text-sm" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1">Valor (R$)</label>
                                        <input type="number" step="0.01" min="0" v-model="exitForm.amount" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400 text-sm" placeholder="0,00" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1">Quantidade</label>
                                        <input type="number" min="1" v-model="exitForm.quantity" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400 text-sm" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1">Desconto (R$)</label>
                                        <input type="number" step="0.01" min="0" v-model="exitForm.discount" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400 text-sm" placeholder="0,00" />
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="block text-xs font-medium text-gray-500 mb-1">Descrição</label>
                                        <input type="text" v-model="exitForm.description" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400 text-sm" placeholder="Opcional" />
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
                            <div class="flex flex-col md:flex-row md:items-center gap-3 md:gap-4 p-4">
                            <!-- Valor -->
                            <div class="md:w-24 flex-shrink-0">
                                <span
                                    :class="[
                                        'font-bold text-sm',
                                        registro.tipo === 'entrada' ? 'text-yellow-500' : 'text-orange-500'
                                    ]"
                                >
                                    {{ registro.tipo === 'entrada' ? '+ ' : '- ' }}{{ formatCurrency(registro.valor) }}
                                </span>
                            </div>

                            <!-- Categoria -->
                            <div class="md:w-32 flex-shrink-0">
                                <span class="text-gray-700 text-sm truncate block">{{ registro.categoria }}</span>
                            </div>

                            <!-- Colaborador -->
                            <div class="md:w-40 flex-shrink-0">
                                <span class="text-gray-700 text-sm truncate block">{{ registro.colaborador || '-' }}</span>
                            </div>

                            <!-- Descrição -->
                            <div class="w-full md:flex-1">
                                <span class="text-gray-600 text-sm block">{{ registro.descricao || '-' }}</span>
                            </div>

                            <!-- Datas -->
                            <div class="w-full md:w-64 flex-shrink-0 md:text-right">
                                <div class="text-gray-700 text-xs">Movimento: {{ formatDate(registro.dataOperacao) }}</div>
                                <div class="text-gray-500 text-xs">Registrado: {{ formatDateTime(registro.registradoEm) }}</div>
                            </div>

                            <!-- Actions -->
                            <div class="flex gap-2 flex-shrink-0 md:ml-auto md:w-24 justify-end">
                                <template v-if="showRemoved">
                                    <button
                                        @click="handleRestore(registro.id)"
                                        :disabled="isRestoreLoading"
                                        :class="[
                                            'p-2 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed',
                                            restoringRecordId === registro.id && isRestoreLoading
                                                ? 'text-green-600 bg-green-50'
                                                : 'text-green-500 hover:bg-green-50'
                                        ]"
                                        :title="restoringRecordId === registro.id && isRestoreLoading ? 'Restaurando...' : 'Restaurar'"
                                    >
                                        <svg
                                            :class="['w-5 h-5', restoringRecordId === registro.id && isRestoreLoading ? 'animate-spin' : '']"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                    </button>
                                </template>
                                <template v-else>
                                    <button
                                        @click="handleDelete(registro.id)"
                                        class="p-2 text-red-500 hover:bg-red-50 rounded-lg transition-colors"
                                        title="Remover"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                    <button
                                        @click="openEditRecord(registro.id)"
                                        class="p-2 text-orange-500 hover:bg-orange-50 rounded-lg transition-colors"
                                        title="Editar"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                </template>
                            </div>
                            </div>

                            <!-- Inline delete confirmation -->
                            <div
                                v-if="deletingRecordId === registro.id"
                                class="border-t border-red-200 bg-red-50/80 px-4 py-3 rounded-b-lg flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
                            >
                                <p class="text-sm font-medium text-gray-800">
                                    Tem certeza que deseja remover este registro de
                                    <span class="font-semibold text-red-600">{{ registro.categoria }}</span>?
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
                </div>
            </main>
            <Footer />
        </div>

    </div>
</template>
