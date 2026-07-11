import { ref, computed } from 'vue';
import axios from 'axios';

// ⭐ CRÍTICO: Configurar axios globalmente para enviar cookies
axios.defaults.withCredentials = true;

let token = ref<string | null>(null);
let user = ref<any>(null);
const isLoading = ref(false);
const DRAFT_STORAGE_PREFIX = 'draft:';

const clearDraftStorage = () => {
    if (typeof window === 'undefined') {
        return;
    }

    const keysToRemove: string[] = [];

    for (let index = 0; index < window.sessionStorage.length; index += 1) {
        const key = window.sessionStorage.key(index);

        if (key && key.startsWith(DRAFT_STORAGE_PREFIX)) {
            keysToRemove.push(key);
        }
    }

    keysToRemove.forEach((key) => {
        window.sessionStorage.removeItem(key);
    });
};

export const useAuth = () => {
    // Carregar usuário atual do servidor (via sessão/cookie)
    const fetchCurrentUser = async () => {
        try {
            const response = await axios.get('/api/auth/user', {
                withCredentials: true, // ⭐ CRÍTICO: Enviar cookies entre portas
            });
            const userData = response.data?.user;
            if (userData) {
                user.value = userData;
                return userData;
            }
        } catch (error) {
            // Usuário não autenticado
            user.value = null;
        }
        return null;
    };

    const setupAxiosInterceptor = () => {
        axios.interceptors.request.use(
            (config) => {
                return config;
            },
            (error) => Promise.reject(error)
        );

        axios.interceptors.response.use(
            (response) => response,
            (error) => {
                // Se erro 401, usuário não está mais autenticado
                if (error.response?.status === 401) {
                    logout();
                    if (typeof window !== 'undefined') {
                        // ⭐ NÃO redirecionar se já estamos em página de login/registro
                        const currentPath = window.location.pathname;
                        const isAuthPage = currentPath.includes('/login') || 
                                          currentPath.includes('/registre-se') || 
                                          currentPath.includes('/oauth/callback') ||
                                          currentPath.includes('/auth/google');
                        
                        if (!isAuthPage) {
                            window.location.href = '/login?error=unauthorized';
                        }
                    }
                }
                return Promise.reject(error);
            }
        );
    };

    setupAxiosInterceptor();

    const setUser = (newUser: any) => {
        user.value = newUser;
    };

    const logout = () => {
        token.value = null;
        user.value = null;
        clearDraftStorage();
    };

    const isAuthenticated = computed(() => !!user.value);

    const getUserName = computed(() => user.value?.name || 'Usuário');

    const getCompanyName = computed(() => user.value?.company?.name || 'Empresa');

    return {
        token: computed(() => token.value),
        user: computed(() => user.value),
        isLoading,
        isAuthenticated,
        getUserName,
        getCompanyName,
        setUser,
        logout,
        fetchCurrentUser,
    };
};
