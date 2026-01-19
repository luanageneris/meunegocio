<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useAuth } from '@/composables/useAuth';
import logoUrl from '@/../../resources/img/icon-logo.svg';

const props = defineProps<{
    activeRoute?: string;
}>();

const auth = useAuth();
const isOpen = ref(true);

onMounted(() => {
    // Restaurar estado do sidebar do localStorage
    const savedState = localStorage.getItem('sidebarOpen');
    if (savedState !== null) {
        isOpen.value = savedState === 'true';
    } else {
        // No mobile, começar fechado; no desktop, começar aberto
        isOpen.value = window.innerWidth >= 1024;
    }
});

const userName = computed(() => auth.getUserName.value || 'Usuário');
const companyName = computed(() => auth.getCompanyName.value || 'Empresa');

const menuItems = [
    { name: 'Painel', icon: 'dashboard', route: '/painel', key: 'painel' },
    { name: 'Personalizar', icon: 'settings', route: '/personalizar', key: 'personalizar' },
    { name: 'Registros', icon: 'list', route: '/registros', key: 'registros' },
    { name: 'Colaboradores', icon: 'people', route: '/colaboradores', key: 'colaboradores' },
    { name: 'Histórico', icon: 'history', route: '/historico', key: 'historico' },
    { name: 'Minha conta', icon: 'person', route: '/minha-conta', key: 'conta' },
    { name: 'Ajuda e Suporte', icon: 'help', route: '/suporte', key: 'suporte' },
    { name: 'Política de Privacidade', icon: 'shield', route: '/politica-de-privacidade', key: 'privacidade' },
];

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
        auth.logout();
        window.location.href = '/login';
    }
};

const toggleSidebar = () => {
    isOpen.value = !isOpen.value;
    // Salvar estado no localStorage
    localStorage.setItem('sidebarOpen', isOpen.value ? 'true' : 'false');
};
</script>

<template>
    <div>
        <!-- Mobile Toggle Button -->
        <button
            @click="toggleSidebar"
            class="lg:hidden fixed top-4 left-4 z-50 p-2 bg-orange-500 text-white rounded-lg shadow-lg"
        >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>

        <!-- Overlay for mobile -->
        <div
            v-if="isOpen"
            @click="toggleSidebar"
            class="lg:hidden fixed inset-0 bg-black/50 z-30"
        ></div>

        <!-- Sidebar -->
        <aside
            :class="[
                'fixed top-0 left-0 h-screen bg-white shadow-2xl transition-transform duration-300 z-40',
                'w-64',
                isOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
            ]"
        >
            <div class="flex flex-col h-full">
                <!-- Header -->
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-center mb-4">
                        <div class="w-20 h-20 rounded-full bg-gradient-to-br from-orange-400 to-amber-500 flex items-center justify-center shadow-lg">
                            <img :src="logoUrl" alt="Logo" class="w-12 h-12" />
                        </div>
                    </div>
                    <p class="text-center text-gray-800 font-semibold text-lg">
                        Olá, {{ companyName }}!
                    </p>
                </div>

                <!-- Menu Items -->
                <nav class="flex-1 overflow-y-auto py-4">
                    <a
                        v-for="item in menuItems"
                        :key="item.key"
                        :href="item.route"
                        :class="[
                            'flex items-center gap-3 px-6 py-3 transition-all duration-200',
                            props.activeRoute === item.key
                                ? 'bg-gradient-to-r from-orange-400 to-amber-500 text-white font-semibold shadow-md'
                                : 'text-gray-700 hover:bg-orange-50'
                        ]"
                    >
                        <!-- Icon -->
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <!-- Dashboard -->
                            <path v-if="item.icon === 'dashboard'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            
                            <!-- Settings -->
                            <path v-else-if="item.icon === 'settings'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            
                            <!-- List -->
                            <path v-else-if="item.icon === 'list'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            
                            <!-- People -->
                            <path v-else-if="item.icon === 'people'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            
                            <!-- History -->
                            <path v-else-if="item.icon === 'history'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            
                            <!-- Person -->
                            <path v-else-if="item.icon === 'person'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            
                            <!-- Help -->
                            <path v-else-if="item.icon === 'help'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />

                            <!-- Shield -->
                            <path v-else-if="item.icon === 'shield'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2l7 4v6c0 5-3.5 9-7 10-3.5-1-7-5-7-10V6l7-4z" />
                        </svg>

                        <span>{{ item.name }}</span>
                    </a>
                </nav>

                <!-- Logout Button -->
                <div class="p-4 border-t border-gray-200">
                    <button
                        @click="handleLogout"
                        class="flex items-center gap-3 w-full px-6 py-3 text-gray-700 hover:bg-red-50 hover:text-red-600 rounded-lg transition-all duration-200"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        <span>Sair</span>
                    </button>
                </div>
            </div>
        </aside>
    </div>
</template>
