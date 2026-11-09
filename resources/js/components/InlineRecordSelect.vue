<script setup lang="ts">
import InlineRecordEditor from '@/components/InlineRecordEditor.vue';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

type Option = { id: number; name: string };
type ServiceDetails = {
    unit: string;
    service_value: number;
    commission_percentage: number;
};

const props = defineProps<{
    label: string;
    value?: number | null;
    text?: string;
    modelValue?: number | null;
    mode?: 'inline' | 'form';
    loadOptions: () => Promise<Option[]>;
    save?: (value: number | null, expectedValue: number | null) => Promise<void>;
    createOption: (
        name: string,
        cpf?: string,
        serviceDetails?: ServiceDetails,
    ) => Promise<Option>;
    requiresCpf?: boolean;
    requiresServiceDetails?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: number | null];
}>();

const isFormMode = computed(() => props.mode === 'form');
const editing = ref(isFormMode.value);
const loading = ref(false);
const saving = ref(false);
const loadFailed = ref(false);
const error = ref('');
const selected = ref<number | null>(
    isFormMode.value ? (props.modelValue ?? null) : null,
);
const options = ref<Option[]>([]);
const open = ref(false);
const query = ref('');
const cpf = ref('');
const askingForCpf = ref(false);
const serviceUnit = ref('');
const serviceValue = ref('');
const commissionPercentage = ref('');
const askingForServiceDetails = ref(false);
const input = ref<HTMLInputElement | null>(null);
const cpfInput = ref<HTMLInputElement | null>(null);
const serviceUnitInput = ref<HTMLInputElement | null>(null);
const filteredOptions = computed(() => {
    const search = query.value.trim().toLocaleLowerCase('pt-BR');
    return search
        ? options.value.filter((option) =>
              option.name.toLocaleLowerCase('pt-BR').includes(search),
          )
        : options.value;
});
let initialValue: number | null = null;
let requestId = 0;

function cancel() {
    if (isFormMode.value) return;
    if (saving.value) return;
    requestId++;
    open.value = false;
    askingForCpf.value = false;
    askingForServiceDetails.value = false;
    editing.value = false;
}

async function startEditing() {
    initialValue = props.value ?? null;
    selected.value = initialValue;
    query.value = props.text ?? '';
    cpf.value = '';
    askingForCpf.value = false;
    askingForServiceDetails.value = false;
    options.value = [];
    open.value = false;
    error.value = '';
    loadFailed.value = false;
    editing.value = true;
    loading.value = true;
    const currentRequest = ++requestId;
    try {
        const loaded = await props.loadOptions();
        if (currentRequest !== requestId) return;
        options.value = loaded;
        if (
            initialValue !== null &&
            !loaded.some((option) => option.id === initialValue)
        ) {
            options.value = [
                {
                    id: initialValue,
                    name: `${props.text || 'Opção anterior'} (indisponível)`,
                },
                ...loaded,
            ];
        }
        loading.value = false;
        open.value = true;
        await nextTick();
        if (currentRequest === requestId) input.value?.focus();
    } catch (reason) {
        if (currentRequest !== requestId) return;
        error.value =
            reason instanceof Error
                ? reason.message
                : 'Não foi possível carregar as opções.';
        loadFailed.value = true;
    } finally {
        if (currentRequest === requestId) loading.value = false;
    }
}

async function loadFormOptions() {
    loading.value = true;
    loadFailed.value = false;
    error.value = '';
    try {
        options.value = await props.loadOptions();
        selected.value = props.modelValue ?? null;
        query.value =
            options.value.find((option) => option.id === selected.value)
                ?.name ?? '';
    } catch (reason) {
        error.value =
            reason instanceof Error
                ? reason.message
                : 'Não foi possível carregar as opções.';
        loadFailed.value = true;
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    if (isFormMode.value) void loadFormOptions();
});

watch(
    () => props.modelValue,
    (value) => {
        if (!isFormMode.value || value === selected.value) return;
        selected.value = value ?? null;
        query.value =
            options.value.find((option) => option.id === selected.value)
                ?.name ?? '';
    },
);

function selectOption(option: Option) {
    selected.value = option.id;
    query.value = option.name;
    if (isFormMode.value) emit('update:modelValue', option.id);
    askingForCpf.value = false;
    cpf.value = '';
    askingForServiceDetails.value = false;
    open.value = false;
}

function clearSelection() {
    selected.value = null;
    query.value = '';
    if (isFormMode.value) emit('update:modelValue', null);
    askingForCpf.value = false;
    askingForServiceDetails.value = false;
    cpf.value = '';
    open.value = false;
}

function handleQueryInput() {
    const normalizedQuery = query.value.trim().toLocaleLowerCase('pt-BR');
    selected.value =
        options.value.find(
            (option) =>
                option.name.toLocaleLowerCase('pt-BR') === normalizedQuery,
        )?.id ?? null;
    askingForCpf.value = false;
    askingForServiceDetails.value = false;
    cpf.value = '';
    open.value = true;
    error.value = '';
}

async function createAndSubmit() {
    const name = query.value.trim();
    if (!name || saving.value || loading.value || loadFailed.value) return;

    if (props.requiresCpf && !askingForCpf.value) {
        askingForCpf.value = true;
        open.value = false;
        await nextTick();
        cpfInput.value?.focus();
        return;
    }

    if (props.requiresServiceDetails && !askingForServiceDetails.value) {
        askingForServiceDetails.value = true;
        open.value = false;
        await nextTick();
        serviceUnitInput.value?.focus();
        return;
    }

    if (
        props.requiresCpf &&
        !/^\d{3}\.\d{3}\.\d{3}-\d{2}$/.test(cpf.value.trim())
    ) {
        error.value = 'Informe o CPF no formato 000.000.000-00.';
        return;
    }

    const parsedServiceValue = Number(serviceValue.value.replace(',', '.'));
    const parsedCommissionPercentage = Number(
        commissionPercentage.value.replace(',', '.'),
    );
    if (
        props.requiresServiceDetails &&
        (!serviceUnit.value.trim() ||
            serviceUnit.value.trim().length > 50 ||
            serviceValue.value.trim() === '' ||
            !Number.isFinite(parsedServiceValue) ||
            parsedServiceValue < 0 ||
            commissionPercentage.value.trim() === '' ||
            !Number.isFinite(parsedCommissionPercentage) ||
            parsedCommissionPercentage < 0 ||
            parsedCommissionPercentage > 100)
    ) {
        error.value =
            'Informe uma unidade, o valor do serviço e uma comissão entre 0 e 100%.';
        return;
    }

    saving.value = true;
    error.value = '';
    try {
        const option = await props.createOption(
            name,
            props.requiresCpf ? cpf.value.trim() : undefined,
            props.requiresServiceDetails
                ? {
                      unit: serviceUnit.value.trim(),
                      service_value: parsedServiceValue,
                      commission_percentage: parsedCommissionPercentage,
                  }
                : undefined,
        );
        options.value = [...options.value, option];
        selectOption(option);
        if (isFormMode.value) {
            return;
        }
        await props.save?.(option.id, initialValue);
        editing.value = false;
    } catch (reason) {
        error.value =
            reason instanceof Error
                ? reason.message
                : 'Não foi possível criar e vincular o item.';
    } finally {
        saving.value = false;
    }
}

async function acceptInput() {
    if (askingForCpf.value) {
        await createAndSubmit();
        return;
    }
    if (askingForServiceDetails.value) {
        await createAndSubmit();
        return;
    }

    if (!query.value.trim()) {
        clearSelection();
        await submit();
        return;
    }

    const firstOption = filteredOptions.value[0];
    if (firstOption) {
        selectOption(firstOption);
        if (!isFormMode.value) await submit();
    } else {
        await createAndSubmit();
    }
}

async function submit() {
    if (
        loading.value ||
        saving.value ||
        loadFailed.value ||
        askingForCpf.value ||
        !props.save ||
        (query.value.trim() && selected.value === null)
    )
        return;
    if (selected.value === initialValue) {
        editing.value = false;
        return;
    }
    saving.value = true;
    error.value = '';
    try {
        await props.save(selected.value, initialValue);
        editing.value = false;
    } catch (reason) {
        error.value =
            reason instanceof Error
                ? reason.message
                : 'Não foi possível salvar a alteração.';
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <InlineRecordEditor
        v-if="editing"
        :label="label"
        :loading="loading"
        :saving="saving"
        :disabled="
            loadFailed ||
            askingForCpf ||
            askingForServiceDetails ||
            (!isFormMode && query.trim() !== '' && selected === null)
        "
        :error="error"
        :show-loading-message="false"
        :show-actions="!isFormMode"
        :auto-save-outside="!isFormMode"
        :allow-cancel="!isFormMode"
        :ignore-outside="['[data-record-select-options]']"
        @save="submit"
        @cancel="cancel"
        @outside="open = false; askingForCpf = false; askingForServiceDetails = false"
    >
        <div :class="['relative min-w-0', isFormMode ? 'w-full' : 'w-36']">
            <input
                ref="input"
                v-model="query"
                type="text"
                :aria-label="`Buscar ${label}`"
                :placeholder="
                    loading
                        ? 'Carregando...'
                        : selected === null
                          ? 'Não selecionado'
                          : 'Digite para buscar'
                "
                :disabled="loading || saving || loadFailed"
                maxlength="255"
                autocomplete="off"
                :class="[
                    isFormMode ? 'app-form-control' : 'app-select-control',
                    'w-full min-w-0 disabled:opacity-60',
                ]"
                @focus="open = true"
                @input="handleQueryInput"
                @keydown.enter.prevent.stop="acceptInput"
            />
            <div
                v-if="open && !askingForCpf && !askingForServiceDetails"
                data-record-select-options
                class="app-select-menu absolute top-full left-0 z-[100] mt-1 max-h-60 w-max max-w-64 min-w-full overflow-auto p-1 text-sm"
            >
                <button
                    v-if="!query.trim()"
                    type="button"
                    class="app-select-option block w-full cursor-pointer px-2 py-2 text-left text-gray-500"
                    @click.prevent="clearSelection"
                >
                    Não selecionado
                </button>
                <p
                    v-if="options.length === 0 && !query.trim()"
                    class="px-2 py-2 text-xs text-gray-500"
                >
                    Nenhum item cadastrado. Digite um nome e pressione Enter
                    para criar.
                </p>
                <button
                    v-if="filteredOptions.length === 0 && query.trim()"
                    type="button"
                    class="app-select-option app-select-create block w-full cursor-pointer px-2 py-2 text-left"
                    @click.prevent="createAndSubmit"
                >
                    Criar "{{ query.trim() }}" e vincular ao registro
                </button>
                <button
                    v-for="option in filteredOptions"
                    :key="option.id"
                    type="button"
                    class="app-select-option select-option block w-full cursor-pointer px-2 py-2 text-left outline-none"
                    @click.prevent="selectOption(option)"
                >
                    {{ option.name }}
                </button>
            </div>
            <div
                v-if="askingForCpf"
                class="app-select-menu absolute top-full left-0 z-[100] mt-1 w-64 p-2 text-sm"
            >
                <p class="mb-2 text-xs text-gray-600">
                    Novo colaborador: informe o CPF para criar e vincular.
                </p>
                <input
                    ref="cpfInput"
                    v-model="cpf"
                    type="text"
                    inputmode="numeric"
                    maxlength="14"
                    placeholder="000.000.000-00"
                    aria-label="CPF do novo colaborador"
                    class="app-select-control w-full text-sm"
                    @keydown.enter.prevent.stop="acceptInput"
                />
            </div>
            <div
                v-if="askingForServiceDetails"
                class="app-select-menu absolute top-full left-0 z-[100] mt-1 w-80 max-w-[90vw] space-y-2 p-3 text-sm"
            >
                <p class="text-xs text-muted-foreground">
                    Informe os dados necessários para cadastrar este tipo de serviço.
                </p>
                <input
                    ref="serviceUnitInput"
                    v-model="serviceUnit"
                    type="text"
                    maxlength="50"
                    placeholder="Unidade (ex.: hora, unidade)"
                    aria-label="Unidade do serviço"
                    class="app-select-control w-full"
                />
                <input
                    v-model="serviceValue"
                    type="number"
                    min="0"
                    step="0.01"
                    placeholder="Valor do serviço"
                    aria-label="Valor do serviço"
                    class="app-select-control w-full"
                />
                <input
                    v-model="commissionPercentage"
                    type="number"
                    min="0"
                    max="100"
                    step="0.01"
                    placeholder="Comissão (%)"
                    aria-label="Percentual de comissão"
                    class="app-select-control w-full"
                    @keydown.enter.prevent.stop="acceptInput"
                />
                <p class="text-xs text-muted-foreground">
                    Pressione Enter para criar e vincular.
                </p>
            </div>
        </div>
        <span v-if="loading" class="sr-only" role="status"
            >Carregando opções...</span
        >
    </InlineRecordEditor>
    <button
        v-else
        type="button"
        class="max-w-full cursor-pointer truncate text-left text-gray-700 decoration-orange-500 decoration-dotted underline-offset-4 hover:underline focus-visible:underline focus-visible:outline focus-visible:outline-orange-500 dark:text-slate-200"
        :aria-label="`Editar ${label}`"
        :title="`Editar ${label}`"
        @click.stop="startEditing"
    >
        {{ text || '-' }}
    </button>
</template>

<style scoped>
.select-option:hover {
    background-color: var(--accent);
}
</style>
