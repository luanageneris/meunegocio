<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useAuth } from '@/composables/useAuth';
import { useToast } from '@/composables/useToast';

const auth = useAuth();
const toast = useToast();
const users = ref<any[]>([]);
const isLoading = ref(false);
const isSubmitting = ref(false);
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

const logout = () => {
    router.post('/logout');
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
    <div class="min-h-screen bg-slate-100 dark:bg-slate-950 p-6 text-slate-800 dark:text-slate-100">
        <div class="mx-auto max-w-6xl space-y-6">
            <div class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-sm uppercase tracking-[0.2em] text-orange-500">Administração</p>
                    <h1 class="mt-2 text-3xl font-bold">Acessos do Sistema</h1>
                </div>

                <button
                    @click="logout"
                    class="rounded-lg border border-slate-300 px-4 py-2 font-medium text-slate-700 transition hover:border-orange-400 hover:text-orange-500 dark:border-slate-700 dark:text-slate-200"
                >
                    Sair
                </button>
            </div>

            <div class="grid gap-6 lg:grid-cols-[420px,1fr]">
                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-xl font-semibold">Criar novo acesso</h2>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Cadastre um novo usuário com e-mail e senha.</p>

                    <div class="mt-5 space-y-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Nome</label>
                            <input v-model="form.name" type="text" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 outline-none transition focus:border-orange-400 dark:border-slate-700 dark:bg-slate-800" placeholder="Ex: Maria Souza" />
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium">E-mail</label>
                            <input v-model="form.email" type="email" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 outline-none transition focus:border-orange-400 dark:border-slate-700 dark:bg-slate-800" placeholder="nome@empresa.com" />
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium">Senha</label>
                            <input v-model="form.password" type="password" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 outline-none transition focus:border-orange-400 dark:border-slate-700 dark:bg-slate-800" placeholder="Mínimo 8 caracteres" />
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium">Perfil</label>
                            <select v-model="form.role" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 outline-none transition focus:border-orange-400 dark:border-slate-700 dark:bg-slate-800">
                                <option value="owner">Owner</option>
                                <option value="root">Root</option>
                            </select>
                        </div>

                        <button
                            :disabled="isSubmitting"
                            @click="createUser"
                            class="w-full rounded-xl bg-orange-500 px-4 py-3 font-semibold text-white transition hover:bg-orange-600 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {{ isSubmitting ? 'Criando acesso...' : 'Criar acesso' }}
                        </button>
                    </div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xl font-semibold">Usuários cadastrados</h2>
                        <span class="rounded-full bg-orange-100 px-3 py-1 text-xs font-semibold text-orange-700 dark:bg-orange-950/40 dark:text-orange-300">
                            {{ users.length }} usuários
                        </span>
                    </div>

                    <div v-if="isLoading" class="mt-6 text-sm text-slate-500 dark:text-slate-400">Carregando usuários...</div>

                    <div v-else class="mt-6 overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
                        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-left text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-800">
                                <tr>
                                    <th class="px-4 py-3 font-semibold">Nome</th>
                                    <th class="px-4 py-3 font-semibold">E-mail</th>
                                    <th class="px-4 py-3 font-semibold">Perfil</th>
                                    <th class="px-4 py-3 font-semibold">Última autenticação</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-900">
                                <tr v-for="user in users" :key="user.id" class="align-top">
                                    <td class="px-4 py-3 font-medium">
                                        {{ user.name }}
                                        <span v-if="user.is_master_admin" class="ml-2 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] uppercase text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">Master</span>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ user.email }}</td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full bg-slate-100 px-2 py-1 text-xs uppercase dark:bg-slate-800">{{ user.role }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ formatDate(user.last_login_at) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>
