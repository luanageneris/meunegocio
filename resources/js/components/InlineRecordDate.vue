<script setup lang="ts">
import { ref } from 'vue';
import InlineRecordEditor from '@/components/InlineRecordEditor.vue';
import DateInput from '@/components/DateInput.vue';

const props = defineProps<{
    value: string;
    text: string;
    save: (value: string, expectedValue: string) => Promise<void>;
}>();

const editing = ref(false);
const saving = ref(false);
const selected = ref('');
const error = ref('');
let initialValue = '';

function startEditing() {
    initialValue = props.value;
    selected.value = initialValue;
    error.value = '';
    editing.value = true;
}

async function submit() {
    if (saving.value) return;
    if (!selected.value) {
        error.value = 'Informe uma data válida.';
        return;
    }
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
        error.value = reason instanceof Error ? reason.message : 'Não foi possível salvar a data.';
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <InlineRecordEditor v-if="editing" label="data do movimento" :loading="false" :saving="saving" :disabled="false" :error="error" :ignore-outside="['[data-record-date-calendar]']" @save="submit" @cancel="editing = false">
        <DateInput v-model="selected" auto-open inline-editor aria-label="Selecionar data do movimento" :disabled="saving" class="app-select-control min-w-0 w-36 text-sm text-gray-800 dark:text-slate-100" />
    </InlineRecordEditor>
    <button v-else type="button" class="font-medium decoration-orange-500 underline-offset-4 decoration-dotted hover:underline focus-visible:underline focus-visible:outline focus-visible:outline-orange-500 cursor-pointer" aria-label="Editar data do movimento" title="Editar data do movimento" @click.stop="startEditing">{{ text }}</button>
</template>
