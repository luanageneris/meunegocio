<script setup lang="ts">
import { onMounted } from 'vue';
import axios from 'axios';
import { useAuth } from '@/composables/useAuth';
import { useApiUrl } from '@/composables/useApiUrl';

const auth = useAuth();
const { getApiUrl } = useApiUrl();

const hasPendingRegistration = async (): Promise<boolean> => {
    try {
        const response = await axios.get(getApiUrl('/api/auth/pending-registration'), {
            withCredentials: true,
        });

        return response.data?.pending === true;
    } catch {
        return false;
    }
};

onMounted(async () => {
    try {
        const response = await axios.get(getApiUrl('/api/auth/user'), {
            withCredentials: true,
        });
        const userData = response.data?.user;

        if (!userData) {
            window.location.href = getApiUrl('/login?error=no_user_data');
            return;
        }

        auth.setUser(userData);

        const needsSetup = !userData.company?.name || userData.company.name === '';

        setTimeout(() => {
            if (needsSetup) {
                window.location.href = getApiUrl('/setup-empresa');
            } else {
                window.location.href = getApiUrl('/dashboard');
            }
        }, 800);
    } catch (error: any) {
        console.error('Erro ao processar autenticação:', error);
        // Só redireciona para registro se houver pendência real.
        if (error.response?.status === 401) {
            const pending = await hasPendingRegistration();

            if (pending) {
                window.location.href = getApiUrl('/registre-se');
                return;
            }

            window.location.href = getApiUrl('/login?error=session_expired');
        } else {
            window.location.href = getApiUrl('/login?error=oauth_failed');
        }
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
