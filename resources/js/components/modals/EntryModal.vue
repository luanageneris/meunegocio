<script setup lang="ts">
import { ref, watch, nextTick, onMounted, computed } from 'vue';
import axios from 'axios';
import ModalShell from './ModalShell.vue';
import UnsavedChangesModal from './UnsavedChangesModal.vue';

interface ServiceType {
    id: number;
    name: string;
    unit: string;
    service_value: string;
    commission_percentage: string;
}

interface Entry {
    id?: number;
    tipo: 'entrada';
    entry_type_id?: number;
    service_type_id?: number;
    collaborator_id?: number;
    tipoEntrada?: string;
    tipoServico?: string;
    responsavel?: string;
    descricao: string;
    data: string;
    valor: number;
    quantidade: number;
    desconto: number;
    observacao?: string;
}

const props = defineProps<{
    show: boolean;
    entry?: Entry | null;
    mode: 'create' | 'edit';
    useServiceTypes?: boolean;
    errors?: { message?: string; fields?: Record<string, string[]> };
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'save', entry: Entry): void;
    (e: 'saveAndReference', entry: Entry): void;
}>();

const form = ref<Entry>({
    tipo: 'entrada',
    tipoEntrada: '',
    tipoServico: '',
    responsavel: '',
    descricao: '',
    data: new Date().toISOString().split('T')[0],
    valor: 0,
    quantidade: 1,
    desconto: 0,
    observacao: ''
});

const initialForm = ref<Entry>({ ...form.value });

// Dynamic options from API
const tiposEntrada = ref<any[]>([]);
const tiposServico = ref<ServiceType[]>([]);
const colaboradores = ref<any[]>([]);

const selectedService = computed(() => {
    return tiposServico.value.find(s => s.name === form.value.tipoServico);
});

const unidade = computed(() => {
    return selectedService.value?.unit || '';
});

const hasChanges = computed(() => JSON.stringify(form.value) !== JSON.stringify(initialForm.value));
const showUnsavedModal = ref(false);

const fetchData = async () => {
    try {
        const requests: Promise<any>[] = [
            axios.get('/api/personalizar/entry-types'),
            axios.get('/api/personalizar/collaborators'),
        ];
        if (props.useServiceTypes !== false) {
            requests.splice(1, 0, axios.get('/api/personalizar/service-types'));
        }
        const responses = await Promise.all(requests);
        // Map responses by whether service types included
        // If useServiceTypes true: [entry, service, collaborators]
        // If false: [entry, collaborators]
        const entryTypesRes = responses[0];
        const serviceTypesRes = (props.useServiceTypes !== false) ? responses[1] : null;
        const collaboratorsRes = (props.useServiceTypes !== false) ? responses[2] : responses[1];
        
        // API pode retornar { data: [...] } ou array direto
        tiposEntrada.value = entryTypesRes.data?.data ?? entryTypesRes.data ?? [];
        tiposServico.value = serviceTypesRes ? (serviceTypesRes.data?.data ?? serviceTypesRes.data ?? []) : [];
        colaboradores.value = collaboratorsRes.data?.data ?? collaboratorsRes.data ?? [];
    } catch (error) {
        console.error('Erro ao carregar dados:', error);
    }
};

watch(() => props.show, async (newVal) => {
    if (newVal) {
        await fetchData();
        
        if (props.entry && props.mode === 'edit') {
            form.value = { ...props.entry };
            initialForm.value = { ...form.value };
        } else {
            resetForm();
            initialForm.value = { ...form.value };
        }
        
        nextTick(() => {
            // Focus no primeiro campo
        });
    }
});

watch(() => form.value.tipoServico, () => {
    if (props.useServiceTypes !== false && selectedService.value) {
        form.value.valor = parseFloat(selectedService.value.service_value);
    }
});

const resetForm = () => {
    form.value = {
        tipo: 'entrada',
        tipoEntrada: tiposEntrada.value[0]?.name || '',
        tipoServico: '',
        responsavel: '',
        descricao: '',
        data: new Date().toISOString().split('T')[0],
        valor: 0,
        quantidade: props.useServiceTypes !== false ? 1 : 1,
        desconto: props.useServiceTypes !== false ? 0 : 0,
        observacao: ''
    };
};

const handleSave = () => {
    // Adicionar IDs correspondentes
    const entryType = tiposEntrada.value.find(t => t.name === form.value.tipoEntrada);
    const serviceType = props.useServiceTypes !== false ? tiposServico.value.find(s => s.name === form.value.tipoServico) : undefined;
    const collaborator = colaboradores.value.find(c => c.name === form.value.responsavel);

    const payload = {
        ...form.value,
        type: 'entry',
        entry_type_id: entryType?.id,
        service_type_id: serviceType?.id,
        collaborator_id: collaborator?.id
    };
    if (props.useServiceTypes === false) {
        (payload as any).quantity = 1;
        (payload as any).discount = 0;
        (payload as any).service_type_id = null;
    }
    
    emit('save', payload);
};

const handleSaveAndReference = () => {
    const entryType = tiposEntrada.value.find(t => t.name === form.value.tipoEntrada);
    const serviceType = props.useServiceTypes !== false ? tiposServico.value.find(s => s.name === form.value.tipoServico) : undefined;
    const collaborator = colaboradores.value.find(c => c.name === form.value.responsavel);

    const payload = {
        ...form.value,
        type: 'entry',
        entry_type_id: entryType?.id,
        service_type_id: serviceType?.id,
        collaborator_id: collaborator?.id
    };
    if (props.useServiceTypes === false) {
        (payload as any).quantity = 1;
        (payload as any).discount = 0;
        (payload as any).service_type_id = null;
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
        <div class="bg-gradient-to-r from-yellow-400 to-amber-500 px-6 py-4 rounded-t-lg">
            <h2 class="text-xl font-bold text-white">
                {{ mode === 'create' ? 'Criando' : 'Editando' }}
            </h2>
            <p class="text-sm text-white opacity-90">
                Registro de Entrada
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

            <!-- Layout otimizado quando modo de serviço está inativo -->
            <template v-if="useServiceTypes === false">
                <!-- Linha: Tipo de entrada, Responsável, Data -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de entrada</label>
                        <select
                            v-model="form.tipoEntrada"
                            @keydown.enter="handleSave"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:border-transparent bg-yellow-50"
                        >
                            <option value="">Selecione</option>
                            <option v-for="tipo in tiposEntrada" :key="tipo.id" :value="tipo.name">
                                {{ tipo.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Responsável</label>
                        <select
                            v-model="form.responsavel"
                            @keydown.enter="handleSave"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:border-transparent"
                        >
                            <option value="">Selecione</option>
                            <option v-for="colab in colaboradores" :key="colab.id" :value="colab.name">
                                {{ colab.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <UnsavedChangesModal
                            :show="showUnsavedModal"
                            @confirm="confirmDiscard"
                            @close="cancelDiscard"
                        />
                        <label class="block text-sm font-medium text-gray-700 mb-1">Data</label>
                        <input
                            v-model="form.data"
                            type="date"
                            @keydown.enter="handleSave"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg font-normal leading-normal [&::-webkit-date-and-time-value]:p-0 [&::-webkit-date-and-time-value]:m-0 [&::-webkit-date-and-time-value]:leading-normal focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:border-transparent"
                        />
                    </div>
                </div>

                <!-- Linha: Valor e Descrição -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Valor R$</label>
                        <div class="relative">
                            <input
                                v-model.number="form.valor"
                                type="number"
                                step="0.01"
                                min="0"
                                :placeholder="`0,00${unidade ? ' - ' + unidade : ''}`"
                                @keydown.enter="handleSave"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:border-transparent bg-yellow-50"
                            />
                            <span v-if="unidade" class="absolute right-3 top-2 text-sm text-gray-500">{{ unidade }}</span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Descrição</label>
                        <input
                            v-model="form.descricao"
                            type="text"
                            placeholder="Descrição"
                            @keydown.enter="handleSave"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:border-transparent"
                        />
                    </div>
                </div>
            </template>

            <!-- Layout padrão quando modo de serviço está ativo -->
            <template v-else>
                <!-- Tipo de entrada e Tipo de Serviço -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de entrada</label>
                        <select
                            v-model="form.tipoEntrada"
                            @keydown.enter="handleSave"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:border-transparent bg-yellow-50"
                        >
                            <option value="">Selecione</option>
                            <option v-for="tipo in tiposEntrada" :key="tipo.id" :value="tipo.name">
                                {{ tipo.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de Serviço</label>
                        <select
                            v-model="form.tipoServico"
                            @keydown.enter="handleSave"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:border-transparent"
                        >
                            <option value="">Selecione</option>
                            <option v-for="servico in tiposServico" :key="servico.id" :value="servico.name">
                                {{ servico.name }}
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Responsável e Data -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Responsável</label>
                        <select
                            v-model="form.responsavel"
                            @keydown.enter="handleSave"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:border-transparent"
                        >
                            <option value="">Selecione</option>
                            <option v-for="colab in colaboradores" :key="colab.id" :value="colab.name">
                                {{ colab.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <UnsavedChangesModal
                            :show="showUnsavedModal"
                            @confirm="confirmDiscard"
                            @close="cancelDiscard"
                        />
                        <label class="block text-sm font-medium text-gray-700 mb-1">Data</label>
                        <input
                            v-model="form.data"
                            type="date"
                            @keydown.enter="handleSave"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg font-normal leading-normal [&::-webkit-date-and-time-value]:p-0 [&::-webkit-date-and-time-value]:m-0 [&::-webkit-date-and-time-value]:leading-normal focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:border-transparent"
                        />
                    </div>
                </div>

                <!-- Descrição -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Descrição</label>
                    <input
                        v-model="form.descricao"
                        type="text"
                        placeholder="Descrição"
                        @keydown.enter="handleSave"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:border-transparent"
                    />
                </div>

                <!-- Valor e Quantidade -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Valor R$</label>
                        <div class="relative">
                            <input
                                v-model.number="form.valor"
                                type="number"
                                step="0.01"
                                min="0"
                                :placeholder="`0,00${unidade ? ' - ' + unidade : ''}`"
                                @keydown.enter="handleSave"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:border-transparent bg-yellow-50"
                                :disabled="useServiceTypes !== false && !!selectedService"
                            />
                            <span v-if="unidade" class="absolute right-3 top-2 text-sm text-gray-500">{{ unidade }}</span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Quantidade</label>
                        <input
                            v-model.number="form.quantidade"
                            type="number"
                            min="1"
                            @keydown.enter="handleSave"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:border-transparent"
                        />
                    </div>
                </div>

                <!-- Desconto (quando modo serviço ativo) -->
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Desconto</label>
                        <input
                            v-model.number="form.desconto"
                            type="number"
                            step="0.01"
                            min="0"
                            @keydown.enter="handleSave"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:border-transparent"
                        />
                    </div>
                </div>
            </template>
        </div>

        <!-- Footer -->
        <div class="bg-gray-50 px-6 py-4 rounded-b-lg flex flex-wrap gap-3 justify-end">
            <button
                v-if="mode === 'create'"
                @click="handleSaveAndReference"
                type="button"
                class="px-4 py-2 text-sm font-medium text-yellow-700 hover:text-yellow-800 transition-colors"
            >
                Salvar e criar um novo
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
                class="px-6 py-2 bg-gradient-to-r from-yellow-400 to-amber-500 text-white rounded-lg font-medium hover:shadow-lg transition-all"
            >
                Salvar
            </button>
        </div>
    </ModalShell>
</template>
