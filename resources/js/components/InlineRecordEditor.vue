<script setup lang="ts">
import { ref } from 'vue';
import { onClickOutside } from '@vueuse/core';
import { Check, X } from 'lucide-vue-next';

const props = withDefaults(defineProps<{ label: string; loading: boolean; saving: boolean; disabled: boolean; error: string; showLoadingMessage?: boolean; showActions?: boolean; autoSaveOutside?: boolean; allowCancel?: boolean; ignoreOutside?: string[] }>(), {
    showLoadingMessage: true,
    showActions: true,
    autoSaveOutside: true,
    allowCancel: true,
    ignoreOutside: () => [],
});
const emit = defineEmits<{ save: []; cancel: []; outside: [] }>();
const editor = ref<HTMLElement | null>(null);

function cancel() {
    if (!props.saving && props.allowCancel) emit('cancel');
}

function submit() {
    if (props.showActions) emit('save');
}

function applyOutside() {
    if (props.saving || props.disabled) return;
    if (!props.autoSaveOutside) {
        emit('outside');
        return;
    }
    if (props.loading) {
        cancel();
        return;
    }
    emit('save');
}

defineExpose({
    contains: (target: EventTarget | null) => target instanceof Node && !!editor.value?.contains(target),
    applyOutside,
});

onClickOutside(editor, applyOutside, { ignore: props.ignoreOutside });
</script>

<template>
    <div ref="editor" class="min-w-0" @click.stop @keydown.stop @keydown.esc.prevent="cancel">
        <form
            :class="['flex max-w-full items-center gap-1', showActions ? 'w-max' : 'w-full']"
            @submit.prevent="submit"
        >
            <slot />
            <button v-if="showActions" type="submit" :disabled="loading || saving || disabled" class="shrink-0 rounded p-1 text-orange-600 hover:bg-orange-100 disabled:opacity-50 cursor-pointer disabled:cursor-not-allowed" :aria-label="`Salvar ${label}`" title="Salvar">
                <Check class="h-4 w-4" />
            </button>
            <button v-if="showActions" type="button" :disabled="saving" class="shrink-0 rounded p-1 text-gray-500 hover:bg-gray-100 disabled:opacity-50 cursor-pointer disabled:cursor-not-allowed" :aria-label="`Cancelar edição de ${label}`" title="Cancelar" @click="cancel">
                <X class="h-4 w-4" />
            </button>
        </form>
        <p v-if="(loading && showLoadingMessage) || saving" class="mt-1 text-xs text-gray-500">{{ loading ? 'Carregando...' : 'Salvando...' }}</p>
        <p v-if="error" role="alert" class="mt-1 break-words text-xs text-red-600">{{ error }}</p>
    </div>
</template>
