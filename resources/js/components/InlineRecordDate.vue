<script setup lang="ts">
import { nextTick, ref } from 'vue';
import InlineRecordEditor from '@/components/InlineRecordEditor.vue';

const props = defineProps<{
    value: string;
    text: string;
    save: (value: string, expectedValue: string) => Promise<void>;
}>();

const editing = ref(false);
const saving = ref(false);
const selected = ref('');
const error = ref('');
const input = ref<HTMLInputElement | null>(null);
let initialValue = '';

async function startEditing() {
    initialValue = props.value;
    selected.value = initialValue;
    error.value = '';
    editing.value = true;
    await nextTick();
    input.value?.focus();
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
    <InlineRecordEditor v-if="editing" label="data do movimento" :loading="false" :saving="saving" :disabled="false" :error="error" @save="submit" @cancel="editing = false">
        <input ref="input" v-model="selected" type="date" aria-label="Selecionar data do movimento" :disabled="saving" class="min-w-0 w-36 rounded border border-orange-400 bg-white px-2 py-1 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-orange-400 dark:bg-slate-950 dark:text-slate-100" />
    </InlineRecordEditor>
    <button v-else type="button" class="font-medium decoration-orange-500 underline-offset-4 decoration-dotted hover:underline focus-visible:underline focus-visible:outline focus-visible:outline-orange-500 cursor-pointer" aria-label="Editar data do movimento" title="Editar data do movimento" @click.stop="startEditing">{{ text }}</button>
</template>
