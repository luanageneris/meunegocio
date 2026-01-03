---
applyTo: '**'
---

# Papel do Agente

Você é um Arquiteto e Desenvolvedor Full Stack Sênior, especialista em Laravel (API REST), Vue 3, Autenticação OAuth (Google Login), Dashboards financeiros, controle de comissões, relatórios e gráficos, e boas práticas de arquitetura (DDD leve, SOLID, Clean Code).

## Contexto do Projeto

Desenvolver o sistema “Meu Negócio” para a empresa familiar Xerografia & Cia, que presta serviços de impressão e web.

A empresa é administrada pela proprietária Dona Genilda, e seus filhos atuam como colaboradores em turnos diferentes, recebendo comissão diária baseada nos serviços realizados e horas trabalhadas.

O sistema deve permitir controle financeiro completo, incluindo:

- Entradas
- Saídas
- Comissões por colaborador 
- Lucro diário, mensal e anual
- Visualização em gráficos
- Controle de turnos e escalas

### Stack Técnica Obrigatória

- Backend: Laravel (API REST)
- Frontend: Vue 3 + Composition API
- Auth: Login via Google (OAuth) + usuário root
- Banco: MySQL
- Gráficos: Chart.js
- Importação: Excel (.xlsx)
- Soft Delete: Laravel SoftDeletes
- Logo: SVG
- Controle de permissões: Perfil proprietário (admin/root)

### Usuários e Autenticação

1. **Login via Google:**
   - Criar conta automaticamente se não existir
   - Usuário inicial obrigatório:
     - Email: luanacarvalhomesquita@gmail.com
     - Perfil: proprietário (root)

2. **Login como root:**
   - Permite logar "como" outro usuário (impersonation)


### Estrutura do App (Menu Principal)

- Painel (Dashboard)
- Registros
- Personalizar


## FUNCIONALIDADES DETALHADAS

### Personalizar
- Colaboradores
  - Criar (nome, CPF)
  - Editar
  - Remover (soft delete)

- Tipos de Entrada
  - Criar
  - Editar
  - Remover (soft delete)
  - Ex: serviço, doação, investimento

- Tipos de Saída
  - Criar
  - Editar
  - Remover (soft delete)
  - Ex: comissão, material, energia, água, internet

- Tipos de Serviço
  - Percentual de comissão
  - Nome do serviço

### Registros - Visualização

- **Modo Lucro**
  - Calendário mensal
  - Mostrar lucro por dia
  - Indicar se o dia está “fechado”

- **Modo Escala**
  - Calendário mensal
  - Mostrar colaboradores por dia e turno

- **Registro de Dados**
  1. Cadastrar saldo atual
  2. Cadastrar entradas e saídas
     - Vincular ou não a colaborador
     - Atributos:
       - Tipo (entrada/saída)
       - Categoria
       - Valor
       - Data
       - Observação
  3. Registrar lucro diário
     - Confirmar que o dia está fechado
     - Bloquear edição após fechamento (opcional)

- **CRUD completo:**
  - Criar
  - Editar
  - Remover (soft delete)


### Painel (Dashboard)

- Gráfico de comissão por colaborador (mensal)
- Top 5 dias mais rentáveis do mês
- Total de entrada por mês
- Total de saída por mês
- Comparativo:
  - Investimento
  - Lucro do proprietário
- (Futuro) Gráfico de turnos e carga horária por colaborador

Filtros:
- Dia
- Semana
- Mês
- Ano


### Importação de Dados

- Importar dados iniciais via Excel
- Mapear:
  - Colaboradores
  - Entradas
  - Saídas
  - Saldo inicial


### Fluxo de Uso Diário

1. Registrar investimentos do dia (saídas)
2. Registrar serviços realizados:
   - Tipo do serviço
   - Valor
   - Quantidade
   - Desconto
   - Colaborador responsável
   - Data
3. Calcular comissão diária por colaborador
4. Registrar pagamento da comissão
5. Conferir lucro
6. Confirmar fechamento do dia

### Modelagem Esperada (Sugestão)

- User
- Collaborator
- ServiceType
- EntryType
- ExitType
- FinancialRecord
- DailyClosure
- Commission
- WorkShift
- ImportedFileLog

### Entregáveis do Agente IA

- Modelagem do banco (ERD)
- Estrutura de pastas Laravel
- API REST documentada
- Telas Vue (componentes principais)
- Lógica de cálculo de comissão e lucro
- Gráficos funcionais
- Fluxo de autenticação Google
- Estratégia de importação Excel


## DÚVIDAS IMPORTANTES RESPONDIDAS

**Comissão:**
- Percentual fixo configurável por serviço
- Comissão baseada em valor após desconto

**Turnos:**
- Turnos fixos (manhã/tarde/noite)
- Pode haver mais de um turno por dia por colaborador.

**Fechamento do dia:**
- Após fechado, pode editar.
- Precisa de confirmação dupla? não é obrigatório.

**Controle financeiro:**
- Saldo acumulado ou apenas demonstrativo? Acumulado
- Separar dinheiro para investimento futuro como categoria especial? sim

**Permissões:**
- Apenas o proprietário pode editar tudo? sim.
- Colaboradores terão acesso futuramente? sim, mas não agora.

**Impostos:**
- Ignorar por enquanto ou preparar estrutura? Ignorar por enquanto.


# PROMPT – GERAÇÃO DO BANCO DE DADOS

Papel do agente: Você é um Arquiteto de Software e Especialista em Banco de Dados, com foco em Laravel, modelagem relacional, sistemas financeiros, controle de comissões, e dashboards analíticos.

Objetivo: Projetar todo o esquema de banco de dados do sistema Meu Negócio, garantindo:
- Normalização adequada
- Escalabilidade
- Facilidade para relatórios
- Compatibilidade total com Laravel (Eloquent ORM)

Requisitos Técnicos Obrigatórios
- Banco: MySQL
- Todas as tabelas devem conter:id, created_at, updated_at
- Tabelas removíveis devem usar deleted_at (Soft Delete)
- Chaves estrangeiras com integridade referencial
- Índices em campos de busca frequente
- Preparar estrutura para filtros por dia / semana / mês / ano

## ENTIDADES PRINCIPAIS E REGRAS

### 👤 users

**Controle de autenticação**

- Login via Google OAuth
- Perfil proprietário (root)

**Campos sugeridos:**

- id
- name
- email (unique)
- google_id (nullable)
- role (root, owner)
- created_at
- updated_at

### 👷 collaborators

**Representa os filhos / colaboradores**

**Campos:**

- id
- name
- cpf (unique)
- active (boolean)
- created_at
- updated_at
- deleted_at

### 🕒 work_shifts

**Registro de turnos trabalhados**

**Campos:**

- id
- collaborator_id (FK)
- date
- start_time
- end_time
- total_hours (decimal)
- created_at
- updated_at
- deleted_at

### 🧾 service_types

**Tipos de serviço prestado**

**Campos:**

- id
- name
- commission_percentage (decimal 5,2)
- created_at
- updated_at
- deleted_at

### 📥 entry_types

**Categorias de entrada financeira**

**Campos:**

- id
- name
- requires_collaborator (boolean)
- created_at
- updated_at
- deleted_at

### 📤 exit_types

**Categorias de saída financeira**

**Campos:**

- id
- name
- created_at
- updated_at
- deleted_at

### 💰 financial_records

**Tabela central de entradas e saídas**

**Campos:**

- id
- type (entry, exit)
- entry_type_id (nullable FK)
- exit_type_id (nullable FK)
- collaborator_id (nullable FK)
- service_type_id (nullable FK)
- work_shift_id (nullable FK)
- amount (decimal 10,2)
- quantity (integer, nullable)
- discount (decimal 10,2, nullable)
- date
- description (text, nullable)
- created_at
- updated_at
- deleted_at

### 🤝 commissions

**Registro do pagamento de comissão**

**Campos:**

- id
- collaborator_id (FK)
- financial_record_id (FK)
- amount (decimal 10,2)
- paid_at (date)
- created_at
- updated_at
- deleted_at

### 📅 daily_closures

**Controle de fechamento diário**

**Campos:**

- id
- date (unique)
- total_entry (decimal 10,2)
- total_exit (decimal 10,2)
- profit (decimal 10,2)
- is_closed (boolean)
- created_at
- updated_at

### 📊 balances

**Controle de saldo**

**Campos:**

- id
- reference_date
- opening_balance (decimal 10,2)
- closing_balance (decimal 10,2)
- created_at
- updated_at

### 📥 import_logs

**Histórico de importações Excel**

**Campos:**

- id
- file_name
- imported_by (FK users)
- status
- created_at
- updated_at

## RELACIONAMENTOS OBRIGATÓRIOS

- collaborator → work_shifts (1:N)
- collaborator → financial_records (1:N)
- service_type → financial_records (1:N)
- financial_records → commissions (1:1)
- daily_closures → financial_records (1:N por data)
- users → import_logs (1:N)

## REQUISITOS DE PERFORMANCE

**Índices em:**

- financial_records.date
- financial_records.type
- collaborator_id
- service_type_id

**Preparar consultas agregadas (SUM, GROUP BY)**

## ENTREGÁVEIS DO AGENTE

- Diagrama ER (descrição textual)
- Lista de tabelas e campos
- Relacionamentos explicados
- Sugestão de migrations Laravel
- Observações sobre cálculos financeiros

## REGRAS IMPORTANTES

- ⚠️ Não criar tabelas desnecessárias
- ⚠️ Evitar campos duplicados
- ⚠️ Pensar sempre em relatórios mensais e anuais
- ⚠️ Preparar estrutura para futuras permissões

Se quiser, no próximo passo posso:

- 🔹 Gerar as migrations Laravel completas
- 🔹 Criar seeders iniciais
- 🔹 Ajustar o banco para MVP enxuto
- 🔹 Validar o modelo contra casos reais do dia a dia
