<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import logoUrl from '@/../../resources/img/icon-logo.svg';

const isLoading = ref(false);
const showCookieBanner = ref(false);

const handleGoogleLogin = () => {
    isLoading.value = true;
    // Redirecionar para o endpoint GET que faz o OAuth com Google
    window.location.href = '/auth/google';
};

const closeCookieBanner = () => {
    showCookieBanner.value = false;
    localStorage.setItem('cookiesAccepted', 'true');
};

// Check if cookies were already accepted
onMounted(() => {
    const accepted = localStorage.getItem('cookiesAccepted');
    if (!accepted) {
        showCookieBanner.value = true;
    }
});
</script>

<template>
    <div class="min-h-screen flex flex-col relative">
        <Head title="Login - MEU NEGÓCIO" />
        
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
                class="fixed top-0 left-0 right-0 z-50 bg-amber-50 border-b-2 border-amber-200 px-4 py-3 md:py-2 shadow-md"
            >
                <div class="container mx-auto flex flex-col md:flex-row items-center justify-between gap-3 md:gap-4 text-xs md:text-sm">
                    <p class="text-gray-700 text-center md:text-left flex-1">
                        Para garantir a melhor experiência em nosso site, utilizamos cookies. Ao continuar navegando, você concorda com o uso de cookies. 
                        <Link href="/politica-de-privacidade" class="text-orange-600 hover:text-orange-700 font-semibold underline">Saiba mais em nossa Política de Privacidade</Link>
                    </p>
                    <button
                        @click="closeCookieBanner"
                        class="text-gray-600 hover:text-gray-800 transition-colors p-1 md:ml-2 flex-shrink-0"
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
        <div class="w-full lg:w-1/2 flex items-center justify-center p-8 bg-gradient-to-br from-amber-50 to-orange-50">
            <div class="w-full max-w-md">
                <!-- Logo for mobile -->
                <div class="lg:hidden text-center mb-8">
                    <img :src="logoUrl" alt="MEU NEGÓCIO" class="w-24 h-24 mx-auto mb-4" />
                    <h2 class="text-3xl font-bold text-orange-600">MEU NEGÓCIO</h2>
                </div>

                <!-- Login Card -->
                <div class="bg-white rounded-2xl shadow-2xl p-8 md:p-12 border border-orange-100">
                    <div class="text-center mb-8">
                        <h3 class="text-2xl font-bold text-gray-800 mb-2">Bem-vindo!</h3>
                        <p class="text-gray-600">Faça login para continuar</p>
                    </div>

                    <!-- Google Login Button -->
                    <button
                        @click="handleGoogleLogin"
                        :disabled="isLoading"
                        class="w-full flex items-center justify-center gap-3 bg-white border-2 border-gray-300 rounded-xl py-4 px-6 text-gray-700 font-semibold hover:bg-gray-50 hover:border-orange-400 transition-all duration-300 transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed shadow-md hover:shadow-xl"
                    >
                        <svg v-if="!isLoading" class="w-6 h-6" viewBox="0 0 24 24">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                        </svg>
                        
                        <svg v-if="isLoading" class="animate-spin h-6 w-6 text-orange-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        
                        <span>{{ isLoading ? 'Conectando...' : 'Entrar com Google' }}</span>
                    </button>
        
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
