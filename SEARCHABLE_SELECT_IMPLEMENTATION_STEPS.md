# SearchableSelect - Guia de Implementação Passo a Passo

## 🎯 Objetivo

Integrar o componente SearchableSelect em 3 contextos principais:
1. **Formulário de Entrada** (EntryModal.vue)
2. **Formulário de Saída** (ExitModal.vue)
3. **Modal de Importação Excel** (ImportDataModal.vue)

---

## ✅ PASSO 1: Verificar Estrutura de Pastas

```
resources/js/
├── components/
│   ├── SearchableSelect.vue          ← ✅ JÁ CRIADO
│   ├── modals/
│   │   ├── ImportDataModal.vue       ← ✅ JÁ CRIADO
│   │   ├── EntryModal.vue
│   │   └── ExitModal.vue
│   └── ui/
│       └── dialog/
│           ├── Dialog.vue
│           └── ... (outros componentes)
```

---

## ✅ PASSO 2: Integrar no EntryModal.vue

### 2.1 Adicionar Imports

```typescript
import SearchableSelect from '@/components/SearchableSelect.vue';

interface SelectOption {
    id: number | string;
    name: string;
    cpf?: string;
}
```

### 2.2 Adicionar Refs para as Opções

```typescript
const entryTypesOptions = ref<SelectOption[]>([]);
const collaboratorsOptions = ref<SelectOption[]>([]);
const selectedEntryType = ref<SelectOption | null>(null);
const selectedCollaborator = ref<SelectOption | null>(null);
```

### 2.3 Atualizar Função fetchData()

Substituir a seção de tipos de entrada:

```typescript
const fetchData = async () => {
    try {
        const responses = await Promise.all([
            axios.get('/api/entry-types'),
            axios.get('/api/collaborators'),
        ]);
        
        // Mapear para SelectOption
        entryTypesOptions.value = (responses[0].data?.data ?? []).map(type => ({
            id: type.id,
            name: type.name,
        }));

        collaboratorsOptions.value = (responses[1].data?.data ?? []).map(collab => ({
            id: collab.id,
            name: collab.name,
            cpf: collab.cpf,
        }));
    } catch (error) {
        console.error('Erro ao carregar dados:', error);
    }
};
```

### 2.4 Adicionar Handlers para Criar Novo Item

```typescript
const handleCreateEntryType = async (data: { name: string }) => {
    try {
        const response = await axios.post('/api/entry-types', { name: data.name });
        const newType = response.data.data;
        
        entryTypesOptions.value.push({
            id: newType.id,
            name: newType.name,
        });
        
        selectedEntryType.value = {
            id: newType.id,
            name: newType.name,
        };
    } catch (error) {
        console.error('Erro ao criar tipo de entrada:', error);
    }
};

const handleCreateCollaborator = async (data: { name: string; cpf: string }) => {
    try {
        const response = await axios.post('/api/collaborators', data);
        const newCollab = response.data.data;
        
        collaboratorsOptions.value.push({
            id: newCollab.id,
            name: newCollab.name,
            cpf: newCollab.cpf,
        });
        
        selectedCollaborator.value = {
            id: newCollab.id,
            name: newCollab.name,
            cpf: newCollab.cpf,
        };
    } catch (error) {
        console.error('Erro ao criar colaborador:', error);
    }
};
```

### 2.5 Adicionar Watchers para Sincronizar IDs

```typescript
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
```

### 2.6 Atualizar onMounted

```typescript
onMounted(async () => {
    await fetchData();
    
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
    }
});
```

### 2.7 Substituir HTML do Formulário

Remover:
```vue
<!-- REMOVER ISSO -->
<select v-model="form.tipoEntrada">
  <option value="">-- Selecione --</option>
  <option v-for="tipo in tiposEntrada" :key="tipo.id">
    {{ tipo.name }}
  </option>
</select>
```

Adicionar:
```vue
<!-- ADICIONAR ISSO -->
<SearchableSelect 
    label="Tipo de Entrada"
    :modelValue="selectedEntryType"
    @update:modelValue="selectedEntryType = $event"
    :options="entryTypesOptions"
    entityType="entry_type"
    placeholder="Pesquise ou crie um novo..."
    required
    @create="handleCreateEntryType"
/>

<SearchableSelect 
    label="Colaborador"
    :modelValue="selectedCollaborator"
    @update:modelValue="selectedCollaborator = $event"
    :options="collaboratorsOptions"
    entityType="collaborator"
    placeholder="Pesquise pelo nome ou CPF..."
    @create="handleCreateCollaborator"
/>
```

---

## ✅ PASSO 3: Integrar no ExitModal.vue

### 3.1 Seguir exatamente o mesmo padrão do Passo 2

Mas com `exitTypes` em vez de `entryTypes`:

```typescript
const exitTypesOptions = ref<SelectOption[]>([]);
const selectedExitType = ref<SelectOption | null>(null);

const handleCreateExitType = async (data: { name: string }) => {
    try {
        const response = await axios.post('/api/exit-types', { name: data.name });
        const newType = response.data.data;
        
        exitTypesOptions.value.push({
            id: newType.id,
            name: newType.name,
        });
        
        selectedExitType.value = {
            id: newType.id,
            name: newType.name,
        };
    } catch (error) {
        console.error('Erro ao criar tipo de saída:', error);
    }
};
```

### 3.2 Template

```vue
<SearchableSelect 
    label="Tipo de Saída"
    :modelValue="selectedExitType"
    @update:modelValue="selectedExitType = $event"
    :options="exitTypesOptions"
    entityType="exit_type"
    placeholder="Pesquise ou crie um novo..."
    required
    @create="handleCreateExitType"
/>

<SearchableSelect 
    label="Colaborador"
    :modelValue="selectedCollaborator"
    @update:modelValue="selectedCollaborator = $event"
    :options="collaboratorsOptions"
    entityType="collaborator"
    placeholder="Pesquise pelo nome ou CPF..."
    @create="handleCreateCollaborator"
/>
```

---

## ✅ PASSO 4: Integrar no Registros.vue (Importação)

### 4.1 Adicionar Import

```typescript
import ImportDataModal from '@/components/modals/ImportDataModal.vue';
```

### 4.2 Adicionar Refs

```typescript
const showImportModal = ref(false);

// As listas já devem existir:
// tiposEntrada, tiposSaida, colaboradoresOptions
```

### 4.3 Adicionar Handlers

```typescript
const handleImportData = async ({ file, defaults }) => {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('default_entry_type_id', defaults.default_entry_type_id);
    formData.append('default_exit_type_id', defaults.default_exit_type_id);
    formData.append('default_collaborator_id', defaults.default_collaborator_id);

    try {
        isImportLoading.value = true;
        const response = await axios.post('/api/import-data', formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });

        importSummary.value = response.data.data;
        showImportPanel.value = true;
        showImportModal.value = false;
        
        // Toast de sucesso
        toast.success(
            `Importação concluída: ${importSummary.value.entries_created} entradas, ` +
            `${importSummary.value.exits_created} saídas, ` +
            `${importSummary.value.commissions_created} comissões`
        );
    } catch (error: any) {
        importError.value = error.response?.data?.message || 'Erro ao importar dados';
        toast.error(importError.value);
    } finally {
        isImportLoading.value = false;
    }
};

const handleCreateInImport = (type: string) => async (data: any) => {
    let endpoint = '';
    if (type === 'entry_type') {
        endpoint = '/api/entry-types';
    } else if (type === 'exit_type') {
        endpoint = '/api/exit-types';
    } else {
        endpoint = '/api/collaborators';
    }

    try {
        const response = await axios.post(endpoint, data);
        const newItem = response.data.data;
        
        // Adicionar à lista correta
        if (type === 'entry_type') {
            tiposEntrada.value.push(newItem);
        } else if (type === 'exit_type') {
            tiposSaida.value.push(newItem);
        } else {
            colaboradoresOptions.value.push(newItem);
        }
    } catch (error) {
        console.error(`Erro ao criar ${type}:`, error);
    }
};
```

### 4.4 Adicionar ao Template

```vue
<!-- Botão para abrir modal de importação -->
<button 
    @click="showImportModal = true"
    type="button"
    class="bg-blue-600 text-white px-4 py-2 rounded-lg"
>
    Importar Entradas e Saídas
</button>

<!-- Modal de Importação -->
<ImportDataModal 
    :show="showImportModal"
    @close="showImportModal = false"
    :entryTypes="tiposEntrada"
    :exitTypes="tiposSaida"
    :collaborators="colaboradoresOptions"
    @import="handleImportData"
    @createEntryType="handleCreateInImport('entry_type')"
    @createExitType="handleCreateInImport('exit_type')"
    @createCollaborator="handleCreateInImport('collaborator')"
/>
```

---

## ✅ PASSO 5: Testes Rápidos

### 5.1 Testar Filtro de Entrada

1. Abrir formulário de entrada
2. Clicar no SearchableSelect "Tipo de Entrada"
3. Digitar um trecho (ex: "Vend")
4. Verificar se filtra corretamente
5. ✅ Se passar, ir ao passo 5.2

### 5.2 Testar Criação de Novo Tipo

1. Clicar em SearchableSelect "Tipo de Entrada"
2. Digitar um nome novo (ex: "Aluguel")
3. Pressionar Enter
4. Modal deve abrir
5. Clicar em "Criar"
6. Novo tipo deve aparecer na lista e ser selecionado
7. ✅ Se passar, ir ao passo 5.3

### 5.3 Testar CPF em Colaborador

1. Clicar em SearchableSelect "Colaborador"
2. Digitar um CPF parcial (ex: "123.456")
3. Verificar se filtra por CPF
4. Digitar um novo CPF
5. Pressionar Enter
6. Modal deve pedir nome e CPF
7. CPF deve ser automaticamente formatado
8. ✅ Se passar, ir ao passo 5.4

### 5.4 Testar Importação

1. Ir para página "Registros"
2. Clicar em "Importar Entradas e Saídas"
3. Selecionar arquivo Excel
4. Verificar se detecta corretamente
5. Selecionar valores padrão via SearchableSelect
6. Clicar em "Importar"
7. Esperar processamento
8. Verificar resumo com total de registros criados
9. ✅ Todos os testes passaram!

---

## ✅ PASSO 6: Proteção de "Não Definido"

### 6.1 Backend - EntryTypeController

```php
public function destroy(EntryType $entryType): Response {
    if ($entryType->name === 'Não definido') {
        return response()->json([
            'message' => 'Não é permitido deletar o tipo de entrada "Não definido"',
        ], 403);
    }
    
    $entryType->delete();
    return response()->json(['message' => 'Removido']);
}

public function update(Request $request, EntryType $entryType): Response {
    if ($entryType->name === 'Não definido' && $request->input('name') !== 'Não definido') {
        return response()->json([
            'message' => 'Não é permitido renomear o tipo de entrada "Não definido"',
        ], 403);
    }
    
    $entryType->update($request->validated());
    return response()->json(['data' => $entryType]);
}
```

### 6.2 Repetar para ExitTypeController e CollaboratorController

---

## ✅ PASSO 7: Testar Proteção

1. Ir para "Personalizar > Tipos de Entrada"
2. Verificar se "Não definido" tem botão Delete desabilizado
3. Tentar renomear via modal - deve ser bloqueado
4. ✅ Proteção funcionando!

---

## 🎯 Resumo da Integração

| Componente | Arquivo | Linhas Adicionadas |
|-----------|---------|-------------------|
| EntryModal.vue | Imports + Types | ~5 |
| | Refs para opções | ~5 |
| | fetchData() atualizado | ~15 |
| | Handlers (@create) | ~30 |
| | Watchers | ~10 |
| | Template (SearchableSelect) | ~20 |
| | **TOTAL** | **~85 linhas** |
| ExitModal.vue | Mesmo padrão | **~85 linhas** |
| Registros.vue | Import ImportDataModal | ~3 |
| | Handlers para import | ~30 |
| | Template + botão | ~20 |
| | **TOTAL** | **~53 linhas** |

**Total: ~220 linhas de modificação**

---

## 📚 Documentação Relacionada

- `SEARCHABLE_SELECT_README.md` - Visão geral
- `SEARCHABLE_SELECT_QUICK_START.md` - Exemplos rápidos
- `INTEGRATION_EXAMPLE_ENTRY_MODAL.md` - Exemplo completo
- `UNDEFINED_ITEMS_PROTECTION.md` - Proteção de padrões
- `SEARCHABLE_SELECT_TESTS.md` - Exemplos de testes

---

## ⚠️ Checklist Final

- [ ] SearchableSelect.vue criado ✅
- [ ] ImportDataModal.vue criado ✅
- [ ] EntryModal.vue atualizado
- [ ] ExitModal.vue atualizado
- [ ] Registros.vue atualizado com importação
- [ ] Backend protege "Não definido"
- [ ] Testes passando (filtro, criação, CPF)
- [ ] Proteção funcionando
- [ ] Documentação atualizada
- [ ] Deploy em produção

---

**Tempo estimado de integração:** 1-2 horas  
**Dificuldade:** Média (copy-paste de padrões)  
**Benefício:** Seleção avançada + criação inline + importação Excel
