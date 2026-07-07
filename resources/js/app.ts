import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { initializeTheme } from './composables/useAppearance';
import { useAuth } from './composables/useAuth';
import { Toaster } from 'vue-sonner';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Inicializar autenticação e interceptadores
const auth = useAuth();

// ⭐ CRÍTICO: Detectar se estamos em página pública antes de carregar usuário
const isPublicPage = () => {
    if (typeof window === 'undefined') return false;
    const path = window.location.pathname;
    return path === '/' ||
           path.includes('/login') || 
           path.includes('/registre-se') || 
           path.includes('/oauth/callback') ||
           path.includes('/auth/google') ||
           path.includes('/politica-de-privacidade');
};

// Função para renderizar a aplicação
const renderApp = () => {
    createInertiaApp({
        title: (title) => (title ? `${title} - ${appName}` : appName),
        resolve: (name) =>
            resolvePageComponent(
                `./pages/${name}.vue`,
                import.meta.glob<DefineComponent>('./pages/**/*.vue'),
            ),
        setup({ el, App, props, plugin }) {
            const app = createApp({ 
                render: () => h('div', [
                    h(Toaster, { 
                        position: 'top-right',
                        expand: true,
                        richColors: true,
                        closeButton: true,
                        toastOptions: {
                            style: {
                                background: 'transparent',
                            },
                        },
                    }),
                    h(App, props)
                ])
            });
            
            app.use(plugin)
                .component('Toaster', Toaster)
                .mount(el);
        },
        progress: {
            color: '#4B5563',
        },
    });
};

// Se está em página pública, renderiza sem tentar carregar usuário
if (isPublicPage()) {
    renderApp();
} else {
    // Se está em página protegida, tenta carregar usuário da sessão
    auth.fetchCurrentUser().then(() => {
        renderApp();
    }).catch((error) => {
        console.error('Falha ao carregar autenticação:', error);
        // Aplicação ainda renderiza mesmo com erro de autenticação
        renderApp();
    });
}

// This will set light / dark mode on page load...
initializeTheme();
