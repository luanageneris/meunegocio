<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import ModalShell from './ModalShell.vue';
import UnsavedChangesModal from './UnsavedChangesModal.vue';
import { useInputMasks } from '@/composables/useInputMasks';

interface ServiceType {
    id?: number;
    name: string;
    unit: string;
    service_value: string | number;
    commission_percentage: string | number;
    commission_value: string | number;
    resource_value: string | number;
}

const props = defineProps<{
    show: boolean;
    serviceType: ServiceType | null;
    mode: 'create' | 'edit';
    isLoading: boolean;
}>();

const emit = defineEmits<{
    close: [];
    save: [payload: Partial<ServiceType>];
}>();

const formData = ref<ServiceType>({
    name: '',
    unit: '',
    service_value: '',
    commission_percentage: '',
    commission_value: '',
    resource_value: '',
});

const showUnsavedModal = ref(false);
const initialFormData = ref<ServiceType>({
    name: '',
    unit: '',
    service_value: '',
    commission_percentage: '',
    commission_value: '',
    resource_value: '',
});

const errors = ref<Record<string, string[]>>({});
const isUpdatingCommission = ref(false); // Flag para evitar loop infinito
const { formatCurrencyInput, parseCurrencyInput } = useInputMasks();

const hasChanges = computed(() => {
    return JSON.stringify(formData.value) !== JSON.stringify(initialFormData.value);
});

watch(
    () => props.show,
    (newVal) => {
        if (newVal) {
            if (props.mode === 'edit' && props.serviceType) {
                const hydrated = {
                    ...props.serviceType,
                    service_value: formatCurrencyInput(props.serviceType.service_value || 0),
                    commission_value: formatCurrencyInput(props.serviceType.commission_value || 0),
                    resource_value: formatCurrencyInput(props.serviceType.resource_value || 0),
                };
                formData.value = hydrated;
                initialFormData.value = { ...hydrated };
            } else {
                const emptyForm = {
                    name: '',
                    unit: '',
                    service_value: '',
                    commission_percentage: '',
                    commission_value: '',
                    resource_value: '',
                };
                formData.value = { ...emptyForm };
                initialFormData.value = { ...emptyForm };
            }
            errors.value = {};
        }
    }
);

// Quando o percentual muda, calcular o valor em R$
watch(
    () => formData.value.commission_percentage,
    (newPercentage) => {
        if (isUpdatingCommission.value) return;
        
        const serviceValue = parseCurrencyInput(formData.value.service_value);
        const percentage = Number(newPercentage);
        
        if (serviceValue > 0 && !isNaN(percentage)) {
            isUpdatingCommission.value = true;
            formData.value.commission_value = formatCurrencyInput((serviceValue * (percentage / 100)).toFixed(2));
            setTimeout(() => {
                isUpdatingCommission.value = false;
            }, 0);
        }
    }
);

// Quando o valor em R$ muda, calcular o percentual
watch(
    () => formData.value.commission_value,
    (newValue) => {
        if (isUpdatingCommission.value) return;
        
        const serviceValue = parseCurrencyInput(formData.value.service_value);
        const commissionValue = parseCurrencyInput(newValue);
        
        if (serviceValue > 0 && !isNaN(commissionValue)) {
            isUpdatingCommission.value = true;
            formData.value.commission_percentage = ((commissionValue / serviceValue) * 100).toFixed(2);
            setTimeout(() => {
                isUpdatingCommission.value = false;
            }, 0);
        }
    }
);

// Quando o valor do serviço muda, recalcular a comissão em R$ baseado no percentual
watch(
    () => formData.value.service_value,
    (newServiceValue) => {
        if (isUpdatingCommission.value) return;
        
        const serviceValue = parseCurrencyInput(newServiceValue);
        const percentage = Number(formData.value.commission_percentage);
        
        if (serviceValue > 0 && !isNaN(percentage) && percentage > 0) {
            isUpdatingCommission.value = true;
            formData.value.commission_value = formatCurrencyInput((serviceValue * (percentage / 100)).toFixed(2));
            setTimeout(() => {
                isUpdatingCommission.value = false;
            }, 0);
        }
    }
);

const handleCurrencyInput = (field: 'service_value' | 'resource_value' | 'commission_value', event: Event) => {
    const target = event.target as HTMLInputElement;
    formData.value[field] = formatCurrencyInput(target.value);
};

const handleClose = () => {
    if (hasChanges.value) {
        showUnsavedModal.value = true;
        return;
    }

    resetFormState();
    emit('close');
};

const resetFormState = () => {
    const emptyForm = {
        name: '',
        unit: '',
        service_value: '',
        commission_percentage: '',
        commission_value: '',
        resource_value: '',
    };
    formData.value = { ...emptyForm };
    initialFormData.value = { ...emptyForm };
    errors.value = {};
};

const confirmDiscard = () => {
    showUnsavedModal.value = false;
    resetFormState();
    emit('close');
};

const cancelDiscard = () => {
    showUnsavedModal.value = false;
};

const handleSave = () => {
    errors.value = {};
    const serviceValue = parseCurrencyInput(formData.value.service_value);
    const commissionPercentage = Number(formData.value.commission_percentage);
    const commissionValue = parseCurrencyInput(formData.value.commission_value);
    const resourceValue = parseCurrencyInput(formData.value.resource_value);

    if (!formData.value.name?.trim()) {
        errors.value.name = ['O nome é obrigatório'];
    }

    if (!formData.value.unit?.trim()) {
        errors.value.unit = ['A unidade é obrigatória'];
    }

    if (!formData.value.service_value || formData.value.service_value === '') {
        errors.value.service_value = ['O valor do serviço é obrigatório'];
    } else if (serviceValue < 0) {
        errors.value.service_value = ['O valor deve ser positivo'];
    }

    if (!formData.value.commission_percentage || formData.value.commission_percentage === '') {
        errors.value.commission_percentage = ['O percentual de comissão é obrigatório'];
    } else if (commissionPercentage < 0 || commissionPercentage > 100) {
        errors.value.commission_percentage = ['O percentual deve ser entre 0 e 100'];
    }

    if (formData.value.commission_value && formData.value.commission_value !== '' && commissionValue < 0) {
        errors.value.commission_value = ['O valor deve ser positivo'];
    }

    if (formData.value.resource_value && formData.value.resource_value !== '' && resourceValue < 0) {
        errors.value.resource_value = ['O valor deve ser positivo'];
    }

    if (Object.keys(errors.value).length > 0) {
        return;
    }

    if (props.isLoading) return; // Previne múltiplos cliques

    emit('save', {
        name: formData.value.name.trim(),
        unit: formData.value.unit.trim(),
        service_value: serviceValue,
        commission_percentage: commissionPercentage,
        commission_value: commissionValue,
        resource_value: resourceValue,
    });
};
</script>

<template>
    <ModalShell :show="show" width-class="max-w-xl" @close="handleClose">
        <!-- Header -->
        <div class="bg-gradient-to-r from-blue-400 to-blue-500 px-6 py-4 rounded-t-lg">
            <h2 class="text-xl font-bold text-white">{{ mode === 'create' ? 'Novo tipo de serviço' : 'Editar tipo de serviço' }}</h2>
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
                    placeholder="Ex: Digitação, Impressão, Web"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent"
                    :disabled="props.isLoading"
                />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Unidade</label>
                <input
                    v-model="formData.unit"
                    type="text"
                    @keydown.enter.prevent="handleSave"
                    placeholder="Ex: Folha, Hora, Serviço"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent"
                    :disabled="props.isLoading"
                />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Valor do Serviço (R$)</label>
                    <input
                        v-model="formData.service_value"
                        type="text"
                        inputmode="decimal"
                        @keydown.enter.prevent="handleSave"
                        placeholder="0,00"
                        @input="(event) => { handleCurrencyInput('service_value', event); }"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent"
                        :disabled="props.isLoading"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Valor do Recurso (R$)</label>
                    <input
                        v-model="formData.resource_value"
                        type="text"
                        inputmode="decimal"
                        @keydown.enter.prevent="handleSave"
                        placeholder="0,00"
                        @input="(event) => handleCurrencyInput('resource_value', event)"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent"
                        :disabled="props.isLoading"
                    />
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Percentual de Comissão (%)
                        <span class="text-xs text-gray-500 ml-1">Altera o valor em R$</span>
                    </label>
                    <input
                        v-model="formData.commission_percentage"
                        type="number"
                        @keydown.enter.prevent="handleSave"
                        placeholder="0,00"
                        step="0.01"
                        min="0"
                        max="100"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent"
                        :disabled="props.isLoading"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Valor da Comissão (R$)
                        <span class="text-xs text-gray-500 ml-1">Altera o percentual</span>
                    </label>
                    <input
                        v-model="formData.commission_value"
                        type="text"
                        inputmode="decimal"
                        @keydown.enter.prevent="handleSave"
                        placeholder="0,00"
                        @input="(event) => { handleCurrencyInput('commission_value', event); }"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent"
                        :disabled="props.isLoading"
                    />
                </div>
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
                class="px-6 py-2 text-sm font-medium bg-blue-500 text-white hover:bg-blue-600 rounded-lg transition-colors disabled:opacity-50"
            >
                {{ props.isLoading ? 'Salvando...' : 'Salvar' }}
            </button>
        </div>
    </ModalShell>

    <UnsavedChangesModal
        :show="showUnsavedModal"
        @confirm="confirmDiscard"
        @close="cancelDiscard"
    />
</template>
