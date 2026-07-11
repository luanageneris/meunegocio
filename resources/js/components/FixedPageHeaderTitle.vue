
<script setup lang="ts">
import PageHeaderTitle from '@/components/PageHeaderTitle.vue';

interface Props {
    title: string;
    subtitle?: string;
    spacerClass?: string;
}

withDefaults(defineProps<Props>(), {
    spacerClass: 'h-[88px] sm:h-[100px]',
});
</script>

<template>
    <div class="fixed top-0 left-0 right-0 lg:left-64 z-40 bg-gradient-to-br from-amber-50 to-orange-100">
        <PageHeaderTitle :title="title" :subtitle="subtitle" />
    </div>
    <div :class="spacerClass"></div>
</template>