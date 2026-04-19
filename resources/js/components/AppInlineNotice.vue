<script setup lang="ts">
import { computed } from 'vue';
import { CheckCircle, AlertCircle, AlertTriangle, Info, X } from 'lucide-vue-next';

interface Props {
    title?: string;
    message: string;
    variant?: 'success' | 'error' | 'warning' | 'info';
    dismissible?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    title: '',
    variant: 'success',
    dismissible: true,
});

const emit = defineEmits<{
    dismiss: [];
}>();

const variantClasses = computed(() => {
    if (props.variant === 'error') {
        return {
            wrapper: 'border-red-300 bg-red-50',
            icon: 'text-red-600',
            title: 'text-red-800',
            message: 'text-red-700',
            close: 'text-red-700 hover:bg-red-100',
        };
    }

    if (props.variant === 'warning') {
        return {
            wrapper: 'border-amber-300 bg-amber-50',
            icon: 'text-amber-600',
            title: 'text-amber-800',
            message: 'text-amber-700',
            close: 'text-amber-700 hover:bg-amber-100',
        };
    }

    if (props.variant === 'info') {
        return {
            wrapper: 'border-sky-300 bg-sky-50',
            icon: 'text-sky-600',
            title: 'text-sky-800',
            message: 'text-sky-700',
            close: 'text-sky-700 hover:bg-sky-100',
        };
    }

    return {
        wrapper: 'border-orange-300 bg-orange-50',
        icon: 'text-orange-600',
        title: 'text-orange-800',
        message: 'text-orange-700',
        close: 'text-orange-700 hover:bg-orange-100',
    };
});

const iconComponent = computed(() => {
    if (props.variant === 'error') return AlertCircle;
    if (props.variant === 'warning') return AlertTriangle;
    if (props.variant === 'info') return Info;
    return CheckCircle;
});

const defaultTitle = computed(() => {
    if (props.variant === 'error') return 'Atenção';
    if (props.variant === 'warning') return 'Aviso';
    if (props.variant === 'info') return 'Informação';
    return 'Sucesso';
});
</script>

<template>
    <div :class="['rounded-lg border px-4 py-3', variantClasses.wrapper]">
        <div class="flex items-start justify-between gap-3">
            <div class="flex items-start gap-3">
                <component :is="iconComponent" :class="['mt-0.5 h-5 w-5 flex-shrink-0', variantClasses.icon]" />
                <div>
                    <p :class="['text-sm font-semibold', variantClasses.title]">{{ title || defaultTitle }}</p>
                    <p :class="['text-sm', variantClasses.message]">{{ message }}</p>
                </div>
            </div>
            <button
                v-if="dismissible"
                type="button"
                :class="['rounded p-1 transition-colors', variantClasses.close]"
                aria-label="Fechar mensagem"
                @click="emit('dismiss')"
            >
                <X class="h-4 w-4" />
            </button>
        </div>
    </div>
</template>
