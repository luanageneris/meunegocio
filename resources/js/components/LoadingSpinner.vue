<script setup lang="ts">
interface Props {
    message?: string;
    size?: 'sm' | 'md' | 'lg';
}

const props = withDefaults(defineProps<Props>(), {
    message: 'Carregando dados...',
    size: 'md',
});

const sizeClasses = {
    sm: 'h-8 w-8',
    md: 'h-12 w-12',
    lg: 'h-16 w-16',
};
</script>

<template>
    <div class="flex items-center justify-center min-h-96">
        <div class="text-center">
            <svg 
                :class="[sizeClasses[size], 'animate-spin text-orange-500 mx-auto mb-4']"
                xmlns="http://www.w3.org/2000/svg" 
                fill="none" 
                viewBox="0 0 24 24"
            >
                <circle 
                    class="opacity-25" 
                    cx="12" 
                    cy="12" 
                    r="10" 
                    stroke="currentColor" 
                    stroke-width="4"
                />
                <path 
                    class="opacity-75" 
                    fill="currentColor" 
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                />
            </svg>
            <p class="text-gray-600 dark:text-muted-foreground">{{ message }}</p>
        </div>
    </div>
</template>
