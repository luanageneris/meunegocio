<script setup lang="ts">
import { onMounted } from 'vue';

onMounted(() => {
    // Pegar o token da URL
    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get('token');
    const user = urlParams.get('user');

    if (token && user) {
        // Salvar token no localStorage
        localStorage.setItem('auth_token', token);
        localStorage.setItem('user', user);

        // Redirecionar para dashboard
        window.location.href = '/dashboard';
    } else {
        // Se não tiver token, redirecionar para login
        window.location.href = '/login';
    }
});
</script>

<template>
    <div class="flex items-center justify-center min-h-screen bg-gradient-to-br from-amber-50 to-orange-50">
        <div class="text-center">
            <svg class="animate-spin h-12 w-12 text-orange-500 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <h2 class="text-2xl font-bold text-gray-800 mt-4">Autenticando...</h2>
            <p class="text-gray-600 mt-2">Conectando com sua conta Google</p>
        </div>
    </div>
</template>
