<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useAuth } from '@/composables/useAuth';
import { useToast } from '@/composables/useToast';
import Sidebar from '@/components/Sidebar.vue';
import FixedPageHeaderTitle from '@/components/FixedPageHeaderTitle.vue';
import LoadingSpinner from '@/components/LoadingSpinner.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

const auth = useAuth();
const toast = useToast();
const users = ref<any[]>([]);
const isLoading = ref(false);
const isSubmitting = ref(false);
const resettingUserId = ref<number | null>(null);
const generatedPassword = ref<null | { userName: string; userEmail: string; password: string }>(null);
const copyPasswordFeedback = ref('');
const showResetPasswordModal = ref(false);
const pendingResetUser = ref<any | null>(null);
const showPassword = ref(false);
const form = ref({
    name: '',
    email: '',
    password: '',
    role: 'owner',
});

const masterEmail = 'admin@meunegocio.cloud';
const currentUserEmail = computed(() => auth.user.value?.email?.toLowerCase() ?? '');

const formatDate = (value: string | null) => {
    if (!value) return 'Nunca';

    const dateValue = new Date(value);
    if (Number.isNaN(dateValue.getTime())) return 'Nunca';

    return dateValue.toLocaleString('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
};

const fetchUsers = async () => {
    isLoading.value = true;

    try {
        const response = await fetch('/api/admin/users', {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            throw new Error(payload.message || 'Não foi possível carregar os usuários.');
        }

        const payload = await response.json();
        users.value = payload.data ?? [];
    } catch (error: any) {
        toast.error('Erro ao carregar usuários', {
            description: error?.message || 'Tente novamente mais tarde.',
        });
    } finally {
        isLoading.value = false;
    }
};

const createUser = async () => {
    if (!form.value.name.trim() || !form.value.email.trim() || !form.value.password.trim()) {
        toast.error('Dados incompletos', {
            description: 'Preencha nome, e-mail e senha para criar o acesso.',
        });
        return;
    }

    isSubmitting.value = true;

    try {
        const response = await fetch('/api/admin/users', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                name: form.value.name.trim(),
                email: form.value.email.trim(),
                password: form.value.password,
                role: form.value.role,
            }),
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(payload.message || 'Não foi possível criar o acesso.');
        }

        form.value = { name: '', email: '', password: '', role: 'owner' };
        toast.success('Acesso criado com sucesso');
        await fetchUsers();
    } catch (error: any) {
        toast.error('Erro ao criar acesso', {
            description: error?.message || 'Tente novamente em instantes.',
        });
    } finally {
        isSubmitting.value = false;
    }
};

const unlockLoginAttempts = async (userEmail: string) => {
    try {
        const response = await fetch('/api/auth/login-attempts/unlock', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                email: userEmail,
            }),
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(payload.message || 'Não foi possível desbloquear as tentativas de login.');
        }

        toast.success('Tentativas de login desbloqueadas', {
            description: `O acesso de ${userEmail} foi liberado.`,
        });
    } catch (error: any) {
        toast.error('Erro ao desbloquear login', {
            description: error?.message || 'Tente novamente em instantes.',
        });
    }
};

const openResetPasswordModal = (user: any) => {
    pendingResetUser.value = user;
    showResetPasswordModal.value = true;
};

const closeResetPasswordModal = () => {
    if (resettingUserId.value) return;

    showResetPasswordModal.value = false;
    pendingResetUser.value = null;
};

const handleResetPasswordModalOpenChange = (isOpen: boolean) => {
    if (isOpen) {
        showResetPasswordModal.value = true;
        return;
    }

    closeResetPasswordModal();
};

const resetUserPassword = async () => {
    if (!pendingResetUser.value) return;

    const user = pendingResetUser.value;
    resettingUserId.value = user.id;

    try {
        const response = await fetch(`/api/admin/users/${user.id}/reset-password`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(payload.message || 'Não foi possível resetar a senha do usuário.');
        }

        generatedPassword.value = {
            userName: user.name,
            userEmail: user.email,
            password: payload?.data?.new_password || '',
        };
        copyPasswordFeedback.value = '';
        showResetPasswordModal.value = false;
        pendingResetUser.value = null;

        toast.success('Senha redefinida com sucesso', {
            description: `Nova senha gerada para ${user.email}.`,
        });
    } catch (error: any) {
        toast.error('Erro ao redefinir senha', {
            description: error?.message || 'Tente novamente em instantes.',
        });
    } finally {
        resettingUserId.value = null;
    }
};

const copyGeneratedPassword = async () => {
    if (!generatedPassword.value?.password) return;

    try {
        await navigator.clipboard.writeText(generatedPassword.value.password);
        copyPasswordFeedback.value = 'Senha copiada para a área de transferência.';
    } catch {
        copyPasswordFeedback.value = 'Não foi possível copiar automaticamente. Copie manualmente a senha exibida.';
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

    fetchUsers();
});
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-amber-50 to-orange-100 dark:from-slate-950 dark:via-slate-900 dark:to-slate-950 flex flex-col overflow-x-hidden">
        <Head title="Controle de Acesso - MEU NEGÓCIO" />

        <Sidebar active-route="admin-usuarios" />

        <div class="flex-1 min-w-0 lg:ml-64 transition-all duration-300 overflow-x-hidden flex flex-col">
            <FixedPageHeaderTitle
                title="Controle de Acesso"
                subtitle="Gerencie usuários, credenciais e controle de acesso do sistema"
            />

            <main class="w-full min-w-0 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 overflow-x-hidden">
                <div class="bg-white dark:bg-slate-900 rounded-xl shadow-lg border border-blue-200/70 dark:border-slate-700 overflow-hidden">
                    <div class="p-6 sm:p-10">
                        <div class="grid gap-6 overflow-hidden 2xl:grid-cols-[420px,minmax(0,1fr)]">
                            <section class="bg-orange-50 dark:bg-slate-800/70 border border-orange-100 dark:border-slate-700 rounded-xl p-6 shadow-sm overflow-hidden">
                                <div>
                                    <p class="text-xs uppercase tracking-wide text-orange-500 dark:text-amber-300 font-semibold">NOVO CADASTRO</p>
                                    <h2 class="text-xl font-semibold text-gray-900 dark:text-slate-100">Formulário de criação de acesso</h2>
                                </div>

                                <div class="mt-5 space-y-4 lg:space-y-0 lg:grid lg:grid-cols-10 lg:gap-3">
                                    <div class="lg:col-span-2">
                                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-slate-200">Nome</label>
                                        <input v-model="form.name" type="text" class="w-full rounded-xl border-2 border-amber-200 dark:border-slate-600 bg-white dark:bg-slate-900 dark:text-slate-100 px-3 py-2.5 outline-none transition focus:border-amber-400 dark:focus:border-amber-300 focus:ring-2 focus:ring-amber-200 dark:focus:ring-amber-500/40" placeholder="Ex: Maria Souza" />
                                    </div>

                                    <div class="lg:col-span-3">
                                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-slate-200">E-mail</label>
                                        <input v-model="form.email" type="email" class="w-full rounded-xl border-2 border-amber-200 dark:border-slate-600 bg-white dark:bg-slate-900 dark:text-slate-100 px-3 py-2.5 outline-none transition focus:border-amber-400 dark:focus:border-amber-300 focus:ring-2 focus:ring-amber-200 dark:focus:ring-amber-500/40" placeholder="nome@empresa.com" />
                                    </div>

                                    <div class="lg:col-span-2">
                                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-slate-200">Senha</label>
                                        <div class="relative">
                                            <input 
                                                v-model="form.password" 
                                                :type="showPassword ? 'text' : 'password'" 
                                                class="w-full rounded-xl border-2 border-amber-200 dark:border-slate-600 bg-white dark:bg-slate-900 dark:text-slate-100 px-3 py-2.5 pr-10 outline-none transition focus:border-amber-400 dark:focus:border-amber-300 focus:ring-2 focus:ring-amber-200 dark:focus:ring-amber-500/40" 
                                                placeholder="Mínimo 8 caracteres" 
                                            />
                                            <button
                                                type="button"
                                                @click="showPassword = !showPassword"
                                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-200 transition"
                                                :aria-label="showPassword ? 'Ocultar senha' : 'Mostrar senha'"
                                            >
                                                <svg v-if="!showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                                <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="lg:col-span-2">
                                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-slate-200">Perfil</label>
                                        <select v-model="form.role" class="w-full rounded-xl border-2 border-amber-200 dark:border-slate-600 bg-white dark:bg-slate-900 dark:text-slate-100 px-3 py-2.5 outline-none transition focus:border-amber-400 dark:focus:border-amber-300 focus:ring-2 focus:ring-amber-200 dark:focus:ring-amber-500/40 appearance-none">
                                            <option value="owner">Owner</option>
                                        </select>
                                    </div>

                                    <div class="lg:col-span-1 flex items-end">
                                        <button
                                            :disabled="isSubmitting"
                                            @click="createUser"
                                            class="w-full rounded-xl bg-gradient-to-r from-amber-400 to-orange-500 px-4 py-2.5 font-semibold text-white transition hover:shadow-lg disabled:cursor-not-allowed disabled:opacity-60"
                                        >
                                            {{ isSubmitting ? 'Criando...' : 'Criar' }}
                                        </button>
                                    </div>
                                </div>
                            </section>

                            <section class="bg-white dark:bg-slate-900 border border-amber-100 dark:border-slate-700 rounded-xl p-6 shadow-sm overflow-hidden">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <p class="text-xs uppercase tracking-wide text-orange-500 dark:text-amber-300 font-semibold">Acessos cadastrados</p>
                                        <h2 class="text-xl font-semibold text-gray-900 dark:text-slate-100">Usuários cadastrados</h2>
                                    </div>
                                    <span class="rounded-full bg-orange-100 dark:bg-amber-500/20 px-3 py-1 text-xs font-semibold text-orange-700 dark:text-amber-200">
                                        {{ users.length }} usuários
                                    </span>
                                </div>

                                <div
                                    v-if="generatedPassword"
                                    class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200"
                                >
                                    <p class="font-semibold">Nova senha gerada para {{ generatedPassword.userName }} ({{ generatedPassword.userEmail }}).</p>
                                    <p class="mt-1">Senha temporária: <span class="font-mono tracking-wide">{{ generatedPassword.password }}</span></p>
                                    <div class="mt-3 flex flex-wrap items-center gap-2">
                                        <button
                                            type="button"
                                            class="rounded-lg border border-emerald-300 px-3 py-1.5 text-xs font-semibold text-emerald-800 transition hover:bg-emerald-100 dark:border-emerald-500/40 dark:text-emerald-200 dark:hover:bg-emerald-500/20"
                                            @click="copyGeneratedPassword"
                                        >
                                            Copiar senha
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-lg border border-transparent cursor-pointer px-3 py-1.5 text-xs font-semibold text-emerald-800 underline underline-offset-2 transition hover:text-emerald-950 dark:text-emerald-200 dark:hover:text-emerald-100"
                                            @click="generatedPassword = null; copyPasswordFeedback = ''"
                                        >
                                            Ocultar
                                        </button>
                                    </div>
                                    <p v-if="copyPasswordFeedback" class="mt-2 text-xs text-emerald-800 dark:text-emerald-200">
                                        {{ copyPasswordFeedback }}
                                    </p>
                                </div>

                                <LoadingSpinner v-if="isLoading" class="mt-6" message="Carregando usuários..." />

                                <div v-else class="mt-6 space-y-4">
                                    <!-- Desktop: Table -->
                                    <div class="hidden 2xl:block overflow-x-auto rounded-xl border border-amber-100 dark:border-slate-700">
                                        <table class="min-w-full divide-y divide-amber-100 dark:divide-slate-700 text-left text-sm">
                                            <thead class="bg-amber-50 dark:bg-slate-800/80">
                                                <tr>
                                                    <th class="px-4 py-3 font-semibold text-gray-800 dark:text-slate-200">Nome</th>
                                                    <th class="px-4 py-3 font-semibold text-gray-800 dark:text-slate-200">E-mail</th>
                                                    <th class="px-4 py-3 font-semibold text-gray-800 dark:text-slate-200">Perfil</th>
                                                    <th class="px-4 py-3 font-semibold text-gray-800 dark:text-slate-200">Última autenticação</th>
                                                    <th class="px-4 py-3 font-semibold text-right text-gray-800 dark:text-slate-200">Ações</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-amber-100 dark:divide-slate-700 bg-white dark:bg-slate-900">
                                                <tr v-for="user in users" :key="user.id" class="align-top">
                                                    <td class="px-4 py-3 font-medium text-gray-800 dark:text-slate-100">
                                                        {{ user.name }}
                                                        <span v-if="user.is_master_admin" class="ml-2 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] uppercase text-emerald-700">Master</span>
                                                    </td>
                                                    <td class="px-4 py-3 text-gray-600 dark:text-slate-300">{{ user.email }}</td>
                                                    <td class="px-4 py-3">
                                                        <span class="rounded-full bg-slate-100 dark:bg-slate-700 px-2 py-1 text-xs uppercase text-gray-700 dark:text-slate-200">{{ user.role }}</span>
                                                    </td>
                                                    <td class="px-4 py-3 text-gray-600 dark:text-slate-300">{{ formatDate(user.last_login_at) }}</td>
                                                    <td class="px-4 py-3 text-right">
                                                        <div class="inline-flex items-center gap-2">
                                                            <button
                                                                type="button"
                                                                class="rounded-lg border border-blue-200 dark:border-blue-400/40 px-3 py-1.5 text-xs font-semibold text-blue-700 dark:text-blue-200 transition hover:bg-blue-50 dark:hover:bg-blue-500/10 disabled:cursor-not-allowed disabled:opacity-60"
                                                                :disabled="user.role === 'root' || resettingUserId === user.id"
                                                                :title="user.role === 'root' ? 'Não é permitido redefinir a senha de um usuário root.' : undefined"
                                                                @click="openResetPasswordModal(user)"
                                                            >
                                                                {{ resettingUserId === user.id ? 'Gerando senha...' : 'Resetar senha' }}
                                                            </button>
                                                            <button
                                                                type="button"
                                                                class="rounded-lg border border-amber-200 dark:border-amber-500/50 px-3 py-1.5 text-xs font-semibold text-amber-700 dark:text-amber-200 transition hover:bg-amber-100 dark:hover:bg-amber-500/10"
                                                                @click="unlockLoginAttempts(user.email)"
                                                            >
                                                                Desbloquear login
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <tr v-if="users.length === 0">
                                                    <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-slate-400">
                                                        Nenhum usuário cadastrado até o momento.
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- Mobile: Cards -->
                                    <div class="2xl:hidden space-y-3">
                                        <div v-if="users.length === 0" class="text-center py-6 text-sm text-gray-500 dark:text-slate-400">
                                            Nenhum usuário cadastrado até o momento.
                                        </div>
                                        <div v-for="user in users" :key="user.id" class="rounded-lg border border-amber-100 dark:border-slate-700 bg-white dark:bg-slate-900 p-4">
                                            <div class="flex items-start justify-between gap-2 mb-3">
                                                <div>
                                                    <p class="font-semibold text-gray-900 dark:text-slate-100">{{ user.name }}</p>
                                                    <span v-if="user.is_master_admin" class="ml-0 mt-1 inline-block rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] uppercase text-emerald-700">Master</span>
                                                </div>
                                                <span class="rounded-full bg-slate-100 dark:bg-slate-700 px-2 py-1 text-xs uppercase text-gray-700 dark:text-slate-200 whitespace-nowrap">{{ user.role }}</span>
                                            </div>
                                            <div class="space-y-2 mb-3 text-sm">
                                                <p class="text-gray-600 dark:text-slate-300">
                                                    <span class="font-medium text-gray-700 dark:text-slate-200">E-mail:</span> {{ user.email }}
                                                </p>
                                                <p class="text-gray-600 dark:text-slate-300">
                                                    <span class="font-medium text-gray-700 dark:text-slate-200">Última autenticação:</span> {{ formatDate(user.last_login_at) }}
                                                </p>
                                            </div>
                                            <div class="flex gap-2">
                                                <button
                                                    type="button"
                                                    class="flex-1 rounded-lg border border-blue-200 dark:border-blue-400/40 px-3 py-2 text-xs font-semibold text-blue-700 dark:text-blue-200 transition hover:bg-blue-50 dark:hover:bg-blue-500/10 disabled:cursor-not-allowed disabled:opacity-60"
                                                    :disabled="user.role === 'root' || resettingUserId === user.id"
                                                    :title="user.role === 'root' ? 'Não é permitido redefinir a senha de um usuário root.' : undefined"
                                                    @click="openResetPasswordModal(user)"
                                                >
                                                    {{ resettingUserId === user.id ? 'Gerando...' : 'Resetar' }}
                                                </button>
                                                <button
                                                    type="button"
                                                    class="flex-1 rounded-lg border border-amber-200 dark:border-amber-500/50 px-3 py-2 text-xs font-semibold text-amber-700 dark:text-amber-200 transition hover:bg-amber-100 dark:hover:bg-amber-500/10"
                                                    @click="unlockLoginAttempts(user.email)"
                                                >
                                                    Desbloquear
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>
                </div>
            </main>
        </div>

        <Dialog :open="showResetPasswordModal" @update:open="handleResetPasswordModalOpenChange">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Confirmar redefinição de senha</DialogTitle>
                    <DialogDescription>
                        A senha atual será substituída por uma nova senha aleatória.
                        <span v-if="pendingResetUser">
                            Deseja continuar para o usuário {{ pendingResetUser.name }}?
                        </span>
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="gap-2 sm:justify-end">
                    <button
                        type="button"
                        class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800"
                        :disabled="!!resettingUserId"
                        @click="closeResetPasswordModal"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-primary dark:bg-primary px-3 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="!!resettingUserId"
                        @click="resetUserPassword"
                    >
                        {{ resettingUserId ? 'Gerando senha...' : 'Confirmar reset' }}
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
