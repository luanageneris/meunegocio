<script setup lang="ts">
import { computed } from 'vue';
import { usePrivacy } from '@/composables/usePrivacy';

const props = withDefaults(
    defineProps<{
        /** O valor a ser exibido */
        value: string | number;
        /** Classe CSS adicional */
        class?: string;
        /** Mostra como moeda (formata automaticamente) */
        currency?: boolean;
    }>(),
    {
        class: '',
        currency: false,
    }
);

const { isPrivacyMode } = usePrivacy();

const displayValue = computed(() => {
    if (isPrivacyMode.value) {
        return props.currency ? 'R$ •••••' : '•••••';
    }

    if (props.currency && typeof props.value === 'number') {
        return props.value.toLocaleString('pt-BR', {
            style: 'currency',
            currency: 'BRL',
        });
    }

    return String(props.value);
});
</script>

<template>
    <span :class="[$props.class, { 'select-none': isPrivacyMode }]">
        {{ displayValue }}
    </span>
</template>
