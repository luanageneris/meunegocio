# 🚀 SearchableSelect - Guia Rápido de Referência

## ⚡ 60 segundos para Começar

### 1. Importar
```typescript
import SearchableSelect from '@/components/SearchableSelect.vue';
```

### 2. Interface
```typescript
interface SelectOption {
  id: number;
  name: string;
  cpf?: string;
}
```

### 3. Dados
```typescript
const entryTypes = ref<SelectOption[]>([
  { id: 1, name: 'Venda' },
  { id: 2, name: 'Devolução' },
]);
const selectedEntryType = ref<SelectOption | null>(null);
```

### 4. Handler
```typescript
const handleCreateEntryType = async (data: { name: string }) => {
  const response = await axios.post('/api/entry-types', data);
  entryTypes.value.push(response.data.data);
  selectedEntryType.value = response.data.data;
};
```

### 5. Template
```vue
<SearchableSelect 
  label="Tipo de Entrada"
  :modelValue="selectedEntryType"
  @update:modelValue="selectedEntryType = $event"
  :options="entryTypes"
  entityType="entry_type"
  @create="handleCreateEntryType"
/>
```

---

## 📍 3 Componentes Principais

### SearchableSelect (uso geral)
```vue
<SearchableSelect 
  label="Seu Label"
  :modelValue="selected"
  @update:modelValue="selected = $event"
  :options="items"
  entityType="entry_type" | "exit_type" | "collaborator"
  placeholder="Pesquise..."
  required
  @create="onCreateNew"
/>
```

### ImportDataModal (importação)
```vue
<ImportDataModal 
  :show="showModal"
  @close="showModal = false"
  :entryTypes="entryTypes"
  :exitTypes="exitTypes"
  :collaborators="collaborators"
  @import="onImport"
  @createEntryType="onCreateEntryType"
  @createExitType="onCreateExitType"
  @createCollaborator="onCreateCollaborator"
/>
```

---

## 🎨 4 Casos de Uso

### 1. Tipo de Entrada
```typescript
entityType="entry_type"
```

### 2. Tipo de Saída
```typescript
entityType="exit_type"
```

### 3. Colaborador (com CPF)
```typescript
entityType="collaborator"
```

### 4. Importação Excel
Use `ImportDataModal` com os 3 acima

---

## 🔧 5 Handler Padrão

```typescript
const handleCreate<Type> = async (data: { name: string; cpf?: string }) => {
  try {
    const response = await axios.post('/api/<type>-types', data);
    <types>Value.value.push(response.data.data);
    selected<Type>.value = response.data.data;
  } catch (error) {
    console.error('Erro:', error);
  }
};
```

---

## ⚙️ Props Essenciais

| Prop | Tipo | Obrigatório |
|------|------|-------------|
| `label` | string | ✅ |
| `modelValue` | SelectOption \| null | ✅ |
| `options` | SelectOption[] | ✅ |
| `entityType` | string | ❌ |
| `@create` | function | ✅ |

---

## 🎯 Eventos

| Evento | Payload | Quando |
|--------|---------|--------|
| `@update:modelValue` | SelectOption | Seleciona item |
| `@create` | { name, cpf? } | Cria novo |

---

## 🔐 Proteção "Não Definido"

### Backend (Laravel)
```php
if ($item->name === 'Não definido') {
    return response()->json(['message' => 'Não permitido'], 403);
}
```

### Frontend
O botão Delete fica desabilizado automaticamente

---

## ✨ Validações Automáticas

✅ Nome obrigatório  
✅ CPF: 11 dígitos (formato XXX.XXX.XXX-XX)  
✅ Máscara automática no CPF  
✅ Case-insensitive na busca  
✅ Filtro em tempo real  

---

## 📁 Arquivos a Consultar

| Arquivo | Quando |
|---------|--------|
| README.md | Visão geral |
| QUICK_START.md | Exemplos rápidos |
| IMPLEMENTATION_STEPS.md | Integração passo a passo |
| INTEGRATION_EXAMPLE.md | Código completo |
| VISUAL_FLOW.md | Entender fluxos |
| TESTS.md | Testar |
| PROTECTION.md | Proteção de dados |

---

## 🚨 Erros Comuns

### ❌ "SelectOption não definido"
✅ Adicione a interface ao seu componente

### ❌ "Não sincroniza com form"
✅ Use watch():
```typescript
watch(selectedEntryType, (newVal) => {
  form.value.entry_type_id = newVal?.id || null;
});
```

### ❌ "Duplica itens"
✅ Adicione à lista APÓS receber resposta
```typescript
entryTypes.value.push(newType); // ✅ AQUI
```

### ❌ "CPF não valida"
✅ Mínimo 11 dígitos, formato XX9.999.999-99

---

## 📋 Checklist Rápido

- [ ] Importar SearchableSelect.vue
- [ ] Definir SelectOption interface
- [ ] Carregar dados com axios.get()
- [ ] Criar handler @create
- [ ] Usar no template
- [ ] Sincronizar com form (watch)
- [ ] Testar filtro
- [ ] Testar criação
- [ ] Testar CPF (se colaborador)

---

## 💡 Dicas Rápidas

💡 **Copie e Cole:** QUICK_START.md tem 4 exemplos prontos  
💡 **Refatore Seguro:** INTEGRATION_EXAMPLE.md mostra exatamente como  
💡 **Veja o Fluxo:** VISUAL_FLOW.md tem diagramas  
💡 **Teste Tudo:** TESTS.md tem 25+ casos  
💡 **Proteja Dados:** PROTECTION.md tem o backend também  

---

## 🎬 Fluxo Rápido

```
User digita → Filtra → 
  ├─ Encontra → Seleciona → Form preenchido
  └─ Não encontra → Enter → Modal → Cria → Seleciona
```

---

## 📞 FAQ Rápido

**P: Como usar?**  
R: Import + 3 props (label, modelValue, options) + @create handler

**P: Funciona offline?**  
R: Não, precisa da API para criar

**P: Quanto tempo pra integrar?**  
R: 2 horas por página (EntryModal, ExitModal, etc)

**P: CPF é obrigatório?**  
R: Apenas para colaborador (entityType="collaborator")

**P: Posso customizar cores?**  
R: Sim, edite as classes Tailwind em SearchableSelect.vue

---

## 🎯 Próximo Passo

👉 **Abra agora:** [SEARCHABLE_SELECT_README.md](./SEARCHABLE_SELECT_README.md)

---

**Versão:** Quick Reference 1.0  
**Tempo:** <1 minuto para ler
