<script setup lang="ts">
import { nextTick, ref } from 'vue';
import { Check, Info, X } from 'lucide-vue-next';
import { onClickOutside } from '@vueuse/core';
import { calculateAmountCents } from '@/utils/recordAmountExpression';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

const props = defineProps<{
    amount: number;
    type: 'entrada' | 'saida';
    isCommission?: boolean;
    save: (totalCents: number, expectedTotalCents: number) => Promise<void>;
}>();

const editing = ref(false);
const saving = ref(false);
const expression = ref('');
const error = ref('');
const input = ref<HTMLInputElement | null>(null);
const editor = ref<HTMLElement | null>(null);
let initialCents = 0;

onClickOutside(editor, () => {
    if (editing.value && !saving.value) void submit();
}, { ignore: ['[data-slot="tooltip-content"]'] });

async function startEditing() {
    initialCents = Math.round(props.amount * 100);
    expression.value = (initialCents / 100).toFixed(2).replace('.', ',');
    error.value = '';
    editing.value = true;
    await nextTick();
    input.value?.select();
}

async function submit() {
    if (saving.value) return;
    error.value = '';
    try {
        const total = calculateAmountCents(expression.value, initialCents);
        if (total === initialCents) {
            editing.value = false;
            return;
        }
        saving.value = true;
        await props.save(total, initialCents);
        editing.value = false;
    } catch (reason) {
        error.value = reason instanceof Error ? reason.message : 'Não foi possível salvar o valor.';
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div class="relative inline-block">
        <button type="button" :class="['cursor-pointer whitespace-nowrap font-bold underline-offset-4 decoration-dotted decoration-primary hover:underline focus-visible:underline focus-visible:outline focus-visible:outline-orange-500', editing ? 'invisible' : '', isCommission ? 'text-blue-600 dark:text-blue-400' : type === 'entrada' ? 'text-green-600 dark:text-green-400' : 'text-orange-500']" title="Editar valor" aria-label="Editar valor" @click.stop="startEditing">
            <span class="text-[1.25em] leading-none">{{ type === 'entrada' ? '+' : '−' }}</span> {{ amount.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) }}
        </button>
        <div v-if="editing" ref="editor" class="absolute left-0 top-full z-30 mt-1 w-max max-w-[calc(100vw-2rem)] rounded-lg border border-gray-200 bg-white p-2 shadow-lg dark:border-slate-700 dark:bg-slate-900" @click.stop @keydown.stop @keydown.esc.prevent="!saving && (editing = false)">
            <form class="flex max-w-full items-center gap-1" @submit.prevent="submit">
                <input ref="input" v-model="expression" type="text" maxlength="100" aria-label="Novo valor ou operação" :disabled="saving" class="app-select-control w-24 min-w-0 text-sm text-gray-800 dark:text-slate-100" />
                <TooltipProvider :delay-duration="0">
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <button type="button" class="rounded p-1 text-gray-500 hover:text-orange-500 cursor-pointer" aria-label="Ajuda sobre cálculos">
                                <Info class="h-4 w-4" />
                            </button>
                        </TooltipTrigger>
                        <TooltipContent>Use +, −, * ou / para calcular.</TooltipContent>
                    </Tooltip>
                </TooltipProvider>
                <button type="submit" :disabled="saving" class="rounded p-1 text-orange-600 hover:bg-orange-100 disabled:opacity-50 cursor-pointer disabled:cursor-not-allowed" aria-label="Salvar valor" title="Salvar valor">
                    <Check class="h-4 w-4" />
                </button>
                <button type="button" :disabled="saving" class="rounded p-1 text-gray-500 hover:bg-gray-100 disabled:opacity-50 cursor-pointer disabled:cursor-not-allowed" aria-label="Cancelar edição do valor" title="Cancelar" @click="editing = false">
                    <X class="h-4 w-4" />
                </button>
            </form>
            <p v-if="saving" class="mt-1 text-xs text-gray-500">Salvando...</p>
            <p v-if="error" role="alert" class="mt-1 break-words text-xs text-red-600">{{ error }}</p>
        </div>
    </div>
</template>
