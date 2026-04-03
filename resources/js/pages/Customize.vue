<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, computed, onMounted, nextTick } from 'vue';
import axios from 'axios';
import Sidebar from '@/components/Sidebar.vue';
import PageHeaderTitle from '@/components/PageHeaderTitle.vue';
import EntryTypeFormModal from '@/components/modals/EntryTypeFormModal.vue';
import ExitTypeFormModal from '@/components/modals/ExitTypeFormModal.vue';
import ServiceTypeFormModal from '@/components/modals/ServiceTypeFormModal.vue';
import DeleteConfirmModal from '@/components/modals/DeleteConfirmModal.vue';
import { useAuth } from '@/composables/useAuth';
import { useToast } from '@/composables/useToast';
import Footer from '@/components/Footer.vue';
import LoadingSpinner from '@/components/LoadingSpinner.vue';

interface EntryType {
    id: number;
    name: string;
    created_at?: string;
    deleted_at?: string | null;
}

interface ExitType {
    id: number;
    name: string;
    created_at?: string;
    deleted_at?: string | null;
}

interface ServiceType {
    id: number;
    name: string;
    unit: string;
    service_value: string | number;
    commission_percentage: string | number;
    commission_value: string | number;
    resource_value: string | number;
    created_at?: string;
    deleted_at?: string | null;
}

const auth = useAuth();
const toast = useToast();

// Entry Types
const entryTypes = ref<EntryType[]>([]);
const entryTypeSearch = ref('');
const showEntryTypeModal = ref(false);
const showEntryTypeDeleteModal = ref(false);
const showEntryTypeRestoreModal = ref(false);
const showNewEntryTypeInline = ref(false);
const entryTypeModalMode = ref<'create' | 'edit'>('create');
const editingEntryType = ref<EntryType | null>(null);
const deletingEntryType = ref<EntryType | null>(null);
const restoringEntryType = ref<EntryType | null>(null);
const isEntryTypeModalLoading = ref(false);
const isEntryTypeDeleteLoading = ref(false);
const isEntryTypeRestoreLoading = ref(false);
const showEntryTypeRemoved = ref(false);
const newEntryTypeName = ref('');
const newEntryTypeError = ref('');
const newEntryTypeInput = ref<HTMLInputElement | null>(null);

// Exit Types
const exitTypes = ref<ExitType[]>([]);
const exitTypeSearch = ref('');
const showExitTypeModal = ref(false);
const showExitTypeDeleteModal = ref(false);
const showExitTypeRestoreModal = ref(false);
const exitTypeModalMode = ref<'create' | 'edit'>('create');
const editingExitType = ref<ExitType | null>(null);
const deletingExitType = ref<ExitType | null>(null);
const restoringExitType = ref<ExitType | null>(null);
const isExitTypeModalLoading = ref(false);
const isExitTypeDeleteLoading = ref(false);
const isExitTypeRestoreLoading = ref(false);
const showExitTypeRemoved = ref(false);

// Service Types
const serviceTypes = ref<ServiceType[]>([]);
const serviceTypeSearch = ref('');
const showServiceTypeModal = ref(false);
const showServiceTypeDeleteModal = ref(false);
const showServiceTypeRestoreModal = ref(false);
const serviceTypeModalMode = ref<'create' | 'edit'>('create');
const editingServiceType = ref<ServiceType | null>(null);
const deletingServiceType = ref<ServiceType | null>(null);
const restoringServiceType = ref<ServiceType | null>(null);
const isServiceTypeModalLoading = ref(false);
const isServiceTypeDeleteLoading = ref(false);
const isServiceTypeRestoreLoading = ref(false);
const showServiceTypeRemoved = ref(false);

const isLoading = ref(false);
const errorMessage = ref('');
const company = ref<any>(null);
const savingSettings = ref(false);
const serviceModeOn = computed(() => company.value?.use_service_types !== false);

// Computed
const filteredEntryTypes = computed(() => {
    if (!entryTypeSearch.value) return entryTypes.value;
    const q = entryTypeSearch.value.toLowerCase();
    return entryTypes.value.filter((t) => t.name.toLowerCase().includes(q));
});

const canSuggestCreateEntryTypeFromSearch = computed(() => {
    const query = entryTypeSearch.value.trim();

    return Boolean(
        query &&
        !showEntryTypeRemoved.value &&
        !showNewEntryTypeInline.value &&
        filteredEntryTypes.value.length === 0
    );
});

const filteredExitTypes = computed(() => {
    if (!exitTypeSearch.value) return exitTypes.value;
    const q = exitTypeSearch.value.toLowerCase();
    return exitTypes.value.filter((t) => t.name.toLowerCase().includes(q));
});

const filteredServiceTypes = computed(() => {
    if (!serviceTypeSearch.value) return serviceTypes.value;
    const q = serviceTypeSearch.value.toLowerCase();
    return serviceTypes.value.filter((t) => t.name.toLowerCase().includes(q));
});

// Fetch data
const fetchData = async () => {
    if (!auth.isAuthenticated.value) {
        window.location.href = '/login';
        return;
    }

    isLoading.value = true;
    errorMessage.value = '';

    try {
        const entryEndpoint = showEntryTypeRemoved.value ? '/api/personalizar/entry-types/with-trashed' : '/api/personalizar/entry-types';
        const exitEndpoint = showExitTypeRemoved.value ? '/api/personalizar/exit-types/with-trashed' : '/api/personalizar/exit-types';
        const serviceEndpoint = showServiceTypeRemoved.value ? '/api/personalizar/service-types/with-trashed' : '/api/personalizar/service-types';

        const [companyRes, entryRes, exitRes, serviceRes] = await Promise.all([
            axios.get('/api/company'),
            axios.get(entryEndpoint),
            axios.get(exitEndpoint),
            axios.get(serviceEndpoint),
        ]);

        company.value = companyRes.data?.data ?? companyRes.data ?? null;
        entryTypes.value = entryRes.data;
        exitTypes.value = exitRes.data;
        serviceTypes.value = serviceRes.data;
    } catch (error: any) {
        errorMessage.value = error?.response?.data?.message || 'Erro ao carregar dados';
    } finally {
        isLoading.value = false;
    }
};

const toggleServiceMode = async () => {
    if (!company.value) return;
    savingSettings.value = true;
    try {
        const payload = { ...company.value, use_service_types: !company.value.use_service_types };
        const { data } = await axios.put('/api/company', payload);
        company.value = data?.data ?? data;
    } catch (error: any) {
        // noop toast here optional
    } finally {
        savingSettings.value = false;
    }
};

// Entry Type handlers
const handleNewEntryType = () => {
    if (showNewEntryTypeInline.value) {
        nextTick(() => newEntryTypeInput.value?.focus());
        return;
    }

    showNewEntryTypeInline.value = true;
    newEntryTypeName.value = '';
    newEntryTypeError.value = '';
    entryTypeModalMode.value = 'create';
    editingEntryType.value = null;
    nextTick(() => newEntryTypeInput.value?.focus());
};

const cancelNewEntryType = () => {
    showNewEntryTypeInline.value = false;
    newEntryTypeName.value = '';
    newEntryTypeError.value = '';
};

const persistNewEntryType = async (name: string) => {
    const normalizedName = name.trim();

    if (!normalizedName) {
        newEntryTypeError.value = 'O nome é obrigatório';
        nextTick(() => newEntryTypeInput.value?.focus());
        return;
    }

    if (isEntryTypeModalLoading.value) return;

    isEntryTypeModalLoading.value = true;
    newEntryTypeError.value = '';

    try {
        const { data } = await axios.post('/api/personalizar/entry-types', { name: normalizedName });

        if (showEntryTypeRemoved.value) {
            showEntryTypeRemoved.value = false;
            await fetchData();
        } else {
            entryTypes.value = [data, ...entryTypes.value];
        }

        toast.success('Tipo criado!', { description: `Tipo de entrada "${data.name}" foi adicionado.` });
        cancelNewEntryType();
    } catch (error: any) {
        const message = error?.response?.data?.message || 'Erro ao salvar tipo de entrada';
        newEntryTypeError.value = message;
        toast.error('Erro ao salvar', { description: message });
        nextTick(() => newEntryTypeInput.value?.focus());
    } finally {
        isEntryTypeModalLoading.value = false;
    }
};

const saveNewEntryType = async () => {
    await persistNewEntryType(newEntryTypeName.value);
};

const saveEntryTypeFromSearch = async () => {
    await persistNewEntryType(entryTypeSearch.value);
};

const handleEditEntryType = (type: EntryType) => {
    if (type.deleted_at) return;
    entryTypeModalMode.value = 'edit';
    editingEntryType.value = { ...type };
    showEntryTypeModal.value = true;
};

const handleDeleteEntryType = (type: EntryType) => {
    if (type.deleted_at) return;
    deletingEntryType.value = type;
    showEntryTypeDeleteModal.value = true;
};

const handleRestoreEntryType = (type: EntryType) => {
    if (!type.deleted_at) return;
    restoringEntryType.value = type;
    showEntryTypeRestoreModal.value = true;
};

const confirmDeleteEntryType = async () => {
    if (!deletingEntryType.value) return;
    isEntryTypeDeleteLoading.value = true;
    try {
        await axios.delete(`/api/personalizar/entry-types/${deletingEntryType.value.id}`);
        await fetchData();
        showEntryTypeDeleteModal.value = false;
        deletingEntryType.value = null;
        toast.success('Removido com sucesso!', { description: 'O tipo de entrada foi removido.' });
    } catch (error: any) {
        const message = error?.response?.data?.message || 'Erro ao remover tipo de entrada';
        toast.error('Erro ao remover', { description: message });
    } finally {
        isEntryTypeDeleteLoading.value = false;
    }
};

const confirmRestoreEntryType = async () => {
    if (!restoringEntryType.value) return;
    isEntryTypeRestoreLoading.value = true;
    try {
        await axios.post(`/api/personalizar/entry-types/${restoringEntryType.value.id}/restore`);
        await fetchData();
        showEntryTypeRestoreModal.value = false;
        restoringEntryType.value = null;
        toast.success('Restaurado com sucesso!', { description: `O tipo de entrada foi restaurado.` });
    } catch (error: any) {
        const message = error?.response?.data?.message || 'Erro ao restaurar tipo de entrada';
        toast.error('Erro ao restaurar', { description: message });
    } finally {
        isEntryTypeRestoreLoading.value = false;
    }
};

const toggleEntryTypeRemoved = async () => {
    const scrollPosition = window.scrollY;
    showEntryTypeRemoved.value = !showEntryTypeRemoved.value;
    await fetchData();
    window.scrollTo(0, scrollPosition);
};

const handleSaveEntryType = async (payload: Partial<EntryType>) => {
    isEntryTypeModalLoading.value = true;
    try {
        if (entryTypeModalMode.value === 'edit' && editingEntryType.value) {
            const { data } = await axios.put(`/api/personalizar/entry-types/${editingEntryType.value.id}`, payload);
            entryTypes.value = entryTypes.value.map((t) => (t.id === editingEntryType.value!.id ? data : t));
            toast.success('Tipo atualizado!', { description: 'Tipo de entrada foi atualizado com sucesso.' });
        } else {
            const { data } = await axios.post('/api/personalizar/entry-types', payload);
            entryTypes.value = [data, ...entryTypes.value];
            toast.success('Tipo criado!', { description: `Tipo de entrada "${data.name}" foi adicionado.` });
        }
        showEntryTypeModal.value = false;
        editingEntryType.value = null;
    } catch (error: any) {
        const message = error?.response?.data?.message || 'Erro ao salvar tipo de entrada';
        toast.error('Erro ao salvar', { description: message });
    } finally {
        isEntryTypeModalLoading.value = false;
    }
};

// Exit Type handlers
const handleNewExitType = () => {
    exitTypeModalMode.value = 'create';
    editingExitType.value = null;
    showExitTypeModal.value = true;
};

const handleEditExitType = (type: ExitType) => {
    if (type.deleted_at) return;
    exitTypeModalMode.value = 'edit';
    editingExitType.value = { ...type };
    showExitTypeModal.value = true;
};

const handleDeleteExitType = (type: ExitType) => {
    if (type.deleted_at) return;
    deletingExitType.value = type;
    showExitTypeDeleteModal.value = true;
};

const handleRestoreExitType = (type: ExitType) => {
    if (!type.deleted_at) return;
    restoringExitType.value = type;
    showExitTypeRestoreModal.value = true;
};

const handleSaveExitType = async (payload: Partial<ExitType>) => {
    isExitTypeModalLoading.value = true;
    try {
        if (exitTypeModalMode.value === 'edit' && editingExitType.value) {
            const { data } = await axios.put(`/api/personalizar/exit-types/${editingExitType.value.id}`, payload);
            exitTypes.value = exitTypes.value.map((t) => (t.id === editingExitType.value!.id ? data : t));
            toast.success('Tipo atualizado!', { description: 'Tipo de saída foi atualizado com sucesso.' });
        } else {
            const { data } = await axios.post('/api/personalizar/exit-types', payload);
            exitTypes.value = [data, ...exitTypes.value];
            toast.success('Tipo criado!', { description: `Tipo de saída "${data.name}" foi adicionado.` });
        }
        showExitTypeModal.value = false;
        editingExitType.value = null;
    } catch (error: any) {
        const message = error?.response?.data?.message || 'Erro ao salvar tipo de saída';
        toast.error('Erro ao salvar', { description: message });
    } finally {
        isExitTypeModalLoading.value = false;
    }
};

const confirmDeleteExitType = async () => {
    if (!deletingExitType.value) return;
    isExitTypeDeleteLoading.value = true;
    try {
        await axios.delete(`/api/personalizar/exit-types/${deletingExitType.value.id}`);
        await fetchData();
        showExitTypeDeleteModal.value = false;
        deletingExitType.value = null;
        toast.success('Removido com sucesso!', { description: 'O tipo de saída foi removido.' });
    } catch (error: any) {
        const message = error?.response?.data?.message || 'Erro ao remover tipo de saída';
        toast.error('Erro ao remover', { description: message });
    } finally {
        isExitTypeDeleteLoading.value = false;
    }
};

const confirmRestoreExitType = async () => {
    if (!restoringExitType.value) return;
    isExitTypeRestoreLoading.value = true;
    try {
        await axios.post(`/api/personalizar/exit-types/${restoringExitType.value.id}/restore`);
        await fetchData();
        showExitTypeRestoreModal.value = false;
        restoringExitType.value = null;
        toast.success('Restaurado com sucesso!', { description: `O tipo de saída foi restaurado.` });
    } catch (error: any) {
        const message = error?.response?.data?.message || 'Erro ao restaurar tipo de saída';
        toast.error('Erro ao restaurar', { description: message });
    } finally {
        isExitTypeRestoreLoading.value = false;
    }
};

const toggleExitTypeRemoved = async () => {
    const scrollPosition = window.scrollY;
    showExitTypeRemoved.value = !showExitTypeRemoved.value;
    await fetchData();
    window.scrollTo(0, scrollPosition);
};

// Service Type handlers
const handleNewServiceType = () => {
    serviceTypeModalMode.value = 'create';
    editingServiceType.value = null;
    showServiceTypeModal.value = true;
};

const handleEditServiceType = (type: ServiceType) => {
    if (type.deleted_at) return;
    serviceTypeModalMode.value = 'edit';
    editingServiceType.value = { ...type };
    showServiceTypeModal.value = true;
};

const handleDeleteServiceType = (type: ServiceType) => {
    if (type.deleted_at) return;
    deletingServiceType.value = type;
    showServiceTypeDeleteModal.value = true;
};

const handleRestoreServiceType = (type: ServiceType) => {
    if (!type.deleted_at) return;
    restoringServiceType.value = type;
    showServiceTypeRestoreModal.value = true;
};

const handleSaveServiceType = async (payload: Partial<ServiceType>) => {
    isServiceTypeModalLoading.value = true;
    try {
        if (serviceTypeModalMode.value === 'edit' && editingServiceType.value) {
            const { data } = await axios.put(`/api/personalizar/service-types/${editingServiceType.value.id}`, payload);
            serviceTypes.value = serviceTypes.value.map((t) => (t.id === editingServiceType.value!.id ? data : t));
            toast.success('Tipo atualizado!', { description: 'Tipo de serviço foi atualizado com sucesso.' });
        } else {
            const { data } = await axios.post('/api/personalizar/service-types', payload);
            serviceTypes.value = [data, ...serviceTypes.value];
            toast.success('Tipo criado!', { description: `Tipo de serviço "${data.name}" foi adicionado.` });
        }
        showServiceTypeModal.value = false;
        editingServiceType.value = null;
    } catch (error: any) {
        const message = error?.response?.data?.message || 'Erro ao salvar tipo de serviço';
        toast.error('Erro ao salvar', { description: message });
    } finally {
        isServiceTypeModalLoading.value = false;
    }
};

const confirmDeleteServiceType = async () => {
    if (!deletingServiceType.value) return;
    isServiceTypeDeleteLoading.value = true;
    try {
        await axios.delete(`/api/personalizar/service-types/${deletingServiceType.value.id}`);
        await fetchData();
        showServiceTypeDeleteModal.value = false;
        deletingServiceType.value = null;
        toast.success('Removido com sucesso!', { description: 'O tipo de serviço foi removido.' });
    } catch (error: any) {
        const message = error?.response?.data?.message || 'Erro ao remover tipo de serviço';
        toast.error('Erro ao remover', { description: message });
    } finally {
        isServiceTypeDeleteLoading.value = false;
    }
};

const confirmRestoreServiceType = async () => {
    if (!restoringServiceType.value) return;
    isServiceTypeRestoreLoading.value = true;
    try {
        await axios.post(`/api/personalizar/service-types/${restoringServiceType.value.id}/restore`);
        await fetchData();
        showServiceTypeRestoreModal.value = false;
        restoringServiceType.value = null;
        toast.success('Restaurado com sucesso!', { description: `O tipo de serviço foi restaurado.` });
    } catch (error: any) {
        const message = error?.response?.data?.message || 'Erro ao restaurar tipo de serviço';
        toast.error('Erro ao restaurar', { description: message });
    } finally {
        isServiceTypeRestoreLoading.value = false;
    }
};

const toggleServiceTypeRemoved = async () => {
    const scrollPosition = window.scrollY;
    showServiceTypeRemoved.value = !showServiceTypeRemoved.value;
    await fetchData();
    window.scrollTo(0, scrollPosition);
};

onMounted(fetchData);
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-amber-50 to-orange-100 flex overflow-x-hidden">
        <Head title="Personalizar - MEU NEGÓCIO" />
        
        <Sidebar active-route="personalizar" />

        <div class="flex-1 lg:ml-64 transition-all duration-300 overflow-x-hidden flex flex-col">
            <PageHeaderTitle title="Personalizar" subtitle="Gerencie tipos de entrada, saída e serviços"/>
            
            <!-- Main Content -->
            <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <!-- Error Message -->
                <div v-if="errorMessage" class="mb-4 text-sm text-red-600 bg-red-50 border border-red-100 rounded-lg p-3">
                    {{ errorMessage }}
                </div>
                
                <LoadingSpinner v-if="isLoading" />

                <div v-else class="space-y-6">
                    <!-- Entry Types Section -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                            <div class="flex items-center gap-2 mb-4">
                                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m0 0h6" />
                                </svg>
                                <h2 class="text-2xl font-bold text-gray-800">Tipos de Entradas</h2>
                            </div>

                            <div class="flex flex-wrap items-center gap-2 mb-4">
                                <button
                                    @click="handleNewEntryType"
                                    class="px-5 py-2 bg-gradient-to-r from-green-400 to-green-500 text-white rounded-lg font-medium hover:shadow-lg transition-all"
                                >
                                    + Novo Tipo
                                </button>
                                <button
                                    @click="toggleEntryTypeRemoved"
                                    :class="[
                                        'px-5 py-2 border-2 rounded-lg font-medium transition-all',
                                        showEntryTypeRemoved
                                            ? 'border-red-300 text-red-600 bg-red-50 hover:bg-red-100'
                                            : 'border-green-300 text-green-500 hover:bg-green-50'
                                    ]"
                                >
                                    {{ showEntryTypeRemoved ? 'Ocultar removidos' : 'Ver removidos' }}
                                </button>
                            </div>

                            <div class="mb-4">
                                <input
                                    v-model="entryTypeSearch"
                                    type="text"
                                    placeholder="Pesquise tipos de entrada..."
                                    class="w-full px-4 py-3 border-2 border-green-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 focus:border-transparent"
                                />
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div
                                    v-if="showNewEntryTypeInline"
                                    class="border-2 border-green-300 rounded-lg p-4 shadow-sm bg-green-50/60"
                                >
                                    <div class="flex items-center gap-3">
                                        <input
                                            ref="newEntryTypeInput"
                                            v-model="newEntryTypeName"
                                            type="text"
                                            placeholder="Digite o nome do novo tipo de entrada"
                                            class="flex-1 bg-transparent text-base font-semibold text-gray-800 placeholder:text-gray-400 focus:outline-none"
                                            :disabled="isEntryTypeModalLoading"
                                            @keydown.enter.prevent="saveNewEntryType"
                                            @keydown.esc.prevent="cancelNewEntryType"
                                        />

                                        <div class="flex items-center gap-2">
                                            <button
                                                @click="saveNewEntryType"
                                                :disabled="isEntryTypeModalLoading"
                                                class="p-2 text-green-600 hover:bg-green-100 rounded-lg transition-colors disabled:opacity-50"
                                                title="Salvar"
                                            >
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                </svg>
                                            </button>
                                            <button
                                                @click="cancelNewEntryType"
                                                :disabled="isEntryTypeModalLoading"
                                                class="p-2 text-gray-500 hover:bg-gray-100 rounded-lg transition-colors disabled:opacity-50"
                                                title="Cancelar"
                                            >
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                                    <p v-if="newEntryTypeError" class="mt-2 text-sm text-red-600">{{ newEntryTypeError }}</p>
                                </div>

                                <div
                                    v-for="type in filteredEntryTypes"
                                    :key="type.id"
                                    :class="[
                                        'border border-gray-200 rounded-lg p-4 shadow-sm transition-shadow',
                                        type.deleted_at ? 'bg-gray-50 opacity-80' : 'hover:shadow-md'
                                    ]"
                                >
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="font-semibold text-gray-800">{{ type.name }}</p>
                                            <p v-if="type.deleted_at" class="text-red-500 font-semibold text-xs mt-1">Removido</p>
                                        </div>
                                        <div class="flex gap-2" v-if="!type.deleted_at">
                                            <button
                                                @click="handleEditEntryType(type)"
                                                class="p-2 text-green-500 hover:bg-green-50 rounded-lg transition-colors"
                                                title="Editar"
                                            >
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                            <button
                                                @click="handleDeleteEntryType(type)"
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
                                                @click="handleRestoreEntryType(type)"
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

                            <div
                                v-if="canSuggestCreateEntryTypeFromSearch"
                                class="mt-4 rounded-lg border border-dashed border-green-300 bg-green-50 p-4"
                            >
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-gray-800">
                                            Nenhum tipo encontrado para "{{ entryTypeSearch.trim() }}".
                                        </p>
                                        <p class="text-sm text-gray-600">
                                            Criar este tipo de entrada com um clique.
                                        </p>
                                    </div>

                                    <button
                                        @click="saveEntryTypeFromSearch"
                                        :disabled="isEntryTypeModalLoading"
                                        class="inline-flex items-center justify-center rounded-lg bg-green-500 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-green-600 disabled:opacity-50"
                                    >
                                        {{ isEntryTypeModalLoading ? 'Criando...' : `Criar "${entryTypeSearch.trim()}"` }}
                                    </button>
                                </div>
                            </div>

                            <div v-if="filteredEntryTypes.length === 0" class="text-center text-gray-500 py-8">
                                Nenhum tipo de entrada encontrado.
                            </div>
                        </div>

                        <!-- Exit Types Section -->
                        <div class="bg-white rounded-xl shadow-lg p-6">
                            <div class="flex items-center gap-2 mb-4">
                                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18l8 0M12 18l-8 0M12 18V6" />
                                </svg>
                                <h2 class="text-2xl font-bold text-gray-800">Tipos de Saídas</h2>
                            </div>

                            <div class="flex flex-wrap items-center gap-2 mb-4">
                                <button
                                    @click="handleNewExitType"
                                    class="px-5 py-2 bg-gradient-to-r from-red-400 to-red-500 text-white rounded-lg font-medium hover:shadow-lg transition-all"
                                >
                                    + Novo Tipo
                                </button>
                                <button
                                    @click="toggleExitTypeRemoved"
                                    :class="[
                                        'px-5 py-2 border-2 rounded-lg font-medium transition-all',
                                        showExitTypeRemoved
                                            ? 'border-red-300 text-red-600 bg-red-50 hover:bg-red-100'
                                            : 'border-red-300 text-red-500 hover:bg-red-50'
                                    ]"
                                >
                                    {{ showExitTypeRemoved ? 'Ocultar removidos' : 'Ver removidos' }}
                                </button>
                            </div>

                            <div class="mb-4">
                                <input
                                    v-model="exitTypeSearch"
                                    type="text"
                                    placeholder="Pesquise tipos de saída..."
                                    class="w-full px-4 py-3 border-2 border-red-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent"
                                />
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div
                                    v-for="type in filteredExitTypes"
                                    :key="type.id"
                                    :class="[
                                        'border border-gray-200 rounded-lg p-4 shadow-sm transition-shadow',
                                        type.deleted_at ? 'bg-gray-50 opacity-80' : 'hover:shadow-md'
                                    ]"
                                >
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="font-semibold text-gray-800">{{ type.name }}</p>
                                            <p v-if="type.deleted_at" class="text-red-500 font-semibold text-xs mt-1">Removido</p>
                                        </div>
                                        <div class="flex gap-2" v-if="!type.deleted_at">
                                            <button
                                                @click="handleEditExitType(type)"
                                                class="p-2 text-red-500 hover:bg-red-50 rounded-lg transition-colors"
                                                title="Editar"
                                            >
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                            <button
                                                @click="handleDeleteExitType(type)"
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
                                                @click="handleRestoreExitType(type)"
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

                            <div v-if="filteredExitTypes.length === 0" class="text-center text-gray-500 py-8">
                                Nenhum tipo de saída encontrado.
                            </div>
                        </div>

                        <!-- Settings Section -->
                        <div class="bg-white rounded-xl shadow-lg p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h2 class="text-xl font-bold text-gray-800">Configurações da Empresa</h2>
                                    <p class="text-sm text-gray-500">Alternar entre formulário simples e por tipo de serviço.</p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-sm text-gray-600">Modo Tipo de Serviço</span>
                                    <button
                                        @click="toggleServiceMode"
                                        :disabled="savingSettings"
                                        :class="[
                                            'relative inline-flex h-6 w-11 items-center rounded-full transition-colors',
                                            (company?.use_service_types !== false) ? 'bg-blue-600' : 'bg-gray-300',
                                            savingSettings ? 'opacity-50 cursor-not-allowed' : ''
                                        ]"
                                    >
                                        <span
                                            :class="[
                                                'inline-block h-4 w-4 transform rounded-full bg-white transition-transform',
                                                (company?.use_service_types !== false) ? 'translate-x-6' : 'translate-x-1'
                                            ]"
                                        />
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Service Types Section -->
                        <div :class="['bg-white rounded-xl shadow-lg p-6', !serviceModeOn ? 'opacity-60' : '']">
                            <div class="flex items-center gap-2 mb-4">
                                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                                <h2 class="text-2xl font-bold text-gray-800">Tipos de Serviços</h2>
                                <span
                                    v-if="!serviceModeOn"
                                    class="ml-2 inline-flex items-center rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-800 border border-yellow-200"
                                >
                                    Irrelevante (modo desativado)
                                </span>
                            </div>

                            <div v-if="!serviceModeOn" class="mb-4 rounded-lg border border-yellow-200 bg-yellow-50 p-3 text-yellow-800 text-sm">
                                O Modo Tipo de Serviço está desativado nas Configurações da Empresa. Este cadastro não é usado ao registrar entradas até ser reativado.
                            </div>

                            <div class="flex flex-wrap items-center gap-2 mb-4">
                                <button
                                    @click="handleNewServiceType"
                                    :disabled="!serviceModeOn"
                                    :class="[
                                        'px-5 py-2 rounded-lg font-medium transition-all',
                                        serviceModeOn
                                            ? 'bg-gradient-to-r from-blue-400 to-blue-500 text-white hover:shadow-lg'
                                            : 'bg-gray-200 text-gray-500 cursor-not-allowed'
                                    ]"
                                >
                                    + Novo Tipo
                                </button>
                                <button
                                    @click="toggleServiceTypeRemoved"
                                    :disabled="!serviceModeOn"
                                    :class="[
                                        'px-5 py-2 border-2 rounded-lg font-medium transition-all',
                                        !serviceModeOn
                                            ? 'border-gray-300 text-gray-400 bg-gray-100 cursor-not-allowed'
                                            : (showServiceTypeRemoved
                                                ? 'border-red-300 text-red-600 bg-red-50 hover:bg-red-100'
                                                : 'border-blue-300 text-blue-500 hover:bg-blue-50')
                                    ]"
                                >
                                    {{ showServiceTypeRemoved ? 'Ocultar removidos' : 'Ver removidos' }}
                                </button>
                            </div>

                            <div class="mb-4">
                                <input
                                    v-model="serviceTypeSearch"
                                    type="text"
                                    placeholder="Pesquise tipos de serviço..."
                                    class="w-full px-4 py-3 border-2 border-blue-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent"
                                />
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div
                                    v-for="type in filteredServiceTypes"
                                    :key="type.id"
                                    :class="[
                                        'border border-gray-200 rounded-lg p-4 shadow-sm transition-shadow',
                                        type.deleted_at ? 'bg-gray-50 opacity-80' : 'hover:shadow-md'
                                    ]"
                                >
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="font-semibold text-gray-800">{{ type.name }}</p>
                                            <p class="text-xs text-gray-500 mt-1">
                                                Unidade: <span class="font-semibold">{{ type.unit }}</span>
                                            </p>
                                            <p class="text-xs text-gray-500 mt-1">
                                                Valor: R$ <span class="font-semibold">{{ parseFloat(String(type.service_value || '0')).toFixed(2) }}</span>
                                            </p>
                                            <p class="text-xs text-gray-500 mt-1">
                                                Comissão: <span class="font-semibold">{{ type.commission_percentage }}%</span>
                                            </p>
                                            <p v-if="type.deleted_at" class="text-red-500 font-semibold text-xs mt-1">Removido</p>
                                        </div>
                                        <div class="flex gap-2" v-if="!type.deleted_at">
                                            <button
                                                @click="handleEditServiceType(type)"
                                                :disabled="!serviceModeOn"
                                                :class="[
                                                    'p-2 rounded-lg transition-colors',
                                                    serviceModeOn ? 'text-blue-500 hover:bg-blue-50' : 'text-gray-400 cursor-not-allowed'
                                                ]"
                                                title="Editar"
                                            >
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                            <button
                                                @click="handleDeleteServiceType(type)"
                                                :disabled="!serviceModeOn"
                                                :class="[
                                                    'p-2 rounded-lg transition-colors',
                                                    serviceModeOn ? 'text-red-500 hover:bg-red-50' : 'text-gray-400 cursor-not-allowed'
                                                ]"
                                                title="Remover"
                                            >
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                        <div v-else class="flex gap-2">
                                            <button
                                                @click="handleRestoreServiceType(type)"
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

                            <div v-if="filteredServiceTypes.length === 0" class="text-center text-gray-500 py-8">
                                Nenhum tipo de serviço encontrado.
                            </div>
                        </div>
                </div>
            </main>
            
            <Footer />
        </div>

        <!-- Modals -->
        <EntryTypeFormModal
            :show="showEntryTypeModal"
            :entry-type="editingEntryType"
            :mode="entryTypeModalMode"
            :is-loading="isEntryTypeModalLoading"
            @close="showEntryTypeModal = false; editingEntryType = null"
            @save="handleSaveEntryType"
        />

        <ExitTypeFormModal
            :show="showExitTypeModal"
            :exit-type="editingExitType"
            :mode="exitTypeModalMode"
            :is-loading="isExitTypeModalLoading"
            @close="showExitTypeModal = false; editingExitType = null"
            @save="handleSaveExitType"
        />

        <ServiceTypeFormModal
            :show="showServiceTypeModal"
            :service-type="editingServiceType"
            :mode="serviceTypeModalMode"
            :is-loading="isServiceTypeModalLoading"
            @close="showServiceTypeModal = false; editingServiceType = null"
            @save="handleSaveServiceType"
        />

        <DeleteConfirmModal
            :show="showEntryTypeDeleteModal"
            :record="deletingEntryType"
            :is-loading="isEntryTypeDeleteLoading"
            item-type="entry-type"
            @close="showEntryTypeDeleteModal = false; deletingEntryType = null"
            @confirm="confirmDeleteEntryType"
        />

        <DeleteConfirmModal
            :show="showExitTypeDeleteModal"
            :record="deletingExitType"
            :is-loading="isExitTypeDeleteLoading"
            item-type="exit-type"
            @close="showExitTypeDeleteModal = false; deletingExitType = null"
            @confirm="confirmDeleteExitType"
        />

        <DeleteConfirmModal
            :show="showServiceTypeDeleteModal"
            :record="deletingServiceType"
            :is-loading="isServiceTypeDeleteLoading"
            item-type="service-type"
            @close="showServiceTypeDeleteModal = false; deletingServiceType = null"
            @confirm="confirmDeleteServiceType"
        />

        <!-- Restore Modals -->
        <DeleteConfirmModal
            :show="showEntryTypeRestoreModal"
            :record="restoringEntryType"
            :is-loading="isEntryTypeRestoreLoading"
            item-type="restore"
            @close="showEntryTypeRestoreModal = false; restoringEntryType = null"
            @confirm="confirmRestoreEntryType"
        />

        <DeleteConfirmModal
            :show="showExitTypeRestoreModal"
            :record="restoringExitType"
            :is-loading="isExitTypeRestoreLoading"
            item-type="restore"
            @close="showExitTypeRestoreModal = false; restoringExitType = null"
            @confirm="confirmRestoreExitType"
        />

        <DeleteConfirmModal
            :show="showServiceTypeRestoreModal"
            :record="restoringServiceType"
            :is-loading="isServiceTypeRestoreLoading"
            item-type="restore"
            @close="showServiceTypeRestoreModal = false; restoringServiceType = null"
            @confirm="confirmRestoreServiceType"
        />
        
    </div>
</template>
