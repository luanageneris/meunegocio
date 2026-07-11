<script setup lang="ts">
interface Props {
    title: string;
    subtitle?: string;
}

defineProps<Props>();
</script>

<template>
    <div class="bg-gradient-to-r from-orange-400 to-amber-500 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-5">
            <div class="lg:ml-0">
                <h1 class="text-2xl sm:text-3xl font-bold text-white mb-1">{{ title }}</h1>
                <p v-if="subtitle" class="text-orange-100 text-xs sm:text-sm font-medium leading-snug">{{ subtitle }}</p>
            </div>
        </div>
    </div>
</template>
