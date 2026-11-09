<script setup lang="ts">
import { nextTick, ref } from 'vue';
import InlineRecordEditor from '@/components/InlineRecordEditor.vue';

const props = defineProps<{
    value: string | null;
    save: (value: string | null, expectedValue: string | null) => Promise<void>;
}>();

const editing = ref(false);
const saving = ref(false);
const text = ref('');
const error = ref('');
const input = ref<HTMLInputElement | null>(null);
let initialValue: string | null = null;

async function startEditing() {
    initialValue = props.value;
    text.value = initialValue ?? '';
    error.value = '';
    editing.value = true;
    await nextTick();
    input.value?.focus();
}

async function submit() {
    if (saving.value) return;
    const value = text.value.trim() || null;
    if (value === initialValue) {
        editing.value = false;
        return;
    }
    saving.value = true;
    error.value = '';
    try {
        await props.save(value, initialValue);
        editing.value = false;
    } catch (reason) {
        error.value = reason instanceof Error ? reason.message : 'Não foi possível salvar a descrição.';
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <InlineRecordEditor v-if="editing" label="descrição" :loading="false" :saving="saving" :disabled="false" :error="error" @save="submit" @cancel="editing = false">
        <input ref="input" v-model="text" type="text" maxlength="500" aria-label="Editar texto da descrição" :disabled="saving" class="min-w-0 w-48 rounded border border-orange-400 bg-white px-2 py-1 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-orange-400 dark:bg-slate-950 dark:text-slate-100" />
    </InlineRecordEditor>
    <button v-else type="button" class="max-w-full truncate text-left text-gray-600 decoration-orange-500 underline-offset-4 decoration-dotted hover:underline focus-visible:underline focus-visible:outline focus-visible:outline-orange-500 dark:text-slate-200 cursor-pointer" aria-label="Editar descrição" title="Editar descrição" @click.stop="startEditing">{{ value || '-' }}</button>
</template>
