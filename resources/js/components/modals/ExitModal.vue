<script setup lang="ts">
import { ref, watch, nextTick, computed, onMounted } from 'vue';
import axios from 'axios';
import ModalShell from './ModalShell.vue';
import RecordFormFields from './RecordFormFields.vue';
import UnsavedChangesModal from './UnsavedChangesModal.vue';

interface Exit {
    id?: number;
    tipo: 'entrada' | 'saida';
    tipoSaida: string;
    categoria: string;
    descricao: string;
    data: string; // datetime completo
    hora?: string; // campo auxiliar
    valor: number;
    quantidade: number;
    desconto: number;
    colaborador?: string;
}

const props = defineProps<{
    show: boolean;
    exit?: Exit | null;
    mode: 'create' | 'edit';
    errors?: { message?: string; fields?: Record<string, string[]> };
    useServiceTypes?: boolean;
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
    hora: new Date().toTimeString().slice(0, 5),
    valor: 0,
    quantidade: 1,
    desconto: 0,
    colaborador: '',
});

const initialForm = ref<Exit>({ ...form.value });

const firstInputRef = ref<HTMLSelectElement | null>(null);

// Options from API
const tiposSaida = ref<any[]>([]);
const colaboradores = ref<any[]>([]);

const valorFinal = ref(0);

const hasChanges = computed(() => JSON.stringify(form.value) !== JSON.stringify(initialForm.value));
const showUnsavedModal = ref(false);

const calculateValorFinal = () => {
    const base = form.value.valor * form.value.quantidade;
    valorFinal.value = base - form.value.desconto;
};

const fetchData = async () => {
    try {
        const [exitTypesRes, collaboratorsRes] = await Promise.all([
            axios.get('/api/personalizar/exit-types'),
            axios.get('/api/personalizar/collaborators'),
        ]);

        tiposSaida.value = exitTypesRes.data?.data ?? exitTypesRes.data ?? [];
        colaboradores.value = collaboratorsRes.data?.data ?? collaboratorsRes.data ?? [];
    } catch (error) {
        console.error('Erro ao carregar dados de saída/colaboradores:', error);
    }
};

watch(() => props.show, async (newVal) => {
    if (newVal) {
        await fetchData();
        if (props.exit && props.mode === 'edit') {
            const [datePart, timePart] = (props.exit.data || '').split('T');
            form.value = {
                ...props.exit,
                data: datePart || new Date().toISOString().split('T')[0],
                hora: (timePart || new Date().toTimeString().slice(0, 5)).slice(0, 5),
            };
            initialForm.value = { ...form.value };
        } else {
            resetForm();
            initialForm.value = { ...form.value };
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
        hora: new Date().toTimeString().slice(0, 5),
        valor: 0,
        quantidade: 1,
        desconto: 0,
        colaborador: '',
    };
};

const handleSave = () => {
    const dateTime = (props.useServiceTypes !== false && form.value.hora)
        ? `${form.value.data}T${form.value.hora}`
        : form.value.data;

    const exitType = (tiposSaida.value || []).find((t: any) => t?.name === form.value.tipoSaida);
    const collaborator = (colaboradores.value || []).find((c: any) => c?.name === form.value.colaborador);

    const payload: any = {
        ...form.value,
        data: dateTime,
        exit_type_id: exitType?.id,
        collaborator_id: collaborator?.id,
    };
    if (props.useServiceTypes === false) {
        payload.quantidade = 1;
        payload.desconto = 0;
    }
    emit('save', payload);
};

const handleSaveAndReference = () => {
    const dateTime = (props.useServiceTypes !== false && form.value.hora)
        ? `${form.value.data}T${form.value.hora}`
        : form.value.data;

    const exitType = (tiposSaida.value || []).find((t: any) => t?.name === form.value.tipoSaida);
    const collaborator = (colaboradores.value || []).find((c: any) => c?.name === form.value.colaborador);

    const payload: any = {
        ...form.value,
        data: dateTime,
        exit_type_id: exitType?.id,
        collaborator_id: collaborator?.id,
    };
    if (props.useServiceTypes === false) {
        payload.quantidade = 1;
        payload.desconto = 0;
    }
    emit('saveAndReference', payload);
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
            <div v-if="errors?.message || (errors?.fields && Object.keys(errors.fields).length)" class="rounded-md border border-red-200 bg-red-50 p-3 text-red-700">
                <div v-if="errors?.message" class="font-medium mb-1">{{ errors.message }}</div>
                <ul class="list-disc list-inside text-sm" v-if="errors?.fields">
                    <li v-for="(msgs, field) in errors.fields" :key="field">
                        {{ msgs[0] }}
                    </li>
                </ul>
            </div>
            <RecordFormFields
                variant="saida"
                :form="form"
                :tipos-saida="tiposSaida"
                :colaboradores="colaboradores"
                :simple-mode="useServiceTypes === false"
                @update:form="(val) => (form.value = val)"
                @submit="handleSave"
                    v-model:data="form.data"
                    v-model:hora="form.hora"
            />

            <!-- Valor Final -->
            <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                <div class="flex justify-between items-center">
                    <span class="text-sm font-medium text-gray-700">Valor Final:</span>
                    <span class="text-xl font-bold text-orange-600">
                        R$ {{ (useServiceTypes !== false ? valorFinal : form.valor).toFixed(2).replace('.', ',') }}
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

    <UnsavedChangesModal
        :show="showUnsavedModal"
        @confirm="confirmDiscard"
        @close="cancelDiscard"
    />
</template>
