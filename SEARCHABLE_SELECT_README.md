# SearchableSelect - Componente de Seleção Avançada

## 📋 O que é

Um componente Vue 3 que oferece:

✅ **Seleção com Filtro em Tempo Real** - Digite para buscar entre as opções  
✅ **Criação de Novos Itens** - Se não encontrar, pressione Enter para criar  
✅ **Modal de Confirmação** - Com validação de CPF para colaboradores  
✅ **Interface Intuitiva** - Totalmente em português  
✅ **Código Limpo** - Desenvolvido em inglês, seguindo SOLID  

## 🚀 Arquivos Criados

```
resources/js/components/
├── SearchableSelect.vue           # ← Componente principal
└── modals/
    └── ImportDataModal.vue        # ← Modal de importação Excel

Documentação:
├── SEARCHABLE_SELECT_README.md    # Este arquivo
├── SEARCHABLE_SELECT_QUICK_START.md
├── SEARCHABLE_SELECT_GUIDE.md
├── INTEGRATION_EXAMPLE_ENTRY_MODAL.md
├── SEARCHABLE_SELECT_TESTS.md
├── UNDEFINED_ITEMS_PROTECTION.md
└── PLANO_MELHORIAS_E_IMPORTACAO.md (atualizado)
```

## 🎯 Casos de Uso

### 1️⃣ Tipo de Entrada

```vue
<SearchableSelect 
  label="Tipo de Entrada"
  :modelValue="selectedEntryType"
  @update:modelValue="selectedEntryType = $event"
  :options="entryTypes"
  entityType="entry_type"
  placeholder="Pesquise ou crie um novo..."
  @create="handleCreateEntryType"
/>
```

### 2️⃣ Tipo de Saída

```vue
<SearchableSelect 
  label="Tipo de Saída"
  :modelValue="selectedExitType"
  @update:modelValue="selectedExitType = $event"
  :options="exitTypes"
  entityType="exit_type"
  placeholder="Pesquise ou crie um novo..."
  @create="handleCreateExitType"
/>
```

### 3️⃣ Colaborador (com CPF)

```vue
<SearchableSelect 
  label="Colaborador"
  :modelValue="selectedCollaborator"
  @update:modelValue="selectedCollaborator = $event"
  :options="collaborators"
  entityType="collaborator"
  placeholder="Pesquise pelo nome ou CPF..."
  @create="handleCreateCollaborator"
/>
```

### 4️⃣ Importação de Excel

```vue
<ImportDataModal 
  :show="showImportModal"
  @close="showImportModal = false"
  :entryTypes="entryTypes"
  :exitTypes="exitTypes"
  :collaborators="collaborators"
  @import="handleImport"
  @createEntryType="handleCreateEntryType"
  @createExitType="handleCreateExitType"
  @createCollaborator="handleCreateCollaborator"
/>
```

## 📖 Como Integrar

### Passo 1: Importar o Componente

```typescript
import SearchableSelect from '@/components/SearchableSelect.vue';
import ImportDataModal from '@/components/modals/ImportDataModal.vue';

// Registrar globalmente (em main.ts) ou localmente no componente
```

### Passo 2: Definir Interface

```typescript
interface SelectOption {
  id: number | string;
  name: string;
  cpf?: string;  // Opcional, para colaboradores
}
```

### Passo 3: Carregar Dados

```typescript
const entryTypes = ref<SelectOption[]>([]);
const selectedEntryType = ref<SelectOption | null>(null);

onMounted(async () => {
  const { data } = await axios.get('/api/entry-types');
  entryTypes.value = data.data;
});
```

### Passo 4: Criar Handler para Novos Itens

```typescript
const handleCreateEntryType = async (data: { name: string }) => {
  try {
    const response = await axios.post('/api/entry-types', data);
    const newType = response.data.data;
    
    // Adicionar à lista
    entryTypes.value.push(newType);
    
    // Selecionar automaticamente
    selectedEntryType.value = newType;
  } catch (error) {
    console.error('Erro ao criar:', error);
  }
};
```

### Passo 5: Usar no Template

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

## 🔒 Proteção de Itens "Não Definido"

Todos os usuários devem ter os seguintes itens que **não podem ser deletados ou renomeados**:

- `Tipo de Entrada > Não definido`
- `Tipo de Saída > Não definido`
- `Colaborador > Não definido`

### Backend Protection

```php
// EntryTypeController@destroy
public function destroy(EntryType $entryType): Response {
    if ($entryType->name === 'Não definido') {
        return response()->json([
            'message' => 'Não é permitido deletar o tipo "Não definido"',
        ], 403);
    }
    
    $entryType->delete();
    return response()->json(['message' => 'Removido']);
}
```

### Frontend Feedback

O componente SearchableSelect já mostra a proteção:
- Desabilita botão de delete para esses itens
- Mostra tooltip explicativo
- Backend rejeita a operação se tentativa avançar

Ver: `UNDEFINED_ITEMS_PROTECTION.md`

## 📤 Importação de Excel

O modal `ImportDataModal` permite:

1. **Selecionar arquivo Excel** com estrutura específica
2. **Configurar valores padrão** para campos não mapeados
3. **Criar novos itens inline** se necessário
4. **Processar múltiplas abas** automaticamente

### Estrutura do Excel Esperada

```
Cada aba deve ter 6 colunas:
A: Data (dd/mm/yyyy)
B: Total de Entrada (R$ XXX,XX)
C: Total de Comissões (R$ XXX,XX)
D: Pagamentos - Saída (R$ XXX,XX)
E: Total Final (ignorado)
F: Motivo da Saída (descrição)

A leitura termina quando coluna A contém "Total"
```

Ver: `PLANO_MELHORIAS_E_IMPORTACAO.md`

## 🧪 Testes

Exemplos de testes com Vitest + Vue Test Utils:

```typescript
test('filtra opções por nome', async () => {
  const wrapper = mount(SearchableSelect, {
    props: {
      label: 'Teste',
      modelValue: null,
      options: [
        { id: 1, name: 'Venda' },
        { id: 2, name: 'Devolução' },
      ],
    },
  });

  wrapper.vm.searchInput = 'vend';
  await wrapper.vm.$nextTick();

  expect(wrapper.vm.filteredOptions).toHaveLength(1);
});
```

Ver: `SEARCHABLE_SELECT_TESTS.md`

## 🎨 Props API

| Prop | Type | Required | Default | Descrição |
|------|------|----------|---------|-----------|
| `label` | string | ✅ | - | Label exibido acima |
| `modelValue` | SelectOption \| null | ✅ | - | Valor selecionado |
| `options` | SelectOption[] | ✅ | - | Lista de opções |
| `entityType` | 'entry_type' \| 'exit_type' \| 'collaborator' | ❌ | 'entry_type' | Tipo da entidade |
| `placeholder` | string | ❌ | 'Selecione uma opção' | Placeholder do input |
| `disabled` | boolean | ❌ | false | Desabilitar campo |
| `required` | boolean | ❌ | false | Marcar como obrigatório |
| `error` | string | ❌ | '' | Mensagem de erro |

## 📡 Eventos

| Evento | Payload | Descrição |
|--------|---------|-----------|
| `update:modelValue` | SelectOption | Quando seleciona um item |
| `create` | { name: string; cpf?: string } | Quando cria novo item |

## ⚡ Funcionalidades Destacadas

### 1. Filtro em Tempo Real
- Digite qualquer trecho do nome
- Busca case-insensitive
- Para colaboradores, busca também por CPF

### 2. Criação Inline
- Se nenhum resultado encontrado
- Digite o nome completo e pressione Enter
- Modal abre para confirmar
- Para colaboradores, pede CPF também

### 3. Máscara Automática
- CPF é automaticamente formatado: `XXX.XXX.XXX-XX`
- Apenas números são aceitos
- Máximo 11 dígitos

### 4. Validação
- Nome obrigatório
- CPF: 11 dígitos (para colaboradores)
- Modal desabilita botão até validar

### 5. UX Melhorada
- Ícone de dropdown com rotação
- Estados de loading
- Mensagens de erro claras
- Suporte a teclado (Enter, Escape)

## 🐛 Troubleshooting

### "SelectOption não está definido"
Adicione a interface ao seu componente:
```typescript
interface SelectOption {
  id: number | string;
  name: string;
  cpf?: string;
}
```

### "Duplica itens ao criar"
Verifique se está adicionando à lista APÓS receber resposta:
```typescript
entryTypes.value.push(newType); // ✅ Correto
selectedEntryType.value = newType;
```

### "CPF não valida"
CPF deve ter 11 dígitos numéricos (máscara é automática).

### "Não funciona em formulário modal"
Sincronize com `watch()`:
```typescript
watch(selectedEntryType, (newVal) => {
  form.value.entry_type_id = newVal?.id || null;
});
```

## 📚 Referências

- `SEARCHABLE_SELECT_QUICK_START.md` - Exemplos rápidos
- `INTEGRATION_EXAMPLE_ENTRY_MODAL.md` - Integração no EntryModal
- `SEARCHABLE_SELECT_TESTS.md` - Exemplos de testes
- `UNDEFINED_ITEMS_PROTECTION.md` - Proteção de padrões
- `PLANO_MELHORIAS_E_IMPORTACAO.md` - Detalhes de importação

## ✅ Checklist de Integração

- [ ] Copiar `SearchableSelect.vue` para `resources/js/components/`
- [ ] Copiar `ImportDataModal.vue` para `resources/js/components/modals/`
- [ ] Importar componente no seu arquivo Vue
- [ ] Definir interface `SelectOption`
- [ ] Carregar dados com `axios.get()`
- [ ] Implementar handler `@create`
- [ ] Adicionar novo item à lista após criar
- [ ] Sincronizar com FormData se em modal
- [ ] Testar criação de novo item
- [ ] Testar filtro/busca
- [ ] Testar CPF (colaboradores)
- [ ] Verificar proteção de "Não definido"

## 🎓 Padrões Seguidos

✅ **Vue 3 Composition API**  
✅ **Composition API v-model Pattern**  
✅ **TypeScript com Interfaces**  
✅ **Validação Integrada**  
✅ **Acessibilidade (labels, aria-labels)**  
✅ **Tratamento de Erros**  
✅ **Código em Inglês, UI em Português**  
✅ **Reutilizável em vários contextos**  

## 📞 Suporte

Para dúvidas ou sugestões sobre a integração:
1. Revise os exemplos em `SEARCHABLE_SELECT_QUICK_START.md`
2. Veja a integração completa em `INTEGRATION_EXAMPLE_ENTRY_MODAL.md`
3. Execute os testes em `SEARCHABLE_SELECT_TESTS.md`
4. Consulte `UNDEFINED_ITEMS_PROTECTION.md` para proteção de dados

---

**Status:** ✅ Pronto para Integração  
**Última Atualização:** 2026-11-09  
**Versão:** 1.0.0
