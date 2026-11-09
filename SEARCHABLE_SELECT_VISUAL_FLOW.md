/**
 * SearchableSelect - Fluxo Visual e Exemplos
 */

# 🎯 SearchableSelect Component - Visual Flow

## Fluxo de Uso do Componente

```
┌─────────────────────────────────────────────────────────────────┐
│                     SearchableSelect.vue                        │
└─────────────────────────────────────────────────────────────────┘
         │
         ├─ 1. Renderizar Dropdown
         │   └─ Mostrar label + placeholder
         │
         ├─ 2. Usuário Clica em Abrir
         │   └─ isOpen = true
         │
         ├─ 3. Dropdown Abre
         │   ├─ Input de busca
         │   └─ Lista de opções
         │
         ├─ 4A. Usuário Digita e Filtra
         │   ├─ filteredOptions computed
         │   └─ Mostrar resultados
         │
         ├─ 4B. Usuário Seleciona Opção
         │   ├─ emit 'update:modelValue'
         │   ├─ Atualizar form
         │   └─ Fechar dropdown
         │
         └─ 4C. Nenhum Resultado + Enter
             ├─ noExactMatch = true
             ├─ Mostrar "Criar novo"
             ├─ Usuário clica/pressiona Enter
             ├─ Modal abre
             ├─ Usuário preenche dados
             ├─ emit 'create' event
             └─ Parent adiciona à lista
```

## 📊 Estados Visuais

### Estado 1: Vazio (Padrão)
```
┌──────────────────────────────────┐
│  Tipo de Entrada                 │
│ ┌──────────────────────────┐     │
│ │ Selecione uma opção     │ ▼   │
│ └──────────────────────────┘     │
└──────────────────────────────────┘
```

### Estado 2: Aberto (Dropdown Visível)
```
┌──────────────────────────────────┐
│  Tipo de Entrada                 │
│ ┌──────────────────────────┐     │
│ │ Digite para buscar...   │ ▲   │
│ ├──────────────────────────┤     │
│ │ ✓ Venda                 │     │
│ │   Devolução             │     │
│ │   Investimento          │     │
│ └──────────────────────────┘     │
└──────────────────────────────────┘
```

### Estado 3: Filtrando
```
┌──────────────────────────────────┐
│  Tipo de Entrada                 │
│ ┌──────────────────────────┐     │
│ │ vend                    │ ▲   │
│ ├──────────────────────────┤     │
│ │ ✓ Venda                 │     │
│ │   (Nenhum resultado)    │     │
│ │                         │     │
│ │ + Criar "vend"          │     │
│ │ ou pressione Enter      │     │
│ └──────────────────────────┘     │
└──────────────────────────────────┘
```

### Estado 4: Selecionado
```
┌──────────────────────────────────┐
│  Tipo de Entrada                 │
│ ┌──────────────────────────┐     │
│ │ Venda                   │ ▼   │
│ └──────────────────────────┘     │
└──────────────────────────────────┘
```

### Estado 5: Modal de Criação
```
┌───────────────────────────────────────────┐
│          Criar novo tipo de entrada       │
├───────────────────────────────────────────┤
│                                           │
│  Nome *                                   │
│  ┌───────────────────────────────────┐   │
│  │ Novo tipo                         │   │
│  └───────────────────────────────────┘   │
│                                           │
│           ┌─────────┐  ┌──────────┐      │
│           │Cancelar │  │  Criar   │      │
│           └─────────┘  └──────────┘      │
└───────────────────────────────────────────┘
```

## 🔄 Fluxo de Criação de Novo Item

```
   User Input
      │
      ├─ Digita "Novo tipo"
      │
      ├─ Pressiona ENTER
      │
      ├─ searchInput = "Novo tipo"
      │ noExactMatch = true
      │
      ├─ Mostra botão "+ Criar 'Novo tipo'"
      │
      ├─ User clica ou pressiona Enter
      │
      ├─ openCreateModal()
      │   └─ createFormData.name = "Novo tipo"
      │
      ├─ Modal abre com formulário
      │
      ├─ User confirma com "Criar"
      │
      ├─ handleCreateConfirm()
      │   ├─ Valida dados
      │   └─ emit 'create' event
      │
      ├─ Parent receives event
      │   ├─ axios.post('/api/entry-types', data)
      │   ├─ Recebe: { id: 1, name: 'Novo tipo' }
      │   ├─ entryTypes.value.push(newType)
      │   └─ selectedEntryType.value = newType
      │
      └─ SearchableSelect: selectedEntryType atualiza
         ├─ Modal fecha
         ├─ Dropdown fecha
         ├─ Item é selecionado
         └─ Form é preenchido
```

## 👥 Fluxo de Colaborador (com CPF)

```
┌────────────────────────────────────────────┐
│         Criar novo colaborador             │
├────────────────────────────────────────────┤
│                                            │
│  Nome *                                    │
│  ┌──────────────────────────────────┐    │
│  │ João Silva                       │    │
│  └──────────────────────────────────┘    │
│                                            │
│  CPF *                                     │
│  ┌──────────────────────────────────┐    │
│  │ 123.456.789-00                   │    │
│  └──────────────────────────────────┘    │
│  CPF deve ter 11 dígitos                   │
│                                            │
│        ┌─────────┐  ┌────────────┐        │
│        │Cancelar │  │  Criar     │        │
│        └─────────┘  └────────────┘        │
│        (Criar desabilitado até CPF OK)    │
└────────────────────────────────────────────┘
```

## 📊 Fluxo de Importação Excel

```
User clica "Importar Entradas e Saídas"
│
├─ ImportDataModal abre
│
├─ User seleciona arquivo Excel
│  └─ Validar extensão (.xlsx ou .xls)
│
├─ File info aparece
│  ├─ Nome do arquivo
│  ├─ Tamanho
│  └─ Botão "Remover"
│
├─ User seleciona valores padrão via SearchableSelect:
│  ├─ Tipo de Entrada (ex: "Não definido")
│  ├─ Tipo de Saída (ex: "Não definido")
│  └─ Colaborador (ex: "Não definido")
│
├─ Pode criar novos itens se clicar nos SearchableSelect
│  └─ Modal dentro de modal (Dialog dentro de Dialog)
│
├─ User clica "Importar"
│  ├─ Desabilita form
│ ├─ Mostra "Importando dados..."
│  └─ FormData é enviado:
│     ├─ file (binary)
│     ├─ default_entry_type_id
│     ├─ default_exit_type_id
│     └─ default_collaborator_id
│
├─ Backend processa
│  ├─ Lê todas as abas do Excel
│  ├─ Para cada linha (até "Total"):
│  │  ├─ Parseia data
│  │  ├─ Se coluna B: cria Entry
│  │  ├─ Se coluna C: cria Commission
│  │  └─ Se coluna D: cria Exit
│  └─ Retorna summary:
│     ├─ entries_created: 15
│     ├─ exits_created: 8
│     ├─ commissions_created: 12
│     ├─ rows_processed: 25
│     └─ sheets_processed: 3
│
├─ Frontend recebe resumo
│  ├─ Modal fecha
│  ├─ Mostra toast de sucesso
│  └─ Exibe resumo:
│     "Importação concluída: 15 entradas,
│      8 saídas, 12 comissões"
│
└─ User vê dados importados na lista
```

## 🎨 Estados do Modal de Importação

### Estado 1: Aguardando Arquivo
```
┌────────────────────────────────────────────┐
│    Importar Entradas e Saídas              │
├────────────────────────────────────────────┤
│                                            │
│  Arquivo Excel                             │
│  ┌────────────────────────────────────┐   │
│  │  📤 Clique ou arraste o arquivo    │   │
│  │                                    │   │
│  │     [Selecionar arquivo]           │   │
│  └────────────────────────────────────┘   │
│                                            │
│  Valores Padrão                            │
│  ...                                       │
│                                            │
└────────────────────────────────────────────┘
```

### Estado 2: Arquivo Selecionado
```
┌────────────────────────────────────────────┐
│    Importar Entradas e Saídas              │
├────────────────────────────────────────────┤
│                                            │
│  Arquivo Excel                             │
│  ┌────────────────────────────────────┐   │
│  │ 📊 relatorio_janeiro.xlsx          │   │
│  │ 245 KB                  [Remover]  │   │
│  └────────────────────────────────────┘   │
│                                            │
│  Valores Padrão                            │
│  ┌─ Tipo de Entrada Padrão ─────────┐   │
│  │ "Não definido"               ▼   │   │
│  └────────────────────────────────────┘   │
│  ┌─ Tipo de Saída Padrão ────────────┐   │
│  │ "Não definido"               ▼   │   │
│  └────────────────────────────────────┘   │
│  ┌─ Colaborador Padrão ──────────────┐   │
│  │ "Não definido"               ▼   │   │
│  └────────────────────────────────────┘   │
│                                            │
│  [Cancelar]              [Importar]       │
└────────────────────────────────────────────┘
```

### Estado 3: Importando
```
┌────────────────────────────────────────────┐
│    Importar Entradas e Saídas              │
├────────────────────────────────────────────┤
│                                            │
│  ⏳ Importando dados...                    │
│  [████████░░░░░░░░░░░░░░░░░░░░░░]  40%   │
│                                            │
│  Processando aba 2 de 3...                 │
│                                            │
└────────────────────────────────────────────┘
```

### Estado 4: Importação Concluída (Toast)
```
✅ Importação concluída: 15 entradas,
   8 saídas, 12 comissões
```

## 🔀 Sincronização FormData

```
SearchableSelect                      Form Data
│                                      │
├─ selectedEntryType                   ├─ entry_type_id
│  └─ { id: 1, name: 'Venda' }         │  └─ 1
│
├─ watch(selectedEntryType) ────────────→ form.entry_type_id = 1
│
├─ selectedCollaborator                ├─ collaborator_id
│  └─ { id: 2, name: 'João', cpf... }  │  └─ 2
│
└─ watch(selectedCollaborator) ────────→ form.collaborator_id = 2
```

## 🧪 Fluxo de Teste

```
Test Suite: SearchableSelect
│
├─ ✓ renderiza com label
├─ ✓ marca como obrigatório
├─ ✓ exibe placeholder
├─ ✓ exibe valor selecionado
├─ ✓ abre dropdown ao clicar
├─ ✓ filtra opções por nome
├─ ✓ filtra case-insensitive
├─ ✓ filtra por CPF
├─ ✓ mostra botão criar quando sem resultado
├─ ✓ valida nome obrigatório
├─ ✓ valida CPF para colaboradores
├─ ✓ emite evento create
├─ ✓ mascara CPF
├─ ✓ emite update:modelValue
└─ ✓ fecha dropdown ao pressionar Escape

Test Suite: Integração
│
├─ ✓ fluxo completo: criar novo item
└─ ✓ fluxo completo: selecionar e pesquisar colaborador
```

## 📚 Estrutura de Pastas Atual

```
my_business/
├── resources/
│   └── js/
│       └── components/
│           ├── SearchableSelect.vue          ← ✅ NOVO
│           ├── modals/
│           │   ├── EntryModal.vue            ← Integrar
│           │   ├── ExitModal.vue             ← Integrar
│           │   └── ImportDataModal.vue       ← ✅ NOVO
│           └── ui/
│               └── dialog/
│                   └── Dialog.vue            (já existe)
│
├── SEARCHABLE_SELECT_README.md               ← ✅ NOVO
├── SEARCHABLE_SELECT_QUICK_START.md          ← ✅ NOVO
├── SEARCHABLE_SELECT_IMPLEMENTATION_STEPS.md ← ✅ NOVO
├── INTEGRATION_EXAMPLE_ENTRY_MODAL.md        ← ✅ NOVO
├── UNDEFINED_ITEMS_PROTECTION.md             ← ✅ NOVO
├── SEARCHABLE_SELECT_TESTS.md                ← ✅ NOVO
├── SEARCHABLE_SELECT_GUIDE.md                ← ✅ NOVO
└── SEARCHABLE_SELECT_SUMMARY.md              ← ✅ NOVO
```

## ✨ Casos de Uso Visuais

### Caso 1: Buscar Tipo Existente
```
User: "Quero uma venda"
      │
      ├─ Clica em "Tipo de Entrada"
      │
      ├─ Dropdown abre
      │
      ├─ Digita "ven"
      │
      ├─ Filtra: [Venda]
      │
      └─ Clica em "Venda"
         └─ Form preenchido com id=1
```

### Caso 2: Criar Novo Tipo
```
User: "Não tem 'Aluguel' na lista"
      │
      ├─ Clica em "Tipo de Entrada"
      │
      ├─ Digita "Aluguel"
      │
      ├─ Nenhum resultado
      │
      ├─ Pressiona ENTER ou clica "+ Criar 'Aluguel'"
      │
      ├─ Modal abre
      │
      ├─ Confirma com "Criar"
      │
      ├─ Backend cria entry_type
      │
      ├─ Frontend adiciona à lista
      │
      └─ Form preenchido com novo tipo
         └─ entry_type_id = 2 (novo)
```

### Caso 3: Importar Relatório Mensal
```
User: "Tenho relatório do Excel"
      │
      ├─ Vai para "Registros"
      │
      ├─ Clica "Importar Entradas e Saídas"
      │
      ├─ Seleciona arquivo "relatorio_janeiro.xlsx"
      │
      ├─ Seleciona:
      │  ├─ Tipo Entrada: "Não definido"
      │  ├─ Tipo Saída: "Não definido"
      │  └─ Colaborador: "Não definido"
      │
      ├─ Clica "Importar"
      │
      ├─ Backend lê 3 abas (Jan, Fev, Mar)
      │ └─ Processa 75 linhas
      │
      ├─ Cria 45 Entradas + 30 Saídas + 20 Comissões
      │
      ├─ Toast: "Importação concluída: 45 entradas..."
      │
      └─ Dados aparecem na lista
         └─ Total de 95 registros criados
```

---

**Diagrama Criado:** 2026-11-09  
**Componente Status:** ✅ Pronto
