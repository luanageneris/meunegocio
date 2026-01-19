import { ref, readonly } from 'vue';

const PRIVACY_KEY = 'privacy_mode';

// Estado global reativo
const isPrivacyMode = ref<boolean>(false);

// Inicializar do localStorage
if (typeof window !== 'undefined') {
    const saved = localStorage.getItem(PRIVACY_KEY);
    isPrivacyMode.value = saved === 'true';
}

export function usePrivacy() {
    /**
     * Ativa ou desativa o modo de privacidade
     */
    function togglePrivacy() {
        isPrivacyMode.value = !isPrivacyMode.value;
        saveToStorage();
    }

    /**
     * Define o estado do modo de privacidade
     */
    function setPrivacy(value: boolean) {
        isPrivacyMode.value = value;
        saveToStorage();
    }

    /**
     * Salva o estado no localStorage
     */
    function saveToStorage() {
        if (typeof window !== 'undefined') {
            localStorage.setItem(PRIVACY_KEY, String(isPrivacyMode.value));
        }
    }

    /**
     * Mascara um valor monetário
     */
    function maskValue(value: string | number): string {
        if (isPrivacyMode.value) {
            return '•••••';
        }
        return String(value);
    }

    /**
     * Formata e mascara um valor de moeda
     */
    function formatCurrencyPrivate(value: number): string {
        const formatted = value.toLocaleString('pt-BR', {
            style: 'currency',
            currency: 'BRL',
        });
        
        if (isPrivacyMode.value) {
            return 'R$ •••••';
        }
        return formatted;
    }

    return {
        isPrivacyMode: readonly(isPrivacyMode),
        togglePrivacy,
        setPrivacy,
        maskValue,
        formatCurrencyPrivate,
    };
}
