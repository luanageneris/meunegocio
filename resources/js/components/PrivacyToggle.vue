<script setup lang="ts">
import { Eye, EyeOff } from 'lucide-vue-next';
import { usePrivacy } from '@/composables/usePrivacy';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

const { isPrivacyMode, togglePrivacy } = usePrivacy();
</script>

<template>
    <TooltipProvider>
        <Tooltip>
            <TooltipTrigger as-child>
                <Button
                    variant="ghost"
                    size="icon"
                    @click="togglePrivacy"
                    :aria-label="isPrivacyMode ? 'Mostrar valores' : 'Ocultar valores'"
                    class="h-8 w-8"
                >
                    <EyeOff v-if="isPrivacyMode" class="h-4 w-4 text-orange-500" />
                    <Eye v-else class="h-4 w-4 text-muted-foreground" />
                </Button>
            </TooltipTrigger>
            <TooltipContent>
                <p>{{ isPrivacyMode ? 'Mostrar valores' : 'Ocultar valores sensíveis' }}</p>
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
