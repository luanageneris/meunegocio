<script setup lang="ts">
import ModalShell from './ModalShell.vue';

interface Record {
    id: number;
    tipo: 'entrada' | 'saida';
    categoria: string;
    descricao: string;
    valor: number;
    data: string;
}

const props = defineProps<{
    show: boolean;
    record: Record | null;
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
        <!-- Header -->
        <div class="bg-gradient-to-r from-red-400 to-red-500 px-6 py-4 rounded-t-lg">
            <h2 class="text-xl font-bold text-white">
                Removendo
            </h2>
        </div>

        <!-- Content -->
        <div class="p-6">
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

                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Data:</span>
                    <span class="text-sm font-medium text-gray-900">
                        {{ record ? formatDate(record.data) : '' }} às {{ record ? formatTime(record.data) : '' }}
                    </span>
                </div>
            </div>

            <p class="text-xs text-gray-500 mt-4">
                Este registro será marcado como removido e poderá ser recuperado posteriormente.
            </p>
        </div>

        <!-- Footer -->
        <div class="bg-gray-50 px-6 py-4 rounded-b-lg flex gap-3 justify-end">
            <button
                @click="handleClose"
                type="button"
                class="px-6 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
            >
                Cancelar
            </button>
            <button
                @click="handleConfirm"
                type="button"
                class="px-6 py-2 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-lg font-medium hover:shadow-lg transition-all"
            >
                Remover
            </button>
        </div>
    </ModalShell>
</template>
