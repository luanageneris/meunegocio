<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useAuth } from '@/composables/useAuth';
import { usePrivacy } from '@/composables/usePrivacy';
import logoUrl from '../../img/icon-logo.svg';
import { Eye, EyeOff } from 'lucide-vue-next';

const props = defineProps<{
    activeRoute?: string;
}>();

const auth = useAuth();
const { isPrivacyMode, togglePrivacy } = usePrivacy();
const isOpen = ref(true);
const showMobileNav = ref(true);
const showLogoutModal = ref(false);

const isShortcutRoute = computed(() => ['dashboard', 'registros'].includes(props.activeRoute ?? ''));

const isTypingTarget = (target: EventTarget | null): boolean => {
    if (!(target instanceof HTMLElement)) {
        return false;
    }

    const tagName = target.tagName;
    return target.isContentEditable || tagName === 'INPUT' || tagName === 'TEXTAREA' || tagName === 'SELECT';
};

const handleGlobalKeydown = (event: KeyboardEvent) => {
    if (showLogoutModal.value) {
        if (event.key === 'Enter') {
            event.preventDefault();
            handleLogout();
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            showLogoutModal.value = false;
            return;
        }
    }

    if (!isShortcutRoute.value || isTypingTarget(event.target) || event.repeat) {
        return;
    }

    if (event.shiftKey && event.key.toLowerCase() === 'o') {
        event.preventDefault();
        togglePrivacy();
    }
};

onMounted(() => {
    // Restaurar estado do sidebar do localStorage
    const savedState = localStorage.getItem('sidebarOpen');
    if (savedState !== null) {
        isOpen.value = savedState === 'true';
    } else {
        // No mobile, começar fechado; no desktop, começar aberto
        isOpen.value = window.innerWidth >= 1024;
    }

    const savedMobileNavState = localStorage.getItem('mobileNavVisible');
    if (savedMobileNavState !== null) {
        showMobileNav.value = savedMobileNavState === 'true';
    }

    window.dispatchEvent(
        new CustomEvent('mobile-nav-visibility-changed', {
            detail: { visible: showMobileNav.value },
        }),
    );

    window.addEventListener('keydown', handleGlobalKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleGlobalKeydown);
});

const companyName = computed(() => auth.getCompanyName.value || 'Empresa');

const userRole = computed(() => String(auth.user.value?.role || '').toLowerCase());
const userEmail = computed(() => String(auth.user.value?.email || '').trim().toLowerCase());
const isRootUser = computed(() => userRole.value === 'root');
const isMasterRootUser = computed(() => isRootUser.value && userEmail.value === 'admin@meunegocio.cloud');

type MenuItem = {
    name: string;
    icon: string;
    route: string;
    key: string;
};

const menuItems = computed<MenuItem[]>(() => {
    if (isMasterRootUser.value) {
        return [
            { name: 'Acesso', icon: 'shield', route: '/admin/usuarios', key: 'admin-usuarios' },
            { name: 'Minha conta', icon: 'person', route: '/minha-conta', key: 'conta' },
        ];
    }

    if (isRootUser.value) {
        return [
            { name: 'Acesso', icon: 'shield', route: '/admin/usuarios', key: 'admin-usuarios' },
            { name: 'Minha conta', icon: 'person', route: '/minha-conta', key: 'conta' },
        ];
    }

    return [
        { name: 'Dashboard', icon: 'dashboard', route: '/dashboard', key: 'dashboard' },
        { name: 'Registros', icon: 'list', route: '/registros', key: 'registros' },
        { name: 'Personalizar', icon: 'settings', route: '/personalizar', key: 'personalizar' },
        { name: 'Colaboradores', icon: 'people', route: '/colaboradores', key: 'colaboradores' },
        { name: 'Histórico', icon: 'history', route: '/historico', key: 'historico' },
        { name: 'Minha conta', icon: 'person', route: '/minha-conta', key: 'conta' },
    ];
});

const isNavigating = ref(false);
const navigatingTo = ref<string | null>(null);

const isItemActive = (item: MenuItem): boolean => {
    return props.activeRoute === item.key || navigatingTo.value === item.key;
};

const isItemDisabled = (item: MenuItem): boolean => {
    return isNavigating.value || props.activeRoute === item.key || navigatingTo.value === item.key;
};

const handleMenuClick = (event: MouseEvent, item: MenuItem) => {
    if (isItemDisabled(item)) {
        event.preventDefault();
        return;
    }

    // Evita múltiplos cliques/disparos enquanto a navegação está processando.
    isNavigating.value = true;
    navigatingTo.value = item.key;
};

const handleLogout = async () => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    try {
        // Prioritize Fortify web logout to fully terminate session/cookies.
        await fetch('/logout', {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
        });

        // Best-effort API token cleanup for Sanctum tokens.
        await fetch('/api/auth/logout', {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Accept': 'application/json',
                'Authorization': `Bearer ${auth.token.value}`,
                'X-CSRF-TOKEN': csrfToken,
            },
        }).catch(() => null);

        auth.logout();
        window.location.href = '/login';
    } catch (error) {
        console.error('Erro ao fazer logout:', error);
        auth.logout();
        window.location.href = '/login';
    }
};

const toggleMobileNav = () => {
    showMobileNav.value = !showMobileNav.value;
    localStorage.setItem('mobileNavVisible', showMobileNav.value ? 'true' : 'false');
    window.dispatchEvent(
        new CustomEvent('mobile-nav-visibility-changed', {
            detail: { visible: showMobileNav.value },
        }),
    );
};
</script>

<template>
    <div>
        <!-- Sidebar -->
        <aside
            @copy.prevent
            :class="[
                'hidden lg:block fixed top-0 left-0 h-screen bg-white shadow-2xl transition-transform duration-300 z-40 select-none',
                'w-64',
                isOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
            ]"
        >
            <div class="flex flex-col h-full">
                <!-- Header -->
                <div class="p-6 border-b border-gray-200">
                    <p class="text-center text-orange-600 font-bold text-xs uppercase tracking-widest mb-1">
                        Meu Negócio
                    </p>
                    <div class="flex items-center justify-center my-4">
                        <div class="w-20 h-20 rounded-full bg-gradient-to-br from-orange-400 to-amber-500 flex items-center justify-center shadow-lg">
                            <img :src="logoUrl" alt="Logo" class="w-12 h-12" />
                        </div>
                    </div>
                    <p class="text-center text-gray-800 font-semibold text-md">
                        Olá, {{ companyName }}!
                    </p>
                </div>

                <!-- Menu Items -->
                <nav class="flex-1 overflow-y-auto py-4">
                    <a
                        v-for="item in menuItems"
                        :key="item.key"
                        :href="item.route"
                        @click="handleMenuClick($event, item)"
                        :aria-disabled="isItemDisabled(item)"
                        :class="[
                            'flex items-center gap-3 px-6 py-3 transition-all duration-200',
                            isItemActive(item)
                                ? 'bg-gradient-to-r from-orange-400 to-amber-500 text-white font-semibold shadow-md'
                                : 'text-gray-700 hover:bg-orange-50',
                            isItemDisabled(item) ? 'cursor-not-allowed opacity-90 pointer-events-none' : ''
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

                <!-- Privacy Toggle -->
                <div class="px-4 pb-2 border-t border-gray-200 pt-4">
                    <button
                        @click="togglePrivacy"
                        class="flex items-center justify-between w-full px-4 py-3 text-gray-700 hover:bg-orange-50 rounded-lg transition-all duration-200"
                    >
                        <div class="flex items-center gap-3">
                            <component :is="isPrivacyMode ? EyeOff : Eye" class="w-5 h-5 text-orange-500" />
                            <span class="text-sm font-medium">Modo Privacidade</span>
                        </div>
                        <span class="text-xs text-gray-500">
                            {{ isPrivacyMode ? 'Oculto' : 'Visível' }}
                        </span>
                    </button>
                </div>

                <!-- Logout Button -->
                <div class="p-4 border-t border-gray-200">
                    <button
                        @click="showLogoutModal = true"
                        class="flex items-center gap-3 w-full px-6 py-3 text-gray-700 hover:bg-orange-50 rounded-lg transition-all duration-200"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        <span>Sair</span>
                    </button>
                </div>
            </div>
        </aside>

        <!-- Bottom mobile navigation (icons only) -->
        <!-- Bottom mobile navigation (icons only) -->
        <nav
            v-if="showMobileNav"
            @copy.prevent
            class="lg:hidden fixed bottom-0 left-0 right-0 z-50 border-t border-orange-100 bg-white/95 backdrop-blur shadow-2xl select-none"
        >
            <div class="flex items-center w-full justify-start sm:justify-center gap-1 sm:gap-2 px-2 sm:px-3 py-2 overflow-x-auto sm:overflow-visible scroll-smooth scrollbar-hide min-h-20">
                <a
                    v-for="item in menuItems"
                    :key="item.key"
                    :href="item.route"
                    @click="handleMenuClick($event, item)"
                    :aria-disabled="isItemDisabled(item)"
                    :class="[
                        'flex flex-col items-center justify-center gap-0.5 rounded-xl px-2 sm:px-3 py-2 text-xs font-medium transition shrink-0 min-w-[60px] sm:min-w-[72px]',
                        isItemActive(item)
                            ? 'text-orange-600 bg-orange-50'
                            : 'text-gray-700 hover:text-orange-600',
                        isItemDisabled(item) ? 'cursor-not-allowed opacity-90 pointer-events-none' : ''
                    ]"
                >
                    <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path v-if="item.icon === 'dashboard'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        <path v-else-if="item.icon === 'settings'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path v-else-if="item.icon === 'list'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        <path v-else-if="item.icon === 'people'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        <path v-else-if="item.icon === 'history'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        <path v-else-if="item.icon === 'person'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        <path v-else-if="item.icon === 'help'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        <path v-else-if="item.icon === 'shield'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2l7 4v6c0 5-3.5 9-7 10-3.5-1-7-5-7-10V6l7-4z" />
                    </svg>
                    <span class="text-[10px] sm:text-[11px] leading-tight">{{ item.name }}</span>
                </a>
            </div>
        </nav>

        <!-- Toggle for mobile bottom nav -->
        <button
            @click="toggleMobileNav"
            :class="[
                'lg:hidden fixed right-3 z-40 p-2 rounded-full border border-orange-200 bg-white/90 text-orange-600 shadow-md backdrop-blur-sm hover:bg-orange-50 transition-all duration-300 ease-out z-50',
                showMobileNav ? 'bottom-16 translate-y-0' : 'bottom-6 translate-y-1'
            ]"
        >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                    v-if="showMobileNav"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M6 9l6 6 6-6"
                />
                <path
                    v-else
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M4 6h16M4 12h16M4 18h16"
                />
            </svg>
        </button>
    <!-- Logout Confirmation Modal -->
    <Teleport to="body">
        <div v-if="showLogoutModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
            <div class="bg-white rounded-2xl shadow-2xl p-6 w-full max-w-sm mx-4">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white mb-2">Sair da conta</h2>
                <p class="text-gray-600 text-sm mb-6">Tem certeza que deseja sair?</p>
                <div class="flex gap-3 justify-end">
                    <button
                        @click="showLogoutModal = false"
                        class="px-4 py-2 text-sm text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition"
                    >
                        Cancelar
                    </button>
                    <button
                        @click="handleLogout"
                        class="px-4 py-2 text-sm text-white bg-red-500 hover:bg-red-600 rounded-lg transition"
                    >
                        Sair
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

    </div>
</template>
