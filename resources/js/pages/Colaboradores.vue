<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, computed, onMounted, nextTick } from 'vue';
import axios from 'axios';
import Sidebar from '@/components/Sidebar.vue';
import FixedPageHeaderTitle from '@/components/FixedPageHeaderTitle.vue';
import { useAuth } from '@/composables/useAuth';
import { useInputMasks } from '@/composables/useInputMasks';
import LoadingSpinner from '@/components/LoadingSpinner.vue';
import Footer from '@/components/Footer.vue';

interface Collaborator {
    id: number;
    name: string;
    cpf: string;
    active: boolean;
    created_at?: string;
    deleted_at?: string | null;
}

type CollaboratorSummaryPeriod = 'ultimos7dias' | 'mensal' | 'anual' | 'todos';

type CollaboratorSummary = {
    sales_total: number;
    commission_total: number;
};

type CollaboratorFormData = Pick<Collaborator, 'name' | 'cpf' | 'active'>;

const createEmptyCollaboratorForm = (): CollaboratorFormData => ({
    name: '',
    cpf: '',
    active: true,
});

const auth = useAuth();
const { formatCpf } = useInputMasks();
const collaborators = ref<Collaborator[]>([]);
const isLoading = ref(false);
const isListLoading = ref(false);
const errorMessage = ref('');
const search = ref('');
const showRemoved = ref(false);
const currentPage = ref(1);
const itemsPerPage = 8;

const showNewInline = ref(false);
const showEditInline = ref(false);
const showDeleteConfirmInline = ref(false);
const editingCollaborator = ref<Collaborator | null>(null);
const deletingCollaborator = ref<Collaborator | null>(null);
const restoringCollaborator = ref<Collaborator | null>(null);

const newCollaboratorForm = ref<CollaboratorFormData>(createEmptyCollaboratorForm());
const editCollaboratorForm = ref<CollaboratorFormData>(createEmptyCollaboratorForm());
const newCollaboratorError = ref('');
const editCollaboratorError = ref('');

const isFormLoading = ref(false);
const isDeleteLoading = ref(false);
const isRestoreLoading = ref(false);
const showSummaryModal = ref(false);
const summaryCollaborator = ref<Collaborator | null>(null);
const summaryPeriod = ref<CollaboratorSummaryPeriod>('ultimos7dias');
const isSummaryLoading = ref(false);
const summaryError = ref('');
const summary = ref<CollaboratorSummary>({
    sales_total: 0,
    commission_total: 0,
});

const newNameInputRef = ref<HTMLInputElement | null>(null);
const editNameInputRef = ref<HTMLInputElement | null>(null);
const deleteConfirmButtonRef = ref<HTMLButtonElement | null>(null);

const applySoftDeleteLocally = (items: Collaborator[], itemId: number, keepRemovedVisible: boolean) => {
    if (keepRemovedVisible) {
        const timestamp = new Date().toISOString();
        return items.map((item) => (item.id === itemId ? { ...item, deleted_at: item.deleted_at ?? timestamp } : item));
    }

    return items.filter((item) => item.id !== itemId);
};

const applyRestoreLocally = (items: Collaborator[], itemId: number) => (
    items.map((item) => (item.id === itemId ? { ...item, deleted_at: null, active: true } : item))
);

const filtered = computed(() => {
    if (!search.value) return collaborators.value;
    const q = search.value.toLowerCase();
    return collaborators.value.filter((c) => (
        c.name.toLowerCase().includes(q) || c.cpf.toLowerCase().includes(q)
    ));
});

const paginatedCollaborators = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    return filtered.value.slice(start, end);
});

const totalPages = computed(() => Math.ceil(filtered.value.length / itemsPerPage));

const canSuggestCreateFromSearch = computed(() => {
    const query = search.value.trim();
    return Boolean(
        query &&
        !showRemoved.value &&
        !showNewInline.value &&
        filtered.value.length === 0
    );
});

const totalAtivos = computed(() => collaborators.value.filter((c) => c.active && !c.deleted_at).length);
const totalInativos = computed(() => collaborators.value.filter((c) => !c.active && !c.deleted_at).length);
const totalRemovidos = computed(() => collaborators.value.filter((c) => c.deleted_at).length);

const closeAllInlineForms = () => {
    showNewInline.value = false;
    showEditInline.value = false;
    showDeleteConfirmInline.value = false;

    editingCollaborator.value = null;
    deletingCollaborator.value = null;

    newCollaboratorForm.value = createEmptyCollaboratorForm();
    editCollaboratorForm.value = createEmptyCollaboratorForm();

    newCollaboratorError.value = '';
    editCollaboratorError.value = '';
};

const fetchCollaborators = async (showPageLoading = false) => {
    if (!auth.isAuthenticated.value) {
        window.location.href = '/login';
        return;
    }

    if (showPageLoading) {
        isLoading.value = true;
    } else {
        isListLoading.value = true;
    }
    errorMessage.value = '';

    try {
        const endpoint = showRemoved.value
            ? '/api/personalizar/collaborators/with-trashed'
            : '/api/personalizar/collaborators';
        const { data } = await axios.get(endpoint);
        collaborators.value = data;
    } catch (error: any) {
        errorMessage.value = error?.response?.data?.message || 'Erro ao carregar colaboradores';
    } finally {
        if (showPageLoading) {
            isLoading.value = false;
        } else {
            isListLoading.value = false;
        }
    }
};

const handleNew = () => {
    closeAllInlineForms();
    showNewInline.value = true;
    nextTick(() => newNameInputRef.value?.focus());
};

const openNewFromSearch = () => {
    closeAllInlineForms();
    newCollaboratorForm.value = { ...createEmptyCollaboratorForm(), name: search.value.trim() };
    showNewInline.value = true;
    nextTick(() => newNameInputRef.value?.focus());
};

const cancelNew = () => {
    showNewInline.value = false;
    newCollaboratorForm.value = createEmptyCollaboratorForm();
    newCollaboratorError.value = '';
};

const saveNew = async () => {
    const payload = {
        name: newCollaboratorForm.value.name.trim(),
        cpf: formatCpf(newCollaboratorForm.value.cpf.trim()),
        active: newCollaboratorForm.value.active,
    };

    if (!payload.name || !payload.cpf) {
        newCollaboratorError.value = 'Nome e CPF são obrigatórios';
        nextTick(() => newNameInputRef.value?.focus());
        return;
    }

    if (isFormLoading.value) return;

    isFormLoading.value = true;
    newCollaboratorError.value = '';

    try {
        const { data } = await axios.post('/api/personalizar/collaborators', payload);

        if (showRemoved.value && data.deleted_at) {
            await fetchCollaborators();
        } else {
            collaborators.value = [data, ...collaborators.value];
        }

        cancelNew();
    } catch (error: any) {
        const message = error?.response?.data?.errors?.cpf?.[0] || error?.response?.data?.message || 'Erro ao salvar colaborador';
        newCollaboratorError.value = message;
        nextTick(() => newNameInputRef.value?.focus());
    } finally {
        isFormLoading.value = false;
    }
};

const handleEdit = (collab: Collaborator) => {
    if (collab.deleted_at) return;

    closeAllInlineForms();
    editingCollaborator.value = { ...collab };
    editCollaboratorForm.value = {
        name: collab.name,
        cpf: formatCpf(collab.cpf),
        active: collab.active,
    };
    showEditInline.value = true;
    nextTick(() => editNameInputRef.value?.focus());
};

const cancelEdit = () => {
    showEditInline.value = false;
    editingCollaborator.value = null;
    editCollaboratorForm.value = createEmptyCollaboratorForm();
    editCollaboratorError.value = '';
};

const saveEdit = async () => {
    if (!editingCollaborator.value) return;

    const payload = {
        name: editCollaboratorForm.value.name.trim(),
        cpf: formatCpf(editCollaboratorForm.value.cpf.trim()),
        active: editCollaboratorForm.value.active,
    };

    if (!payload.name || !payload.cpf) {
        editCollaboratorError.value = 'Nome e CPF são obrigatórios';
        nextTick(() => editNameInputRef.value?.focus());
        return;
    }

    if (isFormLoading.value) return;

    isFormLoading.value = true;
    editCollaboratorError.value = '';

    try {
        const { data } = await axios.put(`/api/personalizar/collaborators/${editingCollaborator.value.id}`, payload);
        collaborators.value = collaborators.value.map((c) => (c.id === editingCollaborator.value!.id ? data : c));
        cancelEdit();
    } catch (error: any) {
        const message = error?.response?.data?.errors?.cpf?.[0] || error?.response?.data?.message || 'Erro ao salvar colaborador';
        editCollaboratorError.value = message;
        nextTick(() => editNameInputRef.value?.focus());
    } finally {
        isFormLoading.value = false;
    }
};

const handleDelete = (collab: Collaborator) => {
    if (collab.deleted_at) return;

    deletingCollaborator.value = collab;
    showDeleteConfirmInline.value = true;
    nextTick(() => deleteConfirmButtonRef.value?.focus());
};

const cancelDelete = () => {
    showDeleteConfirmInline.value = false;
    deletingCollaborator.value = null;
};

const confirmDelete = async () => {
    if (!deletingCollaborator.value) return;

    isDeleteLoading.value = true;

    try {
        const collaboratorId = deletingCollaborator.value.id;
        await axios.delete(`/api/personalizar/collaborators/${collaboratorId}`);
        collaborators.value = applySoftDeleteLocally(collaborators.value, collaboratorId, showRemoved.value);
        cancelDelete();
    } catch (error: any) {
        const message = error?.response?.data?.message || 'Erro ao remover colaborador';
        errorMessage.value = message;
    } finally {
        isDeleteLoading.value = false;
    }
};

const handleRestore = async (collab: Collaborator) => {
    if (!collab.deleted_at || isRestoreLoading.value) return;
    restoringCollaborator.value = collab;
    await confirmRestore();
};

const confirmRestore = async () => {
    if (!restoringCollaborator.value) return;

    isRestoreLoading.value = true;

    try {
        const restoringName = restoringCollaborator.value.name;
        const collaboratorId = restoringCollaborator.value.id;
        await axios.post(`/api/personalizar/collaborators/${collaboratorId}/restore`);
        collaborators.value = applyRestoreLocally(collaborators.value, collaboratorId);
        restoringCollaborator.value = null;
    } catch (error: any) {
        const message = error?.response?.data?.message || 'Erro ao restaurar colaborador';
        errorMessage.value = message;
    } finally {
        isRestoreLoading.value = false;
    }
};

const toggleRemoved = () => {
    showRemoved.value = !showRemoved.value;
    currentPage.value = 1;
    closeAllInlineForms();
    fetchCollaborators();
};

const goToPage = (page: number) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
    }
};

const handleNewCpfInput = (event: Event) => {
    const target = event.target as HTMLInputElement;
    newCollaboratorForm.value.cpf = formatCpf(target.value);
};

const handleEditCpfInput = (event: Event) => {
    const target = event.target as HTMLInputElement;
    editCollaboratorForm.value.cpf = formatCpf(target.value);
};

const clearSearch = () => {
    search.value = '';
    currentPage.value = 1;
};

const periodLabel = computed(() => {
    const labels: Record<CollaboratorSummaryPeriod, string> = {
        ultimos7dias: 'Últimos 7 dias',
        mensal: 'Mensal',
        anual: 'Anual',
        todos: 'Tudo',
    };

    return labels[summaryPeriod.value];
});

const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(Number(value || 0));
};

const loadCollaboratorSummary = async () => {
    if (!summaryCollaborator.value) return;

    isSummaryLoading.value = true;
    summaryError.value = '';

    try {
        const { data } = await axios.get(`/api/personalizar/collaborators/${summaryCollaborator.value.id}/summary`, {
            params: { period: summaryPeriod.value },
        });

        summary.value = {
            sales_total: Number(data?.data?.sales_total || 0),
            commission_total: Number(data?.data?.commission_total || 0),
        };
    } catch (error: any) {
        summaryError.value = error?.response?.data?.message || 'Erro ao carregar resumo do colaborador';
    } finally {
        isSummaryLoading.value = false;
    }
};

const openSummaryModal = async (collab: Collaborator) => {
    if (collab.deleted_at) return;

    summaryCollaborator.value = collab;
    summaryPeriod.value = 'ultimos7dias';
    summary.value = { sales_total: 0, commission_total: 0 };
    summaryError.value = '';
    showSummaryModal.value = true;

    await loadCollaboratorSummary();
};

const closeSummaryModal = () => {
    showSummaryModal.value = false;
    summaryCollaborator.value = null;
    summaryError.value = '';
};

const changeSummaryPeriod = async (period: CollaboratorSummaryPeriod) => {
    summaryPeriod.value = period;
    await loadCollaboratorSummary();
};

onMounted(() => fetchCollaborators(true));
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-amber-50 to-orange-100 flex overflow-x-hidden">
        <Head title="Colaboradores - MEU NEGÓCIO" />

        <Sidebar active-route="colaboradores" />

        <div class="flex-1 min-w-0 lg:ml-64 transition-all duration-300 overflow-x-hidden flex flex-col">
            <FixedPageHeaderTitle
                title="Colaboradores"
                subtitle="Gerencie os colaboradores da sua empresa"
            />

            <main class="w-full min-w-0 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 flex flex-col overflow-x-hidden">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex flex-wrap items-center gap-3 mb-4">
                        <button
                            @click="handleNew"
                            class="px-5 py-2 bg-gradient-to-r from-amber-400 to-orange-500 text-white rounded-lg font-medium hover:shadow-lg transition-all"
                        >
                            Novo
                        </button>
                        <button
                            @click="toggleRemoved"
                            :class="[
                                'px-5 py-2 border-2 rounded-lg font-medium transition-all',
                                showRemoved
                                    ? 'border-red-300 text-red-600 bg-red-50 hover:bg-red-100'
                                    : 'border-amber-300 text-amber-500 hover:bg-amber-50'
                            ]"
                        >
                            {{ showRemoved ? 'Ocultar removidos' : 'Ver removidos' }}
                        </button>
                        <span class="text-sm text-gray-500">Ativos: {{ totalAtivos }}</span>
                        <span v-if="!showRemoved" class="text-sm text-gray-500">Inativos: {{ totalInativos }}</span>
                        <span v-if="showRemoved" class="text-sm text-red-500">Removidos: {{ totalRemovidos }}</span>
                    </div>

                    <div class="mb-4">
                        <div class="relative">
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Pesquise por nome ou CPF"
                                class="w-full px-4 py-3 pr-12 border-2 border-amber-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent"
                            />
                            <button
                                v-if="search.trim()"
                                @click="clearSearch"
                                type="button"
                                class="absolute right-2 top-1/2 -translate-y-1/2 p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors cursor-pointer"
                                title="Limpar filtro"
                                aria-label="Limpar filtro"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div v-if="errorMessage" class="mb-4 text-sm text-red-600 bg-red-50 border border-red-100 rounded-lg p-3">
                        {{ errorMessage }}
                    </div>

                    <LoadingSpinner v-if="isLoading" message="Carregando colaboradores..." />

                    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-3 items-start">
                        <div v-if="isListLoading" class="md:col-span-2 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                            Atualizando itens...
                        </div>
                        <div
                            v-if="showNewInline"
                            class="self-start border-2 border-amber-300 rounded-lg p-4 shadow-sm bg-amber-50/60"
                        >
                            <div class="space-y-2" @keydown.esc.prevent="cancelNew">
                                <div class="flex items-center gap-2">
                                    <input
                                        ref="newNameInputRef"
                                        v-model="newCollaboratorForm.name"
                                        type="text"
                                        placeholder="Nome do colaborador"
                                        class="flex-1 bg-transparent text-base font-semibold text-gray-800 placeholder:text-gray-400 focus:outline-none border-b border-gray-300"
                                        :disabled="isFormLoading"
                                        @keydown.enter.prevent="saveNew"
                                    />
                                    <div class="flex items-center gap-1 shrink-0">
                                        <button
                                            @click="saveNew"
                                            :disabled="isFormLoading"
                                            class="p-2 text-amber-600 hover:bg-amber-100 rounded-lg transition-colors disabled:opacity-50"
                                            title="Salvar"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </button>
                                        <button
                                            @click="cancelNew"
                                            :disabled="isFormLoading"
                                            class="p-2 text-gray-500 hover:bg-gray-100 rounded-lg transition-colors disabled:opacity-50"
                                            title="Cancelar"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input
                                        v-model="newCollaboratorForm.cpf"
                                        @input="handleNewCpfInput"
                                        type="text"
                                        placeholder="CPF"
                                        class="flex-1 bg-transparent text-xs text-gray-700 placeholder:text-gray-400 focus:outline-none border-b border-gray-300"
                                        :disabled="isFormLoading"
                                        @keydown.enter.prevent="saveNew"
                                    />
                                    <label class="inline-flex items-center gap-1 text-xs text-gray-700 shrink-0">
                                        <input v-model="newCollaboratorForm.active" type="checkbox" class="rounded border-gray-300 text-amber-500 focus:ring-amber-400" :disabled="isFormLoading" />
                                        Ativo
                                    </label>
                                </div>
                                <p v-if="newCollaboratorError" class="text-xs text-red-600">{{ newCollaboratorError }}</p>
                            </div>
                        </div>

                        <div
                            v-for="collab in paginatedCollaborators"
                            :key="collab.id"
                            :class="[
                                'self-start border rounded-lg p-4 shadow-sm transition-shadow',
                                editingCollaborator?.id === collab.id && showEditInline
                                    ? 'border-2 border-amber-300 bg-amber-50/60'
                                    : [
                                        'border-gray-200',
                                        collab.deleted_at ? 'bg-gray-50 opacity-80' : 'hover:shadow-md'
                                    ]
                            ]"
                        >
                            <div v-if="editingCollaborator?.id === collab.id && showEditInline" class="space-y-2" @keydown.esc.prevent="cancelEdit">
                                <div class="flex items-center gap-2">
                                    <input
                                        ref="editNameInputRef"
                                        v-model="editCollaboratorForm.name"
                                        type="text"
                                        class="flex-1 bg-transparent text-base font-semibold text-gray-800 placeholder:text-gray-400 focus:outline-none border-b border-gray-300"
                                        :disabled="isFormLoading"
                                        @keydown.enter.prevent="saveEdit"
                                    />
                                    <div class="flex items-center gap-1 shrink-0">
                                        <button
                                            @click="saveEdit"
                                            :disabled="isFormLoading"
                                            class="p-2 text-amber-600 hover:bg-amber-100 rounded-lg transition-colors disabled:opacity-50"
                                            title="Salvar"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </button>
                                        <button
                                            @click="cancelEdit"
                                            :disabled="isFormLoading"
                                            class="p-2 text-gray-500 hover:bg-gray-100 rounded-lg transition-colors disabled:opacity-50"
                                            title="Cancelar"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input
                                        v-model="editCollaboratorForm.cpf"
                                        @input="handleEditCpfInput"
                                        type="text"
                                        class="flex-1 bg-transparent text-xs text-gray-700 placeholder:text-gray-400 focus:outline-none border-b border-gray-300"
                                        :disabled="isFormLoading"
                                        @keydown.enter.prevent="saveEdit"
                                    />
                                    <label class="inline-flex items-center gap-1 text-xs text-gray-700 shrink-0">
                                        <input v-model="editCollaboratorForm.active" type="checkbox" class="rounded border-gray-300 text-amber-500 focus:ring-amber-400" :disabled="isFormLoading" />
                                        Ativo
                                    </label>
                                </div>
                                <p v-if="editCollaboratorError" class="text-xs text-red-600">{{ editCollaboratorError }}</p>
                            </div>

                            <div v-else class="flex justify-between items-start">
                                <div>
                                    <p class="font-semibold text-gray-800">{{ collab.name }}</p>
                                    <p class="text-xs text-gray-500">CPF: {{ formatCpf(collab.cpf) }}</p>
                                    <p class="mt-1 text-xs flex items-center gap-2">
                                        <span :class="collab.active ? 'text-green-600' : 'text-gray-400'">
                                            {{ collab.active ? 'Ativo' : 'Inativo' }}
                                        </span>
                                        <span v-if="collab.deleted_at" class="text-red-500 font-semibold">Removido</span>
                                    </p>
                                </div>
                                <div class="flex gap-2" v-if="!collab.deleted_at">
                                    <button
                                        @click="openSummaryModal(collab)"
                                        class="p-2 text-blue-500 hover:bg-blue-50 rounded-lg transition-colors"
                                        title="Resumo"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20V10m5 10V4m5 16v-7" />
                                        </svg>
                                    </button>
                                    <button
                                        @click="handleEdit(collab)"
                                        class="p-2 text-amber-500 hover:bg-amber-50 rounded-lg transition-colors"
                                        title="Editar"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                    <button
                                        @click="handleDelete(collab)"
                                        class="p-2 text-red-500 hover:bg-red-50 rounded-lg transition-colors"
                                        title="Remover"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                                <div v-else class="flex gap-2">
                                    <button
                                        @click="handleRestore(collab)"
                                        :disabled="isRestoreLoading"
                                        :class="[
                                            'p-2 rounded-lg transition-colors',
                                            restoringCollaborator?.id === collab.id && isRestoreLoading
                                                ? 'text-gray-400 cursor-not-allowed'
                                                : 'text-green-500 hover:bg-green-50'
                                        ]"
                                        :title="restoringCollaborator?.id === collab.id && isRestoreLoading ? 'Restaurando...' : 'Restaurar'"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <div
                                v-if="deletingCollaborator?.id === collab.id && showDeleteConfirmInline"
                                class="border-l-4 border-red-500 bg-red-50/80 p-4 rounded mt-2 space-y-3"
                            >
                                <p class="text-sm font-semibold text-gray-800">
                                    Tem certeza que deseja remover "<span class="text-red-600">{{ collab.name }}</span>"?
                                </p>
                                <div class="flex gap-2 justify-end">
                                    <button
                                        @click="cancelDelete"
                                        :disabled="isDeleteLoading"
                                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors disabled:opacity-50"
                                    >
                                        Cancelar
                                    </button>
                                    <button
                                        ref="deleteConfirmButtonRef"
                                        @click="confirmDelete"
                                        :disabled="isDeleteLoading"
                                        class="px-4 py-2 text-sm font-medium text-white bg-red-500 rounded-lg hover:bg-red-600 transition-colors disabled:opacity-50"
                                    >
                                        {{ isDeleteLoading ? 'Removendo...' : 'Remover' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="canSuggestCreateFromSearch"
                        class="mt-4 rounded-lg border border-dashed border-amber-300 bg-amber-50 p-4"
                    >
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-800">
                                    Nenhum colaborador encontrado para "{{ search.trim() }}".
                                </p>
                                <p class="text-sm text-gray-600">
                                    Criar novo colaborador com este nome.
                                </p>
                            </div>
                            <button
                                @click="openNewFromSearch"
                                class="inline-flex items-center justify-center rounded-lg bg-amber-500 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-amber-600"
                            >
                                Criar "{{ search.trim() }}"
                            </button>
                        </div>
                    </div>

                    <div v-if="!isLoading && !isListLoading && filtered.length === 0 && !canSuggestCreateFromSearch" class="text-center text-gray-500 py-8">
                        Nenhum colaborador encontrado.
                    </div>

                    <div v-if="!isLoading && !isListLoading && totalPages > 1" class="flex items-center justify-center gap-2 mt-6">
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
                                    ? 'bg-amber-400 text-white font-semibold'
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

        <Transition name="fade">
            <div v-if="showSummaryModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/60" @click="closeSummaryModal"></div>
                <div class="relative w-full max-w-2xl bg-white rounded-2xl shadow-2xl border border-amber-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-amber-100 bg-gradient-to-r from-amber-50 to-orange-50">
                        <p class="text-xs font-semibold text-amber-600 uppercase tracking-wide">Resumo do colaborador</p>
                        <h3 class="text-xl font-bold text-gray-900 mt-1">{{ summaryCollaborator?.name }}</h3>
                    </div>

                    <div class="px-6 py-5 space-y-5">
                        <div class="flex flex-wrap gap-2">
                            <button
                                type="button"
                                @click="changeSummaryPeriod('ultimos7dias')"
                                :class="[
                                    'px-3 py-2 text-sm rounded-lg border transition',
                                    summaryPeriod === 'ultimos7dias' ? 'bg-amber-500 text-white border-amber-500' : 'bg-white text-gray-700 border-amber-200 hover:bg-amber-50'
                                ]"
                            >
                                Últimos 7 dias
                            </button>
                            <button
                                type="button"
                                @click="changeSummaryPeriod('mensal')"
                                :class="[
                                    'px-3 py-2 text-sm rounded-lg border transition',
                                    summaryPeriod === 'mensal' ? 'bg-amber-500 text-white border-amber-500' : 'bg-white text-gray-700 border-amber-200 hover:bg-amber-50'
                                ]"
                            >
                                Mensal
                            </button>
                            <button
                                type="button"
                                @click="changeSummaryPeriod('anual')"
                                :class="[
                                    'px-3 py-2 text-sm rounded-lg border transition',
                                    summaryPeriod === 'anual' ? 'bg-amber-500 text-white border-amber-500' : 'bg-white text-gray-700 border-amber-200 hover:bg-amber-50'
                                ]"
                            >
                                Anual
                            </button>
                            <button
                                type="button"
                                @click="changeSummaryPeriod('todos')"
                                :class="[
                                    'px-3 py-2 text-sm rounded-lg border transition',
                                    summaryPeriod === 'todos' ? 'bg-amber-500 text-white border-amber-500' : 'bg-white text-gray-700 border-amber-200 hover:bg-amber-50'
                                ]"
                            >
                                Tudo
                            </button>
                        </div>

                        <div v-if="isSummaryLoading" class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                            Carregando totais...
                        </div>

                        <p v-if="summaryError" class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
                            {{ summaryError }}
                        </p>

                        <div v-if="!isSummaryLoading" class="grid gap-4 sm:grid-cols-2">
                            <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3">
                                <p class="text-xs uppercase tracking-wide text-green-700 font-semibold">Total vendido</p>
                                <p class="text-xl font-bold text-green-800 mt-1">{{ formatCurrency(summary.sales_total) }}</p>
                                <p class="text-xs text-green-700 mt-1">Período: {{ periodLabel }}</p>
                            </div>
                            <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3">
                                <p class="text-xs uppercase tracking-wide text-blue-700 font-semibold">Comissão recebida</p>
                                <p class="text-xl font-bold text-blue-800 mt-1">{{ formatCurrency(summary.commission_total) }}</p>
                                <p class="text-xs text-blue-700 mt-1">Período: {{ periodLabel }}</p>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button
                                type="button"
                                class="px-4 py-2 rounded-lg border border-gray-300 bg-gray-50 text-gray-700 hover:bg-gray-100 transition"
                                @click="closeSummaryModal"
                            >
                                Fechar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>
