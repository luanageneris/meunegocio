/**
 * SearchableSelect Component - Guia de Uso
 * 
 * Este componente oferece:
 * 1. Seleção com filtro/busca
 * 2. Criação de novo item ao digitar (modal)
 * 3. Suporte para CPF (colaboradores)
 * 4. Integração com tipos de entrada, saída e colaboradores
 */

// ============================================
// EXEMPLO 1: Usando em um formulário de Entrada
// ============================================

import SearchableSelect from '@/components/SearchableSelect.vue';

// No componente pai (ex: EntryModal.vue)

const entryTypes = ref<SelectOption[]>([]);
const selectedEntryType = ref<SelectOption | null>(null);

onMounted(() => {
  // Carregar tipos de entrada do backend
  axios.get('/api/entry-types').then(res => {
    entryTypes.value = res.data.data;
  });
});

const handleCreateEntryType = async (data: { name: string }) => {
  try {
    const response = await axios.post('/api/entry-types', data);
    const newType = response.data.data;
    
    // Adicionar à lista
    entryTypes.value.push(newType);
    
    // Selecionar automaticamente
    selectedEntryType.value = newType;
    
    toast.success('Tipo de entrada criado com sucesso!');
  } catch (error) {
    toast.error('Erro ao criar tipo de entrada');
  }
};

// No template:
// <SearchableSelect 
//   label="Tipo de Entrada"
//   :modelValue="selectedEntryType"
//   @update:modelValue="selectedEntryType = $event"
//   :options="entryTypes"
//   entityType="entry_type"
//   placeholder="Selecione ou digite..."
//   @create="handleCreateEntryType"
// />


// ============================================
// EXEMPLO 2: Usando com Tipo de Saída
// ============================================

const exitTypes = ref<SelectOption[]>([]);
const selectedExitType = ref<SelectOption | null>(null);

const handleCreateExitType = async (data: { name: string }) => {
  try {
    const response = await axios.post('/api/exit-types', data);
    const newType = response.data.data;
    exitTypes.value.push(newType);
    selectedExitType.value = newType;
    toast.success('Tipo de saída criado com sucesso!');
  } catch (error) {
    toast.error('Erro ao criar tipo de saída');
  }
};

// Template:
// <SearchableSelect 
//   label="Tipo de Saída"
//   :modelValue="selectedExitType"
//   @update:modelValue="selectedExitType = $event"
//   :options="exitTypes"
//   entityType="exit_type"
//   placeholder="Selecione ou digite..."
//   @create="handleCreateExitType"
// />


// ============================================
// EXEMPLO 3: Usando com Colaboradores (CPF obrigatório)
// ============================================

const collaborators = ref<SelectOption[]>([]);
const selectedCollaborator = ref<SelectOption | null>(null);

const handleCreateCollaborator = async (data: { name: string; cpf: string }) => {
  try {
    const response = await axios.post('/api/collaborators', data);
    const newCollaborator = response.data.data;
    collaborators.value.push(newCollaborator);
    selectedCollaborator.value = newCollaborator;
    toast.success('Colaborador criado com sucesso!');
  } catch (error) {
    toast.error('Erro ao criar colaborador');
  }
};

// Template:
// <SearchableSelect 
//   label="Colaborador"
//   :modelValue="selectedCollaborator"
//   @update:modelValue="selectedCollaborator = $event"
//   :options="collaborators"
//   entityType="collaborator"
//   placeholder="Pesquise pelo nome ou CPF..."
//   @create="handleCreateCollaborator"
// />


// ============================================
// INTERFACE SelectOption (necessária para TypeScript)
// ============================================

interface SelectOption {
  id: number | string;
  name: string;
  cpf?: string;  // opcional, usado apenas para colaboradores
}
