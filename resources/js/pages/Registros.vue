<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import { useAuth } from '@/composables/useAuth';
import { usePrivacy } from '@/composables/usePrivacy';
import Sidebar from '@/components/Sidebar.vue';
import PageHeaderTitle from '@/components/PageHeaderTitle.vue';
import EntryModal from '@/components/modals/EntryModal.vue';
import ExitModal from '@/components/modals/ExitModal.vue';
import DeleteConfirmModal from '@/components/modals/DeleteConfirmModal.vue';

const auth = useAuth();
const { isPrivacyMode } = usePrivacy();
const company = ref<any>(null);
const isLoading = ref(false);
const activeTab = ref<'hoje' | 'ultimos7dias' | 'mensal'>('hoje');
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

// Modal states
const showEntryModal = ref(false);
const showExitModal = ref(false);
const showDeleteModal = ref(false);
const editingRecord = ref<any>(null);
const deletingRecord = ref<any>(null);
const modalMode = ref<'create' | 'edit'>('create');
const exitErrors = ref<{ message?: string; fields?: Record<string, string[]> }>({});
const entryErrors = ref<{ message?: string; fields?: Record<string, string[]> }>({});
const showRemoved = ref(false);

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

const fetchRegistros = async (page = 1) => {
    isLoading.value = true;
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
        registros.value = (json?.data) ?? [];
        pagination.value = json.pagination || {
            total: json.data?.length ?? 0,
            per_page: json.data?.length || 1,
            current_page: page,
        };
        currentPage.value = pagination.value.current_page || page;
    } catch (error) {
        console.error('Erro ao carregar registros:', error);
    } finally {
        isLoading.value = false;
    }
};

const handleDelete = (id: number) => {
    const registro = viewRegistros.value.find(r => r.id === id);
    if (registro) {
        deletingRecord.value = registro;
        showDeleteModal.value = true;
    }
};

const confirmDelete = async () => {
    if (!deletingRecord.value) return;

    try {
        const response = await fetch(`/api/registros/financial-records/${deletingRecord.value.id}`, {
            method: 'DELETE',
            headers: {
                'Authorization': `Bearer ${auth.token.value}`,
                'Accept': 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error('Erro ao remover registro');
        }

        await fetchRegistros(currentPage.value);
    } catch (error) {
        console.error('Erro ao remover registro:', error);
    } finally {
        showDeleteModal.value = false;
        deletingRecord.value = null;
    }
};

const handleRestore = async (id: number) => {
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

        await fetchRegistros(1);
    } catch (error) {
        console.error(error);
    }
};

const handleEdit = (id: number) => {
    const raw = registros.value.find((r: any) => r.id === id);
    if (!raw) return;

    const isEntry = raw.type === 'entry';
    if (isEntry) {
        const [datePart, timePart] = (raw.date || '').split('T');
        editingRecord.value = {
            id: raw.id,
            tipo: 'entrada',
            tipoEntrada: raw.entry_type?.name || '',
            tipoServico: raw.service_type?.name || '',
            responsavel: raw.collaborator?.name || '',
            descricao: raw.description || '',
            data: datePart || raw.date,
            hora: (timePart || '').slice(0, 5),
            valor: Number(raw.amount || 0),
            quantidade: raw.quantity || 1,
            desconto: Number(raw.discount || 0),
            observacao: '',
            entry_type_id: raw.entry_type_id,
            service_type_id: raw.service_type_id,
            collaborator_id: raw.collaborator_id,
        };
        modalMode.value = 'edit';
        showEntryModal.value = true;
    } else {
        const [datePart, timePart] = (raw.date || '').split('T');
        editingRecord.value = {
            id: raw.id,
            tipo: 'saida',
            tipoSaida: raw.exit_type?.name || '',
            categoria: raw.exit_type?.name || '',
            descricao: raw.description || '',
            data: datePart || raw.date,
            hora: (timePart || '').slice(0, 5),
            valor: Number(raw.amount || 0),
            quantidade: raw.quantity || 1,
            desconto: Number(raw.discount || 0),
            colaborador: raw.collaborator?.name || '',
            observacao: '',
            exit_type_id: raw.exit_type_id,
            collaborator_id: raw.collaborator_id,
        };
        modalMode.value = 'edit';
        showExitModal.value = true;
    }
};

const handleNovaEntrada = () => {
    editingRecord.value = null;
    modalMode.value = 'create';
    entryErrors.value = {};
    showEntryModal.value = true;
};

const handleNovaSaida = () => {
    editingRecord.value = null;
    modalMode.value = 'create';
    exitErrors.value = {};
    showExitModal.value = true;
};

const handleSaveEntry = async (entry: any) => {
    // Montar payload conforme API
    const payload = {
        date: entry.data,
        amount: entry.valor,
        quantity: entry.quantidade,
        discount: entry.desconto,
        description: entry.descricao,
        type: 'entry',
        entry_type_id: entry.entry_type_id ?? null,
        service_type_id: entry.service_type_id ?? null,
        collaborator_id: entry.collaborator_id ?? null,
    };

    const isEdit = modalMode.value === 'edit' && editingRecord.value?.id;
    const url = isEdit
        ? `/api/registros/financial-records/${editingRecord.value.id}`
        : '/api/registros/financial-records';
    const method = isEdit ? 'PUT' : 'POST';

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
            let payload: any = null;
            try { payload = await response.json(); } catch (e) { /* ignore */ }
            entryErrors.value = {
                message: payload?.message || 'Erro ao salvar entrada',
                fields: payload?.errors || {},
            };
            return;
        }

        // Atualizar lista a partir do backend
        await fetchRegistros(currentPage.value);
        showEntryModal.value = false;
        editingRecord.value = null;
        entryErrors.value = {};
    } catch (error) {
        console.error(error);
    }
};

const handleSaveEntryAndReference = (entry: any) => {
    handleSaveEntry(entry);
    // Reabrir modal para criar outro registro
    setTimeout(() => {
        handleNovaEntrada();
    }, 100);
};

const handleSaveExit = async (exit: any) => {
    exitErrors.value = {};
    const payload = {
        date: exit.data,
        amount: exit.valor,
        quantity: exit.quantidade,
        discount: exit.desconto,
        description: exit.descricao,
        type: 'exit',
        exit_type_id: exit.exit_type_id ?? null,
        collaborator_id: exit.collaborator_id ?? null,
    };

    const isEdit = modalMode.value === 'edit' && editingRecord.value?.id;
    const url = isEdit
        ? `/api/registros/financial-records/${editingRecord.value.id}`
        : '/api/registros/financial-records';
    const method = isEdit ? 'PUT' : 'POST';

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
            let payload: any = null;
            try {
                payload = await response.json();
            } catch (e) {
                // ignore
            }
            exitErrors.value = {
                message: payload?.message || 'Erro ao salvar saída',
                fields: payload?.errors || {},
            };
            return;
        }

        await fetchRegistros(currentPage.value);
        showExitModal.value = false;
        editingRecord.value = null;
    } catch (error) {
        console.error(error);
    }
};

const handleSaveExitAndReference = (exit: any) => {
    handleSaveExit(exit);
    // Reabrir modal para criar outro registro
    setTimeout(() => {
        handleNovaSaida();
    }, 100);
};

const handleVerRemovidos = () => {
    showRemoved.value = !showRemoved.value;
    currentPage.value = 1;
    fetchRegistros(1);
};

const goToPage = (page: number) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
        fetchRegistros(page);
    }
};

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

    await fetchRegistros();
});
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-amber-50 to-orange-100 flex">
        <Head title="Registros - MEU NEGÓCIO" />

        <!-- Sidebar -->
        <Sidebar active-route="registros" />

        <!-- Main Content -->
        <div class="flex-1 lg:ml-64 transition-all duration-300">
            <PageHeaderTitle 
                title="Registros" 
                subtitle="Gerencie suas entradas, saídas e comissões"
            />

            <!-- Main Content -->
            <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <!-- Tabs -->
                <div class="flex justify-center gap-2 mb-8">
                    <button
                        @click="(activeTab = 'hoje', currentPage = 1, fetchRegistros(1))"
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
                        @click="(activeTab = 'ultimos7dias', currentPage = 1, fetchRegistros(1))"
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
                        @click="(activeTab = 'mensal', currentPage = 1, fetchRegistros(1))"
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
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <!-- Action Buttons -->
                    <div class="flex flex-col sm:flex-row flex-wrap gap-3 mb-6">
                        <button
                            @click="handleNovaEntrada"
                            class="w-full sm:w-auto px-6 py-2 bg-gradient-to-r from-yellow-400 to-amber-500 text-white rounded-lg font-medium hover:shadow-lg transition-all"
                        >
                            Nova Entrada
                        </button>
                        <button
                            @click="handleNovaSaida"
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
                    <div class="space-y-3">
                        <div
                            v-for="registro in paginatedRegistros"
                            :key="registro.id"
                            class="flex flex-col md:flex-row md:items-center gap-3 md:gap-4 p-4 border border-gray-200 rounded-lg hover:shadow-md transition-shadow"
                        >
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
                            <div class="flex gap-2 flex-shrink-0 md:ml-auto">
                                <template v-if="showRemoved">
                                    <button
                                        @click="handleRestore(registro.id)"
                                        class="p-2 text-green-500 hover:bg-green-50 rounded-lg transition-colors"
                                        title="Restaurar"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                    </button>
                                </template>
                                <template v-else>
                                    <button
                                        @click="handleDelete(registro.id)"
                                        class="p-2 text-yellow-500 hover:bg-yellow-50 rounded-lg transition-colors"
                                        title="Remover"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                    <button
                                        @click="handleEdit(registro.id)"
                                        class="p-2 text-yellow-500 hover:bg-yellow-50 rounded-lg transition-colors"
                                        title="Editar"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-if="paginatedRegistros.length === 0" class="text-center py-12">
                        <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <p class="text-gray-500">Nenhum registro encontrado</p>
                    </div>

                    <!-- Pagination -->
                    <div v-if="totalPages > 1" class="flex items-center justify-center gap-2 mt-6">
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
        </div>

        <!-- Modals -->
        <EntryModal
            :show="showEntryModal"
            :entry="editingRecord"
            :mode="modalMode"
            :errors="entryErrors"
            :use-service-types="company?.use_service_types !== false"
            @close="showEntryModal = false; editingRecord = null"
            @save="handleSaveEntry"
            @save-and-reference="handleSaveEntryAndReference"
        />

        <ExitModal
            :show="showExitModal"
            :exit="editingRecord"
            :mode="modalMode"
            :errors="exitErrors"
            :use-service-types="company?.use_service_types !== false"
            @close="showExitModal = false; editingRecord = null"
            @save="handleSaveExit"
            @save-and-reference="handleSaveExitAndReference"
        />

        <DeleteConfirmModal
            :show="showDeleteModal"
            :record="deletingRecord"
            @close="showDeleteModal = false; deletingRecord = null"
            @confirm="confirmDelete"
        />
    </div>
</template>
