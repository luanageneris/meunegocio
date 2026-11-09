<script setup lang="ts">
import { nextTick, ref } from 'vue';
import { Check, Info, X } from 'lucide-vue-next';
import { onClickOutside } from '@vueuse/core';
import { calculateAmountCents } from '@/utils/recordAmountExpression';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

const props = defineProps<{
    amount: number;
    type: 'entrada' | 'saida';
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
    if (editing.value && !saving.value) editing.value = false;
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
    <div v-if="editing" ref="editor" class="min-w-0" @click.stop @keydown.stop @keydown.esc.prevent="!saving && (editing = false)">
        <form class="flex w-max items-center gap-1" @submit.prevent="submit">
            <input ref="input" v-model="expression" type="text" maxlength="100" aria-label="Novo valor ou operação" :disabled="saving" class="w-24 rounded border border-orange-400 bg-white px-2 py-1 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-orange-400 dark:bg-slate-950 dark:text-slate-100" />
            <TooltipProvider :delay-duration="0">
                <Tooltip>
                    <TooltipTrigger as-child>
                        <button type="button" class="rounded p-1 text-gray-500 hover:text-orange-500" aria-label="Ajuda sobre cálculos">
                            <Info class="h-4 w-4" />
                        </button>
                    </TooltipTrigger>
                    <TooltipContent>Use +, −, * ou / para calcular.</TooltipContent>
                </Tooltip>
            </TooltipProvider>
            <button type="submit" :disabled="saving" class="rounded p-1 text-orange-600 hover:bg-orange-100 disabled:opacity-50" aria-label="Salvar valor" title="Salvar valor">
                <Check class="h-4 w-4" />
            </button>
            <button type="button" :disabled="saving" class="rounded p-1 text-gray-500 hover:bg-gray-100 disabled:opacity-50" aria-label="Cancelar edição do valor" title="Cancelar" @click="editing = false">
                <X class="h-4 w-4" />
            </button>
        </form>
        <p v-if="saving" class="mt-1 text-xs text-gray-500">Salvando...</p>
        <p v-if="error" role="alert" class="mt-1 text-xs text-red-600">{{ error }}</p>
    </div>
    <button v-else type="button" :class="['font-bold underline-offset-4 decoration-dotted hover:underline focus-visible:underline focus-visible:outline focus-visible:outline-orange-500', type === 'entrada' ? 'text-yellow-500 decoration-orange-500' : 'text-orange-500 decoration-orange-500']" title="Editar valor" aria-label="Editar valor" @click.stop="startEditing">
        {{ type === 'entrada' ? '+' : '−' }} {{ amount.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) }}
    </button>
</template>
