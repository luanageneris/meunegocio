<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const currentYear = new Date().getFullYear();
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />

            <footer
                class="mt-8 border-t border-gray-200 bg-white/80 backdrop-blur px-4 sm:px-6 lg:px-8 py-4 text-xs text-gray-600 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
            >
                <div class="flex flex-wrap gap-3">
                    <Link
                        href="/politica-de-privacidade"
                        class="font-semibold text-orange-700 hover:text-orange-800 transition"
                    >
                        Política de Privacidade
                    </Link>
                    <Link
                        href="/suporte"
                        class="font-semibold text-orange-700 hover:text-orange-800 transition"
                    >
                        Ajuda e Suporte
                    </Link>
                </div>
                <span class="text-gray-500">
                    © {{ currentYear }} Meu Negócio · Segurança multi-tenant
                </span>
            </footer>
        </AppContent>
    </AppShell>
</template>
