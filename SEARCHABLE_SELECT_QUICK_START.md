/**
 * GUIA RÁPIDO - SearchableSelect Component
 * 
 * Um componente Vue 3 de seleção avançada com:
 * ✓ Filtro/busca em tempo real
 * ✓ Criação de novos itens com modal
 * ✓ Suporte para CPF (colaboradores)
 * ✓ Validação integrada
 * ✓ Totalmente em português
 * ✓ Código em inglês (ES6)
 */

// ============================================
// INSTALAÇÃO / SETUP
// ============================================

// 1. Copiar arquivo: resources/js/components/SearchableSelect.vue
// 2. Copiar arquivo: resources/js/components/modals/ImportDataModal.vue
// 3. Importar nos componentes onde precisar


// ============================================
// EXEMPLO 1: Tipo de Entrada Simples
// ============================================

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import SearchableSelect from '@/components/SearchableSelect.vue';

const entryTypes = ref([]);
const selectedEntryType = ref(null);

onMounted(async () => {
    const { data } = await axios.get('/api/entry-types');
    entryTypes.value = data.data; // [{ id: 1, name: 'Venda' }, ...]
});

const handleCreateEntryType = async (data) => {
    const response = await axios.post('/api/entry-types', data);
    entryTypes.value.push(response.data.data);
    selectedEntryType.value = response.data.data;
};
</script>

<template>
    <SearchableSelect 
        label="Tipo de Entrada"
        :modelValue="selectedEntryType"
        @update:modelValue="selectedEntryType = $event"
        :options="entryTypes"
        entityType="entry_type"
        placeholder="Pesquise ou crie um novo..."
        required
        @create="handleCreateEntryType"
    />
</template>


// ============================================
// EXEMPLO 2: Colaborador com CPF
// ============================================

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import SearchableSelect from '@/components/SearchableSelect.vue';

const collaborators = ref([]);
const selectedCollaborator = ref(null);

onMounted(async () => {
    const { data } = await axios.get('/api/collaborators');
    collaborators.value = data.data; // [{ id: 1, name: 'João', cpf: '123.456.789-00' }, ...]
});

const handleCreateCollaborator = async (data) => {
    // data = { name: 'Maria Silva', cpf: '987.654.321-11' }
    const response = await axios.post('/api/collaborators', data);
    collaborators.value.push(response.data.data);
    selectedCollaborator.value = response.data.data;
};
</script>

<template>
    <SearchableSelect 
        label="Colaborador"
        :modelValue="selectedCollaborator"
        @update:modelValue="selectedCollaborator = $event"
        :options="collaborators"
        entityType="collaborator"
        placeholder="Pesquise pelo nome ou CPF..."
        @create="handleCreateCollaborator"
    />
</template>


// ============================================
// EXEMPLO 3: Em um Formulário Modal
// ============================================

<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import SearchableSelect from '@/components/SearchableSelect.vue';

interface SelectOption {
    id: number;
    name: string;
    cpf?: string;
}

interface FormData {
    entryTypeId: number | null;
    collaboratorId: number | null;
    amount: number;
}

const props = defineProps({
    show: boolean,
    entryTypes: Array<SelectOption>,
    collaborators: Array<SelectOption>,
});

const emit = defineEmits(['save', 'close', 'createEntryType', 'createCollaborator']);

const form = ref<FormData>({
    entryTypeId: null,
    collaboratorId: null,
    amount: 0,
});

const selectedEntryType = ref<SelectOption | null>(null);
const selectedCollaborator = ref<SelectOption | null>(null);

const isFormValid = computed(() => {
    return selectedEntryType.value && form.value.amount > 0;
});

// Sincronizar SelectOption com IDs do formulário
watch(selectedEntryType, (newVal) => {
    form.value.entryTypeId = newVal?.id || null;
});

watch(selectedCollaborator, (newVal) => {
    form.value.collaboratorId = newVal?.id || null;
});

const handleSave = () => {
    if (!isFormValid.value) return;
    emit('save', form.value);
};
</script>

<template>
    <div class="space-y-4">
        <SearchableSelect 
            label="Tipo de Entrada"
            :modelValue="selectedEntryType"
            @update:modelValue="selectedEntryType = $event"
            :options="entryTypes"
            entityType="entry_type"
            required
            @create="(data) => $emit('createEntryType', data)"
        />

        <SearchableSelect 
            label="Colaborador (opcional)"
            :modelValue="selectedCollaborator"
            @update:modelValue="selectedCollaborator = $event"
            :options="collaborators"
            entityType="collaborator"
            @create="(data) => $emit('createCollaborator', data)"
        />

        <input 
            v-model.number="form.amount" 
            type="number" 
            placeholder="Valor"
            min="0"
            step="0.01"
        />

        <button 
            @click="handleSave"
            :disabled="!isFormValid"
            type="button"
        >
            Salvar
        </button>
    </div>
</template>


// ============================================
// EXEMPLO 4: Com Importação de Excel
// ============================================

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import ImportDataModal from '@/components/modals/ImportDataModal.vue';
import SearchableSelect from '@/components/SearchableSelect.vue';

const showImportModal = ref(false);
const entryTypes = ref([]);
const exitTypes = ref([]);
const collaborators = ref([]);

onMounted(async () => {
    // Carregar dados
    const [entryRes, exitRes, collabRes] = await Promise.all([
        axios.get('/api/entry-types'),
        axios.get('/api/exit-types'),
        axios.get('/api/collaborators'),
    ]);
    
    entryTypes.value = entryRes.data.data;
    exitTypes.value = exitRes.data.data;
    collaborators.value = collabRes.data.data;
});

const handleImport = async ({ file, defaults }) => {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('default_entry_type_id', defaults.default_entry_type_id);
    formData.append('default_exit_type_id', defaults.default_exit_type_id);
    formData.append('default_collaborator_id', defaults.default_collaborator_id);

    try {
        const response = await axios.post('/api/import-data', formData);
        console.log('Importação concluída:', response.data);
        // Mostrar resumo: entries_created, exits_created, etc.
    } catch (error) {
        console.error('Erro na importação:', error);
    }
};

const handleCreateInImport = (type) => async (data) => {
    let endpoint = '';
    switch(type) {
        case 'entry_type':
            endpoint = '/api/entry-types';
            break;
        case 'exit_type':
            endpoint = '/api/exit-types';
            break;
        case 'collaborator':
            endpoint = '/api/collaborators';
            break;
    }

    const response = await axios.post(endpoint, data);
    
    // Adicionar à lista apropriada
    if (type === 'entry_type') {
        entryTypes.value.push(response.data.data);
    } else if (type === 'exit_type') {
        exitTypes.value.push(response.data.data);
    } else {
        collaborators.value.push(response.data.data);
    }
};
</script>

<template>
    <div>
        <button 
            @click="showImportModal = true"
            type="button"
            class="bg-blue-600 text-white px-4 py-2 rounded"
        >
            Importar Dados
        </button>

        <ImportDataModal 
            :show="showImportModal"
            @close="showImportModal = false"
            :entryTypes="entryTypes"
            :exitTypes="exitTypes"
            :collaborators="collaborators"
            @import="handleImport"
            @createEntryType="handleCreateInImport('entry_type')"
            @createExitType="handleCreateInImport('exit_type')"
            @createCollaborator="handleCreateInImport('collaborator')"
        />
    </div>
</template>


// ============================================
// API DE PROPS
// ============================================

<SearchableSelect
    // OBRIGATÓRIO
    label="Nome do campo"                    // String: Label exibido
    :modelValue="selectedItem"              // SelectOption | null
    @update:modelValue="selectedItem = ..."  // Atualizar seleção
    :options="items"                        // Array<SelectOption>

    // OPCIONAL
    entityType="entry_type"                  // 'entry_type' | 'exit_type' | 'collaborator'
    placeholder="Digite..."                  // String: placeholder do input
    :disabled="false"                        // Boolean: desabilitar campo
    :required="false"                        // Boolean: marcar como obrigatório
    error="Mensagem de erro"                 // String: mostrar erro

    // EVENTOS
    @create="(data) => ..."                 // Criação de novo item
/>


// ============================================
// INTERFACE SelectOption
// ============================================

interface SelectOption {
    id: number | string;           // ID único
    name: string;                  // Nome exibido
    cpf?: string;                  // Apenas para colaboradores
}


// ============================================
// PAYLOAD DO EVENTO @create
// ============================================

// Para entry_type e exit_type:
@create="(data) => {
    // data = { name: 'Novo tipo' }
}"

// Para collaborator:
@create="(data) => {
    // data = { name: 'João Silva', cpf: '123.456.789-00' }
}"


// ============================================
// ERROS COMUNS E SOLUÇÕES
// ============================================

❌ "SelectOption não está definido"
✅ Adicione o import e interface ao seu componente

❌ "Duplicar dados ao criar"
✅ Verifique se está adicionando o item na lista após criar

❌ "Não sincroniza com Form IDs"
✅ Use watch() para sincronizar selectedItem com form.field_id

❌ "CPF não valida"
✅ Checkout: Mínimo 11 dígitos numéricos (máscara é automática)

❌ "Modal não abre ao pressionar Enter"
✅ Confirme que noExactMatch = true (nenhum item corresponde)


// ============================================
// CHECKLIST DE INTEGRAÇÃO
// ============================================

□ Importar SearchableSelect.vue
□ Definir interface SelectOption
□ Carregar dados com axios.get('/api/...')
□ Criar handler para @create
□ Adicionar novo item à lista após criar
□ Sincronizar com FormData usando watch()
□ Testar criação de novo item
□ Testar pesquisa/filtro
□ Testar CPF para colaboradores
