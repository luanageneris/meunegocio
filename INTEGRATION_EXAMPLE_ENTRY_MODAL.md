/**
 * EXEMPLO DE INTEGRAÇÃO: SearchableSelect no EntryModal.vue
 * 
 * Este arquivo mostra como integrar o SearchableSelect no formulário de entrada
 * com suporte a criação de novos tipos de entrada in-line.
 * 
 * Copie e substitua as seções relevantes do seu EntryModal.vue
 */

// ============================================
// SECTION 1: Imports
// ============================================

import { ref, watch, nextTick, onMounted, computed } from 'vue';
import axios from 'axios';
import ModalShell from './ModalShell.vue';
import UnsavedChangesModal from './UnsavedChangesModal.vue';
import SearchableSelect from '@/components/SearchableSelect.vue';  // ← NOVO

interface ServiceType {
    id: number;
    name: string;
    unit: string;
    service_value: string;
    commission_percentage: string;
}

// ← NOVO: Interface para SelectOption
interface SelectOption {
    id: number | string;
    name: string;
    cpf?: string;
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

// ============================================
// SECTION 2: Props e Emits
// ============================================

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

// ============================================
// SECTION 3: Form State - ATUALIZADO
// ============================================

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

// ← NOVO: Usar SelectOption em vez de string
const selectedEntryType = ref<SelectOption | null>(null);
const selectedCollaborator = ref<SelectOption | null>(null);
const selectedServiceType = ref<ServiceType | null>(null);

const initialForm = ref<Entry>({ ...form.value });

// ← ATUALIZADO: Dynamic options agora com formato SelectOption
const entryTypesOptions = ref<SelectOption[]>([]);
const tiposServico = ref<ServiceType[]>([]);
const collaboratorsOptions = ref<SelectOption[]>([]);

// ============================================
// SECTION 4: Fetch Data - ATUALIZADO
// ============================================

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
        
        const entryTypesRes = responses[0];
        const serviceTypesRes = (props.useServiceTypes !== false) ? responses[1] : null;
        const collaboratorsRes = (props.useServiceTypes !== false) ? responses[2] : responses[1];
        
        // ← ATUALIZADO: Mapeando para SelectOption
        entryTypesOptions.value = (entryTypesRes.data?.data ?? entryTypesRes.data ?? []).map((type: any) => ({
            id: type.id,
            name: type.name,
        }));

        tiposServico.value = serviceTypesRes ? (serviceTypesRes.data?.data ?? serviceTypesRes.data ?? []) : [];
        
        collaboratorsOptions.value = (collaboratorsRes.data?.data ?? collaboratorsRes.data ?? []).map((collab: any) => ({
            id: collab.id,
            name: collab.name,
            cpf: collab.cpf,
        }));
    } catch (error) {
        console.error('Erro ao carregar dados:', error);
    }
};

// ============================================
// SECTION 5: Handlers para Criar Novo Item - NOVO
// ============================================

const handleCreateEntryType = async (data: { name: string }) => {
    try {
        const response = await axios.post('/api/entry-types', { name: data.name });
        const newType = response.data.data;
        
        // Adicionar à lista
        entryTypesOptions.value.push({
            id: newType.id,
            name: newType.name,
        });
        
        // Selecionar automaticamente
        selectedEntryType.value = {
            id: newType.id,
            name: newType.name,
        };
        
        console.log('Tipo de entrada criado:', newType);
    } catch (error) {
        console.error('Erro ao criar tipo de entrada:', error);
    }
};

const handleCreateCollaborator = async (data: { name: string; cpf: string }) => {
    try {
        const response = await axios.post('/api/collaborators', {
            name: data.name,
            cpf: data.cpf,
        });
        const newCollab = response.data.data;
        
        // Adicionar à lista
        collaboratorsOptions.value.push({
            id: newCollab.id,
            name: newCollab.name,
            cpf: newCollab.cpf,
        });
        
        // Selecionar automaticamente
        selectedCollaborator.value = {
            id: newCollab.id,
            name: newCollab.name,
            cpf: newCollab.cpf,
        };
        
        console.log('Colaborador criado:', newCollab);
    } catch (error) {
        console.error('Erro ao criar colaborador:', error);
    }
};

// ============================================
// SECTION 6: Template - ATUALIZADO
// ============================================

// Substitua a seção de "Form" no template por:
/*
<!-- Form -->
<div class="p-6 space-y-4">
    <!-- Data -->
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Data</label>
        <input
            v-model="form.data"
            type="date"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400"
        />
    </div>

    <!-- Tipo de Entrada - NOVO SearchableSelect -->
    <SearchableSelect 
        label="Tipo de Entrada"
        :modelValue="selectedEntryType"
        @update:modelValue="selectedEntryType = $event"
        :options="entryTypesOptions"
        entityType="entry_type"
        placeholder="Pesquise ou crie um novo..."
        @create="handleCreateEntryType"
        required
    />

    <!-- Colaborador - NOVO SearchableSelect -->
    <SearchableSelect 
        label="Colaborador"
        :modelValue="selectedCollaborator"
        @update:modelValue="selectedCollaborator = $event"
        :options="collaboratorsOptions"
        entityType="collaborator"
        placeholder="Pesquise pelo nome ou CPF..."
        @create="handleCreateCollaborator"
    />

    <!-- Tipo de Serviço (se habilitado) -->
    <div v-if="props.useServiceTypes !== false">
        <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de Serviço</label>
        <select 
            v-model="selectedServiceType"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg"
        >
            <option :value="null">-- Selecione --</option>
            <option v-for="service in tiposServico" :key="service.id" :value="service">
                {{ service.name }}
            </option>
        </select>
    </div>

    <!-- Valor -->
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Valor</label>
        <input
            v-model="form.valor"
            type="number"
            step="0.01"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg"
        />
    </div>

    <!-- Quantidade -->
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Quantidade</label>
        <input
            v-model="form.quantidade"
            type="number"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg"
        />
    </div>

    <!-- Desconto -->
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Desconto</label>
        <input
            v-model="form.desconto"
            type="number"
            step="0.01"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg"
        />
    </div>

    <!-- Descrição -->
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Descrição</label>
        <textarea
            v-model="form.descricao"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg"
            rows="3"
        ></textarea>
    </div>

    <!-- Observação -->
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Observação</label>
        <textarea
            v-model="form.observacao"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg"
            rows="2"
        ></textarea>
    </div>
</div>
*/

// ============================================
// SECTION 7: Watchers para sincronizar IDs
// ============================================

watch(selectedEntryType, (newVal) => {
    if (newVal) {
        form.value.entry_type_id = newVal.id as number;
    }
});

watch(selectedCollaborator, (newVal) => {
    if (newVal) {
        form.value.collaborator_id = newVal.id as number;
    }
});

watch(selectedServiceType, (newVal) => {
    if (newVal) {
        form.value.service_type_id = newVal.id;
    }
});

// ============================================
// SECTION 8: onMounted - ATUALIZADO
// ============================================

onMounted(async () => {
    await fetchData();
    
    // Se for modo edit, pré-selecionar os valores
    if (props.mode === 'edit' && props.entry) {
        if (props.entry.entry_type_id) {
            selectedEntryType.value = entryTypesOptions.value.find(
                t => t.id === props.entry!.entry_type_id
            ) || null;
        }
        if (props.entry.collaborator_id) {
            selectedCollaborator.value = collaboratorsOptions.value.find(
                c => c.id === props.entry!.collaborator_id
            ) || null;
        }
        if (props.entry.service_type_id) {
            selectedServiceType.value = tiposServico.value.find(
                s => s.id === props.entry!.service_type_id
            ) || null;
        }
    }
});
