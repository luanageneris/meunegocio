<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useAuth } from '@/composables/useAuth';
import Sidebar from '@/components/Sidebar.vue';
import FixedPageHeaderTitle from '@/components/FixedPageHeaderTitle.vue';
import LoadingSpinner from '@/components/LoadingSpinner.vue';

interface AccessLog {
    id: number;
    user_id: number;
    user_name: string;
    user_email: string;
    ip_address: string;
    user_agent: string;
    status: 'success' | 'failed';
    attempted_at: string;
    created_at: string;
}

const auth = useAuth();
const logs = ref<AccessLog[]>([]);
const isLoading = ref(false);
const currentPage = ref(1);
const totalPages = ref(1);

const masterEmail = 'admin@meunegocio.cloud';
const currentUserEmail = computed(() => auth.user.value?.email?.toLowerCase() ?? '');

const formatDate = (value: string | null) => {
    if (!value) return '-';

    const dateValue = new Date(value);
    if (Number.isNaN(dateValue.getTime())) return '-';

    return dateValue.toLocaleString('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
};

const getStatusLabel = (status: string) => {
    return status === 'success' ? 'Sucesso' : 'Falha';
};

const getStatusColor = (status: string) => {
    return status === 'success'
        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200'
        : 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-200';
};

const fetchLogs = async (page = 1) => {
    isLoading.value = true;
    try {
        const response = await fetch(`/api/admin/access-logs?page=${page}`, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        });

        const payload = await response.json();

        if (response.ok) {
            logs.value = payload.data || [];
            currentPage.value = payload.pagination?.current_page || 1;
            totalPages.value = payload.pagination?.last_page || 1;
        }
    } catch (error) {
        console.error('Erro ao carregar logs:', error);
    } finally {
        isLoading.value = false;
    }
};

const goToPage = (page: number) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
        fetchLogs(page);
    }
};

const exportLogs = async () => {
    try {
        const response = await fetch('/api/admin/access-logs/export', {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        });

        if (response.ok) {
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `access-logs-${new Date().toISOString().split('T')[0]}.csv`;
            link.click();
            window.URL.revokeObjectURL(url);
        }
    } catch (error) {
        console.error('Erro ao exportar logs:', error);
    }
};

onMounted(async () => {
    await auth.fetchCurrentUser();

    if (!auth.isAuthenticated.value) {
        window.location.href = '/login';
        return;
    }

    if (currentUserEmail.value !== masterEmail) {
        window.location.href = '/dashboard';
        return;
    }

    fetchLogs();
});
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-amber-50 to-orange-100 dark:from-slate-950 dark:via-slate-900 dark:to-slate-950 flex flex-col overflow-x-hidden">
        <Head title="Logs de Acesso - MEU NEGÓCIO" />

        <Sidebar active-route="admin-access-logs" />

        <div class="flex-1 min-w-0 lg:ml-64 transition-all duration-300 overflow-x-hidden flex flex-col">
            <FixedPageHeaderTitle
                title="Logs de Acesso"
                subtitle="Monitore tentativas de acesso ao sistema por IP e usuário"
            />

            <main class="w-full min-w-0 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 overflow-x-hidden">
                <div class="bg-white dark:bg-slate-900 rounded-xl shadow-lg border border-blue-200/70 dark:border-slate-700 overflow-hidden">
                    <div class="p-6 sm:p-10">
                        <!-- Header -->
                        <div class="flex items-center justify-between gap-4 mb-6">
                            <div>
                                <p class="text-xs uppercase tracking-wide text-orange-500 dark:text-amber-300 font-semibold">Segurança</p>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-slate-100">Histórico de acessos</h2>
                            </div>
                            <button
                                v-if="!isLoading && logs.length > 0"
                                @click="exportLogs"
                                class="rounded-xl bg-gradient-to-r from-amber-400 to-orange-500 px-4 py-2.5 font-semibold text-white transition hover:shadow-lg"
                            >
                                Exportar CSV
                            </button>
                        </div>

                        <LoadingSpinner v-if="isLoading" message="Carregando logs de acesso..." />

                        <div v-else class="space-y-4">
                            <!-- Desktop: Table -->
                            <div class="hidden md:block overflow-x-auto rounded-xl border border-amber-100 dark:border-slate-700">
                                <table class="min-w-full divide-y divide-amber-100 dark:divide-slate-700 text-left text-sm">
                                    <thead class="bg-amber-50 dark:bg-slate-800/80">
                                        <tr>
                                            <th class="px-4 py-3 font-semibold text-gray-800 dark:text-slate-200">Usuário</th>
                                            <th class="px-4 py-3 font-semibold text-gray-800 dark:text-slate-200">IP</th>
                                            <th class="px-4 py-3 font-semibold text-gray-800 dark:text-slate-200">Status</th>
                                            <th class="px-4 py-3 font-semibold text-gray-800 dark:text-slate-200">Data/Hora</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-amber-100 dark:divide-slate-700 bg-white dark:bg-slate-900">
                                        <tr v-for="log in logs" :key="log.id" class="hover:bg-amber-50 dark:hover:bg-slate-800/50 transition">
                                            <td class="px-4 py-3">
                                                <div>
                                                    <p class="font-medium text-gray-800 dark:text-slate-100">{{ log.user_name }}</p>
                                                    <p class="text-xs text-gray-500 dark:text-slate-400">{{ log.user_email }}</p>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 font-mono text-xs text-gray-600 dark:text-slate-300">{{ log.ip_address }}</td>
                                            <td class="px-4 py-3">
                                                <span :class="['rounded-full px-2.5 py-1 text-xs font-semibold', getStatusColor(log.status)]">
                                                    {{ getStatusLabel(log.status) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-gray-600 dark:text-slate-300">{{ formatDate(log.attempted_at) }}</td>
                                        </tr>
                                        <tr v-if="logs.length === 0">
                                            <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-slate-400">
                                                Nenhum log de acesso registrado.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Mobile: Cards -->
                            <div class="md:hidden space-y-3">
                                <div v-if="logs.length === 0" class="text-center py-6 text-sm text-gray-500 dark:text-slate-400">
                                    Nenhum log de acesso registrado.
                                </div>
                                <div v-for="log in logs" :key="log.id" class="rounded-lg border border-amber-100 dark:border-slate-700 bg-white dark:bg-slate-900 p-4">
                                    <div class="flex items-start justify-between gap-2 mb-3">
                                        <div>
                                            <p class="font-semibold text-gray-900 dark:text-slate-100">{{ log.user_name }}</p>
                                            <p class="text-xs text-gray-500 dark:text-slate-400">{{ log.user_email }}</p>
                                        </div>
                                        <span :class="['rounded-full px-2.5 py-1 text-xs font-semibold whitespace-nowrap', getStatusColor(log.status)]">
                                            {{ getStatusLabel(log.status) }}
                                        </span>
                                    </div>
                                    <div class="space-y-2 text-sm">
                                        <p class="text-gray-600 dark:text-slate-300">
                                            <span class="font-medium text-gray-700 dark:text-slate-200">IP:</span> {{ log.ip_address }}
                                        </p>
                                        <p class="text-gray-600 dark:text-slate-300">
                                            <span class="font-medium text-gray-700 dark:text-slate-200">Data/Hora:</span> {{ formatDate(log.attempted_at) }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Pagination -->
                            <div v-if="totalPages > 1" class="flex items-center justify-center gap-2 mt-6">
                                <button
                                    @click="goToPage(currentPage - 1)"
                                    :disabled="currentPage === 1"
                                    class="rounded-lg border border-amber-200 px-3 py-2 text-sm font-medium text-amber-700 transition hover:bg-amber-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800"
                                >
                                    Anterior
                                </button>
                                <div class="flex gap-1">
                                    <button
                                        v-for="page in totalPages"
                                        :key="page"
                                        @click="goToPage(page)"
                                        :class="[
                                            'rounded-lg px-3 py-2 text-sm font-medium transition',
                                            currentPage === page
                                                ? 'bg-gradient-to-r from-amber-400 to-orange-500 text-white'
                                                : 'border border-amber-200 text-amber-700 hover:bg-amber-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800',
                                        ]"
                                    >
                                        {{ page }}
                                    </button>
                                </div>
                                <button
                                    @click="goToPage(currentPage + 1)"
                                    :disabled="currentPage === totalPages"
                                    class="rounded-lg border border-amber-200 px-3 py-2 text-sm font-medium text-amber-700 transition hover:bg-amber-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800"
                                >
                                    Próxima
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>
