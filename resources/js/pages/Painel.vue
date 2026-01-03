<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import { useAuth } from '@/composables/useAuth';

const auth = useAuth();
const company = ref<any>(null);
const isLoading = ref(true);

const handleLogout = async () => {
    try {
        await fetch('/api/auth/logout', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${auth.token.value}`,
            },
        });

        auth.logout();
        window.location.href = '/login';
    } catch (error) {
        console.error('Erro ao fazer logout:', error);
    }
};

onMounted(async () => {
    try {
        const response = await fetch('/api/company', {
            headers: {
                'Authorization': `Bearer ${auth.token.value}`,
            },
        });

        if (response.ok) {
            const data = await response.json();
            company.value = data.data;
        }
    } catch (error) {
        console.error('Erro ao carregar empresa:', error);
    } finally {
        isLoading.value = false;
    }
});
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-amber-50 to-orange-100">
        <Head title="Painel - MEU NEGÓCIO" />

        <!-- Header -->
        <header class="bg-white shadow-sm border-b border-orange-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <h1 class="text-2xl font-bold text-gray-800">{{ company?.name || 'MEU NEGÓCIO' }}</h1>
                </div>
                <div class="flex items-center gap-4">
                    <div class="text-right">
                        <p class="text-sm text-gray-600">Bem-vindo,</p>
                        <p class="font-semibold text-gray-800">{{ auth.getUserName.value }}</p>
                    </div>
                    <button
                        @click="handleLogout"
                        class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg transition-colors text-sm font-medium"
                    >
                        Sair
                    </button>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div v-if="isLoading" class="flex items-center justify-center min-h-96">
                <div class="text-center">
                    <svg class="animate-spin h-12 w-12 text-orange-500 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="text-gray-600">Carregando dados...</p>
                </div>
            </div>

            <div v-else class="space-y-6">
                <!-- Welcome Card -->
                <div class="bg-white rounded-lg shadow-md border-l-4 border-orange-500 p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-2">Bem-vindo ao seu painel!</h2>
                    <p class="text-gray-600">Esta é a página inicial do seu negócio. Aqui você poderá gerenciar toda a sua operação.</p>
                </div>

                <!-- Grid de Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Card: Registros -->
                    <a href="#" class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow p-6 border-t-4 border-blue-500 cursor-pointer">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-800">Registros</h3>
                            <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <p class="text-gray-600 text-sm">Gerenciar entradas, saídas e comissões</p>
                    </a>

                    <!-- Card: Personalizar -->
                    <a href="#" class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow p-6 border-t-4 border-purple-500 cursor-pointer">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-800">Personalizar</h3>
                            <svg class="w-8 h-8 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <p class="text-gray-600 text-sm">Colaboradores, tipos e configurações</p>
                    </a>

                    <!-- Card: Dashboard -->
                    <a href="#" class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow p-6 border-t-4 border-green-500 cursor-pointer">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-800">Dashboard</h3>
                            <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                        </div>
                        <p class="text-gray-600 text-sm">Gráficos e análises de negócio</p>
                    </a>

                    <!-- Card: Importar -->
                    <a href="#" class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow p-6 border-t-4 border-orange-500 cursor-pointer">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-800">Importar</h3>
                            <svg class="w-8 h-8 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                        </div>
                        <p class="text-gray-600 text-sm">Importar dados via Excel</p>
                    </a>
                </div>

                <!-- Company Info Card -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Informações da Empresa</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-600">Nome</p>
                            <p class="font-medium text-gray-800">{{ company?.name || 'Não preenchido' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Email</p>
                            <p class="font-medium text-gray-800">{{ company?.email || 'Não preenchido' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Telefone</p>
                            <p class="font-medium text-gray-800">{{ company?.phone || 'Não preenchido' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">CNPJ/CPF</p>
                            <p class="font-medium text-gray-800">{{ company?.document || 'Não preenchido' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>
