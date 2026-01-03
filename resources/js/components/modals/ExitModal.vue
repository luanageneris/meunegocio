<script setup lang="ts">
import { ref, watch, nextTick } from 'vue';
import ModalShell from './ModalShell.vue';
import RecordFormFields from './RecordFormFields.vue';

interface Exit {
    id?: number;
    tipo: 'entrada' | 'saida';
    tipoSaida: string;
    categoria: string;
    descricao: string;
    data: string;
    valor: number;
    quantidade: number;
    desconto: number;
    colaborador?: string;
    observacao?: string;
}

const props = defineProps<{
    show: boolean;
    exit?: Exit | null;
    mode: 'create' | 'edit';
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'save', exit: Exit): void;
    (e: 'saveAndReference', exit: Exit): void;
}>();

const form = ref<Exit>({
    tipo: 'saida',
    tipoSaida: 'Comissão',
    categoria: 'Comissão',
    descricao: '',
    data: new Date().toISOString().split('T')[0],
    valor: 0,
    quantidade: 1,
    desconto: 0,
    colaborador: '',
    observacao: ''
});

const firstInputRef = ref<HTMLSelectElement | null>(null);

// Mock options
const tiposSaida = ['Comissão', 'Material', 'Energia', 'Água', 'Internet', 'Aluguel', 'Outros'];
const colaboradores = ['João Silva', 'Maria Santos', 'Pedro Costa', 'Ana Oliveira'];

const valorFinal = ref(0);

const calculateValorFinal = () => {
    const base = form.value.valor * form.value.quantidade;
    valorFinal.value = base - form.value.desconto;
};

watch(() => props.show, (newVal) => {
    if (newVal) {
        if (props.exit && props.mode === 'edit') {
            form.value = { ...props.exit };
        } else {
            resetForm();
        }
        calculateValorFinal();
        nextTick(() => {
            firstInputRef.value?.focus();
        });
    }
});

watch([() => form.value.valor, () => form.value.quantidade, () => form.value.desconto], () => {
    calculateValorFinal();
});

const resetForm = () => {
    form.value = {
        tipo: 'saida',
        tipoSaida: 'Comissão',
        categoria: 'Comissão',
        descricao: '',
        data: new Date().toISOString().split('T')[0],
        valor: 0,
        quantidade: 1,
        desconto: 0,
        colaborador: '',
        observacao: ''
    };
};

const handleSave = () => {
    emit('save', { ...form.value });
};

const handleSaveAndReference = () => {
    emit('saveAndReference', { ...form.value });
};

const handleClose = () => {
    emit('close');
};

</script>

<template>
    <ModalShell :show="show" width-class="max-w-2xl" @close="handleClose">
        <!-- Header -->
        <div class="bg-gradient-to-r from-orange-400 to-orange-500 px-6 py-4 rounded-t-lg">
            <h2 class="text-xl font-bold text-white">
                {{ mode === 'create' ? 'Criando' : 'Editando' }}
            </h2>
            <p class="text-sm text-white opacity-90">
                Registro de Saída
            </p>
        </div>

        <!-- Form -->
        <div class="p-6 space-y-4">
            <RecordFormFields
                variant="saida"
                :form="form"
                :tipos-saida="tiposSaida"
                :colaboradores="colaboradores"
                @update:form="(val) => (form.value = val)"
            />

            <!-- Valor Final -->
            <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                <div class="flex justify-between items-center">
                    <span class="text-sm font-medium text-gray-700">Valor Final:</span>
                    <span class="text-xl font-bold text-orange-600">
                        R$ {{ valorFinal.toFixed(2).replace('.', ',') }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="bg-gray-50 px-6 py-4 rounded-b-lg flex flex-wrap gap-3 justify-end">
            <button
                v-if="mode === 'create'"
                @click="handleSaveAndReference"
                type="button"
                class="px-4 py-2 text-sm font-medium text-orange-700 hover:text-orange-800 transition-colors"
            >
                Salvar e criar referência
            </button>
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
                class="px-6 py-2 bg-gradient-to-r from-orange-400 to-orange-500 text-white rounded-lg font-medium hover:shadow-lg transition-all"
            >
                Salvar
            </button>
        </div>
    </ModalShell>
</template>
