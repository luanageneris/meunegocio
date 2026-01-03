<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import { useAuth } from '@/composables/useAuth';
import Sidebar from '@/components/Sidebar.vue';
import PageHeader from '@/components/PageHeader.vue';
import EntryModal from '@/components/modals/EntryModal.vue';
import ExitModal from '@/components/modals/ExitModal.vue';
import DeleteConfirmModal from '@/components/modals/DeleteConfirmModal.vue';

const auth = useAuth();
const company = ref<any>(null);
const isLoading = ref(false);
const activeTab = ref<'hoje' | 'ultimos7dias' | 'mensal'>('hoje');
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = 7;

// Modal states
const showEntryModal = ref(false);
const showExitModal = ref(false);
const showDeleteModal = ref(false);
const editingRecord = ref<any>(null);
const deletingRecord = ref<any>(null);
const modalMode = ref<'create' | 'edit'>('create');

// Mock Data
const mockRegistros = [
    { id: 1, tipo: 'entrada', valor: 10.00, categoria: 'Serviço', descricao: 'Impressão', data: '2024-01-01T10:30:00', removido: false },
    { id: 2, tipo: 'saida', valor: 300.00, categoria: 'Energia', descricao: '', data: '2024-01-01T10:25:00', removido: false },
    { id: 3, tipo: 'entrada', valor: 10.00, categoria: 'Serviço', descricao: 'Digitação', data: '2024-01-01T10:10:00', removido: false },
    { id: 4, tipo: 'saida', valor: 10.00, categoria: 'Recursos', descricao: 'em Papel', data: '2024-01-01T10:05:00', removido: false },
    { id: 5, tipo: 'entrada', valor: 10.00, categoria: 'Serviço', descricao: 'Pesquisa', data: '2024-01-01T09:30:00', removido: false },
    { id: 6, tipo: 'saida', valor: 50.00, categoria: 'Recursos', descricao: 'em Tintas', data: '2024-01-01T09:20:00', removido: false },
    { id: 7, tipo: 'entrada', valor: 10.00, categoria: 'Serviço', descricao: 'Digitação', data: '2024-01-01T10:10:00', removido: false },
    { id: 8, tipo: 'entrada', valor: 15.00, categoria: 'Serviço', descricao: 'Cópia Colorida', data: '2024-01-01T11:15:00', removido: false },
    { id: 9, tipo: 'saida', valor: 80.00, categoria: 'Recursos', descricao: 'Papel A4', data: '2024-01-01T08:45:00', removido: false },
    { id: 10, tipo: 'entrada', valor: 25.00, categoria: 'Serviço', descricao: 'Encadernação', data: '2024-01-01T14:20:00', removido: false },
];

const registros = ref(mockRegistros);

const formatCurrency = (value: number) => {
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

const filteredRegistros = computed(() => {
    let filtered = registros.value.filter(r => !r.removido);
    
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        filtered = filtered.filter(r => 
            r.categoria.toLowerCase().includes(query) ||
            r.descricao.toLowerCase().includes(query) ||
            formatCurrency(r.valor).includes(query)
        );
    }
    
    return filtered;
});

const paginatedRegistros = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    return filteredRegistros.value.slice(start, end);
});

const totalPages = computed(() => {
    return Math.ceil(filteredRegistros.value.length / itemsPerPage);
});

const totalRegistros = computed(() => filteredRegistros.value.length);

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

const handleDelete = (id: number) => {
    const registro = registros.value.find(r => r.id === id);
    if (registro) {
        deletingRecord.value = registro;
        showDeleteModal.value = true;
    }
};

const confirmDelete = () => {
    if (deletingRecord.value) {
        deletingRecord.value.removido = true;
        showDeleteModal.value = false;
        deletingRecord.value = null;
    }
};

const handleEdit = (id: number) => {
    const registro = registros.value.find(r => r.id === id);
    if (registro) {
        editingRecord.value = registro;
        modalMode.value = 'edit';
        if (registro.tipo === 'entrada') {
            showEntryModal.value = true;
        } else {
            showExitModal.value = true;
        }
    }
};

const handleNovaEntrada = () => {
    editingRecord.value = null;
    modalMode.value = 'create';
    showEntryModal.value = true;
};

const handleNovaSaida = () => {
    editingRecord.value = null;
    modalMode.value = 'create';
    showExitModal.value = true;
};

const handleSaveEntry = (entry: any) => {
    if (modalMode.value === 'edit' && editingRecord.value) {
        Object.assign(editingRecord.value, entry);
    } else {
        const newEntry = {
            ...entry,
            id: registros.value.length + 1,
            data: `${entry.data}T${new Date().toTimeString().split(' ')[0]}`,
            removido: false
        };
        registros.value.unshift(newEntry);
    }
    showEntryModal.value = false;
    editingRecord.value = null;
};

const handleSaveEntryAndReference = (entry: any) => {
    handleSaveEntry(entry);
    // Reabrir modal para criar outro registro
    setTimeout(() => {
        handleNovaEntrada();
    }, 100);
};

const handleSaveExit = (exit: any) => {
    if (modalMode.value === 'edit' && editingRecord.value) {
        Object.assign(editingRecord.value, exit);
    } else {
        const newExit = {
            ...exit,
            id: registros.value.length + 1,
            data: `${exit.data}T${new Date().toTimeString().split(' ')[0]}`,
            removido: false
        };
        registros.value.unshift(newExit);
    }
    showExitModal.value = false;
    editingRecord.value = null;
};

const handleSaveExitAndReference = (exit: any) => {
    handleSaveExit(exit);
    // Reabrir modal para criar outro registro
    setTimeout(() => {
        handleNovaSaida();
    }, 100);
};

const handleVerRemovidos = () => {
    console.log('Ver removidos');
    // TODO: Implementar visualização de removidos
};

const goToPage = (page: number) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
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
});
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-amber-50 to-orange-100 flex">
        <Head title="Registros - MEU NEGÓCIO" />

        <!-- Sidebar -->
        <Sidebar active-route="registros" />

        <!-- Main Content -->
        <div class="flex-1 lg:ml-64 transition-all duration-300">
            <PageHeader title-class="ml-12 lg:ml-0" />

            <!-- Main Content -->
            <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <!-- Title -->
                <h2 class="text-2xl font-bold text-center text-orange-500 mb-6">Registros</h2>

                <!-- Tabs -->
                <div class="flex justify-center gap-2 mb-8">
                    <button
                        @click="activeTab = 'hoje'"
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
                        @click="activeTab = 'ultimos7dias'"
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
                        @click="activeTab = 'mensal'"
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

                <!-- Content Card -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <!-- Action Buttons -->
                    <div class="flex flex-wrap gap-3 mb-6">
                        <button
                            @click="handleNovaEntrada"
                            class="px-6 py-2 bg-gradient-to-r from-yellow-400 to-amber-500 text-white rounded-lg font-medium hover:shadow-lg transition-all"
                        >
                            Nova Entrada
                        </button>
                        <button
                            @click="handleNovaSaida"
                            class="px-6 py-2 bg-gradient-to-r from-orange-400 to-orange-500 text-white rounded-lg font-medium hover:shadow-lg transition-all"
                        >
                            Nova Saída
                        </button>
                        <button
                            @click="handleVerRemovidos"
                            class="px-6 py-2 border-2 border-yellow-400 text-yellow-600 rounded-lg font-medium hover:bg-yellow-50 transition-all"
                        >
                            Ver removidos
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

                    <!-- Records List -->
                    <div class="space-y-3">
                        <div
                            v-for="registro in paginatedRegistros"
                            :key="registro.id"
                            class="flex items-center gap-4 p-4 border border-gray-200 rounded-lg hover:shadow-md transition-shadow"
                        >
                            <!-- Valor -->
                            <div class="w-24 flex-shrink-0">
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
                            <div class="w-32 flex-shrink-0">
                                <span class="text-gray-700 text-sm">{{ registro.categoria }}</span>
                            </div>

                            <!-- Descrição -->
                            <div class="flex-1">
                                <span class="text-gray-600 text-sm">{{ registro.descricao || '-' }}</span>
                            </div>

                            <!-- Data -->
                            <div class="w-48 flex-shrink-0">
                                <span class="text-gray-500 text-xs">{{ formatDateTime(registro.data) }}</span>
                            </div>

                            <!-- Actions -->
                            <div class="flex gap-2 flex-shrink-0">
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
            @close="showEntryModal = false; editingRecord = null"
            @save="handleSaveEntry"
            @save-and-reference="handleSaveEntryAndReference"
        />

        <ExitModal
            :show="showExitModal"
            :exit="editingRecord"
            :mode="modalMode"
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
