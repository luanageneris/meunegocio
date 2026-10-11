<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import Sidebar from '@/components/Sidebar.vue';
import FixedPageHeaderTitle from '@/components/FixedPageHeaderTitle.vue';
import LoadingSpinner from '@/components/LoadingSpinner.vue';
import { useAuth } from '@/composables/useAuth';
import Footer from '@/components/Footer.vue';

interface AuditLog {
  id: number;
  action: string;
  model: string;
  model_id: number;
  user: { id: number; name: string } | null;
  endpoint: string;
  ip_address: string;
  created_at: string;
  action_label?: string;
  model_label?: string;
  entity_reference?: string;
  changed_fields?: string[];
  changed_fields_count?: number;
  summary?: string;
  details?: {
    type: string;
    fields: Array<{
      field: string;
      old: unknown;
      new: unknown;
    }>;
  };
}

interface Stats {
  total: number;
  by_action: Record<string, number>;
  by_model: Array<{ model: string; model_label: string; count: number }>;
  by_user: Array<{ user_id: number; user_name: string; count: number }>;
}

const auth = useAuth();
const logs = ref<AuditLog[]>([]);
const stats = ref<Stats | null>(null);
const loading = ref(false);
const selectedFilter = ref<'all' | 'create' | 'update' | 'delete' | 'restore' | 'login' | 'login_failed'>('all');
const selectedModel = ref('');
const dateFrom = ref('');
const dateTo = ref('');
const currentPage = ref(1);
const perPage = ref(20);
const totalPages = ref(1);
const totalRecords = ref(0);
const expandedLog = ref<number | null>(null);
const activeQuickFilter = ref<'all' | 'create' | 'update' | 'delete' | null>(null);

const quickFilterCardClass = (action: 'all' | 'create' | 'update' | 'delete') => {
  const isActive = activeQuickFilter.value === action;

  return [
    'bg-white rounded-xl p-4 shadow-sm border transition text-left w-full min-h-[110px]',
    isActive ? 'ring-2 ring-orange-400 border-orange-300 shadow-md' : 'hover:shadow-md',
    action === 'all' ? 'border-orange-200' : '',
    action === 'create' ? 'border-green-200' : '',
    action === 'update' ? 'border-blue-200' : '',
    action === 'delete' ? 'border-red-200' : '',
  ];
};

const getLast30DaysRange = () => {
  const to = new Date();
  const from = new Date();
  from.setDate(from.getDate() - 29);

  const toInput = to.toISOString().split('T')[0];
  const fromInput = from.toISOString().split('T')[0];

  return { fromInput, toInput };
};

const applyQuickFilter = (action: 'all' | 'create' | 'update' | 'delete') => {
  const { fromInput, toInput } = getLast30DaysRange();

  activeQuickFilter.value = action;
  selectedFilter.value = action;
  dateFrom.value = fromInput;
  dateTo.value = toInput;
  currentPage.value = 1;
  fetchLogs(1);
};

const clearAllFilters = () => {
  activeQuickFilter.value = null;
  selectedFilter.value = 'all';
  selectedModel.value = '';
  dateFrom.value = '';
  dateTo.value = '';
  currentPage.value = 1;
  fetchLogs(1);
};

const hasActiveFilters = () => {
  return selectedFilter.value !== 'all' || selectedModel.value !== '' || dateFrom.value !== '' || dateTo.value !== '';
};

const formatInputDate = (value: string) => {
  const parts = value.split('-');
  if (parts.length !== 3) return value;
  return `${parts[2]}/${parts[1]}/${parts[0]}`;
};

const removeActionFilter = () => {
  selectedFilter.value = 'all';
  activeQuickFilter.value = null;
};

const removeModelFilter = () => {
  selectedModel.value = '';
};

const removeDateFromFilter = () => {
  dateFrom.value = '';
};

const removeDateToFilter = () => {
  dateTo.value = '';
};

// Funções helpers
const getActionBadgeClass = (action: string) => {
  return {
    'create': 'bg-green-100 text-green-800',
    'update': 'bg-blue-100 text-blue-800',
    'delete': 'bg-red-100 text-red-800',
    'restore': 'bg-purple-100 text-purple-800',
    'login': 'bg-emerald-100 text-emerald-800',
    'login_failed': 'bg-amber-100 text-amber-800',
  }[action] || 'bg-gray-100 text-gray-800';
};

const getActionLabel = (action: string) => {
  return {
    'create': 'Criado',
    'update': 'Atualizado',
    'delete': 'Deletado',
    'restore': 'Restaurado',
    'login': 'Login realizado',
    'login_failed': 'Falha no login',
  }[action] || action;
};

const getModelLabel = (model: string) => {
  const modelName = model.split('\\').pop() || model;
  return {
    'Collaborator': 'Colaborador',
    'FinancialRecord': 'Registro Financeiro',
    'Commission': 'Comissão',
    'WorkShift': 'Turno de Trabalho',
    'ServiceType': 'Tipo de Serviço',
    'EntryType': 'Tipo de Entrada',
    'ExitType': 'Tipo de Saída',
    'DailyClosure': 'Fechamento Diário',
    'Balance': 'Saldo',
    'User': 'Usuário',
  }[modelName] || modelName;
};

const stripEntityId = (text: string) => {
  return text
    .replace(/\s*#\d+\b/g, '')
    .replace(/\bID\s*\d+\b/gi, '')
    .replace(/\s{2,}/g, ' ')
    .trim();
};

const getDisplayModelLabel = (log: AuditLog) => {
  const raw = log.model_label || getModelLabel(log.model);
  return stripEntityId(raw);
};

const getDisplaySummary = (log: AuditLog) => {
  const raw = log.summary || (getActionLabel(log.action) + ' ' + getModelLabel(log.model));
  return stripEntityId(raw);
};

const fieldLabels: Record<string, Record<string, string>> = {
  Collaborator: {
    name: 'Nome',
    cpf: 'CPF',
    active: 'Ativo',
  },
  FinancialRecord: {
    type: 'Tipo (entrada/saída)',
    entry_type_id: 'Tipo de Entrada',
    entry_type_name: 'Tipo de Entrada',
    entry_type: 'Tipo de Entrada',
    exit_type_id: 'Tipo de Saída',
    exit_type_name: 'Tipo de Saída',
    exit_type: 'Tipo de Saída',
    collaborator_id: 'Colaborador',
    collaborator_name: 'Colaborador',
    collaborator: 'Colaborador',
    service_type_id: 'Tipo de Serviço',
    service_type_name: 'Tipo de Serviço',
    service_type: 'Tipo de Serviço',
    work_shift_id: 'Turno',
    work_shift_name: 'Turno',
    amount: 'Valor',
    quantity: 'Quantidade',
    discount: 'Desconto',
    date: 'Data',
    description: 'Descrição',
  },
  Commission: {
    collaborator_id: 'Colaborador',
    collaborator_name: 'Colaborador',
    financial_record_id: 'Registro Financeiro',
    financial_record_name: 'Registro Financeiro',
    daily_closure_id: 'Fechamento Diário',
    daily_closure_name: 'Fechamento Diário',
    amount: 'Valor',
    paid_at: 'Pago em',
    status: 'Status',
  },
  WorkShift: {
    collaborator_id: 'Colaborador',
    collaborator_name: 'Colaborador',
    date: 'Data',
    start_time: 'Início',
    end_time: 'Fim',
    total_hours: 'Horas Totais',
    shift_type: 'Turno',
  },
  ServiceType: {
    name: 'Nome',
    commission_percentage: 'Percentual de Comissão',
    description: 'Descrição',
  },
  EntryType: {
    name: 'Nome',
    description: 'Descrição',
  },
  ExitType: {
    name: 'Nome',
    description: 'Descrição',
  },
  DailyClosure: {
    date: 'Data',
    total_entry: 'Total de Entradas',
    total_exit: 'Total de Saídas',
    total_profit: 'Lucro',
    is_closed: 'Fechado',
  },
  Balance: {
    balance: 'Saldo',
    date: 'Data',
  },
  ImportLog: {
    import_summary: 'Resumo da Importação',
  },
  User: {
    event: 'Evento',
    login_at: 'Momento do login',
    attempted_at: 'Momento da tentativa',
    ip_address: 'IP',
    location: 'Localização',
  },
};

const genericFieldLabels: Record<string, string> = {
  use_service_types: 'Usar Tipos de Serviço',
  use_entry_types: 'Usar Tipos de Entrada',
  use_exit_types: 'Usar Tipos de Saída',
  required_collaborator: 'Colaborador obrigatório',
  default_collaborator_id: 'Colaborador padrão',
  default_entry_type_id: 'Tipo de entrada padrão',
  default_exit_type_id: 'Tipo de saída padrão',
};

const formatKey = (key: unknown) => {
  if (key === null || key === undefined) return '';
  const safeKey = typeof key === 'string' ? key : String(key);

  return safeKey
    .split('_')
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ');
};

const getFieldLabel = (model: unknown, field: unknown) => {
  const modelSafe = typeof model === 'string' ? model : String(model ?? '');
  const modelName = modelSafe.split('\\').pop() || modelSafe;
  const fieldKey = typeof field === 'string' ? field : String(field ?? '');

  return fieldLabels[modelName]?.[fieldKey] || genericFieldLabels[fieldKey] || formatKey(fieldKey);
};

const shouldHideField = (field: unknown) => {
  const key = typeof field === 'string' ? field : String(field ?? '');
  const hidden = ['id', 'company_id', 'created_at', 'updated_at', 'deleted_at', 'pivot', 'collaborator_id', 'endpoint', 'required_collaborator'];
  return hidden.includes(key) || key.endsWith('_id');
};

const formatDate = (date: string) => {
  return new Intl.DateTimeFormat('pt-BR', {
    dateStyle: 'short',
    timeStyle: 'short',
  }).format(new Date(date));
};

const formatValue = (value: unknown, field: string = '') => {
  if (value === null || value === undefined || value === '') {
    return '—';
  }

  const lowerField = field.toLowerCase();

  if (typeof value === 'number') {
    const moneyFields = ['amount', 'discount', 'balance', 'total_entry', 'total_exit', 'total_profit'];
    if (moneyFields.includes(field)) {
      return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
    }

    return String(value);
  }

  if (typeof value === 'boolean') {
    return value ? 'Sim' : 'Não';
  }

  if (typeof value === 'string') {
    const normalizedField = field.toLowerCase();
    const isJsonCandidateField = ['payload', 'request', 'response', 'summary', 'details'].includes(normalizedField);

    if (isJsonCandidateField) {
      const parsed = tryParseJson(value);

      if (parsed !== null) {
        const importSummary = buildImportSummary(parsed);
        if (importSummary) {
          return importSummary;
        }

        return JSON.stringify(parsed, null, 2);
      }
    }

    if ((lowerField === 'type' || lowerField.endsWith('_type')) && ['entry', 'exit'].includes(value.toLowerCase())) {
      return value.toLowerCase() === 'entry' ? 'Entrada' : 'Saída';
    }

    const asDate = new Date(value);
    const looksLikeDateField = lowerField.includes('date') || lowerField.endsWith('_at');
    const hasTime = /T\d{2}:\d{2}|\d{2}:\d{2}:\d{2}/.test(value);

    if (looksLikeDateField && !Number.isNaN(asDate.getTime())) {
      if (hasTime) {
        return new Intl.DateTimeFormat('pt-BR', {
          dateStyle: 'short',
          timeStyle: 'short',
        }).format(asDate);
      }

      return new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'short',
      }).format(asDate);
    }

    if (/^\d+\.\d{2}$/.test(value) && ['amount', 'discount', 'balance', 'total_entry', 'total_exit', 'total_profit'].includes(field)) {
      return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value));
    }

    return value;
  }

  if (Array.isArray(value)) {
    return `${value.length} itens`;
  }

  if (typeof value === 'object') {
    const objectValue = value as Record<string, unknown>;

    const importSummary = buildImportSummary(objectValue);
    if (importSummary) {
      return importSummary;
    }

    if (typeof objectValue.name === 'string' && objectValue.name.trim() !== '') {
      return objectValue.name;
    }

    if (typeof objectValue.label === 'string' && objectValue.label.trim() !== '') {
      return objectValue.label;
    }

    if (typeof objectValue.description === 'string' && objectValue.description.trim() !== '') {
      return objectValue.description;
    }

    return JSON.stringify(value, null, 2);
  }

  return String(value);
};

const tryParseJson = (value: string) => {
  const trimmed = value.trim();

  if ((!trimmed.startsWith('{') || !trimmed.endsWith('}')) && (!trimmed.startsWith('[') || !trimmed.endsWith(']'))) {
    return null;
  }

  try {
    return JSON.parse(trimmed) as unknown;
  } catch {
    return null;
  }
};

const buildImportSummary = (value: unknown) => {
  if (!value || typeof value !== 'object' || Array.isArray(value)) {
    return null;
  }

  const obj = value as Record<string, unknown>;
  const summary = obj.summary;

  if (!summary || typeof summary !== 'object' || Array.isArray(summary)) {
    return null;
  }

  const s = summary as Record<string, unknown>;
  const lines: string[] = [];

  const appendLine = (label: string, key: string) => {
    const raw = s[key];
    if (raw === null || raw === undefined || raw === '') {
      return;
    }
    lines.push(`${label}: ${raw}`);
  };

  appendLine('Aba(s) processada(s)', 'sheets_processed');
  appendLine('Linha(s) processada(s)', 'rows_processed');
  appendLine('Entrada(s) criada(s)', 'entries_created');
  appendLine('Saída(s) criada(s)', 'exits_created');
  appendLine('Comissão(ões) criada(s)', 'commissions_created');

  if (lines.length === 0) {
    return null;
  }

  return lines.join('\n');
};

const isComplexValue = (value: unknown, field: unknown) => {
  const fieldKey = typeof field === 'string' ? field.toLowerCase() : String(field ?? '').toLowerCase();

  if (['payload', 'request', 'response', 'summary', 'details'].includes(fieldKey)) {
    return true;
  }

  if (typeof value === 'object' && value !== null) {
    return true;
  }

  if (typeof value === 'string') {
    const trimmed = value.trim();
    return (trimmed.startsWith('{') && trimmed.endsWith('}')) || (trimmed.startsWith('[') && trimmed.endsWith(']')) || trimmed.includes('\n') || value.length > 80;
  }

  return false;
};

const isEmptyAuditValue = (value: unknown) => {
  if (value === null || value === undefined) {
    return true;
  }

  if (typeof value === 'string') {
    return value.trim() === '';
  }

  return false;
};

const isImportLogModel = (model: unknown) => {
  const modelSafe = typeof model === 'string' ? model : String(model ?? '');
  const modelName = modelSafe.split('\\').pop() || modelSafe;
  return modelName === 'ImportLog';
};

const getImportSummaryFromLog = (log: AuditLog) => {
  const details = log.details?.fields || [];

  const candidates = details
    .filter((item) => ['response', 'payload', 'summary'].includes(String(item.field).toLowerCase()))
    .map((item) => item.new ?? item.old)
    .filter((value) => !isEmptyAuditValue(value));

  for (const candidate of candidates) {
    const summary = buildImportSummary(candidate);
    if (summary) {
      return summary;
    }

    if (typeof candidate === 'string') {
      const parsed = tryParseJson(candidate);
      const parsedSummary = parsed ? buildImportSummary(parsed) : null;

      if (parsedSummary) {
        return parsedSummary;
      }
    }
  }

  return null;
};

const getVisibleDetails = (log: AuditLog) => {
  const details = log.details?.fields || [];

  if (details.length === 0) {
    return [];
  }

  if (isImportLogModel(log.model)) {
    const summary = getImportSummaryFromLog(log);

    if (!summary) {
      return [];
    }

    return [
      {
        field: 'import_summary',
        old: null,
        new: summary,
      },
    ];
  }

  return details.filter((item) => {
    if (shouldHideField(item.field)) {
      return false;
    }

    const oldIsEmpty = isEmptyAuditValue(item.old);
    const newIsEmpty = isEmptyAuditValue(item.new);

    // Hide rows where there is no meaningful comparison between old and new values.
    return !(oldIsEmpty && newIsEmpty);
  });
};

const hasMeaningfulDetails = (log: AuditLog) => {
  return getVisibleDetails(log).length > 0;
};

const getDetailDisplayMode = (log: AuditLog) => {
  if (log.action === 'create' || log.action === 'restore') {
    return 'new-only';
  }

  if (log.action === 'delete') {
    return 'old-only';
  }

  const visibleDetails = getVisibleDetails(log);
  const hasOldValues = visibleDetails.some((detail) => !isEmptyAuditValue(detail.old));
  const hasNewValues = visibleDetails.some((detail) => !isEmptyAuditValue(detail.new));

  if (!hasOldValues && hasNewValues) {
    return 'new-only';
  }

  if (hasOldValues && !hasNewValues) {
    return 'old-only';
  }

  return 'both';
};

// Carregando dados
const fetchLogs = async (page = 1) => {
  loading.value = true;
  try {
    const params: any = {
      per_page: perPage.value,
      page: page,
    };

    if (selectedFilter.value !== 'all') {
      params.action = selectedFilter.value;
    }

    if (selectedModel.value) {
      params.model = selectedModel.value;
    }

    if (dateFrom.value) {
      params.from_date = dateFrom.value;
    }

    if (dateTo.value) {
      params.to_date = dateTo.value;
    }

    const response = await axios.get('/api/history', { params });
    logs.value = response.data.data;
    totalPages.value = response.data.pagination.last_page;
    totalRecords.value = response.data.pagination.total;
    currentPage.value = page;
  } catch (error) {
    console.error('Erro ao carregar histórico:', error);
  } finally {
    loading.value = false;
  }
};

const fetchStats = async () => {
  try {
    const days = 30;
    const response = await axios.get(`/api/history/stats?days=${days}`);
    stats.value = response.data.data;
  } catch (error) {
    console.error('Erro ao carregar estatísticas:', error);
  }
};

// Watch para refazer a busca quando filtros mudam
watch([selectedFilter, selectedModel, dateFrom, dateTo], () => {
  currentPage.value = 1;
  fetchLogs();
});

const toggleDetails = (logId: number) => {
  const isClosing = expandedLog.value === logId;
  expandedLog.value = isClosing ? null : logId;
};

const isExpanded = (logId: number) => {
  return expandedLog.value === logId;
};

onMounted(() => {
  if (!auth.isAuthenticated.value) {
    window.location.href = '/login';
    return;
  }

  fetchLogs();
  fetchStats();
});
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-amber-50 to-orange-100 dark:from-slate-950 dark:via-slate-900 dark:to-slate-950 flex overflow-x-hidden">
    <Head title="Histórico - Meu Negócio" />

    <Sidebar active-route="historico" />

    <div class="flex-1 min-w-0 lg:ml-64 transition-all duration-300 overflow-x-hidden flex flex-col">
      <FixedPageHeaderTitle
        title="Histórico"
       subtitle="Acompanhe todas as alterações realizadas no sistema"
      />

      <main class="w-full min-w-0 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 overflow-x-hidden">
        <!-- Stats Cards -->
        <div v-if="stats" class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-8">
          <button type="button" :class="quickFilterCardClass('all')" @click="applyQuickFilter('all')">
            <p class="text-gray-600 text-xs font-semibold text-center">Últimos 30 dias</p>
            <p class="text-3xl font-black text-orange-600 text-center mt-2">{{ stats.total }}</p>
          </button>
          <button type="button" :class="quickFilterCardClass('create')" @click="applyQuickFilter('create')">
            <p class="text-gray-600 text-xs font-semibold text-center">Novos registros</p>
            <p class="text-3xl font-black text-green-600 text-center mt-2">{{ stats.by_action.create }}</p>
          </button>
          <button type="button" :class="quickFilterCardClass('update')" @click="applyQuickFilter('update')">
            <p class="text-gray-600 text-xs font-semibold text-center">Registros modificados</p>
            <p class="text-3xl font-black text-blue-600 text-center mt-2">{{ stats.by_action.update }}</p>
          </button>
          <button type="button" :class="quickFilterCardClass('delete')" @click="applyQuickFilter('delete')">
            <p class="text-gray-600 text-xs font-semibold text-center">Registros removidos</p>
            <p class="text-3xl font-black text-red-600 text-center mt-2">{{ stats.by_action.delete }}</p>
          </button>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-orange-200 mb-8">
          <div class="mb-4 flex items-center justify-between gap-3 flex-wrap">
            <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
              <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
              </svg>
              Filtros
            </h2>

            <button
              type="button"
              :disabled="!hasActiveFilters()"
              :class="[
                'px-4 py-2 rounded-lg border-2 transition font-semibold',
                hasActiveFilters()
                  ? 'border-orange-200 text-orange-700 hover:bg-orange-100'
                  : 'border-gray-200 text-gray-400 cursor-not-allowed bg-gray-100',
              ]"
              @click="clearAllFilters"
            >
              Limpar filtros
            </button>
          </div>

          <div v-if="hasActiveFilters()" class="mb-4 flex flex-wrap items-center gap-2">
            <span class="text-sm font-semibold text-gray-700">Filtros aplicados:</span>

            <button
              v-if="selectedFilter !== 'all'"
              type="button"
              class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-orange-100 text-orange-800 text-xs font-semibold border border-orange-200 hover:bg-orange-200 transition"
              @click="removeActionFilter"
            >
              Ação: {{ getActionLabel(selectedFilter) }}
              <span aria-hidden="true">×</span>
            </button>

            <button
              v-if="selectedModel"
              type="button"
              class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-orange-100 text-orange-800 text-xs font-semibold border border-orange-200 hover:bg-orange-200 transition"
              @click="removeModelFilter"
            >
              Tipo: {{ getModelLabel(selectedModel) }}
              <span aria-hidden="true">×</span>
            </button>

            <button
              v-if="dateFrom"
              type="button"
              class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-orange-100 text-orange-800 text-xs font-semibold border border-orange-200 hover:bg-orange-200 transition"
              @click="removeDateFromFilter"
            >
              De: {{ formatInputDate(dateFrom) }}
              <span aria-hidden="true">×</span>
            </button>

            <button
              v-if="dateTo"
              type="button"
              class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-orange-100 text-orange-800 text-xs font-semibold border border-orange-200 hover:bg-orange-200 transition"
              @click="removeDateToFilter"
            >
              Até: {{ formatInputDate(dateTo) }}
              <span aria-hidden="true">×</span>
            </button>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            <!-- Action Filter -->
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Ação</label>
              <select
                v-model="selectedFilter"
                class="w-full px-4 py-2 border-2 border-amber-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
              >
                <option value="all">Todas as ações</option>
                <option value="create">Criado</option>
                <option value="update">Atualizado</option>
                <option value="delete">Deletado</option>
                <option value="restore">Restaurado</option>
                <option value="login">Login realizado</option>
                <option value="login_failed">Falha no login</option>
              </select>
            </div>

            <!-- Model Filter -->
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Tipo de Registro</label>
              <select
                v-model="selectedModel"
                class="w-full px-4 py-2 border-2 border-amber-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
              >
                <option value="">Todos</option>
                <option value="App\Models\Collaborator">Colaboradores</option>
                <option value="App\Models\FinancialRecord">Registros Financeiros</option>
                <option value="App\Models\Commission">Comissões</option>
                <option value="App\Models\WorkShift">Turnos de Trabalho</option>
                <option value="App\Models\ServiceType">Tipos de Serviço</option>
                <option value="App\Models\EntryType">Tipos de Entrada</option>
                <option value="App\Models\ExitType">Tipos de Saída</option>
                <option value="App\Models\DailyClosure">Fechamentos Diários</option>
                <option value="App\Models\Balance">Saldos</option>
                <option value="App\Models\User">Usuários</option>
              </select>
            </div>

            <!-- Date From -->
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">De</label>
              <input
                v-model="dateFrom"
                type="date"
                class="w-full h-11 px-4 py-2 border-2 border-amber-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
              />
            </div>

            <!-- Date To -->
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Até</label>
              <input
                v-model="dateTo"
                type="date"
                class="w-full h-11 px-4 py-2 border-2 border-amber-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
              />
            </div>
          </div>
        </div>

        <!-- Logs Table/List -->
        <div class="bg-white rounded-xl shadow-sm border border-orange-200 overflow-hidden">
          <div v-if="loading" class="p-8 text-center">
            <LoadingSpinner message="Carregando histórico..." />
          </div>

          <div v-else-if="logs.length === 0" class="p-8 text-center">
            <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <p class="text-gray-600 text-lg">Nenhum registro encontrado com os filtros selecionados</p>
          </div>

          <div v-else class="divide-y divide-orange-100">
            <div
              v-for="log in logs"
              :key="log.id"
              class="hover:bg-orange-50 transition cursor-pointer"
              @click="toggleDetails(log.id)"
            >
              <!-- Log Row -->
              <div class="px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-4 flex-1">
                  <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1 flex-wrap">
                      <span :class="['px-3 py-1 rounded-full text-xs font-bold', getActionBadgeClass(log.action)]">
                        {{ log.action_label || getActionLabel(log.action) }}
                      </span>
                      <span class="font-semibold text-gray-900">{{ getDisplayModelLabel(log) }}</span>
                      <span v-if="(log.changed_fields_count || 0) > 0" class="px-2 py-1 text-xs rounded-full bg-slate-100 text-slate-700 font-semibold">
                        {{ log.changed_fields_count }} campo(s)
                      </span>
                    </div>
                    <div class="text-sm text-gray-600">
                      Por <span class="font-semibold">{{ log.user?.name || 'Sistema' }}</span>
                      • {{ formatDate(log.created_at) }}
                    </div>
                  </div>
                </div>
                <div class="text-gray-400">
                  {{ isExpanded(log.id) ? '▲' : '▼' }}
                </div>
              </div>

              <!-- Expanded Details -->
              <transition
                enter-active-class="transition duration-200"
                leave-active-class="transition duration-150"
                enter-from-class="opacity-0 max-h-0"
                enter-to-class="opacity-100 max-h-96"
                leave-from-class="opacity-100 max-h-96"
                leave-to-class="opacity-0 max-h-0"
              >
                <div v-if="isExpanded(log.id)" class="bg-orange-50 px-6 py-4 border-t border-orange-100">
                  <div class="space-y-4">
                    <!-- Change Details -->
                    <div v-if="getVisibleDetails(log).length > 0" class="bg-white rounded-lg p-4 border border-orange-100">
                      <h4 class="font-bold text-gray-900 mb-3">Alterações</h4>
                      <div class="space-y-3">
                        <div
                          v-for="detail in getVisibleDetails(log)"
                          :key="detail.field"
                          :class="[
                            'grid grid-cols-1 gap-2 text-sm',
                            getDetailDisplayMode(log) === 'both' ? 'md:grid-cols-3' : 'md:grid-cols-2',
                          ]"
                        >
                          <span class="font-semibold text-gray-700">{{ getFieldLabel(log.model, detail.field) }}</span>
                          <div
                            v-if="getDetailDisplayMode(log) !== 'new-only'"
                            :class="[
                              'text-red-700 bg-red-50 px-2 py-1 rounded border border-red-100',
                              isComplexValue(detail.old, detail.field) ? 'whitespace-pre-wrap break-words font-mono text-xs leading-5' : '',
                            ]"
                          >
                            {{ formatValue(detail.old, String(detail.field)) }}
                          </div>
                          <div
                            v-if="getDetailDisplayMode(log) !== 'old-only'"
                            :class="[
                              'text-green-700 bg-green-50 px-2 py-1 rounded border border-green-100',
                              isComplexValue(detail.new, detail.field) ? 'whitespace-pre-wrap break-words font-mono text-xs leading-5' : '',
                            ]"
                          >
                            {{ formatValue(detail.new, String(detail.field)) }}
                          </div>
                        </div>
                      </div>
                    </div>

                    <div v-if="!hasMeaningfulDetails(log)" class="bg-white rounded-lg p-4 border border-orange-100 text-sm text-gray-600">
                      Este evento foi registrado sem mudanças detalhadas de campos.
                    </div>
                  </div>
                </div>
              </transition>
            </div>
          </div>

          <!-- Pagination -->
          <div v-if="totalPages > 1" class="px-6 py-4 border-t border-orange-100 flex items-center justify-between bg-orange-50">
            <p class="text-sm text-gray-600">
              Mostrando {{ (currentPage - 1) * perPage + 1 }} a {{ Math.min(currentPage * perPage, totalRecords) }} de {{ totalRecords }} registros
            </p>
            <div class="flex items-center gap-2">
              <button
                v-if="currentPage > 1"
                @click="fetchLogs(currentPage - 1)"
                class="px-4 py-2 rounded-lg border-2 border-orange-200 text-orange-700 hover:bg-orange-100 transition font-semibold"
              >
                ← Anterior
              </button>
              <span class="text-sm text-gray-600">Página {{ currentPage }} de {{ totalPages }}</span>
              <button
                v-if="currentPage < totalPages"
                @click="fetchLogs(currentPage + 1)"
                class="px-4 py-2 rounded-lg bg-orange-500 text-white hover:bg-orange-600 transition font-semibold"
              >
                Próxima →
              </button>
            </div>
          </div>
        </div>
      </main>
      
      <Footer />
    </div>
  </div>
</template>
