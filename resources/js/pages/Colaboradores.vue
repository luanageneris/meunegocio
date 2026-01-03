<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import Sidebar from '@/components/Sidebar.vue';
import CollaboratorModal from '@/components/modals/CollaboratorModal.vue';
import { useAuth } from '@/composables/useAuth';

interface Collaborator {
    id: number;
    name: string;
    cpf: string;
    active: boolean;
    created_at?: string;
}

const auth = useAuth();
const collaborators = ref<Collaborator[]>([]);
const isLoading = ref(false);
const errorMessage = ref('');
const search = ref('');
const showModal = ref(false);
const modalMode = ref<'create' | 'edit'>('create');
const editing = ref<Collaborator | null>(null);

const filtered = computed(() => {
    if (!search.value) return collaborators.value;
    const q = search.value.toLowerCase();
    return collaborators.value.filter((c) =>
        c.name.toLowerCase().includes(q) || c.cpf.toLowerCase().includes(q)
    );
});

const totalAtivos = computed(() => collaborators.value.filter((c) => c.active).length);

const fetchCollaborators = async () => {
    if (!auth.isAuthenticated.value) {
        window.location.href = '/login';
        return;
    }
    isLoading.value = true;
    errorMessage.value = '';
    try {
        const { data } = await axios.get('/api/personalizar/collaborators');
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
    showModal.value = true;
};

const handleEdit = (collab: Collaborator) => {
    modalMode.value = 'edit';
    editing.value = { ...collab };
    showModal.value = true;
};

const handleSave = async (payload: Partial<Collaborator>) => {
    saving.value = true;
    try {
        if (modalMode.value === 'edit' && editing.value) {
            const { data } = await axios.put(`/api/personalizar/collaborators/${editing.value.id}`, payload);
            collaborators.value = collaborators.value.map((c) => (c.id === editing.value!.id ? data : c));
        } else {
            const { data } = await axios.post('/api/personalizar/collaborators', payload);
            collaborators.value = [data, ...collaborators.value];
        }
        showModal.value = false;
        editing.value = null;
    } catch (error: any) {
        alert(error?.response?.data?.message || 'Erro ao salvar colaborador');
    } finally {
        saving.value = false;
    }
};

const handleDelete = async (collab: Collaborator) => {
    const confirmed = window.confirm(`Remover colaborador ${collab.name}?`);
    if (!confirmed) return;
    try {
        await axios.delete(`/api/personalizar/collaborators/${collab.id}`);
        collaborators.value = collaborators.value.filter((c) => c.id !== collab.id);
    } catch (error: any) {
        alert(error?.response?.data?.message || 'Erro ao remover colaborador');
    }
};

onMounted(fetchCollaborators);
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-amber-50 to-orange-100 flex">
        <Head title="Colaboradores - MEU NEGÓCIO" />

        <Sidebar active-route="colaboradores" />

        <div class="flex-1 lg:ml-64 transition-all duration-300">
            <!-- Header -->
            <header class="bg-gradient-to-r from-orange-400 to-amber-500 shadow-lg">
                <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                    <h1 class="text-2xl font-bold text-white">Colaboradores</h1>
                </div>
            </header>

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
                            disabled
                            class="px-5 py-2 border-2 border-amber-300 text-amber-500 rounded-lg font-medium opacity-60 cursor-not-allowed"
                            title="Em breve"
                        >
                            Ver removidos
                        </button>
                        <span class="text-sm text-gray-500">Ativos: {{ totalAtivos }}</span>
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

                    <div v-if="isLoading" class="py-10 text-center text-amber-500 font-medium">Carregando...</div>

                    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div
                            v-for="collab in filtered"
                            :key="collab.id"
                            class="border border-gray-200 rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow"
                        >
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="font-semibold text-gray-800">{{ collab.name }}</p>
                                    <p class="text-xs text-gray-500">CPF: {{ collab.cpf }}</p>
                                    <p class="mt-1 text-xs" :class="collab.active ? 'text-green-600' : 'text-gray-400'">
                                        {{ collab.active ? 'Ativo' : 'Inativo' }}
                                    </p>
                                </div>
                                <div class="flex gap-2">
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
                            </div>
                        </div>
                    </div>

                    <div v-if="!isLoading && filtered.length === 0" class="text-center text-gray-500 py-8">
                        Nenhum colaborador encontrado.
                    </div>
                </div>
            </main>
        </div>

        <CollaboratorModal
            :show="showModal"
            :collaborator="editing"
            :mode="modalMode"
            @close="showModal = false; editing = null"
            @save="handleSave"
        />
    </div>
</template>
