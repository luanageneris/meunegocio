<script setup lang="ts">
import { ref, watch, nextTick } from 'vue';
import ModalShell from './ModalShell.vue';

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

const firstInputRef = ref<HTMLInputElement | null>(null);

watch(
    () => props.show,
    (val) => {
        if (val) {
            if (props.collaborator && props.mode === 'edit') {
                form.value = { ...props.collaborator };
            } else {
                resetForm();
            }
            nextTick(() => firstInputRef.value?.focus());
        }
    }
);

const resetForm = () => {
    form.value = { name: '', cpf: '', active: true };
};

const handleSave = () => {
    emit('save', { ...form.value });
};

const handleClose = () => emit('close');
</script>

<template>
    <ModalShell :show="show" width-class="max-w-xl" @close="handleClose">
        <!-- Header -->
        <div class="bg-gradient-to-r from-amber-400 to-orange-500 px-6 py-4 rounded-t-lg">
            <h2 class="text-xl font-bold text-white">{{ mode === 'create' ? 'Novo colaborador' : 'Editar colaborador' }}</h2>
        </div>

        <!-- Form -->
        <div class="p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nome</label>
                <input
                    ref="firstInputRef"
                    v-model="form.name"
                    type="text"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent"
                    placeholder="Nome completo"
                />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">CPF</label>
                <input
                    v-model="form.cpf"
                    type="text"
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
                type="button"
                class="px-6 py-2 bg-gradient-to-r from-amber-400 to-orange-500 text-white rounded-lg font-medium hover:shadow-lg transition-all"
            >
                Salvar
            </button>
        </div>
    </ModalShell>
</template>
