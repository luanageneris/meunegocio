<script setup lang="ts">
import { Info } from 'lucide-vue-next';
import { ref } from 'vue';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

defineProps<{ total: string; showFormula: boolean }>();
const formulaOpen = ref(false);
</script>

<template>
    <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-orange-200 bg-orange-50 px-3 py-2 dark:border-orange-500/30 dark:bg-orange-950/30">
        <div class="flex items-center gap-2">
            <span class="text-xs font-medium text-gray-600 dark:text-slate-300">Valor total</span>
            <TooltipProvider v-if="showFormula" :delay-duration="0">
                <Tooltip v-model:open="formulaOpen">
                    <TooltipTrigger as-child>
                        <button type="button" class="cursor-pointer rounded-full text-orange-600 focus-visible:outline focus-visible:outline-orange-500 dark:text-orange-300" aria-label="Mostrar fórmula do cálculo" @click="formulaOpen = !formulaOpen" @keydown.enter.stop>
                            <Info class="h-4 w-4" />
                        </button>
                    </TooltipTrigger>
                    <TooltipContent>Cálculo: valor x quantidade - desconto</TooltipContent>
                </Tooltip>
            </TooltipProvider>
        </div>
        <span class="text-base font-semibold text-orange-700 dark:text-orange-300">{{ total }}</span>
    </div>
</template>
