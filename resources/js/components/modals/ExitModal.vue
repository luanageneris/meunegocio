<script setup lang="ts">
import { ref, watch, nextTick, computed } from 'vue';
import axios from 'axios';
import ModalShell from './ModalShell.vue';
import UnsavedChangesModal from './UnsavedChangesModal.vue';
import SearchableSelect from '@/components/SearchableSelect.vue';

interface SelectOption {
    id: number | string;
    name: string;
    cpf?: string;
}

interface Exit {
    id?: number;
    tipo: 'entrada' | 'saida';
    exit_type_id?: number;
    collaborator_id?: number;
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
const tiposSaidaOptions = ref<SelectOption[]>([]);
const colaboradores = ref<any[]>([]);
const colaboradoresOptions = ref<SelectOption[]>([]);

// Selected options for SearchableSelect
const selectedExitType = ref<SelectOption | null>(null);
const selectedCollaborator = ref<SelectOption | null>(null);

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
        
        // Map to SelectOption format
        tiposSaidaOptions.value = tiposSaida.value.map((t: any) => ({
            id: t.id,
            name: t.name
        }));
        
        colaboradoresOptions.value = colaboradores.value.map((c: any) => ({
            id: c.id,
            name: c.name,
            cpf: c.cpf
        }));
    } catch (error) {
        console.error('Erro ao carregar dados de saída/colaboradores:', error);
    }
};

const handleCreateExitType = async (data: { name: string; company_id?: number }) => {
    try {
        const response = await axios.post('/api/personalizar/exit-types', data);
        const newExitType = response.data?.data || response.data;
        
        tiposSaida.value.push(newExitType);
        tiposSaidaOptions.value.push({
            id: newExitType.id,
            name: newExitType.name
        });
        
        selectedExitType.value = {
            id: newExitType.id,
            name: newExitType.name
        };
    } catch (error) {
        console.error('Erro ao criar tipo de saída:', error);
    }
};

const handleCreateCollaborator = async (data: { name: string; cpf: string; company_id?: number }) => {
    try {
        const response = await axios.post('/api/personalizar/collaborators', data);
        const newCollaborator = response.data?.data || response.data;
        
        colaboradores.value.push(newCollaborator);
        colaboradoresOptions.value.push({
            id: newCollaborator.id,
            name: newCollaborator.name,
            cpf: newCollaborator.cpf
        });
        
        selectedCollaborator.value = {
            id: newCollaborator.id,
            name: newCollaborator.name,
            cpf: newCollaborator.cpf
        };
    } catch (error) {
        console.error('Erro ao criar colaborador:', error);
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
            
            // Pre-select options for edit mode
            if (props.exit.exit_type_id) {
                selectedExitType.value = tiposSaidaOptions.value.find(
                    t => t.id === props.exit!.exit_type_id
                ) || null;
            }
            if (props.exit.collaborator_id) {
                selectedCollaborator.value = colaboradoresOptions.value.find(
                    c => c.id === props.exit!.collaborator_id
                ) || null;
            }
        } else {
            resetForm();
            initialForm.value = { ...form.value };
            selectedExitType.value = null;
            selectedCollaborator.value = null;
        }
        
        calculateValorFinal();
        nextTick(() => {
            firstInputRef.value?.focus();
        });
    }
});

// Sync selectedExitType to form
watch(selectedExitType, (newVal) => {
    if (newVal) {
        form.value.exit_type_id = newVal.id as number;
    }
});

// Sync selectedCollaborator to form
watch(selectedCollaborator, (newVal) => {
    if (newVal) {
        form.value.collaborator_id = newVal.id as number;
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

    const payload: any = {
        ...form.value,
        data: dateTime,
        exit_type_id: selectedExitType.value?.id,
        collaborator_id: selectedCollaborator.value?.id,
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

    const payload: any = {
        ...form.value,
        data: dateTime,
        exit_type_id: selectedExitType.value?.id,
        collaborator_id: selectedCollaborator.value?.id,
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

onMounted(() => {
    fetchData();
});

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

            <!-- Layout quando useServiceTypes === false (simpleMode) -->
            <template v-if="useServiceTypes === false">
                <!-- Linha: Tipo de Saída, Data, Colaborador -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <SearchableSelect
                        label="Tipo de Saída"
                        :modelValue="selectedExitType"
                        @update:modelValue="selectedExitType = $event"
                        :options="tiposSaidaOptions"
                        entityType="exit_type"
                        placeholder="Pesquise ou crie..."
                        required
                        @create="handleCreateExitType"
                    />

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Data</label>
                        <input
                            v-model="form.data"
                            type="date"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg font-normal leading-normal [&::-webkit-date-and-time-value]:p-0 [&::-webkit-date-and-time-value]:m-0 [&::-webkit-date-and-time-value]:leading-normal focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent"
                        />
                    </div>

                    <SearchableSelect
                        label="Colaborador"
                        :modelValue="selectedCollaborator"
                        @update:modelValue="selectedCollaborator = $event"
                        :options="colaboradoresOptions"
                        entityType="collaborator"
                        placeholder="Pesquise pelo nome..."
                        @create="handleCreateCollaborator"
                    />
                </div>

                <!-- Linha: Valor e Descrição -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Valor R$</label>
                        <input
                            v-model.number="form.valor"
                            type="number"
                            step="0.01"
                            min="0"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Descrição</label>
                        <input
                            v-model="form.descricao"
                            type="text"
                            placeholder="Descrição"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent"
                        />
                    </div>
                </div>
            </template>

            <!-- Layout padrão quando useServiceTypes !== false -->
            <template v-else>
                <!-- Linha: Tipo de Saída e Data -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <SearchableSelect
                        label="Tipo de Saída"
                        :modelValue="selectedExitType"
                        @update:modelValue="selectedExitType = $event"
                        :options="tiposSaidaOptions"
                        entityType="exit_type"
                        placeholder="Pesquise ou crie..."
                        required
                        @create="handleCreateExitType"
                    />

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Data</label>
                        <input
                            v-model="form.data"
                            type="date"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg font-normal leading-normal [&::-webkit-date-and-time-value]:p-0 [&::-webkit-date-and-time-value]:m-0 [&::-webkit-date-and-time-value]:leading-normal focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent"
                        />
                    </div>
                </div>

                <!-- Linha: Hora e Colaborador -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Hora</label>
                        <input
                            v-model="form.hora"
                            type="time"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent"
                        />
                    </div>

                    <SearchableSelect
                        label="Colaborador"
                        :modelValue="selectedCollaborator"
                        @update:modelValue="selectedCollaborator = $event"
                        :options="colaboradoresOptions"
                        entityType="collaborator"
                        placeholder="Pesquise pelo nome..."
                        @create="handleCreateCollaborator"
                    />
                </div>

                <!-- Descrição -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Descrição</label>
                    <input
                        v-model="form.descricao"
                        type="text"
                        placeholder="Descrição"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent"
                    />
                </div>

                <!-- Valor e Quantidade -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Valor R$</label>
                        <input
                            v-model.number="form.valor"
                            type="number"
                            step="0.01"
                            min="0"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Quantidade</label>
                        <input
                            v-model.number="form.quantidade"
                            type="number"
                            min="1"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent"
                        />
                    </div>
                </div>

                <!-- Desconto -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Desconto</label>
                    <input
                        v-model.number="form.desconto"
                        type="number"
                        step="0.01"
                        min="0"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent"
                    />
                </div>
            </template>

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
