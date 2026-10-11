<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, onBeforeUnmount, onMounted } from 'vue';
import { useAuth } from '@/composables/useAuth';
import { usePrivacy } from '@/composables/usePrivacy';
import { useToast } from '@/composables/useToast';
import { Eye, EyeOff, Moon, Sun } from 'lucide-vue-next';
import logoUrl from '../../../img/icon-logo.svg';
import Footer from '@/components/Footer.vue';
import AppInlineNotice from '@/components/AppInlineNotice.vue';
import { useAppearance } from '@/composables/useAppearance';

const auth = useAuth();
const toast = useToast();
const isLoading = ref(false);
const showCookieBanner = ref(false);
const inlineErrorMessage = ref('');
const email = ref('');
const password = ref('');
const showPassword = ref(false);
const { isPrivacyMode, togglePrivacy } = usePrivacy();
const { appearance, updateAppearance } = useAppearance();

function toggleTheme() {
    updateAppearance(appearance.value === 'dark' ? 'light' : 'dark');
}

const dismissInlineErrorMessage = () => {
    inlineErrorMessage.value = '';
};

const normalizeLoginErrorMessage = (message: string) => {
    const normalized = String(message || '').trim().toLowerCase();
    const knownGenericCredentialErrors = [
        'these credentials do not match our records.',
        'auth.failed',
    ];

    if (knownGenericCredentialErrors.includes(normalized)) {
        return 'As credenciais informadas não correspondem aos nossos registros.';
    }

    return message;
};

const ensureAuthenticatedUserRedirect = async () => {
    const serverUser = await auth.fetchCurrentUser();

    if (!serverUser) {
        return false;
    }

    await redirectAfterAuthentication();
    return true;
};

const redirectAfterAuthentication = async () => {
    const serverUser = await auth.fetchCurrentUser();
    const currentEmail = (serverUser?.email || auth.user.value?.email || '').trim().toLowerCase();
    const destination = currentEmail === 'admin@meunegocio.cloud' ? '/admin/usuarios' : '/dashboard';
    window.location.href = destination;
};

const handleEmailLogin = () => {
    const trimmedEmail = email.value.trim();

    if (!trimmedEmail || !password.value) {
        inlineErrorMessage.value = 'Informe seu e-mail e sua senha para continuar.';
        return;
    }

    isLoading.value = true;
    inlineErrorMessage.value = '';

    router.post('/login', {
        email: trimmedEmail,
        password: password.value,
        remember: true,
    }, {
        preserveScroll: true,
        onSuccess: async () => {
            await redirectAfterAuthentication();
        },
        onError: (errors: Record<string, string>) => {
            const firstError = Object.values(errors)[0] || 'Não foi possível entrar no sistema.';
            inlineErrorMessage.value = normalizeLoginErrorMessage(firstError);
            isLoading.value = false;
        },
        onFinish: () => {
            isLoading.value = false;
        },
    });
};

const closeCookieBanner = () => {
    showCookieBanner.value = false;
    localStorage.setItem('cookiesAccepted', 'true');
};

const handlePageShow = async (event: PageTransitionEvent) => {
    if (!event.persisted) {
        return;
    }

    await ensureAuthenticatedUserRedirect();
};

onMounted(() => {
    window.addEventListener('pageshow', handlePageShow);

    void ensureAuthenticatedUserRedirect().then((wasRedirected) => {
        if (wasRedirected) {
            return;
        }

        const accepted = localStorage.getItem('cookiesAccepted');
        if (!accepted) {
            showCookieBanner.value = true;
        }

        const urlParams = new URLSearchParams(window.location.search);
        const error = urlParams.get('error');

        if (error === 'session_expired') {
            inlineErrorMessage.value = 'Sua sessão expirou durante o login. Tente novamente.';
        }
    });
});

onBeforeUnmount(() => {
    window.removeEventListener('pageshow', handlePageShow);
});
</script>

<template>
    <div class="min-h-screen flex flex-col relative bg-background">
        <Head title="Login - MEU NEGÓCIO" />

        <!-- Theme Toggle - fixed top right -->
        <button
            @click="toggleTheme"
            :aria-label="appearance === 'dark' ? 'Ativar modo claro' : 'Ativar modo escuro'"
            class="fixed top-4 right-4 z-50 inline-flex h-9 w-9 items-center justify-center rounded-full bg-white/80 dark:bg-card/80 backdrop-blur border border-gray-200 dark:border-border text-gray-600 dark:text-muted-foreground hover:text-orange-500 dark:hover:text-orange-400 shadow-sm transition-colors"
        >
            <Sun v-if="appearance === 'dark'" class="h-4 w-4" />
            <Moon v-else class="h-4 w-4" />
        </button>
        
        <!-- Cookie Banner - Top -->
        <Transition
            enter-active-class="transition-all duration-300 ease-out"
            enter-from-class="-translate-y-full opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition-all duration-300 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="-translate-y-full opacity-0"
        >
            <div 
                v-if="showCookieBanner" 
                class="fixed top-0 left-0 right-0 z-50 bg-amber-50 dark:bg-card border-b-2 border-amber-200 dark:border-border px-4 py-3 md:py-2 shadow-md"
            >
                <div class="container mx-auto flex flex-col md:flex-row items-center justify-between gap-3 md:gap-4 text-xs md:text-sm">
                    <p class="text-gray-700 dark:text-foreground text-center md:text-left flex-1">
                        Para garantir a melhor experiência em nosso site, utilizamos cookies. Ao continuar navegando, você concorda com o uso de cookies. 
                        <Link href="/politica-de-privacidade" class="text-orange-600 hover:text-orange-700 font-semibold underline">Saiba mais em nossa Política de Privacidade</Link>
                    </p>
                    <button
                        @click="closeCookieBanner"
                        class="text-gray-600 dark:text-muted-foreground hover:text-gray-800 dark:hover:text-foreground transition-colors p-1 md:ml-2 flex-shrink-0"
                        aria-label="Fechar banner de cookies"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </Transition>
        
        <!-- Main Content -->
        <div class="flex-1 flex" :class="{ 'pt-20 md:pt-14': showCookieBanner }">
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

        <!-- Right Side - Login Form -->
        <div class="w-full lg:w-1/2 flex items-center justify-center p-2 bg-gradient-to-br from-amber-50 to-orange-50 dark:bg-none dark:bg-background">
            <div class="w-full max-w-md">
                <!-- Logo for mobile -->
                <div class="lg:hidden text-center mb-8">
                    <img :src="logoUrl" alt="MEU NEGÓCIO" class="w-24 h-24 mx-auto mb-4" />
                    <h2 class="text-3xl font-bold text-orange-600">MEU NEGÓCIO</h2>
                </div>

                <!-- Login Card -->
                <div class="bg-white dark:bg-card rounded-2xl shadow-2xl dark:shadow-none dark:border-2 p-4 md:p-12 border border-orange-100 dark:border-border">
                    <div class="text-center mb-8">
                        <h3 class="text-2xl font-bold text-gray-800 dark:text-foreground mb-2">Bem-vindo!</h3>
                        <p class="text-gray-600 dark:text-muted-foreground">Faça login para continuar</p>
                    </div>

                    <AppInlineNotice
                        v-if="inlineErrorMessage"
                        class="mb-6"
                        variant="error"
                        title="Falha ao entrar"
                        :message="inlineErrorMessage"
                        @dismiss="dismissInlineErrorMessage"
                    />

                    <form @submit.prevent="handleEmailLogin" class="space-y-4">
                        <div>
                            <label for="email" class="mb-2 block text-sm font-medium text-gray-700 dark:text-foreground">E-mail</label>
                            <input
                                id="email"
                                v-model="email"
                                type="email"
                                autocomplete="email"
                                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-gray-800 shadow-sm outline-none transition focus:border-orange-400 focus:ring-2 focus:ring-orange-200 dark:border-border dark:bg-input dark:text-foreground"
                                placeholder="seu@email.com"
                            />
                        </div>

                        <div>
                            <label for="password" class="mb-2 block text-sm font-medium text-gray-700 dark:text-foreground">Senha</label>
                            <div class="relative">
                                <input
                                    id="password"
                                    v-model="password"
                                    :type="showPassword ? 'text' : 'password'"
                                    autocomplete="current-password"
                                    class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 pr-12 text-gray-800 shadow-sm outline-none transition focus:border-orange-400 focus:ring-2 focus:ring-orange-200 dark:border-border dark:bg-input dark:text-foreground"
                                    placeholder="••••••••"
                                />
                                <button
                                    type="button"
                                    @click="showPassword = !showPassword"
                                    class="absolute inset-y-0 right-3 flex items-center text-gray-500 hover:text-gray-700 dark:text-muted-foreground dark:hover:text-foreground"
                                    aria-label="Mostrar ou ocultar senha"
                                >
                                    <EyeOff v-if="showPassword" class="h-5 w-5" />
                                    <Eye v-else class="h-5 w-5" />
                                </button>
                            </div>
                        </div>

                        <button
                            type="submit"
                            :disabled="isLoading"
                            class="w-full rounded-xl bg-gradient-to-r from-orange-500 to-amber-500 px-4 py-3 font-semibold text-white shadow-lg shadow-orange-500/20 transition hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {{ isLoading ? 'Entrando...' : 'Entrar' }}
                        </button>
                    </form>

                    <div class="mt-4 space-y-4">
                        <button
                            @click="togglePrivacy"
                            class="w-full flex items-center justify-between gap-3 bg-gray-50 dark:bg-input border border-gray-200 dark:border-border rounded-xl py-3 px-4 text-gray-700 dark:text-foreground hover:bg-gray-100 dark:hover:bg-accent hover:border-orange-300 transition-all duration-200"
                        >
                            <div class="flex items-center gap-3">
                                <EyeOff v-if="isPrivacyMode" class="w-5 h-5 text-orange-500" />
                                <Eye v-else class="w-5 h-5 text-gray-500" />
                                <div class="text-left">
                                    <p class="text-sm font-medium">Modo Privacidade</p>
                                    <p class="text-xs text-gray-500 dark:text-muted-foreground">
                                        {{ isPrivacyMode ? 'Valores ocultos' : 'Valores visíveis' }}
                                    </p>
                                </div>
                            </div>
                            <div class="relative inline-block w-12 h-6 transition duration-200 ease-in-out">
                                <input
                                    type="checkbox"
                                    :checked="isPrivacyMode"
                                    class="opacity-0 w-0 h-0 peer"
                                />
                                <span
                                    class="absolute cursor-pointer top-0 left-0 right-0 bottom-0 bg-gray-300 rounded-full transition-all duration-200"
                                    :class="{ 'bg-orange-500': isPrivacyMode }"
                                >
                                    <span
                                        class="absolute h-5 w-5 left-0.5 bottom-0.5 bg-white rounded-full transition-transform duration-200"
                                        :class="{ 'translate-x-6': isPrivacyMode }"
                                    ></span>
                                </span>
                            </div>
                        </button>
                        <p class="text-xs text-gray-500 dark:text-muted-foreground text-center">
                            Ative para ocultar valores financeiros totais após o login
                        </p>

                    </div>
                </div>
            </div>
        </div>
        </div>
    </div>
    <Footer />
</template>
