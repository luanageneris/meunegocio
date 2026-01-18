<script setup lang="ts">
import { ref, watch, nextTick, computed } from 'vue';
import ModalShell from './ModalShell.vue';
import UnsavedChangesModal from './UnsavedChangesModal.vue';

interface Collaborator {
    id?: number;
    name: string;
    cpf: string;
    active: boolean;
}

const props = defineProps<{
    show: boolean;
    collaborator?: Collaborator | null;
    mode: 'create' | 'edit';
    isLoading?: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'save', payload: Collaborator): void;
}>();

const form = ref<Collaborator>({
    name: '',
    cpf: '',
    active: true,
});

const initialForm = ref<Collaborator>({ ...form.value });

const errorMessage = ref('');
const firstInputRef = ref<HTMLInputElement | null>(null);
const hasChanges = computed(() => JSON.stringify(form.value) !== JSON.stringify(initialForm.value));
const showUnsavedModal = ref(false);

const maskCPF = (value: string): string => {
    const cleanValue = value.replace(/\D/g, '').slice(0, 11);
    if (cleanValue.length <= 3) return cleanValue;
    if (cleanValue.length <= 6) return `${cleanValue.slice(0, 3)}.${cleanValue.slice(3)}`;
    if (cleanValue.length <= 9) return `${cleanValue.slice(0, 3)}.${cleanValue.slice(3, 6)}.${cleanValue.slice(6)}`;
    return `${cleanValue.slice(0, 3)}.${cleanValue.slice(3, 6)}.${cleanValue.slice(6, 9)}-${cleanValue.slice(9)}`;
};

const handleCPFInput = (e: Event) => {
    const input = e.target as HTMLInputElement;
    form.value.cpf = maskCPF(input.value);
};

watch(
    () => props.show,
    (val) => {
        if (val) {
            if (props.collaborator && props.mode === 'edit') {
                form.value = { ...props.collaborator };
                initialForm.value = { ...props.collaborator };
            } else {
                resetForm();
                initialForm.value = { ...form.value };
            }
            errorMessage.value = '';
            nextTick(() => firstInputRef.value?.focus());
        }
    }
);

const resetForm = () => {
    form.value = { name: '', cpf: '', active: true };
};

const validateForm = (): boolean => {
    errorMessage.value = '';
    
    if (!form.value.name.trim()) {
        errorMessage.value = 'Nome é obrigatório';
        return false;
    }
    
    const cpfClean = form.value.cpf.replace(/\D/g, '');
    if (cpfClean.length !== 11) {
        errorMessage.value = 'CPF deve ter 11 dígitos (formato: XXX.XXX.XXX-XX)';
        return false;
    }
    
    return true;
};

const handleSave = () => {
    if (!validateForm()) return;
    emit('save', { ...form.value });
};

const handleClose = () => {
    if (hasChanges.value) {
        showUnsavedModal.value = true;
        return;
    }
    emit('close');
};

const confirmDiscard = () => {
    showUnsavedModal.value = false;
    form.value = { ...initialForm.value };
    emit('close');
};

const cancelDiscard = () => {
    showUnsavedModal.value = false;
};
</script>

<template>
    <ModalShell :show="show" width-class="max-w-xl" @close="handleClose">
        <!-- Header -->
        <div class="bg-gradient-to-r from-amber-400 to-orange-500 px-6 py-4 rounded-t-lg">
            <h2 class="text-xl font-bold text-white">{{ mode === 'create' ? 'Novo colaborador' : 'Editar colaborador' }}</h2>
        </div>

        <!-- Error Message -->
        <div v-if="errorMessage" class="mx-6 mt-4 text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg p-3">
            {{ errorMessage }}
        </div>

        <!-- Form -->
        <div class="p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nome</label>
                <input
                    ref="firstInputRef"
                    v-model="form.name"
                    type="text"
                    @keydown.enter.prevent="handleSave"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent"
                    placeholder="Nome completo"
                />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">CPF</label>
                <input
                    :value="form.cpf"
                    @input="handleCPFInput"
                    @keydown.enter.prevent="handleSave"
                    type="text"
                    inputmode="numeric"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent"
                    placeholder="000.000.000-00"
                />
            </div>

            <div class="flex items-center gap-2">
                <input
                    id="active"
                    v-model="form.active"
                    type="checkbox"
                    class="h-4 w-4 text-amber-500 border-gray-300 rounded"
                />
                <label for="active" class="text-sm text-gray-700">Ativo</label>
            </div>
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
                @click="handleSave"
                :disabled="isLoading || !form.name.trim()"
                type="button"
                class="px-6 py-2 bg-gradient-to-r from-amber-400 to-orange-500 text-white rounded-lg font-medium hover:shadow-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed"
            >
                {{ isLoading ? 'Salvando...' : 'Salvar' }}
            </button>
        </div>
    </ModalShell>

    <UnsavedChangesModal
        :show="showUnsavedModal"
        @confirm="confirmDiscard"
        @close="cancelDiscard"
    />
</template>
