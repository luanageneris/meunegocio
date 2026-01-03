# 📋 Checklist de Desenvolvimento - Meu Negócio

> Sistema de Controle Financeiro e Comissões para Xerografia & Cia

**Versão:** 0.1.0 | **Data Início:** 03/01/2026 | **Stack:** Laravel + Vue 3

---

## 🎯 FASE 1: SETUP E FUNDAÇÕES (Backend)

### 1.1 Configuração do Banco de Dados
- [ ] Criar migrations das tabelas principais
  - [ ] users (com suporte OAuth Google)
  - [ ] collaborators
  - [ ] service_types
  - [ ] entry_types
  - [ ] exit_types
  - [ ] financial_records
  - [ ] work_shifts
  - [ ] commissions
  - [ ] daily_closures
  - [ ] balances
  - [ ] import_logs
- [ ] Implementar relacionamentos Eloquent
- [ ] Adicionar indexes de performance
- [ ] Criar seeders iniciais para testes

### 1.2 Modelos Eloquent
- [ ] Model: User (com Google OAuth)
- [ ] Model: Collaborator (soft delete)
- [ ] Model: ServiceType (soft delete)
- [ ] Model: EntryType (soft delete)
- [ ] Model: ExitType (soft delete)
- [ ] Model: FinancialRecord (soft delete)
- [ ] Model: WorkShift (soft delete)
- [ ] Model: Commission (soft delete)
- [ ] Model: DailyClosure
- [ ] Model: Balance
- [ ] Model: ImportLog
- [ ] Validar relacionamentos com testes unitários

### 1.3 Configuração de Autenticação
- [ ] Configurar Sanctum/JWT para API
- [ ] Integrar Google OAuth (Socialite)
- [ ] Criar controller de autenticação
- [ ] Implementar criação automática de usuário Google
- [ ] Criar seeder com usuário root (luanacarvalhomesquita@gmail.com)
- [ ] Testes de autenticação

### 1.4 Estrutura de Pastas Laravel
- [ ] app/Models/
- [ ] app/Http/Controllers/Api/
- [ ] app/Http/Requests/
- [ ] app/Services/ (lógica de negócio)
- [ ] app/Repositories/ (acesso a dados)
- [ ] database/migrations/
- [ ] database/seeders/
- [ ] routes/api.php (estruturado)
- [ ] tests/Feature/
- [ ] tests/Unit/

---

## 🎯 FASE 2: API REST - ENDPOINTS BÁSICOS

### 2.1 Autenticação
- [ ] POST /api/auth/google (login com Google)
- [ ] POST /api/auth/logout
- [ ] GET /api/auth/user (usuário atual)
- [ ] POST /api/auth/impersonate (root pode logar como outro)
- [ ] Testes Feature para cada endpoint

### 2.2 CRUD Colaboradores (Personalizar)
- [ ] GET /api/collaborators (listar)
- [ ] POST /api/collaborators (criar)
- [ ] GET /api/collaborators/{id}
- [ ] PUT /api/collaborators/{id} (editar)
- [ ] DELETE /api/collaborators/{id} (soft delete)
- [ ] Validações (CPF único, nome obrigatório)
- [ ] Testes Feature completos
- [ ] Permissões (apenas root)

### 2.3 CRUD Tipos de Entrada
- [ ] GET /api/entry-types
- [ ] POST /api/entry-types
- [ ] PUT /api/entry-types/{id}
- [ ] DELETE /api/entry-types/{id}
- [ ] Testes Feature
- [ ] Permissões

### 2.4 CRUD Tipos de Saída
- [ ] GET /api/exit-types
- [ ] POST /api/exit-types
- [ ] PUT /api/exit-types/{id}
- [ ] DELETE /api/exit-types/{id}
- [ ] Testes Feature
- [ ] Permissões

### 2.5 CRUD Tipos de Serviço
- [ ] GET /api/service-types
- [ ] POST /api/service-types (com commission_percentage)
- [ ] PUT /api/service-types/{id}
- [ ] DELETE /api/service-types/{id}
- [ ] Validações (percentual 0-100)
- [ ] Testes Feature
- [ ] Permissões

---

## 🎯 FASE 3: LÓGICA FINANCEIRA (Backend)

### 3.1 Registros Financeiros
- [ ] GET /api/financial-records (com filtros)
- [ ] POST /api/financial-records (criar entrada/saída)
- [ ] PUT /api/financial-records/{id}
- [ ] DELETE /api/financial-records/{id}
- [ ] Validações (amount > 0, type válido, etc)
- [ ] Testes Feature
- [ ] Permissões

### 3.2 Turnos de Trabalho
- [ ] GET /api/work-shifts
- [ ] POST /api/work-shifts (registrar turno)
- [ ] PUT /api/work-shifts/{id}
- [ ] DELETE /api/work-shifts/{id}
- [ ] Calcular total_hours automaticamente
- [ ] Testes Feature

### 3.3 Cálculo de Comissões
- [ ] Service: ComissionCalculator
  - [ ] Calcular comissão por registro financeiro
  - [ ] Aplicar desconto antes do cálculo
  - [ ] Registrar em commissions table
- [ ] GET /api/commissions (filtrar por colaborador/período)
- [ ] POST /api/commissions/mark-as-paid
- [ ] Testes Unit (ComissionCalculator)
- [ ] Testes Feature (endpoints)

### 3.4 Fechamento Diário
- [ ] GET /api/daily-closures (listar)
- [ ] POST /api/daily-closures (criar closure do dia)
  - [ ] Somar entries da data
  - [ ] Somar exits da data
  - [ ] Calcular lucro (entries - exits)
  - [ ] Marcar como fechado
- [ ] Bloquear edições após fechamento (regra de negócio)
- [ ] Testes Feature
- [ ] Testes Unit (lógica)

### 3.5 Saldo
- [ ] Service: BalanceCalculator
  - [ ] Calcular saldo acumulado
  - [ ] Considerar opening_balance
  - [ ] GET /api/balance (saldo atual)
- [ ] Testes Unit

### 3.6 Filtros por Período
- [ ] Query Scopes em FinancialRecord
  - [ ] byDate(), byWeek(), byMonth(), byYear()
- [ ] Testes Unit (scopes)

---

## 🎯 FASE 4: IMPORTAÇÃO EXCEL

### 4.1 Estrutura de Importação
- [ ] Service: ExcelImporter
  - [ ] Validar estrutura do Excel
  - [ ] Mapear colaboradores
  - [ ] Mapear entradas
  - [ ] Mapear saídas
  - [ ] Importar saldo inicial
- [ ] POST /api/import (upload do arquivo)
- [ ] GET /api/import-logs (histórico)
- [ ] Testes Unit (ExcelImporter)
- [ ] Testes Feature (endpoint)

### 4.2 Validação de Dados
- [ ] Validar CPF duplicado
- [ ] Validar valores positivos
- [ ] Validar datas válidas
- [ ] Rollback em caso de erro
- [ ] Relatório de erros detalhado

---

## 🎯 FASE 5: SETUP FRONTEND (Vue 3)

### 5.1 Estrutura de Pastas
- [ ] resources/js/
  - [ ] components/ (reutilizáveis)
  - [ ] pages/ (telas)
  - [ ] composables/ (lógica compartilhada)
  - [ ] stores/ (Pinia)
  - [ ] api/ (chamadas HTTP)
  - [ ] types/ (TypeScript types)
  - [ ] layouts/ (layouts)
  - [ ] router/ (rotas)

### 5.2 Configuração Base
- [ ] Instalar Pinia (state management)
- [ ] Instalar Vue Router
- [ ] Instalar Axios (HTTP client)
- [ ] Instalar TailwindCSS (ou escolher estilo)
- [ ] Instalar Chart.js + vue-chartjs
- [ ] Configurar TypeScript
- [ ] Setup de testes (Vitest)

### 5.3 API Client
- [ ] axiosInstance com interceptors
  - [ ] Adicionar token Bearer
  - [ ] Refresh token
  - [ ] Tratamento de erro 401
- [ ] Services para cada recurso
  - [ ] authService
  - [ ] collaboratorService
  - [ ] serviceTypeService
  - [ ] financialRecordService
  - [ ] etc

---

## 🎯 FASE 6: AUTENTICAÇÃO FRONTEND

### 6.1 Login via Google
- [ ] Instalar @react-oauth/google (ou lib Vue equivalente)
- [ ] Página de Login
  - [ ] Botão Google OAuth
  - [ ] Botão Login Root
  - [ ] Validações
- [ ] Store Pinia: authStore
  - [ ] Gerenciar token
  - [ ] Gerenciar usuário atual
  - [ ] Logout
- [ ] Middleware de rota (ProtectedRoute)
- [ ] Testes Unit (authStore)
- [ ] Testes E2E (fluxo login)

### 6.2 Root Impersonation
- [ ] Seletor de usuários (apenas para root)
- [ ] API call para impersonate
- [ ] UI feedback de impersonation
- [ ] Testes

---

## 🎯 FASE 7: TELAS PRINCIPAIS

### 7.1 Layout Geral
- [ ] AppLayout (header, sidebar, footer)
- [ ] Menu principal (Painel, Registros, Personalizar)
- [ ] Responsividade
- [ ] Testes de componentes

### 7.2 Painel (Dashboard)
- [ ] Cards de resumo
  - [ ] Total entrada (mês)
  - [ ] Total saída (mês)
  - [ ] Lucro (mês)
  - [ ] Saldo atual
- [ ] Gráficos
  - [ ] Comissão por colaborador (bar chart)
  - [ ] Top 5 dias rentáveis (line chart)
  - [ ] Entrada vs Saída (pie chart)
- [ ] Filtros (dia, semana, mês, ano)
- [ ] Testes Unit (composables)
- [ ] Testes E2E (interação)

### 7.3 Registros - Visualização Lucro
- [ ] Calendário mensal
- [ ] Mostrar lucro por dia
- [ ] Indicador de "fechado"
- [ ] Click para ver detalhes
- [ ] Testes

### 7.4 Registros - Visualização Escala
- [ ] Calendário mensal
- [ ] Mostrar colaboradores por turno
- [ ] Cores por colaborador
- [ ] Testes

### 7.5 Registros - Cadastro de Dados
- [ ] Formulário: Saldo Inicial
- [ ] Formulário: Entrada/Saída
  - [ ] Tipo (entry/exit)
  - [ ] Categoria
  - [ ] Valor
  - [ ] Desconto (se aplicável)
  - [ ] Colaborador (opcional)
  - [ ] Data
  - [ ] Descrição
  - [ ] Validações
- [ ] Testes Unit (validações)
- [ ] Testes E2E (formulário)

### 7.6 Registros - Fechamento Diário
- [ ] Modal de confirmação
- [ ] Mostrar resumo do dia
- [ ] Confirmar fechamento
- [ ] Bloqueio de edição pós-fechamento
- [ ] Testes

### 7.7 CRUD Colaboradores (Personalizar)
- [ ] Tabela de colaboradores
- [ ] Modal criar/editar
- [ ] Soft delete com confirmação
- [ ] Validações
- [ ] Testes E2E

### 7.8 CRUD Tipos de Entrada/Saída/Serviço
- [ ] Tabelas com CRUD
- [ ] Modais criar/editar
- [ ] Soft delete
- [ ] Validações
- [ ] Testes

---

## 🎯 FASE 8: IMPORTAÇÃO (Frontend)

### 8.1 Upload de Excel
- [ ] Formulário de upload
- [ ] Validação de arquivo (.xlsx)
- [ ] Progresso de upload
- [ ] Feedback de sucesso/erro
- [ ] Testes E2E

### 8.2 Histórico de Importações
- [ ] Tabela com log de imports
- [ ] Status (sucesso, erro, etc)
- [ ] Data e usuário
- [ ] Testes

---

## ✅ FASE 9: TESTES E QUALIDADE

### 9.1 Testes Backend
- [ ] Cobertura mínima 80% em Services
- [ ] Testes Unit para Calculators
- [ ] Testes Feature para todos os endpoints
- [ ] Testes de validação
- [ ] Testes de permissão

### 9.2 Testes Frontend
- [ ] Cobertura mínima 70% em composables
- [ ] Testes Unit para stores (Pinia)
- [ ] Testes Unit para components
- [ ] Testes E2E para fluxos críticos
  - [ ] Login
  - [ ] Criar entrada/saída
  - [ ] Calcular comissão
  - [ ] Fechar dia
  - [ ] Importar Excel

### 9.3 Code Quality
- [ ] Laravel: PHPStan level 8
- [ ] Vue: ESLint + Prettier
- [ ] Formatação automática
- [ ] CI/CD pipeline (GitHub Actions)

---

## 🚀 FASE 10: AJUSTES FINAIS E DEPLOY

### 10.1 Performance
- [ ] Otimizar queries N+1
- [ ] Caching de dados
- [ ] Compressão de assets
- [ ] CDN para imagens/logos

### 10.2 Documentação
- [ ] README.md completo
- [ ] API documentation (Swagger/Postman)
- [ ] Frontend components documentation
- [ ] Guia de instalação
- [ ] Guia de uso

### 10.3 Deploy
- [ ] Preparar ambiente de produção
- [ ] Variáveis de ambiente
- [ ] Backup strategy
- [ ] Monitoring
- [ ] Logging

### 10.4 Segurança
- [ ] CORS configurado
- [ ] Rate limiting
- [ ] SQL injection prevention
- [ ] XSS prevention
- [ ] CSRF protection
- [ ] Password hashing
- [ ] Encrypt sensitive data

---

## 📊 GUIA DE USO DESTE CHECKLIST

### Convenções
- `[ ]` = Não iniciado
- `[x]` = Concluído
- Marque com `[ ]` quando começar uma subtarefa
- Mova para próxima fase quando 100% da anterior estiver ok

### Recomendações por Sprint
- **Sprint 1:** Fase 1 + 2 (Backend setup + auth)
- **Sprint 2:** Fase 3 (Lógica financeira)
- **Sprint 3:** Fase 4 + 5 (Importação + Frontend setup)
- **Sprint 4:** Fase 6 + 7 (Autenticação + Telas)
- **Sprint 5:** Fase 8 + 9 (Importação frontend + Testes)
- **Sprint 6:** Fase 10 (Deploy)

### Checklist Paralelas
- Testes podem ser escritos conforme features
- Frontend pode começar em paralelo após Fase 2

---

## 🔗 REFERÊNCIAS

- **Instruções Gerais:** [intro.instructions.md](.github/instructions/intro.instructions.md)
- **Stack:** Laravel 11 + Vue 3 + Composition API
- **Banco:** MySQL
- **Testing:** Pest (backend), Vitest (frontend)

---

**Status Geral:** 🔴 Não Iniciado | 🟡 Em Progresso | 🟢 Completo
