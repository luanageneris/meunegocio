import { ref, computed } from 'vue';
import axios from 'axios';

const token = ref<string | null>(localStorage.getItem('auth_token'));
const user = ref<any>(localStorage.getItem('user') ? JSON.parse(localStorage.getItem('user')!) : null);
const isLoading = ref(false);

export const useAuth = () => {
    // Configurar axios com token
    const setupAxiosInterceptor = () => {
        axios.interceptors.request.use(
            (config) => {
                if (token.value) {
                    config.headers.Authorization = `Bearer ${token.value}`;
                }
                return config;
            },
            (error) => Promise.reject(error)
        );

        axios.interceptors.response.use(
            (response) => response,
            (error) => {
                // Se erro 401, limpar token e redirecionar para login
                if (error.response?.status === 401) {
                    logout();
                    window.location.href = '/login?error=unauthorized';
                }
                return Promise.reject(error);
            }
        );
    };

    const setToken = (newToken: string) => {
        token.value = newToken;
        localStorage.setItem('auth_token', newToken);
        setupAxiosInterceptor();
    };

    const setUser = (newUser: any) => {
        user.value = newUser;
        localStorage.setItem('user', JSON.stringify(newUser));
    };

    const logout = () => {
        token.value = null;
        user.value = null;
        localStorage.removeItem('auth_token');
        localStorage.removeItem('user');
    };

    const isAuthenticated = computed(() => !!token.value && !!user.value);

    const getUserName = computed(() => user.value?.name || 'Usuário');

    const getCompanyName = computed(() => user.value?.company?.name || 'Empresa');

    // Configurar interceptador ao inicializar
    setupAxiosInterceptor();

    return {
        token: computed(() => token.value),
        user: computed(() => user.value),
        isLoading,
        isAuthenticated,
        getUserName,
        getCompanyName,
        setToken,
        setUser,
        logout,
    };
};
