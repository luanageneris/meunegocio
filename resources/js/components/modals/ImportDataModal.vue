<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import SearchableSelect from '@/components/SearchableSelect.vue';
import LoadingSpinner from '@/components/LoadingSpinner.vue';

interface SelectOption {
    id: number | string;
    name: string;
    cpf?: string;
}

interface ImportFile {
    name: string;
    size: number;
}

interface ImportDefaults {
    default_entry_type_id: number | null;
    default_exit_type_id: number | null;
    default_collaborator_id: number | null;
}

interface Props {
    show: boolean;
    entryTypes: SelectOption[];
    exitTypes: SelectOption[];
    collaborators: SelectOption[];
}

interface Emits {
    (e: 'close'): void;
    (e: 'import', payload: { file: File; defaults: ImportDefaults }): void;
    (e: 'createEntryType', data: { name: string }): void;
    (e: 'createExitType', data: { name: string }): void;
    (e: 'createCollaborator', data: { name: string; cpf: string }): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

const selectedFile = ref<File | null>(null);
const fileInfo = ref<ImportFile | null>(null);
const selectedEntryType = ref<SelectOption | null>(null);
const selectedExitType = ref<SelectOption | null>(null);
const selectedCollaborator = ref<SelectOption | null>(null);
const isImporting = ref(false);
const importError = ref('');
const fileInputRef = ref<HTMLInputElement | null>(null);

const fileSize = computed(() => {
    if (!fileInfo.value) return '';
    const bytes = fileInfo.value.size;
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
});

const isFormValid = computed(() => {
    return selectedFile.value && selectedEntryType.value && selectedExitType.value && selectedCollaborator.value;
});

const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    
    if (!file) return;

    if (!file.name.toLowerCase().endsWith('.xlsx') && !file.name.toLowerCase().endsWith('.xls')) {
        importError.value = 'Por favor, selecione um arquivo Excel (.xlsx ou .xls)';
        return;
    }

    selectedFile.value = file;
    fileInfo.value = {
        name: file.name,
        size: file.size,
    };
    importError.value = '';
};

const removeFile = () => {
    selectedFile.value = null;
    fileInfo.value = null;
    importError.value = '';
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
};

const handleImport = async () => {
    if (!isFormValid.value) return;

    isImporting.value = true;
    importError.value = '';

    const defaults: ImportDefaults = {
        default_entry_type_id: selectedEntryType.value?.id as number | null,
        default_exit_type_id: selectedExitType.value?.id as number | null,
        default_collaborator_id: selectedCollaborator.value?.id as number | null,
    };

    emit('import', { file: selectedFile.value!, defaults });

    // Reset after emit
    isImporting.value = false;
};

const handleClose = () => {
    if (!isImporting.value) {
        removeFile();
        selectedEntryType.value = null;
        selectedExitType.value = null;
        selectedCollaborator.value = null;
        emit('close');
    }
};

const openFileDialog = () => {
    fileInputRef.value?.click();
};

// Set default values
watch(
    () => props.show,
    (newVal) => {
        if (newVal) {
            // Tentar pré-selecionar "Não definido" se existir
            const undefinedEntry = props.entryTypes.find(t => t.name === 'Não definido');
            const undefinedExit = props.exitTypes.find(t => t.name === 'Não definido');
            const undefinedCollab = props.collaborators.find(c => c.name === 'Não definido');

            selectedEntryType.value = undefinedEntry || null;
            selectedExitType.value = undefinedExit || null;
            selectedCollaborator.value = undefinedCollab || null;
        }
    }
);
</script>

<template>
    <Dialog :open="show" @update:open="show && handleClose()">
        <DialogContent class="max-w-2xl">
            <DialogHeader>
                <DialogTitle>Importar Entradas e Saídas</DialogTitle>
                <DialogDescription>
                    Selecione um arquivo Excel e configure os valores padrão para campos não mapeados
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-6 py-4">
                <!-- File Selection -->
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Arquivo Excel</label>
                    
                    <input
                        ref="fileInputRef"
                        type="file"
                        accept=".xlsx,.xls"
                        class="hidden"
                        @change="handleFileSelect"
                    />

                    <div v-if="!selectedFile" class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                            <path d="M28 8H12a4 4 0 00-4 4v20a4 4 0 004 4h24a4 4 0 004-4V20" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M32 4v12M32 4l-4 4M32 4l4 4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <p class="mt-2 text-sm text-gray-600">
                            Clique no botão abaixo ou arraste um arquivo Excel
                        </p>
                        <button
                            @click="openFileDialog"
                            type="button"
                            class="mt-3 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 cursor-pointer"
                        >
                            Selecionar arquivo
                        </button>
                    </div>

                    <div v-else class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ fileInfo?.name }}</p>
                                <p class="text-xs text-gray-500">{{ fileSize }}</p>
                            </div>
                            <button
                                @click="removeFile"
                                type="button"
                                :disabled="isImporting"
                                class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-red-700 bg-red-100 hover:bg-red-200 disabled:opacity-50 cursor-pointer disabled:cursor-not-allowed"
                            >
                                Remover
                            </button>
                        </div>
                    </div>

                    <p v-if="importError" class="text-sm text-red-600">{{ importError }}</p>
                </div>

                <!-- Default Values Selection -->
                <div class="space-y-4">
                    <label class="block text-sm font-medium text-gray-700">Valores Padrão</label>

                    <!-- Entry Type -->
                    <SearchableSelect
                        label="Tipo de Entrada Padrão"
                        :modelValue="selectedEntryType"
                        @update:modelValue="selectedEntryType = $event"
                        :options="entryTypes"
                        entityType="entry_type"
                        placeholder="Selecione..."
                        required
                        @create="$emit('createEntryType', $event)"
                    />

                    <!-- Exit Type -->
                    <SearchableSelect
                        label="Tipo de Saída Padrão"
                        :modelValue="selectedExitType"
                        @update:modelValue="selectedExitType = $event"
                        :options="exitTypes"
                        entityType="exit_type"
                        placeholder="Selecione..."
                        required
                        @create="$emit('createExitType', $event)"
                    />

                    <!-- Collaborator -->
                    <SearchableSelect
                        label="Colaborador Padrão"
                        :modelValue="selectedCollaborator"
                        @update:modelValue="selectedCollaborator = $event"
                        :options="collaborators"
                        entityType="collaborator"
                        placeholder="Selecione..."
                        required
                        @create="$emit('createCollaborator', $event)"
                    />
                </div>

                <!-- Loading State -->
                <div v-if="isImporting" class="flex items-center justify-center gap-3 py-4">
                    <LoadingSpinner />
                    <span class="text-sm text-gray-600">Importando dados...</span>
                </div>
            </div>

            <DialogFooter>
                <button
                    @click="handleClose"
                    type="button"
                    :disabled="isImporting"
                    class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg transition-colors disabled:opacity-50 cursor-pointer disabled:cursor-not-allowed"
                >
                    Cancelar
                </button>
                <button
                    @click="handleImport"
                    type="button"
                    :disabled="!isFormValid || isImporting"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 flex items-center gap-2 cursor-pointer disabled:cursor-not-allowed"
                >
                    <svg v-if="!isImporting" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ isImporting ? 'Importando...' : 'Importar' }}
                </button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
