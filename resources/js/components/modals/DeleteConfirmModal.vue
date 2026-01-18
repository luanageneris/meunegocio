<script setup lang="ts">
import ModalShell from './ModalShell.vue';

interface DeleteItem {
    id: number;
    name?: string;
    tipo?: 'entrada' | 'saida';
    categoria?: string;
    descricao?: string;
    valor?: number;
    data?: string;
}

const props = defineProps<{
    show: boolean;
    record: DeleteItem | null;
    isLoading?: boolean;
    itemType?: string;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'confirm'): void;
}>();

const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(value);
};

const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
};

const formatTime = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleTimeString('pt-BR', {
        hour: '2-digit',
        minute: '2-digit'
    });
};

const handleConfirm = () => {
    emit('confirm');
};

const handleClose = () => {
    emit('close');
};
</script>

<template>
    <ModalShell :show="show && !!record" width-class="max-w-md" @close="handleClose">
        <!-- Header para Delete -->
        <div v-if="itemType !== 'restore'" class="bg-gradient-to-r from-red-500 to-red-600 px-6 py-4 rounded-t-lg">
            <h2 class="text-xl font-bold text-white">Confirmar Exclusão</h2>
        </div>

        <!-- Header para Restore -->
        <div v-else class="bg-gradient-to-r from-green-500 to-green-600 px-6 py-4 rounded-t-lg">
            <h2 class="text-xl font-bold text-white">Confirmar Restauração</h2>
        </div>

        <!-- Content -->
        <div class="p-6">
            <!-- Icon para Delete -->
            <div v-if="itemType !== 'restore'" class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 rounded-full mb-4">
                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
            </div>

            <!-- Icon para Restore -->
            <div v-else class="flex items-center justify-center w-12 h-12 mx-auto bg-green-100 rounded-full mb-4">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
            </div>

            <!-- Para Colaboradores Remover -->
            <template v-if="itemType === 'collaborator' && record?.name">
                <p class="text-center text-gray-700 font-semibold mb-2">
                    Remover colaborador?
                </p>
                <p class="text-center text-gray-600 text-sm mb-4">
                    Tem certeza que deseja remover <span class="font-semibold text-gray-800">{{ record.name }}</span>?
                </p>
            </template>

            <!-- Para Colaboradores Restaurar -->
            <template v-else-if="itemType === 'restore' && record?.name">
                <p class="text-center text-gray-700 font-semibold mb-2">
                    Restaurar este item?
                </p>
                <p class="text-center text-gray-600 text-sm mb-4">
                    Tem certeza que deseja restaurar <span class="font-semibold text-gray-800">{{ record.name }}</span>?
                </p>
                <p class="text-center text-gray-500 text-xs">
                    O item será ativado e voltará à lista principal.
                </p>
            </template>

            <!-- Para Registros (Entrada/Saída) -->
            <template v-else-if="record?.tipo && record?.categoria">
                <p class="text-sm text-gray-600 mb-4">
                    Confirmar a remoção
                </p>

                <div class="bg-gray-50 rounded-lg p-4 space-y-2">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Tipo:</span>
                        <span class="text-sm font-medium text-gray-900">
                            {{ record?.tipo === 'entrada' ? 'Entrada' : 'Saída' }}
                        </span>
                    </div>

                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Categoria:</span>
                        <span class="text-sm font-medium text-gray-900">
                            {{ record?.categoria }}
                        </span>
                    </div>

                    <div v-if="record?.descricao" class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Descrição:</span>
                        <span class="text-sm font-medium text-gray-900">
                            {{ record?.descricao }}
                        </span>
                    </div>

                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Valor:</span>
                        <span 
                            :class="[
                                'text-sm font-bold',
                                record?.tipo === 'entrada' ? 'text-yellow-600' : 'text-orange-600'
                            ]"
                        >
                            {{ formatCurrency(record?.valor || 0) }}
                        </span>
                    </div>

                    <div v-if="record?.data" class="flex justify-between items-center pt-2 border-t border-gray-200">
                        <span class="text-sm text-gray-600">Data e Hora:</span>
                        <span class="text-sm font-medium text-gray-900">
                            {{ formatDate(record.data) }} às {{ formatTime(record.data) }}
                        </span>
                    </div>
                </div>
            </template>

            <p v-if="itemType !== 'restore'" class="text-center text-gray-500 text-xs mt-4">
                Esta ação é permanente.
            </p>
        </div>

        <!-- Footer -->
        <div class="bg-gray-50 px-6 py-4 rounded-b-lg flex gap-3">
            <button
                @click="handleClose"
                :disabled="isLoading"
                class="flex-1 px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors disabled:opacity-50 disabled:cursor-not-allowed font-medium"
            >
                Cancelar
            </button>
            <!-- Botão para Delete -->
            <button
                v-if="itemType !== 'restore'"
                @click="handleConfirm"
                :disabled="isLoading"
                class="flex-1 px-4 py-2 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-lg hover:shadow-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed font-medium"
            >
                {{ isLoading ? 'Removendo...' : 'Remover' }}
            </button>
            <!-- Botão para Restore -->
            <button
                v-else
                @click="handleConfirm"
                :disabled="isLoading"
                class="flex-1 px-4 py-2 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-lg hover:shadow-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed font-medium"
            >
                {{ isLoading ? 'Restaurando...' : 'Restaurar' }}
            </button>
        </div>
    </ModalShell>
</template>
