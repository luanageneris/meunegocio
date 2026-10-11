<script setup lang="ts">
import ModalShell from './ModalShell.vue';

defineProps<{
    show: boolean;
    title?: string;
    message?: string;
    confirmText?: string;
    cancelText?: string;
    isLoading?: boolean;
}>();

const emit = defineEmits<{
    (e: 'confirm'): void;
    (e: 'close'): void;
}>();

const handleConfirm = () => emit('confirm');
const handleClose = () => emit('close');
</script>

<template>
    <ModalShell :show="show" width-class="max-w-md" @close="handleClose">
        <div class="bg-gradient-to-r from-amber-400 to-orange-500 px-6 py-4 rounded-t-lg">
            <h2 class="text-xl font-bold text-white">
                {{ title || 'Alterações não salvas' }}
            </h2>
            <p class="text-sm text-white/90">
                {{ message || 'Você possui alterações não salvas. Deseja sair e descartar as alterações?' }}
            </p>
        </div>

        <div class="p-6">
            <div class="flex items-center justify-center w-12 h-12 mx-auto bg-amber-100 rounded-full mb-4">
                <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 9v2m0 4h.01M12 19c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7z" />
                </svg>
            </div>
            <p class="text-center text-gray-700 mb-2 font-semibold">Deseja realmente sair?</p>
            <p class="text-center text-gray-500 text-sm">As alterações atuais serão perdidas.</p>
        </div>

        <div class="bg-gray-50 px-6 py-4 rounded-b-lg flex gap-3">
            <button
                @click="handleClose"
                :disabled="isLoading"
                class="flex-1 px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 transition-colors disabled:opacity-50"
            >
                {{ cancelText || 'Cancelar' }}
            </button>
            <button
                @click="handleConfirm"
                :disabled="isLoading"
                class="flex-1 px-4 py-2 bg-gradient-to-r from-amber-500 to-orange-500 text-white rounded-lg hover:shadow-lg transition-all disabled:opacity-50"
            >
                {{ confirmText || 'Sair sem salvar' }}
            </button>
        </div>
    </ModalShell>
</template>
