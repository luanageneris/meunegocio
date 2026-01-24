<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import { useAuth } from '@/composables/useAuth';
import { useToast } from '@/composables/useToast';
import logoUrl from '@/../../resources/img/icon-logo.svg';

const auth = useAuth();
const toast = useToast();
const isLoading = ref(false);
const isSubmitting = ref(false);

// Dados do formulário
const googleUser = ref<any>(null);
const form = ref({
    company_name: '',
});

const errors = ref<Record<string, string[]>>({});
const hasGoogleData = computed(() => !!googleUser.value);

const fetchPendingRegistration = async () => {
    isLoading.value = true;
    try {
        const response = await fetch('/api/auth/pending-registration', {
            headers: {
                'Accept': 'application/json',
            },
            credentials: 'include',
        });

        if (!response.ok) {
            // Se não houver pendência, refaça o login Google para criar o cookie
            window.location.href = '/auth/google';
            return;
        }

        const result = await response.json();
        googleUser.value = result.data;
    } catch (error: any) {
        window.location.href = '/login?error=pending_fetch_failed';
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    // Se usuário já está autenticado, redirecionar para painel
    if (auth.isAuthenticated.value) {
        window.location.href = '/painel';
        return;
    }

    fetchPendingRegistration();
});

const handleSubmit = async () => {
    if (!hasGoogleData.value) {
        toast.error('Conecte com o Google para continuar');
        return;
    }

    if (!form.value.company_name.trim()) {
        errors.value = { company_name: ['O nome da empresa é obrigatório'] };
        return;
    }

    isSubmitting.value = true;
    errors.value = {};

    try {
        const response = await fetch('/api/auth/register', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            credentials: 'include',
            body: JSON.stringify({
                company_name: form.value.company_name,
            }),
        });

        const result = await response.json();

        if (!response.ok) {
            errors.value = result.errors || {};
            if (result.message) {
                toast.error('Erro ao registrar', { description: result.message });
            }
            return;
        }

        // Salvar token e usuário
        auth.setToken(result.data.token);
        auth.setUser(result.data.user);

        toast.success('Conta criada com sucesso!', {
            description: `Bem-vindo, ${result.data.user.name}!`,
        });

        // Redirecionar para painel
        setTimeout(() => {
            window.location.href = '/painel';
        }, 500);
    } catch (error: any) {
        toast.error('Erro ao registrar', {
            description: error?.message || 'Ocorreu um erro inesperado',
        });
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <div class="min-h-screen flex flex-col relative">
        <Head title="Registre-se - MEU NEGÓCIO" />

        <!-- Main Content -->
        <div class="flex-1 flex">
            <!-- Left Side - Branding -->
            <div class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-orange-400 via-orange-500 to-amber-500 p-12 flex-col justify-center items-center relative overflow-hidden">
                <!-- Decorative circles -->
                <div class="absolute top-10 right-10 w-64 h-64 bg-white opacity-10 rounded-full"></div>
                <div class="absolute bottom-20 left-10 w-96 h-96 bg-white opacity-5 rounded-full"></div>

                <div class="relative z-10 text-center text-white max-w-md">
                    <img :src="logoUrl" alt="MEU NEGÓCIO" class="w-48 h-48 mx-auto mb-8 drop-shadow-2xl" />
                    <h1 class="text-5xl font-bold mb-6 drop-shadow-lg">MEU NEGÓCIO</h1>
                    <p class="text-xl leading-relaxed opacity-95 font-medium">
                        Liberte o potencial do seu negócio!
                    </p>
                    <p class="mt-6 text-md opacity-90">
                        Controle financeiro completo para pequenas empresas
                    </p>
                </div>
            </div>

            <!-- Right Side - Registration Form -->
            <div class="w-full lg:w-1/2 flex items-center justify-center p-8 bg-gradient-to-br from-amber-50 to-orange-50">
                <div class="w-full max-w-md">
                    <!-- Logo for mobile -->
                    <div class="lg:hidden text-center mb-8">
                        <img :src="logoUrl" alt="MEU NEGÓCIO" class="w-24 h-24 mx-auto mb-4" />
                        <h2 class="text-3xl font-bold text-orange-600">MEU NEGÓCIO</h2>
                    </div>

                    <!-- Registration Card -->
                    <div class="bg-white rounded-2xl shadow-2xl p-8 md:p-12 border border-orange-100">
                        <div class="text-center mb-8">
                            <h3 class="text-2xl font-bold text-gray-800 mb-2">Criar conta</h3>
                            <p class="text-gray-600 text-sm">Complete seus dados para começar</p>
                        </div>

                        <!-- User Info Display -->
                        <div v-if="googleUser" class="mb-6 p-4 bg-orange-50 border border-orange-200 rounded-lg">
                            <p class="text-xs text-orange-600 uppercase font-semibold mb-2">Conectado com</p>
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-white shadow flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-orange-500" viewBox="0 0 533.5 544.3" xmlns="http://www.w3.org/2000/svg">
                                        <path fill="#4285f4" d="M533.5 278.4c0-17.4-1.5-34.1-4.3-50.3H272v95.2h146.9c-6.3 33.9-25 62.6-53.2 81.8v68.1h85.9c50.2-46.3 81.9-114.6 81.9-194.8z"/>
                                        <path fill="#34a853" d="M272 544.3c72.6 0 133.5-24 178-65.3l-85.9-68.1c-23.8 16-54.3 25.5-92.1 25.5-70.8 0-130.8-47.7-152.3-111.7H30.7v70.2c44.4 88 134.5 149.4 241.3 149.4z"/>
                                        <path fill="#fbbc04" d="M119.7 324.7c-10.4-31-10.4-64.4 0-95.4V159h-89c-38.7 77.3-38.7 168.1 0 245.4l89-69.7z"/>
                                        <path fill="#ea4335" d="M272 107.7c39.5-.6 77.4 14.5 106.4 42.3l79.7-79.7C431.3 24.2 373.9 0 272 0 165.2 0 75.1 61.4 30.7 149.4l89 70.2C141.2 155.4 201.2 107.7 272 107.7z"/>
                                    </svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm text-gray-700 font-medium break-all">{{ googleUser.name }}</p>
                                    <p class="text-xs text-gray-500 break-all">{{ googleUser.email }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Company Name Input -->
                        <form @submit.prevent="handleSubmit" class="space-y-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    Nome da sua empresa <span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model="form.company_name"
                                    type="text"
                                    placeholder="Ex: Xerografia & Cia"
                                    :disabled="isSubmitting || !hasGoogleData"
                                    class="w-full px-4 py-3 border-2 border-orange-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent disabled:bg-gray-100 disabled:cursor-not-allowed transition-all"
                                />
                                <p v-if="errors.company_name" class="text-sm text-red-600 mt-2">
                                    {{ errors.company_name[0] }}
                                </p>
                                <p class="text-xs text-gray-500 mt-1">
                                    Você poderá editar mais informações após criar a conta.
                                </p>
                            </div>

                            <!-- Submit Button -->
                            <button
                                type="submit"
                                :disabled="isSubmitting || !form.company_name.trim() || !hasGoogleData"
                                class="w-full flex items-center justify-center gap-2 bg-gradient-to-r from-amber-400 to-orange-500 text-white rounded-lg py-3 px-4 font-semibold hover:shadow-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed transform hover:scale-105 disabled:hover:scale-100"
                            >
                                <svg v-if="!isSubmitting" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <svg v-if="isSubmitting" class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>{{ isSubmitting ? 'Criando conta...' : 'Criar minha conta' }}</span>
                            </button>

                            <!-- Back to Login Link -->
                            <div class="text-center pt-4 border-t border-orange-100">
                                <p class="text-sm text-gray-600">
                                    Já tem conta?
                                    <Link href="/login" class="text-orange-600 hover:text-orange-700 font-semibold underline">
                                        Faça login aqui
                                    </Link>
                                </p>
                            </div>
                        </form>
                    </div>

                    <!-- Footer -->
                    <div class="mt-8 text-center text-sm text-gray-500 space-y-2">
                        <p>&copy; {{ new Date().getFullYear() }} | MEU NEGÓCIO. Todos os direitos reservados.</p>
                        <div class="flex justify-center gap-4 text-xs font-semibold">
                            <Link href="/politica-de-privacidade" class="text-orange-600 hover:text-orange-700 underline">Política de Privacidade</Link>
                            <Link href="/suporte" class="text-orange-600 hover:text-orange-700 underline">Ajuda e Suporte</Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

