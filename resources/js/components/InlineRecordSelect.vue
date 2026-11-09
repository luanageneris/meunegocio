<script setup lang="ts">
import { nextTick, ref } from 'vue';
import InlineRecordEditor from '@/components/InlineRecordEditor.vue';

type Option = { id: number; name: string };

const props = defineProps<{
    label: string;
    value: number | null;
    text: string;
    loadOptions: () => Promise<Option[]>;
    save: (value: number | null, expectedValue: number | null) => Promise<void>;
}>();

const editing = ref(false);
const loading = ref(false);
const saving = ref(false);
const loadFailed = ref(false);
const error = ref('');
const selected = ref<number | null>(null);
const options = ref<Option[]>([]);
const select = ref<HTMLSelectElement | null>(null);
let initialValue: number | null = null;
let requestId = 0;

function cancel() {
    if (saving.value) return;
    requestId++;
    editing.value = false;
}

async function startEditing() {
    initialValue = props.value;
    selected.value = initialValue;
    options.value = [];
    error.value = '';
    loadFailed.value = false;
    editing.value = true;
    loading.value = true;
    const currentRequest = ++requestId;
    try {
        const loaded = await props.loadOptions();
        if (currentRequest !== requestId) return;
        options.value = loaded;
        if (initialValue !== null && !loaded.some((option) => option.id === initialValue)) {
            options.value = [{ id: initialValue, name: `${props.text || 'Opção anterior'} (indisponível)` }, ...loaded];
        }
        loading.value = false;
        await nextTick();
        if (currentRequest === requestId) select.value?.focus();
    } catch (reason) {
        if (currentRequest !== requestId) return;
        error.value = reason instanceof Error ? reason.message : 'Não foi possível carregar as opções.';
        loadFailed.value = true;
    } finally {
        if (currentRequest === requestId) loading.value = false;
    }
}

async function submit() {
    if (loading.value || saving.value || loadFailed.value) return;
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
        error.value = reason instanceof Error ? reason.message : 'Não foi possível salvar a alteração.';
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <InlineRecordEditor v-if="editing" :label="label" :loading="loading" :saving="saving" :disabled="loadFailed" :error="error" :show-loading-message="false" @save="submit" @cancel="cancel">
        <select ref="select" v-model="selected" :disabled="loading || saving || loadFailed" :aria-label="`Selecionar ${label}`" class="w-36 min-w-0 rounded border border-orange-400 bg-white px-2 py-1 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-orange-400 dark:bg-slate-950 dark:text-slate-100">
            <option v-if="loading && initialValue !== null" :value="initialValue">Carregando...</option>
            <option :value="null">{{ loading ? 'Carregando...' : 'Não selecionado' }}</option>
            <option v-for="option in options" :key="option.id" :value="option.id">{{ option.name }}</option>
        </select>
        <span v-if="loading" class="sr-only" role="status">Carregando opções...</span>
    </InlineRecordEditor>
    <button v-else type="button" class="max-w-full truncate text-left text-gray-700 decoration-orange-500 underline-offset-4 decoration-dotted hover:underline focus-visible:underline focus-visible:outline focus-visible:outline-orange-500 dark:text-slate-200 cursor-pointer" :aria-label="`Editar ${label}`" :title="`Editar ${label}`" @click.stop="startEditing">
        {{ text || '-' }}
    </button>
</template>
