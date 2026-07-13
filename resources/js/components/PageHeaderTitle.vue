<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useAuth } from '@/composables/useAuth';
import { useAppearance } from '@/composables/useAppearance';
import { Moon, Sun } from 'lucide-vue-next';

interface Props {
    title: string;
    subtitle?: string;
}

defineProps<Props>();

const auth = useAuth();
const showLogoutModal = ref(false);
const { appearance, updateAppearance } = useAppearance();

function toggleTheme() {
    updateAppearance(appearance.value === 'dark' ? 'light' : 'dark');
}

const handleLogout = async () => {
    try {
        await fetch('/api/auth/logout', {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Accept': 'application/json',
                'Authorization': `Bearer ${auth.token.value}`,
            },
        });
    } catch (error) {
        console.error('Erro ao fazer logout:', error);
    } finally {
        auth.logout();
        window.location.href = '/login';
    }
};

const openLogoutModal = () => {
    showLogoutModal.value = true;
};

const closeLogoutModal = () => {
    showLogoutModal.value = false;
};

const confirmLogout = async () => {
    showLogoutModal.value = false;
    await handleLogout();
};

const handleKeydown = (event: KeyboardEvent) => {
    if (!showLogoutModal.value) {
        return;
    }

    if (event.key === 'Escape') {
        closeLogoutModal();
        return;
    }

    if (event.key === 'Enter' || event.key === 'NumpadEnter') {
        event.preventDefault();
        void confirmLogout();
    }
};

onMounted(() => {
    if (typeof window !== 'undefined') {
        window.addEventListener('keydown', handleKeydown);
    }
});

onBeforeUnmount(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('keydown', handleKeydown);
    }
});
</script>

<template>
    <div class="bg-gradient-to-r from-orange-400 to-amber-500 shadow-lg select-none" @copy.prevent>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-5">
            <div class="lg:ml-0 flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <h1 class="text-2xl sm:text-3xl font-bold text-white mb-1">{{ title }}</h1>
                    <p v-if="subtitle" class="text-orange-100 text-xs sm:text-sm font-medium leading-snug">{{ subtitle }}</p>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center h-9 w-9 rounded-lg bg-white/20 text-white hover:bg-white/30 transition-colors cursor-pointer"
                        :title="appearance === 'dark' ? 'Modo claro' : 'Modo escuro'"
                        :aria-label="appearance === 'dark' ? 'Ativar modo claro' : 'Ativar modo escuro'"
                        @click="toggleTheme"
                    >
                        <Sun v-if="appearance === 'dark'" class="h-4 w-4" />
                        <Moon v-else class="h-4 w-4" />
                    </button>

                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg bg-white/20 px-3 py-2 text-xs sm:text-sm font-semibold text-white hover:bg-white/30 transition-colors cursor-pointer"
                        title="Sair"
                        aria-label="Sair"
                        @click="openLogoutModal"
                    >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    <span class="hidden sm:inline">Logout</span>
                    <span class="sm:hidden">Sair</span>
                </button>
                </div>
            </div>
        </div>

        <div
            v-if="showLogoutModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4"
        >
            <div class="w-full max-w-md rounded-xl bg-white shadow-2xl border border-gray-200">
                <div class="p-5 sm:p-6">
                    <h3 class="text-lg font-bold text-gray-900">Confirmar saída</h3>
                    <p class="mt-2 text-sm text-gray-600">Tem certeza que deseja sair da sua conta?</p>

                    <div class="mt-5 flex justify-end gap-2">
                        <button
                            type="button"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors cursor-pointer"
                            @click="closeLogoutModal"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            class="px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-orange-500 to-amber-500 rounded-lg hover:shadow-md transition-all cursor-pointer"
                            @click="confirmLogout"
                        >
                            Sim, sair
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
