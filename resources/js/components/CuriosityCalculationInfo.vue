<script setup lang="ts">
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { Info } from 'lucide-vue-next';

defineProps<{
    title: string;
    paragraphs: string[];
}>();
</script>

<template>
    <TooltipProvider :delay-duration="0">
        <Dialog>
            <Tooltip>
                <TooltipTrigger as-child>
                    <DialogTrigger as-child>
                        <button
                            type="button"
                            class="inline-flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-full text-orange-600 transition-colors hover:bg-orange-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary dark:text-orange-300 dark:hover:bg-slate-800"
                            :aria-label="`Como calculamos: ${title}`"
                        >
                            <Info class="h-4 w-4" aria-hidden="true" />
                        </button>
                    </DialogTrigger>
                </TooltipTrigger>
                <TooltipContent
                    >Clique para saber como calculamos</TooltipContent
                >
            </Tooltip>
            <DialogContent
                :show-close-button="false"
                class="max-h-[85dvh] overflow-y-auto border-gray-200 bg-white text-gray-800 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
            >
                <DialogHeader>
                    <DialogTitle class="pr-2">{{ title }}</DialogTitle>
                    <DialogDescription
                        class="text-gray-600 dark:text-slate-300"
                    >
                        Dados considerados e regras do cálculo
                    </DialogDescription>
                </DialogHeader>
                <div
                    class="space-y-3 text-sm leading-relaxed text-gray-700 dark:text-slate-300"
                >
                    <p v-for="paragraph in paragraphs" :key="paragraph">
                        {{ paragraph }}
                    </p>
                </div>
                <DialogFooter>
                    <DialogClose as-child>
                        <button
                            type="button"
                            class="cursor-pointer rounded-lg bg-primary px-4 py-2 font-semibold text-primary-foreground transition hover:opacity-90 active:scale-[0.98]"
                        >
                            Entendi
                        </button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </TooltipProvider>
</template>
