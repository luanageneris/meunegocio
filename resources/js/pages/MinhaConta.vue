<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import Sidebar from '@/components/Sidebar.vue';
import PageHeader from '@/components/PageHeader.vue';
import LoadingSpinner from '@/components/LoadingSpinner.vue';
import { useAuth } from '@/composables/useAuth';
import { useToast } from '@/composables/useToast';
import logoUrl from '@/../../resources/img/icon-logo.svg';

type CompanyForm = {
    name: string;
    email: string;
    phone: string;
    document: string;
    address: string;
    city: string;
    state: string;
    postal_code: string;
};

const auth = useAuth();
const toast = useToast();
const company = ref<any>(null);
const form = ref<CompanyForm>({
    name: '',
    email: '',
    phone: '',
    document: '',
    address: '',
    city: '',
    state: '',
    postal_code: '',
});
const errors = ref<Record<string, string[]>>({});
const isLoading = ref(true);
const isSaving = ref(false);

const canEdit = computed(() => {
    const role = auth.user.value?.role;
    return role === 'root' || role === 'owner';
});

const createdAtText = computed(() => {
    const date = company.value?.created_at;
    if (!date) return '-';
    try {
        return new Date(date).toLocaleString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch (err) {
        return '-';
    }
});

const fillForm = (data: any) => {
    if (!data) return;
    company.value = data;
    form.value = {
        name: data.name ?? '',
        email: data.email ?? '',
        phone: data.phone ?? '',
        document: data.document ?? '',
        address: data.address ?? '',
        city: data.city ?? '',
        state: data.state ?? '',
        postal_code: data.postal_code ?? '',
    };
};

const fetchCompany = async () => {
    isLoading.value = true;
    try {
        const response = await fetch('/api/company', {
            headers: {
                'Authorization': `Bearer ${auth.token.value}`,
                'Accept': 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error('Não foi possível carregar os dados da empresa.');
        }

        const json = await response.json();
        fillForm(json.data);
    } catch (error: any) {
        toast.error('Erro ao carregar dados', { description: error?.message || 'Tente novamente em instantes.' });
    } finally {
        isLoading.value = false;
    }
};

const save = async () => {
    if (!canEdit.value) {
        toast.info('Acesso somente leitura', { description: 'Somente o usuário root pode editar a conta.' });
        return;
    }

    isSaving.value = true;
    errors.value = {};

    try {
        const response = await fetch('/api/company', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': `Bearer ${auth.token.value}`,
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify(form.value),
        });

        const payload = await response.json();

        if (!response.ok) {
            errors.value = payload?.errors || {};
            throw new Error(payload?.message || 'Erro ao salvar');
        }

        fillForm(payload.data);

        // Atualiza o usuário em memória para manter company sincronizado
        const currentUser = auth.user.value || {};
        auth.setUser({ ...currentUser, company: payload.data });

        toast.success('Dados atualizados', { description: 'Informações salvas com sucesso.' });
    } catch (error: any) {
        toast.error('Não foi possível salvar', { description: error?.message || 'Revise os campos e tente novamente.' });
    } finally {
        isSaving.value = false;
    }
};

onMounted(async () => {
    if (!auth.isAuthenticated.value) {
        window.location.href = '/login';
        return;
    }

    // Pré-preenche com dados existentes (ex.: após login)
    if (auth.user.value?.company) {
        fillForm(auth.user.value.company);
    }

    await fetchCompany();
});
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-amber-50 to-orange-100 flex">
        <Head title="Minha Conta - MEU NEGÓCIO" />

        <Sidebar active-route="conta" />

        <div class="flex-1 lg:ml-64 transition-all duration-300">
            <PageHeader title="Minha Conta" container-class="max-w-6xl" />

            <main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div class="bg-white rounded-xl shadow-lg border border-blue-200/70">
                    <div class="px-6 sm:px-10 py-8 border-b border-blue-100/70">
                        <h2 class="text-2xl font-bold text-center text-orange-600">Minha Conta</h2>
                        <p class="text-center text-gray-600 mt-2">Gerencie as informações da sua empresa e acesso.</p>
                    </div>

                    <div class="p-6 sm:p-10">
                        <LoadingSpinner v-if="isLoading" message="Carregando informações da conta..." />

                        <div v-else class="space-y-8">
                            <div class="grid gap-6 lg:grid-cols-2">
                                <section class="bg-orange-50 border border-orange-100 rounded-xl p-6 shadow-sm">
                                    <div class="flex items-center justify-between mb-4">
                                        <div>
                                            <p class="text-xs uppercase tracking-wide text-orange-500 font-semibold">Informações da Conta</p>
                                            <h3 class="text-lg font-bold text-gray-800">Dados da empresa</h3>
                                        </div>
                                        <span v-if="!canEdit" class="text-xs font-medium text-gray-500 bg-gray-100 px-3 py-1 rounded-full">Somente leitura</span>
                                    </div>

                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Nome da empresa *</label>
                                            <input
                                                v-model="form.name"
                                                type="text"
                                                :disabled="!canEdit"
                                                placeholder="Ex: Xerografia & Cia"
                                                class="w-full px-4 py-3 border-2 border-amber-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent disabled:bg-gray-100"
                                            />
                                            <p v-if="errors.name" class="text-sm text-red-600 mt-1">{{ errors.name[0] }}</p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Email de contato</label>
                                            <input
                                                v-model="form.email"
                                                type="email"
                                                :disabled="!canEdit"
                                                placeholder="contato@empresa.com"
                                                class="w-full px-4 py-3 border-2 border-amber-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent disabled:bg-gray-100"
                                            />
                                            <p v-if="errors.email" class="text-sm text-red-600 mt-1">{{ errors.email[0] }}</p>
                                        </div>

                                        <div class="grid gap-4 sm:grid-cols-2">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Telefone</label>
                                                <input
                                                    v-model="form.phone"
                                                    type="tel"
                                                    :disabled="!canEdit"
                                                    placeholder="(11) 99999-9999"
                                                    class="w-full px-4 py-3 border-2 border-amber-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent disabled:bg-gray-100"
                                                />
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">CNPJ/CPF</label>
                                                <input
                                                    v-model="form.document"
                                                    type="text"
                                                    :disabled="!canEdit"
                                                    placeholder="00.000.000/0000-00"
                                                    class="w-full px-4 py-3 border-2 border-amber-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent disabled:bg-gray-100"
                                                />
                                                <p v-if="errors.document" class="text-sm text-red-600 mt-1">{{ errors.document[0] }}</p>
                                            </div>
                                        </div>
                                    </div>

                                </section>

                                <section class="bg-white border border-amber-100 rounded-xl p-6 shadow-sm">
                                    <p class="text-xs uppercase tracking-wide text-orange-500 font-semibold">Endereço (opcional)</p>
                                    <h3 class="text-lg font-bold text-gray-800 mb-4">Onde sua empresa fica?</h3>

                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Endereço</label>
                                            <input
                                                v-model="form.address"
                                                type="text"
                                                :disabled="!canEdit"
                                                placeholder="Rua, número, bairro"
                                                class="w-full px-4 py-3 border-2 border-amber-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent disabled:bg-gray-100"
                                            />
                                        </div>

                                        <div class="grid gap-4 sm:grid-cols-2">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Cidade</label>
                                                <input
                                                    v-model="form.city"
                                                    type="text"
                                                    :disabled="!canEdit"
                                                    placeholder="Cidade"
                                                    class="w-full px-4 py-3 border-2 border-amber-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent disabled:bg-gray-100"
                                                />
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">UF</label>
                                                <input
                                                    v-model="form.state"
                                                    type="text"
                                                    maxlength="2"
                                                    :disabled="!canEdit"
                                                    placeholder="UF"
                                                    class="w-full px-4 py-3 border-2 border-amber-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent uppercase disabled:bg-gray-100"
                                                />
                                            </div>
                                        </div>

                                        <div class="grid gap-4 sm:grid-cols-2">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">CEP</label>
                                                <input
                                                    v-model="form.postal_code"
                                                    type="text"
                                                    :disabled="!canEdit"
                                                    placeholder="00000-000"
                                                    class="w-full px-4 py-3 border-2 border-amber-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent disabled:bg-gray-100"
                                                />
                                            </div>
                                            <div class="flex items-center gap-3 text-sm text-gray-600 bg-orange-50 border border-orange-100 rounded-lg px-3 py-2">
                                                <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z" />
                                                </svg>
                                                <span>Endereço é opcional e pode ser usado em relatórios futuros.</span>
                                            </div>
                                        </div>
                                    </div>
                                </section>
                            </div>

                            <div class="flex flex-col sm:flex-row flex-wrap gap-3 justify-end">
                                <button
                                    type="button"
                                    :disabled="isSaving || !canEdit"
                                    @click="save"
                                    class="w-full sm:w-auto px-5 py-3 bg-gradient-to-r from-amber-400 to-orange-500 text-white rounded-lg font-medium hover:shadow-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    {{ isSaving ? 'Salvando...' : 'Salvar alterações' }}
                                </button>
                                <button
                                    type="button"
                                    :disabled="isSaving"
                                    @click="fetchCompany"
                                    class="w-full sm:w-auto px-4 py-3 border border-amber-200 text-orange-600 rounded-lg font-medium hover:bg-orange-50 transition-all disabled:opacity-50"
                                >
                                    Recarregar dados
                                </button>
                            </div>

                            <div class="grid gap-6 lg:grid-cols-2">
                                <section class="bg-white border border-amber-100 rounded-xl p-6 shadow-sm">
                                    <p class="text-xs uppercase tracking-wide text-orange-500 font-semibold">Acesso</p>
                                    <h3 class="text-lg font-bold text-gray-800 mb-2">Login via Google</h3>
                                    <p class="text-gray-600 mb-4">
                                        Você está conectado com sua conta Google. Alterações de senha são feitas diretamente no Google. Para trocar o e-mail de acesso, conecte com outra conta Google.
                                    </p>
                                    <div class="flex items-center gap-3 bg-orange-50 border border-orange-100 rounded-lg px-4 py-3">
                                        <div class="w-10 h-10 rounded-full bg-white shadow flex items-center justify-center">
                                            <svg class="w-5 h-5 text-orange-500" viewBox="0 0 533.5 544.3" xmlns="http://www.w3.org/2000/svg">
                                                <path fill="#4285f4" d="M533.5 278.4c0-17.4-1.5-34.1-4.3-50.3H272v95.2h146.9c-6.3 33.9-25 62.6-53.2 81.8v68.1h85.9c50.2-46.3 81.9-114.6 81.9-194.8z"/>
                                                <path fill="#34a853" d="M272 544.3c72.6 0 133.5-24 178-65.3l-85.9-68.1c-23.8 16-54.3 25.5-92.1 25.5-70.8 0-130.8-47.7-152.3-111.7H30.7v70.2c44.4 88 134.5 149.4 241.3 149.4z"/>
                                                <path fill="#fbbc04" d="M119.7 324.7c-10.4-31-10.4-64.4 0-95.4V159h-89c-38.7 77.3-38.7 168.1 0 245.4l89-69.7z"/>
                                                <path fill="#ea4335" d="M272 107.7c39.5-.6 77.4 14.5 106.4 42.3l79.7-79.7C431.3 24.2 373.9 0 272 0 165.2 0 75.1 61.4 30.7 149.4l89 70.2C141.2 155.4 201.2 107.7 272 107.7z"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-500">Conta conectada</p>
                                            <p class="text-base font-semibold text-gray-800">{{ auth.user.value?.email || '---' }}</p>
                                        </div>
                                    </div>
                                </section>

                                <section class="bg-white border border-amber-100 rounded-xl p-6 shadow-sm flex flex-col gap-4">
                                    <p class="text-xs uppercase tracking-wide text-orange-500 font-semibold">Identidade visual</p>
                                    <div class="flex flex-col sm:flex-row items-center gap-4">
                                        <div class="w-24 h-24 rounded-full border-2 border-orange-200 bg-orange-50 flex items-center justify-center overflow-hidden shadow">
                                            <img :src="logoUrl" alt="Logomarca" class="w-16 h-16 object-contain" />
                                        </div>
                                        <div class="space-y-1">
                                            <h3 class="text-lg font-bold text-gray-800">Logomarca ou foto</h3>
                                            <p class="text-sm text-gray-600">Envio de logo estará disponível em breve.</p>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        disabled
                                        class="w-full sm:w-auto px-4 py-3 border border-amber-200 text-orange-600 rounded-lg font-medium bg-orange-50 cursor-not-allowed"
                                    >
                                        Upload disponível em breve
                                    </button>
                                </section>
                            </div>

                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-orange-50 border border-orange-100 rounded-xl px-4 py-3 text-center sm:text-left">
                                <div class="w-full sm:w-auto">
                                    <p class="text-sm text-gray-600">Conta criada em</p>
                                    <p class="text-lg font-semibold text-gray-800">{{ createdAtText }}</p>
                                </div>
                                <button
                                    type="button"
                                    disabled
                                    class="w-full sm:w-auto px-5 py-3 bg-red-50 text-red-600 border border-red-200 rounded-lg font-medium cursor-not-allowed"
                                >
                                    Apagar conta (fale com o suporte)
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>
