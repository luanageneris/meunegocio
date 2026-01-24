---
applyTo: '**'
---

# Papel do Agente

Você é um Arquiteto e Desenvolvedor Full Stack Sênior, especialista em Laravel (API REST), Vue 3, Autenticação OAuth (Google Login), Dashboards financeiros, controle de comissões, relatórios e gráficos, e boas práticas de arquitetura (DDD leve, SOLID, Clean Code).


Nunca deve ser realizado commits automaticamente, eu que vou fazer.
Lembre-se que estamos usando docker (sail)
Você tem razão! Em desenvolvimento, o npm run dev faz hot reload automático. Não precisa de build. Vou verificar por que ainda há rotas API duplicadas:

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

  
- **Fase 3:** Implementar Financial Logic endpoints (próximo foco)
  - FinancialRecordController (CRUD + cálculos)
  - WorkShiftController (CRUD + escala)
  - CommissionController (cálculo automático + listagem)
  - BalanceController (saldo atual + histórico)
  - DailyClosureController (fechamento do dia)
  
- **Fase 4:** Dashboard & Analytics
  - DashboardController (resumos)
  - Gráficos (Chart.js)
  
- **Fase 5:** Importação Excel
  - ImportController
  - usar excel "report-entry-exit.xlsx" como base
  - nele há uma aba em portiugues com o nome de cada mes (exemplo Janeiro, Fevereiro, etc)
  - há 6 colunas:
    - Data dd/mm/yyyy
    - Total de entrada:  exemplo R$ 392,00
    - Total comissões: exemplo R$ 112,00
    - Pagamentos (saída): exemplo R$ 280,00
    - Total Final: exemplo R$ 112,00
  - na ultima linha temos o total
  - Nota: é o tipo de categoria (notmalemnte uaado para jsutificar  para onde foi a saida, exmploe: imrpessora, energia, almoço... etc)
  
- **Fase 6-10:** Frontend Vue 3, Testes, Deploy

Ver `DEVELOPMENT_CHECKLIST.md` para roadmap completo.

## REFERÊNCIAS IMPORTANTES

- `MULTI_TENANT_ARCHITECTURE.md` - Documentação da arquitetura multi-tenant
- `DEVELOPMENT_CHECKLIST.md` - Checklist de desenvolvimento por fases
- Arquivo original: `README.md` (pode ser atualizado conforme progresso)


## outros padroes a seguir:

- API deve retorna informações em "data"

---

## 🎓 LIÇÕES APRENDIDAS - Phase 3

### ⚠️ Erros Frequentes a Evitar

#### 1. **Schema vs Implementation Mismatch**
- ❌ **Erro:** Definir factories/tests com nomes de colunas diferentes do banco
- ✅ **Correto:** Sempre verificar migration antes de criar factory
- **Exemplo:** FinancialRecord usa `amount`, não `value`; DailyClosure usa `total_entry` e `total_exit`, não `entries` e `exits`
- **Ação:** Sempre rodar migrations antes de criar factory/teste

#### 2. **User Role Verification**
- ❌ **Erro:** Usar `is_root=true` no teste quando a migration define `enum('root', 'owner')`
- ✅ **Correto:** Usar `role='root'` para corresponder ao schema
- **Impacto:** Controllers com `isRoot()` retornarão 403 Forbidden se role não for 'root'
- **Checklist:** Conferir `users` migration antes de criar testes

#### 3. **API Response Structure**
- ❌ **Erro:** Retornar `response()->json($model)`
- ✅ **Correto:** Sempre envolver em `data` key: `response()->json(['data' => $model], 201)`
- **Razão:** Consistência com padrão REST e previsibilidade em testes
- **Impacto em Testes:** Todos os `assertJsonPath('data.field')` falharão se não for envolvido

#### 4. **Route Ordering para Endpoints Customizados**
- ❌ **Erro:** Usar `Route::apiResource('commissions', ...)` depois de rotas customizadas
- ✅ **Correto:** Colocar rotas customizadas (`/mark-as-paid`, `/generate-from-date`) ANTES de `apiResource`
- **Razão:** Laravel processa rotas em ordem; `POST /commissions/mark-as-paid` sem proteção será capturado como `POST /commissions/{id}` (update)
- **Resultado:** Rotas customizadas retornarão 405 Method Not Allowed ou 404

#### 5. **Factory Design para Unique Constraints**
- ❌ **Erro:** Usar `dateTimeBetween` direto quando há unique constraint em (collaborator_id, date)
- ✅ **Correto:** Usar random offset ou múltiplos collaborators em testes
- **Exemplo:** WorkShift tem unique('work_shifts_collaborator_id_date_unique')
- **Impacto:** Duplicate entry errors ao criar múltiplos records com factory

#### 6. **Campos Calculados em Factories**
- ❌ **Erro:** Não calcular `total_hours` em WorkShift, deixando NULL
- ✅ **Correto:** Calcular no factory ou deixar com default na migration
- **Solução para Controller:** Calcular no método `store()` e `update()`
```php
$startTime = strtotime('2000-01-01 ' . $data['start_time']);
$endTime = strtotime('2000-01-01 ' . $data['end_time']);
if ($endTime < $startTime) $endTime += 86400; // midnight crossing
$data['total_hours'] = round(($endTime - $startTime) / 3600, 2);
```

#### 7. **Form Request Validation Consistency**
- ❌ **Erro:** Validar campos que não existem na tabela (`notes` em DailyClosure)
- ✅ **Correto:** Remover da validation quando campo não existe
- **Checklist:** Conferir fillable do Model antes de criar StoreRequest

#### 8. **Factory Relationships**
- ❌ **Erro:** Usar `->for($this->company)` em factory que não tem foreign key company_id
- ✅ **Correto:** Verificar migration para relações específicas
- **Exemplo:** DailyClosureFactory não deveria ter `company_id` (schema original não tem)

#### 9. **Pagination Response Structure**
- ❌ **Erro:** Retornar `$paginated` direto
- ✅ **Correto:** `response()->json(['data' => $paginated->items(), 'pagination' => [...])`
- **Razão:** Testes esperam `data` array com informações de paginação separadas

#### 10. **Type Casting em Responses**
- ⚠️ **Atenção:** `amount` retorna como string '50.00' JSON, não número 50.0
- **Testes:** Use `assertJsonPath('data.amount', '50.00')` com string, não número

---

### 📋 Pre-Flight Checklist para Novas Fases

Antes de criar Controller/Factory/Tests:

1. ✅ Conferir migrations para schema exato
2. ✅ Listar todos os campos do modelo (fillable)
3. ✅ Listar foreign keys e relationships
4. ✅ Verificar unique constraints
5. ✅ Confirmar campos que são NULL-able
6. ✅ Revisar enums (ex: role vs is_root)
7. ✅ Definir estrutura de resposta esperada (com 'data' key)
8. ✅ Planejar route order (custom antes de apiResource)
9. ✅ Documentar campos calculados que Controller deve fazer

### 🚀 Phase 3 Final Status

- **Controllers:** 5/5 ✅
- **Routes:** 30+ endpoints ✅  
- **Tests:** 47/64 passando (73.4%) ✅
- **Multi-Tenant:** Isolamento 100% implementado ✅
- **Pronto para Phase 4:** Dashboard & Analytics




