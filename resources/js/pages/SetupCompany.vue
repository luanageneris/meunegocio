<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import { useAuth } from '@/composables/useAuth';
import { useInputMasks } from '@/composables/useInputMasks';
import logoUrl from '../../img/icon-logo.svg';

const page = usePage();
const auth = useAuth();
const { formatPhone, formatCpfCnpj } = useInputMasks();
const formData = ref({
    name: '',
    email: '',
    phone: '',
    document: '',
});
const isLoading = ref(false);
const error = ref<string | null>(null);
const success = ref(false);

const getCsrfToken = (): string => {
    const csrfFromProps = (page.props as any).csrf_token;
    if (csrfFromProps) return csrfFromProps;
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
};

const handleSubmit = async () => {
    isLoading.value = true;
    error.value = null;

    try {
        // ⭐ CRÍTICO: Usar credentials para enviar cookies de sessão
        const response = await fetch('/api/company', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'include', // Envia cookies de sessão
            body: JSON.stringify(formData.value),
        });

        if (!response.ok) {
            const data = await response.json();
            throw new Error(data.message || 'Erro ao atualizar empresa');
        }

        const data = await response.json();
        auth.setUser(data.data);
        success.value = true;

        // Redirecionar para dashboard após 1 segundo
        setTimeout(() => {
            window.location.href = '/dashboard';
        }, 1000);
    } catch (err) {
        error.value = err instanceof Error ? err.message : 'Erro desconhecido';
        isLoading.value = false;
    }
};

const handlePhoneInput = (event: Event) => {
    const target = event.target as HTMLInputElement;
    formData.value.phone = formatPhone(target.value);
};

const handleDocumentInput = (event: Event) => {
    const target = event.target as HTMLInputElement;
    formData.value.document = formatCpfCnpj(target.value);
};

onMounted(() => {
    // Verificar autenticação
    if (!auth.isAuthenticated.value) {
        window.location.href = '/login';
        return;
    }
    
    // Preencher o nome da empresa existente se houver
    if (auth.user.value?.company?.name) {
        formData.value.name = auth.user.value.company.name;
    }

    if (auth.user.value?.company?.phone) {
        formData.value.phone = formatPhone(auth.user.value.company.phone);
    }

    if (auth.user.value?.company?.document) {
        formData.value.document = formatCpfCnpj(auth.user.value.company.document);
    }
});
</script>

<template>
    <div class="min-h-screen flex flex-col items-center justify-center bg-gradient-to-br from-amber-50 to-orange-100 dark:from-slate-950 dark:via-slate-900 dark:to-slate-950 p-4">
        <Head title="Configurar Empresa - MEU NEGÓCIO" />

        <div class="w-full max-w-md">
            <!-- Logo -->
            <div class="text-center mb-8">
                <img :src="logoUrl" alt="MEU NEGÓCIO" class="w-24 h-24 mx-auto mb-4" />
                <h1 class="text-3xl font-bold text-gray-800 dark:text-slate-100 mb-2">MEU NEGÓCIO</h1>
                <p class="text-gray-600 dark:text-slate-300">Defina os dados da sua empresa</p>
            </div>

            <!-- Card -->
            <div class="bg-white dark:bg-slate-900 rounded-lg shadow-lg p-8 border border-orange-100 dark:border-slate-700 border-t-4 border-t-orange-500">
                <!-- Success Message -->
                <Transition
                    enter-active-class="transition-all duration-300"
                    leave-active-class="transition-all duration-300"
                    enter-from-class="opacity-0 translate-y-2"
                    leave-to-class="opacity-0 translate-y-2"
                >
                    <div v-if="success" class="mb-6 p-4 bg-green-50 dark:bg-emerald-500/10 border border-green-200 dark:border-emerald-500/40 rounded-lg">
                        <p class="text-green-700 dark:text-emerald-200 font-medium flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            Dados salvos com sucesso!
                        </p>
                    </div>
                </Transition>

                <!-- Error Message -->
                <Transition
                    enter-active-class="transition-all duration-300"
                    leave-active-class="transition-all duration-300"
                    enter-from-class="opacity-0 translate-y-2"
                    leave-to-class="opacity-0 translate-y-2"
                >
                    <div v-if="error" class="mb-6 p-4 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/40 rounded-lg">
                        <p class="text-red-700 dark:text-red-200 font-medium">{{ error }}</p>
                    </div>
                </Transition>

                <form @submit.prevent="handleSubmit" class="space-y-5">
                    <!-- Nome da Empresa -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-slate-200 mb-2">
                            Nome da Empresa *
                        </label>
                        <input
                            id="name"
                            v-model="formData.name"
                            type="text"
                            required
                            placeholder="Digite o nome de sua empresa"
                            class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-950 text-gray-900 dark:text-slate-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 dark:focus:ring-amber-400/60 focus:border-transparent transition-all"
                        />
                    </div>

                    <!-- Email -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-slate-200 mb-2">
                            Email
                        </label>
                        <input
                            id="email"
                            v-model="formData.email"
                            type="email"
                            placeholder="contato@empresa.com.br"
                            class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-950 text-gray-900 dark:text-slate-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 dark:focus:ring-amber-400/60 focus:border-transparent transition-all"
                        />
                    </div>

                    <!-- Telefone -->
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-slate-200 mb-2">
                            Telefone
                        </label>
                        <input
                            id="phone"
                            v-model="formData.phone"
                            @input="handlePhoneInput"
                            type="tel"
                            placeholder="(11) 9999-9999"
                            class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-950 text-gray-900 dark:text-slate-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 dark:focus:ring-amber-400/60 focus:border-transparent transition-all"
                        />
                    </div>

                    <!-- CNPJ/CPF -->
                    <div>
                        <label for="document" class="block text-sm font-medium text-gray-700 dark:text-slate-200 mb-2">
                            CNPJ/CPF
                        </label>
                        <input
                            id="document"
                            v-model="formData.document"
                            @input="handleDocumentInput"
                            type="text"
                            placeholder="00.000.000/0000-00"
                            class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-950 text-gray-900 dark:text-slate-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 dark:focus:ring-amber-400/60 focus:border-transparent transition-all"
                        />
                    </div>

                    <!-- Submit Button -->
                    <button
                        type="submit"
                        :disabled="isLoading || success"
                        class="w-full bg-orange-500 hover:bg-orange-600 disabled:bg-gray-400 dark:disabled:bg-slate-600 text-white font-bold py-3 px-4 rounded-lg transition-colors mt-6"
                    >
                        <span v-if="isLoading" class="flex items-center justify-center">
                            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Salvando...
                        </span>
                        <span v-else-if="success">✓ Dados Salvos</span>
                        <span v-else>Salvar e Continuar</span>
                    </button>
                </form>

                <p class="text-center text-gray-500 dark:text-slate-400 text-xs mt-4">
                    Você pode atualizar estes dados depois
                </p>
            </div>
        </div>
    </div>
</template>
