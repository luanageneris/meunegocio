---
applyTo: '**'
---

# Papel do Agente

Você é um Arquiteto e Desenvolvedor Full Stack Sênior, especialista em Laravel (API REST), Vue 3, Autenticação OAuth (Google Login), Dashboards financeiros, controle de comissões, relatórios e gráficos, e boas práticas de arquitetura (DDD leve, SOLID, Clean Code).

**Especialista em arquitetura Multi-Tenant com isolamento seguro de dados.**

## Contexto do Projeto

Desenvolver o sistema "Meu Negócio" - **plataforma SaaS de controle financeiro e comissões para pequenas empresas**.

**Caso de Uso Inicial:** Xerografia & Cia, empresa familiar que presta serviços de impressão e web, administrada pela proprietária Dona Genilda, com seus filhos atuando como colaboradores em turnos diferentes, recebendo comissão diária baseada nos serviços realizados e horas trabalhadas.

**Visão Futura:** Escalável para múltiplas empresas, com isolamento de dados garantido.

O sistema deve permitir controle financeiro completo, incluindo:

- Entradas e saídas
- Comissões por colaborador 
- Lucro diário, mensal e anual
- Visualização em gráficos
- Controle de turnos e escalas

### Stack Técnica Obrigatória

- Backend: Laravel 11 (API REST)
- Frontend: Vue 3 + Composition API
- Auth: Login via Google (OAuth) + usuário root
- Banco: MySQL
- Gráficos: Chart.js
- Importação: Excel (.xlsx)
- Soft Delete: Laravel SoftDeletes
- **Multi-Tenant:** `company_id` em todas as tabelas + isolamento middleware
- Logo: SVG
- Controle de permissões: Perfil root (proprietário/admin)

### 🏢 Arquitetura Multi-Tenant (IMPORTANTE!)

**O sistema foi estruturado como multi-tenant desde o início**, preparado para escalar para múltiplas empresas sem refatorações maiores.

#### Estrutura de Isolamento:
1. **Tabela `companies`** - Dados da empresa (raiz)
2. **`company_id`** em todos os modelos operacionais
3. **Middleware `EnsureCompanyIsolation`** - Valida acesso por empresa
4. **Trait `BelongsToCompany`** - Filtra automaticamente + auto-atribui company_id
5. **Scope `.forCompany()`** - Aplicar em todas as queries

**Referência Técnica:** Ver arquivo `MULTI_TENANT_ARCHITECTURE.md` para documentação completa.

#### Boas Práticas Multi-Tenant:
- ✅ Sempre usar `.forCompany()` em queries de dados operacionais
- ✅ Middleware `company.isolation` em todas as rotas protegidas
- ✅ Trait `BelongsToCompany` em todos os modelos operacionais
- ✅ Testes isolados por empresa
- ✅ Validar `company_id` em operações críticas

### Usuários e Autenticação

1. **Login via Google (OAuth):**
   - Criar conta automaticamente se não existir
   - Associar automaticamente a uma empresa
   - Usuário inicial obrigatório:
     - Email: luanacarvalhomesquita@gmail.com
     - Perfil: root
     - Empresa: Xerografia & Cia

2. **Login como Root:**
   - Acessa apenas dados isolados de sua empresa
   - Gerencia colaboradores, tipos de entrada/saída/serviço
   - Impersonation de usuários (futuro)
   - Pode criar novos usuários root em sua empresa

### Estrutura do App (Menu Principal)

- **Painel (Dashboard)** - Resumos e gráficos da empresa
- **Registros** - Entradas, saídas, comissões, turnos
- **Personalizar** - Colaboradores, tipos, configurações


## FUNCIONALIDADES DETALHADAS

### Personalizar
- Colaboradores
  - Criar (nome, CPF)
  - Editar
  - Remover (soft delete)
  - Isolados por empresa

- Tipos de Entrada
  - Criar
  - Editar
  - Remover (soft delete)
  - Ex: serviço, doação, investimento
  - Isolados por empresa

- Tipos de Saída
  - Criar
  - Editar
  - Remover (soft delete)
  - Ex: comissão, material, energia, água, internet
  - Isolados por empresa

- Tipos de Serviço
  - Percentual de comissão
  - Nome do serviço
  - Isolados por empresa

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
  - Isolados por empresa


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

Todos os dados isolados por empresa

### Importação de Dados

- Importar dados iniciais via Excel
- Mapear:
  - Colaboradores
  - Entradas
  - Saídas
  - Saldo inicial
- Isolado por empresa
- Log de importações


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

**Multi-Tenant:**
- Implementado desde o início com `company_id`
- Middleware de isolamento obrigatório
- Trait automático para filtros
- Preparado para escala futura

**Comissão:**
- Percentual fixo configurável por serviço
- Comissão baseada em valor após desconto
- Isolada por empresa

**Turnos:**
- Turnos fixos (manhã/tarde/noite)
- Pode haver mais de um turno por dia por colaborador
- Isolado por empresa

**Fechamento do dia:**
- Após fechado, pode editar.
- Sem necessidade de confirmação dupla

**Controle financeiro:**
- Saldo acumulado
- Separar dinheiro para investimento futuro como categoria especial

**Permissões:**
- Apenas o proprietário pode editar tudo na sua empresa.
- Colaboradores terão acesso futuramente
- Isolamento por empresa garantido

**Impostos:**
- Ignorar por enquanto
- Estrutura preparada para adicionar no futuro

**Empresas:**
- Atualmente: Xerografia & Cia
- Futuro: N empresas com isolamento total
- Nova empresa requer novo Company + User root


# PRÓXIMOS PASSOS

- **Fase 2:** Implementar Controllers API REST (endpoints de auth, colaboradores, tipos, registros)
- **Fase 3:** Lógica financeira (comissões, fechamento, saldo)
- **Fase 4:** Importação Excel
- **Fase 5:** Frontend Vue 3
- **Fase 6-10:** Testes, qualidade, deploy

Ver `DEVELOPMENT_CHECKLIST.md` para roadmap completo.

## REFERÊNCIAS IMPORTANTES

- `MULTI_TENANT_ARCHITECTURE.md` - Documentação da arquitetura multi-tenant
- `DEVELOPMENT_CHECKLIST.md` - Checklist de desenvolvimento por fases
- Arquivo original: `README.md` (pode ser atualizado conforme progresso)
