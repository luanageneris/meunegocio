<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

export interface SelectOption {
    id: number | string;
    name: string;
    cpf?: string;
}

interface Props {
    label: string;
    modelValue: SelectOption | null;
    options: SelectOption[];
    placeholder?: string;
    entityType?: 'entry_type' | 'exit_type' | 'collaborator';
    disabled?: boolean;
    required?: boolean;
    error?: string;
}

interface Emits {
    (e: 'update:modelValue', value: SelectOption | null): void;
    (e: 'create', value: { name: string; cpf?: string }): void;
}

const props = withDefaults(defineProps<Props>(), {
    placeholder: 'Selecione uma opção',
    entityType: 'entry_type',
    disabled: false,
    required: false,
    error: '',
});

const emit = defineEmits<Emits>();

const searchInput = ref('');
const isOpen = ref(false);
const showCreateModal = ref(false);
const createFormData = ref<{ name: string; cpf: string }>({ name: '', cpf: '' });
const inputRef = ref<HTMLInputElement | null>(null);
const isLoadingCreate = ref(false);

const filteredOptions = computed(() => {
    if (!searchInput.value.trim()) {
        return props.options;
    }

    const query = searchInput.value.toLowerCase();
    return props.options.filter(
        (option) =>
            option.name.toLowerCase().includes(query) ||
            (option.cpf && option.cpf.includes(query))
    );
});

const noExactMatch = computed(() => {
    if (!searchInput.value.trim()) return false;

    const query = searchInput.value.toLowerCase();
    return !filteredOptions.value.some(
        (option) => option.name.toLowerCase() === query
    );
});

const maskCPF = (value: string): string => {
    const cleanValue = value.replace(/\D/g, '').slice(0, 11);
    if (cleanValue.length <= 3) return cleanValue;
    if (cleanValue.length <= 6) return `${cleanValue.slice(0, 3)}.${cleanValue.slice(3)}`;
    if (cleanValue.length <= 9)
        return `${cleanValue.slice(0, 3)}.${cleanValue.slice(3, 6)}.${cleanValue.slice(6)}`;
    return `${cleanValue.slice(0, 3)}.${cleanValue.slice(3, 6)}.${cleanValue.slice(6, 9)}-${cleanValue.slice(9)}`;
};

const handleCPFInput = (e: Event) => {
    const input = e.target as HTMLInputElement;
    createFormData.value.cpf = maskCPF(input.value);
};

const selectOption = (option: SelectOption) => {
    emit('update:modelValue', option);
    searchInput.value = '';
    isOpen.value = false;
};

const handleKeyDown = (e: KeyboardEvent) => {
    if (e.key === 'Enter' && noExactMatch.value) {
        e.preventDefault();
        openCreateModal();
    } else if (e.key === 'Escape') {
        isOpen.value = false;
    }
};

const openCreateModal = () => {
    createFormData.value = { name: searchInput.value.trim(), cpf: '' };
    showCreateModal.value = true;
};

const closeCreateModal = () => {
    showCreateModal.value = false;
    createFormData.value = { name: '', cpf: '' };
};

const handleCreateConfirm = () => {
    const cpfClean = createFormData.value.cpf.replace(/\D/g, '');

    if (!createFormData.value.name.trim()) {
        return;
    }

    if (props.entityType === 'collaborator' && cpfClean.length !== 11) {
        return;
    }

    isLoadingCreate.value = true;

    const payload: any = {
        name: createFormData.value.name.trim(),
    };

    if (props.entityType === 'collaborator') {
        payload.cpf = createFormData.value.cpf;
    }

    emit('create', payload);
    closeCreateModal();
    isLoadingCreate.value = false;
};

const toggleDropdown = async () => {
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        await nextTick();
        inputRef.value?.focus();
    }
};

watch(
    () => props.options,
    () => {
        // Atualizar quando as opções mudam
    }
);
</script>

<template>
    <div class="w-full">
        <!-- Label -->
        <label v-if="label" class="block text-sm font-medium text-gray-700 mb-1">
            {{ label }}
            <span v-if="required" class="text-red-500">*</span>
        </label>

        <!-- Dropdown Trigger -->
        <div class="relative">
            <button
                @click="toggleDropdown"
                type="button"
                :disabled="disabled"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-left flex justify-between items-center hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent transition-colors disabled:bg-gray-100 disabled:cursor-not-allowed cursor-pointer disabled:cursor-not-allowed"
                :class="{ 'ring-2 ring-red-400': error }"
            >
                <span :class="{ 'text-gray-500': !modelValue }">
                    {{ modelValue?.name || placeholder }}
                </span>
                <svg
                    class="w-4 h-4 text-gray-500 transition-transform"
                    :class="{ 'rotate-180': isOpen }"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                </svg>
            </button>

            <!-- Dropdown Menu -->
            <div
                v-show="isOpen"
                class="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg"
            >
                <!-- Search Input -->
                <div class="p-2 border-b border-gray-200">
                    <input
                        ref="inputRef"
                        v-model="searchInput"
                        type="text"
                        @keydown="handleKeyDown"
                        placeholder="Digite para buscar..."
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent text-sm"
                    />
                </div>

                <!-- Options List -->
                <div v-if="filteredOptions.length > 0" class="max-h-60 overflow-y-auto">
                    <button
                        v-for="option in filteredOptions"
                        :key="option.id"
                        @click="selectOption(option)"
                        type="button"
                        class="w-full px-3 py-2 text-left text-sm hover:bg-blue-50 transition-colors flex justify-between items-center cursor-pointer"
                        :class="{
                            'bg-blue-100 text-blue-900': modelValue?.id === option.id,
                        }"
                    >
                        <span>{{ option.name }}</span>
                        <span v-if="option.cpf" class="text-gray-500 text-xs ml-2">{{ option.cpf }}</span>
                    </button>
                </div>

                <!-- No Results -->
                <div v-else-if="searchInput.trim()" class="p-3 text-center text-sm text-gray-500">
                    Nenhum resultado encontrado
                </div>

                <!-- Create New Option Hint -->
                <div
                    v-if="searchInput.trim() && noExactMatch"
                    class="p-2 border-t border-gray-200 bg-blue-50"
                >
                    <button
                        @click="openCreateModal"
                        type="button"
                        class="w-full px-3 py-2 text-sm text-blue-600 hover:bg-blue-100 rounded-lg transition-colors font-medium flex items-center gap-2 cursor-pointer"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Criar "{{ searchInput.trim() }}"
                    </button>
                    <p class="text-xs text-gray-500 mt-1 ml-3">ou pressione Enter</p>
                </div>
            </div>
        </div>

        <!-- Error Message -->
        <p v-if="error" class="mt-1 text-sm text-red-600">{{ error }}</p>

        <!-- Create Modal -->
        <Dialog :open="showCreateModal" @update:open="showCreateModal = $event">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        Criar novo
                        {{
                            entityType === 'collaborator'
                                ? 'colaborador'
                                : entityType === 'entry_type'
                                ? 'tipo de entrada'
                                : 'tipo de saída'
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        Preencha os dados para criar um novo
                        {{
                            entityType === 'collaborator'
                                ? 'colaborador'
                                : entityType === 'entry_type'
                                ? 'tipo de entrada'
                                : 'tipo de saída'
                        }}.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <!-- Name Field -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nome</label>
                        <input
                            v-model="createFormData.name"
                            type="text"
                            placeholder="Digite o nome"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent"
                            @keydown.enter="handleCreateConfirm"
                        />
                    </div>

                    <!-- CPF Field (only for collaborators) -->
                    <div v-if="entityType === 'collaborator'">
                        <label class="block text-sm font-medium text-gray-700 mb-1">CPF</label>
                        <input
                            :value="createFormData.cpf"
                            @input="handleCPFInput"
                            type="text"
                            inputmode="numeric"
                            placeholder="000.000.000-00"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent"
                            @keydown.enter="handleCreateConfirm"
                        />
                        <p class="text-xs text-gray-500 mt-1">
                            CPF deve ter 11 dígitos
                        </p>
                    </div>
                </div>

                <DialogFooter>
                    <button
                        @click="closeCreateModal"
                        type="button"
                        :disabled="isLoadingCreate"
                        class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg transition-colors disabled:opacity-50 cursor-pointer disabled:cursor-not-allowed"
                    >
                        Cancelar
                    </button>
                    <button
                        @click="handleCreateConfirm"
                        type="button"
                        :disabled="isLoadingCreate || !createFormData.name.trim() || (entityType === 'collaborator' && createFormData.cpf.replace(/\D/g, '').length !== 11)"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 cursor-pointer disabled:cursor-not-allowed"
                    >
                        {{ isLoadingCreate ? 'Criando...' : 'Criar' }}
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
