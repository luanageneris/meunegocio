<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted, computed, watch } from 'vue';
import { useAuth } from '@/composables/useAuth';
import { useApiUrl } from '@/composables/useApiUrl';
import logoUrl from '../../../img/icon-logo.svg';
import Footer from '@/components/Footer.vue';
import AppInlineNotice from '@/components/AppInlineNotice.vue';

const auth = useAuth();
const { getApiUrl } = useApiUrl();
const isLoading = ref(false);
const isSubmitting = ref(false);
const inlineSuccessMessage = ref('');
const inlineErrorMessage = ref('');
const registerDraftStorageKey = 'draft:auth.register';
const autoSubmitStorageKey = 'registration_auto_submit';
const registrationSuccessToastKey = 'registration_success_toast';

// Dados do formulário
const googleUser = ref<any>(null);
const form = ref({
    company_name: '',
});

const errors = ref<Record<string, string[]>>({});
const hasGoogleData = computed(() => !!googleUser.value);

const getRegisterErrorDescription = (result: any) => {
    if (result?.errors?.company_name?.length) {
        return result.errors.company_name[0];
    }

    if (typeof result?.message === 'string' && !result.message.includes('SQLSTATE')) {
        return result.message;
    }

    return 'Nao foi possivel concluir o cadastro agora. Tente novamente em instantes.';
};

const restoreFormDraft = () => {
    if (typeof window === 'undefined') {
        return;
    }

    const savedDraft = window.sessionStorage.getItem(registerDraftStorageKey);

    if (!savedDraft) {
        return;
    }

    try {
        const parsed = JSON.parse(savedDraft);
        const draftCompanyName = parsed?.company_name;

        if (typeof draftCompanyName === 'string' && !form.value.company_name) {
            form.value.company_name = draftCompanyName;
        }
    } catch {
        window.sessionStorage.removeItem(registerDraftStorageKey);
    }
};

const persistFormDraft = () => {
    if (typeof window === 'undefined') {
        return;
    }

    window.sessionStorage.setItem(registerDraftStorageKey, JSON.stringify({
        company_name: form.value.company_name,
    }));
};

const clearFormDraft = () => {
    if (typeof window === 'undefined') {
        return;
    }

    window.sessionStorage.removeItem(registerDraftStorageKey);
};

const shouldAutoSubmit = () => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.sessionStorage.getItem(autoSubmitStorageKey) === '1';
};

const markAutoSubmit = () => {
    if (typeof window === 'undefined') {
        return;
    }

    window.sessionStorage.setItem(autoSubmitStorageKey, '1');
};

const clearAutoSubmit = () => {
    if (typeof window === 'undefined') {
        return;
    }

    window.sessionStorage.removeItem(autoSubmitStorageKey);
};

const dismissInlineSuccessMessage = () => {
    inlineSuccessMessage.value = '';
};

const dismissInlineErrorMessage = () => {
    inlineErrorMessage.value = '';
};

const markRegistrationSuccessToast = (message: string) => {
    if (typeof window === 'undefined') {
        return;
    }

    window.sessionStorage.setItem(registrationSuccessToastKey, message);
};

const getCookieValue = (name: string): string | null => {
    if (typeof document === 'undefined') {
        return null;
    }

    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);

    if (parts.length === 2) {
        return parts.pop()?.split(';').shift() ?? null;
    }

    return null;
};

const ensureCsrfToken = async () => {
    await fetch(getApiUrl('/sanctum/csrf-cookie'), {
        method: 'GET',
        credentials: 'include',
    });

    const xsrfToken = getCookieValue('XSRF-TOKEN');
    return xsrfToken ? decodeURIComponent(xsrfToken) : null;
};

const fetchPendingRegistration = async () => {
    isLoading.value = true;
    try {
        const response = await fetch('/api/auth/pending-registration', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
            },
            credentials: 'include',
        });

        if (!response.ok) {
            console.error(`Erro ao buscar registro pendente: ${response.status} ${response.statusText}`);
            const errorBody = await response.text();
            console.error('Resposta:', errorBody);
            googleUser.value = null;
            clearAutoSubmit();
            return false;
        }

        const result = await response.json();
        console.log('Resultado pendente:', result);

        if (result.pending === true && result.data) {
            googleUser.value = result.data;
            return true;
        }

        googleUser.value = null;
        clearAutoSubmit();
        return false;
    } catch (error: any) {
        console.error('Erro ao buscar registro pendente:', error);
        inlineErrorMessage.value = 'Não conseguimos carregar seus dados de registro. Tente fazer login novamente.';
        return false;
    } finally {
        isLoading.value = false;
    }
};

const initializeRegistrationFlow = async () => {
    // Se usuário já está autenticado, redirecionar para registros
    if (auth.isAuthenticated.value) {
        window.location.href = '/registros';
        return;
    }

    restoreFormDraft();
    // ⭐ CRÍTICO: Verificar se google_data está na URL (vindo do callback)
    const urlParams = new URLSearchParams(window.location.search);
    const googleDataParam = urlParams.get('google_data');
    
    let hasPendingRegistration = false;
    if (googleDataParam) {
        try {
            const decoded = JSON.parse(atob(googleDataParam));
            googleUser.value = decoded;
            hasPendingRegistration = true;
            // Limpar a URL para evitar recarregar com parâmetro
            window.history.replaceState({}, document.title, '/registre-se');
        } catch (error) {
            console.error('Erro ao decodificar google_data:', error);
        }
    } else {
        // Fallback: tentar buscar da API (para outros casos)
        hasPendingRegistration = await fetchPendingRegistration();
    }
    
    console.log('Has pending registration:', hasPendingRegistration, 'googleUser:', googleUser.value);

    if (hasPendingRegistration && hasGoogleData.value && form.value.company_name.trim()) {
        clearAutoSubmit();
        await handleSubmit();
    }
};

onMounted(() => {
    initializeRegistrationFlow();
});

watch(
    () => form.value,
    () => {
        persistFormDraft();
    },
    { deep: true }
);

const handleGoogleConnect = () => {
    persistFormDraft();
    markAutoSubmit();
    window.location.href = getApiUrl('/auth/google');
};

const handleSubmit = async () => {
    if (!hasGoogleData.value) {
        handleGoogleConnect();
        return;
    }

    isSubmitting.value = true;
    errors.value = {};
    inlineErrorMessage.value = '';
    inlineSuccessMessage.value = '';
    persistFormDraft();

    try {
        const csrfToken = await ensureCsrfToken();

        const response = await fetch('/api/auth/register', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                ...(csrfToken ? { 'X-XSRF-TOKEN': csrfToken } : {}),
            },
            credentials: 'include',
            body: JSON.stringify({
                company_name: form.value.company_name.trim() || null,
                google_user: hasGoogleData.value
                    ? {
                        name: googleUser.value?.name || null,
                        email: googleUser.value?.email || null,
                        google_id: googleUser.value?.google_id || null,
                    }
                    : null,
            }),
        });

        const result = await response.json();

        if (!response.ok) {
            errors.value = result.errors || {};
            inlineErrorMessage.value = getRegisterErrorDescription(result);
            return;
        }

        // Salvar usuário
        auth.setUser(result.data.user);
        clearFormDraft();
        clearAutoSubmit();
        const successMessage = result?.data?.message || 'Conta criada com sucesso!';
        inlineSuccessMessage.value = successMessage;
        markRegistrationSuccessToast(successMessage);

        // Redirecionar para registros
        setTimeout(() => {
            window.location.href = '/registros';
        }, 900);
    } catch (error: any) {
        inlineErrorMessage.value = 'Nao foi possivel concluir o cadastro agora. Tente novamente em instantes.';
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
                    <img :src="logoUrl" alt="MEU NEGÓCIO" class="w-24 h-24 mx-auto mb-8 drop-shadow-2xl" />
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
            <div class="w-full lg:w-1/2 flex items-center justify-center p-2 bg-gradient-to-br from-amber-50 to-orange-50">
                <div class="w-full max-w-md">
                    <!-- Logo for mobile -->
                    <div class="lg:hidden text-center mb-8">
                        <img :src="logoUrl" alt="MEU NEGÓCIO" class="w-24 h-24 mx-auto mb-4" />
                        <h2 class="text-3xl font-bold text-orange-600">MEU NEGÓCIO</h2>
                    </div>

                    <!-- Registration Card -->
                    <div class="bg-white rounded-2xl shadow-2xl p-4 md:p-12 border border-orange-100">
                        <div class="text-center mb-8">
                            <h3 class="text-2xl font-bold text-gray-800 mb-2">Criar conta</h3>
                            <p class="text-gray-600 text-sm">Complete seus dados para começar</p>
                        </div>

                        <AppInlineNotice
                            v-if="inlineSuccessMessage"
                            class="mb-6"
                            variant="success"
                            title="Conta criada"
                            :message="inlineSuccessMessage"
                            @dismiss="dismissInlineSuccessMessage"
                        />

                        <AppInlineNotice
                            v-if="inlineErrorMessage"
                            class="mb-6"
                            variant="error"
                            title="Não foi possível criar a conta"
                            :message="inlineErrorMessage"
                            @dismiss="dismissInlineErrorMessage"
                        />

                        <!-- User Info Display -->
                        <div v-if="googleUser" class="mb-6 space-y-3">
                            <div class="p-4 bg-emerald-50 border-l-4 border-emerald-400 rounded-lg">
                                <p class="text-xs text-emerald-700 uppercase font-semibold mb-3 flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 5v8a2 2 0 01-2 2h-5l-5 4v-4H4a2 2 0 01-2-2V5a2 2 0 012-2h12a2 2 0 012 2zm-11-1a1 1 0 11-2 0 1 1 0 012 0z" clip-rule="evenodd"/></svg>
                                    Conta Google Selecionada
                                </p>
                                <div class="flex items-center gap-3 bg-white p-3 rounded-lg border border-emerald-200">
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-emerald-400 to-emerald-600 shadow flex items-center justify-center flex-shrink-0">
                                        <svg class="w-5 h-5 text-white" viewBox="0 0 533.5 544.3" xmlns="http://www.w3.org/2000/svg">
                                            <path fill="white" d="M533.5 278.4c0-17.4-1.5-34.1-4.3-50.3H272v95.2h146.9c-6.3 33.9-25 62.6-53.2 81.8v68.1h85.9c50.2-46.3 81.9-114.6 81.9-194.8z"/>
                                            <path fill="white" d="M272 544.3c72.6 0 133.5-24 178-65.3l-85.9-68.1c-23.8 16-54.3 25.5-92.1 25.5-70.8 0-130.8-47.7-152.3-111.7H30.7v70.2c44.4 88 134.5 149.4 241.3 149.4z"/>
                                            <path fill="white" d="M119.7 324.7c-10.4-31-10.4-64.4 0-95.4V159h-89c-38.7 77.3-38.7 168.1 0 245.4l89-69.7z"/>
                                            <path fill="white" d="M272 107.7c39.5-.6 77.4 14.5 106.4 42.3l79.7-79.7C431.3 24.2 373.9 0 272 0 165.2 0 75.1 61.4 30.7 149.4l89 70.2C141.2 155.4 201.2 107.7 272 107.7z"/>
                                        </svg>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs text-gray-500 uppercase tracking-wide">Email</p>
                                        <p class="text-sm text-gray-800 font-medium break-all">{{ googleUser.email }}</p>
                                        <p class="text-xs text-gray-600 mt-1">{{ googleUser.name }}</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Change Email Button -->
                            <button
                                type="button"
                                :disabled="isSubmitting"
                                @click="handleGoogleConnect"
                                class="w-full flex items-center justify-center gap-2 border-2 border-emerald-400 text-emerald-700 rounded-lg py-2 px-4 font-medium hover:bg-emerald-50 transition-all disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m14.836 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-14.837-2m14.837 2H15" />
                                </svg>
                                <span>Usar outra conta Google</span>
                            </button>
                        </div>

                        <!-- Company Name Input -->
                        <form @submit.prevent="handleSubmit" class="space-y-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    Nome da sua empresa <span class="text-gray-400">(opcional)</span>
                                </label>
                                <input
                                    v-model="form.company_name"
                                    @input="errors = {}"
                                    type="text"
                                    placeholder="Ex: Xerografia & Cia"
                                    :disabled="isSubmitting"
                                    class="w-full px-4 py-3 border-2 border-orange-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent disabled:bg-gray-100 disabled:cursor-not-allowed transition-all"
                                />
                                <p v-if="errors.company_name" class="text-sm text-red-600 mt-2">
                                    {{ errors.company_name[0] }}
                                </p>
                                <p class="text-xs text-gray-500 mt-1">
                                    Você poderá editar mais informações após criar a conta.
                                </p>
                            </div>

                            <div v-if="!hasGoogleData" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                                <p class="font-semibold mb-2">Próximo passo: Selecione sua conta Google</p>
                                <p class="text-sm">Clique no botão abaixo para selecionar uma conta Google. Você poderá deixar o nome da empresa em branco e criaremos um nome padrão automaticamente.</p>
                            </div>

                            <!-- Submit Button -->
                            <button
                                type="submit"
                                :disabled="isSubmitting || !hasGoogleData"
                                class="w-full flex items-center justify-center gap-2 bg-gradient-to-r from-amber-400 to-orange-500 text-white rounded-lg py-3 px-4 font-semibold hover:shadow-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed transform hover:scale-105 disabled:hover:scale-100"
                            >
                                <svg v-if="!isSubmitting && hasGoogleData" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <svg v-if="isSubmitting" class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>
                                    {{
                                        isSubmitting
                                            ? 'Criando conta...'
                                            : 'Criar minha conta'
                                    }}
                                </span>
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

                </div>
            </div>
        </div>
        <Footer/>
    </div>
</template>

