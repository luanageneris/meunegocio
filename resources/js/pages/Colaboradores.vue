<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import Sidebar from '@/components/Sidebar.vue';
import PageHeaderTitle from '@/components/PageHeaderTitle.vue';
import CollaboratorModal from '@/components/modals/CollaboratorModal.vue';
import DeleteConfirmModal from '@/components/modals/DeleteConfirmModal.vue';
import { useAuth } from '@/composables/useAuth';
import { useToast } from '@/composables/useToast';
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

const auth = useAuth();
const toast = useToast();
const collaborators = ref<Collaborator[]>([]);
const isLoading = ref(false);
const errorMessage = ref('');
const search = ref('');
const showRemoved = ref(false);
const showModal = ref(false);
const showDeleteModal = ref(false);
const showRestoreModal = ref(false);
const isModalLoading = ref(false);
const isDeleteLoading = ref(false);
const isRestoreLoading = ref(false);
const modalMode = ref<'create' | 'edit'>('create');
const currentPage = ref(1);
const itemsPerPage = 8;
const editing = ref<Collaborator | null>(null);
const deletingCollaborator = ref<Collaborator | null>(null);
const restoringCollaborator = ref<Collaborator | null>(null);
const modalError = ref('');

const filtered = computed(() => {
    if (!search.value) return collaborators.value;
    const q = search.value.toLowerCase();
    return collaborators.value.filter((c) =>
        c.name.toLowerCase().includes(q) || c.cpf.toLowerCase().includes(q)
    );
});

const paginatedCollaborators = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    return filtered.value.slice(start, end);
});

const totalPages = computed(() => {
    return Math.ceil(filtered.value.length / itemsPerPage);
});

const totalAtivos = computed(() => collaborators.value.filter((c) => c.active && !c.deleted_at).length);
const totalInativos = computed(() => collaborators.value.filter((c) => !c.active && !c.deleted_at).length);
const totalRemovidos = computed(() => collaborators.value.filter((c) => c.deleted_at).length);

const fetchCollaborators = async () => {
    if (!auth.isAuthenticated.value) {
        window.location.href = '/login';
        return;
    }
    isLoading.value = true;
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
        isLoading.value = false;
    }
};

const handleNew = () => {
    modalMode.value = 'create';
    editing.value = null;
    modalError.value = '';
    showModal.value = true;
};

const handleEdit = (collab: Collaborator) => {
    if (collab.deleted_at) return;
    modalMode.value = 'edit';
    editing.value = { ...collab };
    modalError.value = '';
    showModal.value = true;
};

const handleSave = async (payload: Partial<Collaborator>) => {
    isModalLoading.value = true;
    try {
        if (modalMode.value === 'edit' && editing.value) {
            const { data } = await axios.put(`/api/personalizar/collaborators/${editing.value.id}`, payload);
            collaborators.value = collaborators.value.map((c) => (c.id === editing.value!.id ? data : c));
            showModal.value = false;
            editing.value = null;
            toast.success('Colaborador atualizado!', { description: 'Os dados foram atualizados com sucesso.' });
        } else {
            const { data } = await axios.post('/api/personalizar/collaborators', payload);
            collaborators.value = [data, ...collaborators.value];
            showModal.value = false;
            editing.value = null;
            toast.success('Colaborador criado!', { description: `${data.name} foi adicionado à lista.` });
        }
    } catch (error: any) {
        const message = error?.response?.data?.errors?.cpf?.[0] || error?.response?.data?.message || 'Erro ao salvar colaborador';
        modalError.value = message;
        // mantém modal aberto e exibe erro inline
        showModal.value = true;
    } finally {
        isModalLoading.value = false;
    }
};

const handleDelete = async (collab: Collaborator) => {
    if (collab.deleted_at) return;
    deletingCollaborator.value = collab;
    showDeleteModal.value = true;
};

const confirmDelete = async () => {
    if (!deletingCollaborator.value) return;
    isDeleteLoading.value = true;
    try {
        await axios.delete(`/api/personalizar/collaborators/${deletingCollaborator.value.id}`);
        await fetchCollaborators();
        showDeleteModal.value = false;
        deletingCollaborator.value = null;
        toast.success('Removido com sucesso!', { description: 'O colaborador foi movido para removidos.' });
    } catch (error: any) {
        const message = error?.response?.data?.message || 'Erro ao remover colaborador';
        toast.error('Erro ao remover', { description: message });
    } finally {
        isDeleteLoading.value = false;
    }
};

const handleRestore = async (collab: Collaborator) => {
    if (!collab.deleted_at) return;
    restoringCollaborator.value = collab;
    showRestoreModal.value = true;
};

const confirmRestore = async () => {
    if (!restoringCollaborator.value) return;
    isRestoreLoading.value = true;
    try {
        await axios.post(`/api/personalizar/collaborators/${restoringCollaborator.value.id}/restore`);
        await fetchCollaborators();
        showRestoreModal.value = false;
        restoringCollaborator.value = null;
        toast.success('Restaurado com sucesso!', { description: `${restoringCollaborator.value.name} foi restaurado e ativado.` });
    } catch (error: any) {
        const message = error?.response?.data?.message || 'Erro ao restaurar colaborador';
        toast.error('Erro ao restaurar', { description: message });
    } finally {
        isRestoreLoading.value = false;
    }
};

const toggleRemoved = () => {
    showRemoved.value = !showRemoved.value;
    currentPage.value = 1;
    fetchCollaborators();
};

const goToPage = (page: number) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
    }
};

onMounted(fetchCollaborators);
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-amber-50 to-orange-100 flex overflow-x-hidden">
        <Head title="Colaboradores - MEU NEGÓCIO" />

        <Sidebar active-route="colaboradores" />

        <div class="flex-1 lg:ml-64 transition-all duration-300 overflow-x-hidden flex flex-col">
            <PageHeaderTitle 
                title="Colaboradores" 
                subtitle="Gerencie os colaboradores da sua empresa"
            />

            <main class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
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
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Pesquise por nome ou CPF"
                            class="w-full px-4 py-3 border-2 border-amber-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent"
                        />
                    </div>

                    <div v-if="errorMessage" class="mb-4 text-sm text-red-600 bg-red-50 border border-red-100 rounded-lg p-3">
                        {{ errorMessage }}
                    </div>

                    <LoadingSpinner v-if="isLoading" message="Carregando colaboradores..." />

                    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div
                            v-for="collab in paginatedCollaborators"
                            :key="collab.id"
                            :class="[
                                'border border-gray-200 rounded-lg p-4 shadow-sm transition-shadow',
                                collab.deleted_at ? 'bg-gray-50 opacity-80' : 'hover:shadow-md'
                            ]"
                        >
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="font-semibold text-gray-800">{{ collab.name }}</p>
                                    <p class="text-xs text-gray-500">CPF: {{ collab.cpf }}</p>
                                    <p class="mt-1 text-xs flex items-center gap-2">
                                        <span :class="collab.active ? 'text-green-600' : 'text-gray-400'">
                                            {{ collab.active ? 'Ativo' : 'Inativo' }}
                                        </span>
                                        <span v-if="collab.deleted_at" class="text-red-500 font-semibold">Removido</span>
                                    </p>
                                </div>
                                <div class="flex gap-2" v-if="!collab.deleted_at">
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
                                        class="p-2 text-amber-500 hover:bg-amber-50 rounded-lg transition-colors"
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
                                        class="p-2 text-green-500 hover:bg-green-50 rounded-lg transition-colors"
                                        title="Restaurar"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="!isLoading && filtered.length === 0" class="text-center text-gray-500 py-8">
                        Nenhum colaborador encontrado.
                    </div>

                    <!-- Pagination -->
                    <div v-if="!isLoading && totalPages > 1" class="flex items-center justify-center gap-2 mt-6">
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
                <Footer />
            </main>
        </div>

        <CollaboratorModal
            :show="showModal"
            :collaborator="editing"
            :mode="modalMode"
            :is-loading="isModalLoading"
            :server-error="modalError"
            @close="showModal = false; editing = null; modalError = ''"
            @save="handleSave"
        />

        <DeleteConfirmModal
            :show="showDeleteModal"
            :record="deletingCollaborator"
            :is-loading="isDeleteLoading"
            item-type="collaborator"
            @close="showDeleteModal = false; deletingCollaborator = null"
            @confirm="confirmDelete"
        />

        <DeleteConfirmModal
            :show="showRestoreModal"
            :record="restoringCollaborator"
            :is-loading="isRestoreLoading"
            item-type="restore"
            @close="showRestoreModal = false; restoringCollaborator = null"
            @confirm="confirmRestore"
        />
    </div>
</template>
