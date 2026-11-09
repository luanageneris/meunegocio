<script setup lang="ts">
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

defineProps<{ registeredAt: string }>();
</script>

<template>
    <TooltipProvider :delay-duration="200">
        <Tooltip>
            <TooltipTrigger as-child>
                <span class="inline-block" tabindex="0"><slot /></span>
            </TooltipTrigger>
            <TooltipContent>Registrado: {{ registeredAt }}</TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
