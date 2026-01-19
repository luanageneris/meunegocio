<script setup lang="ts">
const props = withDefaults(defineProps<{
    containerClass?: string;
    titleClass?: string;
}>(), {
    containerClass: 'max-w-7xl',
    titleClass: '',
});
</script>

<template>
    <header class="bg-gradient-to-r from-orange-400 to-amber-500 shadow-lg">
        <div :class="`${containerClass} mx-auto px-4 sm:px-3 lg:px-8 py-6`">
            <h1 :class="['text-2xl font-bold text-white text-center', titleClass]">Meu Negócio</h1>
        </div>
    </header>
</template>
