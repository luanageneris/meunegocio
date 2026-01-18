<script setup lang="ts">
import { ref, watch } from 'vue';
import ModalShell from './ModalShell.vue';

interface ExitType {
    id?: number;
    name: string;
}

const props = defineProps<{
    show: boolean;
    exitType: ExitType | null;
    mode: 'create' | 'edit';
    isLoading: boolean;
}>();

const emit = defineEmits<{
    close: [];
    save: [payload: Partial<ExitType>];
}>();

const formData = ref<ExitType>({
    name: '',
});

const errors = ref<Record<string, string[]>>({});

watch(
    () => props.show,
    (newVal) => {
        if (newVal) {
            if (props.mode === 'edit' && props.exitType) {
                formData.value = { ...props.exitType };
            } else {
                formData.value = { name: '' };
            }
            errors.value = {};
        }
    }
);

const handleClose = () => {
    formData.value = { name: '' };
    errors.value = {};
    emit('close');
};

const handleSave = () => {
    errors.value = {};

    if (!formData.value.name?.trim()) {
        errors.value.name = ['O nome é obrigatório'];
        return;
    }

    if (props.isLoading) return; // Previne múltiplos cliques

    emit('save', {
        name: formData.value.name.trim(),
    });
};
</script>

<template>
    <ModalShell :show="show" width-class="max-w-xl" @close="handleClose">
        <!-- Header -->
        <div class="bg-gradient-to-r from-red-400 to-red-500 px-6 py-4 rounded-t-lg">
            <h2 class="text-xl font-bold text-white">{{ mode === 'create' ? 'Novo tipo de saída' : 'Editar tipo de saída' }}</h2>
        </div>

        <!-- Error Messages -->
        <div v-if="Object.keys(errors).length > 0" class="mx-6 mt-4 text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg p-3">
            <div v-for="(messages, field) in errors" :key="field">
                <div v-for="msg in messages" :key="msg">{{ msg }}</div>
            </div>
        </div>

        <!-- Form -->
        <div class="p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nome</label>
                <input
                    v-model="formData.name"
                    type="text"
                    @keydown.enter.prevent="handleSave"
                    placeholder="Ex: Comissão, Material, Energia, Água"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent"
                    :disabled="props.isLoading"
                />
            </div>
        </div>

        <!-- Footer -->
        <div class="bg-gray-50 px-6 py-4 rounded-b-lg flex gap-3 justify-end">
            <button
                @click="handleClose"
                type="button"
                :disabled="props.isLoading"
                class="px-6 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg transition-colors disabled:opacity-50"
            >
                Cancelar
            </button>
            <button
                @click="handleSave"
                type="button"
                :disabled="props.isLoading"
                class="px-6 py-2 text-sm font-medium bg-red-500 text-white hover:bg-red-600 rounded-lg transition-colors disabled:opacity-50"
            >
                {{ props.isLoading ? 'Salvando...' : 'Salvar' }}
            </button>
        </div>
    </ModalShell>
</template>
