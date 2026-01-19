<script setup lang="ts">
import { Eye, EyeOff } from 'lucide-vue-next';
import { usePrivacy } from '@/composables/usePrivacy';
import { Button } from '@/components/ui/button';

const { isPrivacyMode, togglePrivacy } = usePrivacy();
</script>

<template>
    <Button
        @click="togglePrivacy"
        variant="ghost"
        class="w-full justify-start gap-3 px-3 py-2 h-auto hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
    >
        <EyeOff v-if="isPrivacyMode" class="h-4 w-4 text-orange-500 shrink-0" />
        <Eye v-else class="h-4 w-4 text-muted-foreground shrink-0" />
        <div class="flex flex-col items-start text-left flex-1 min-w-0">
            <span class="text-sm font-medium">Modo Privacidade</span>
            <span class="text-xs text-muted-foreground">
                {{ isPrivacyMode ? 'Valores ocultos' : 'Valores visíveis' }}
            </span>
        </div>
    </Button>
</template>
