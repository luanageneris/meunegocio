/**
 * Retorna a URL base da API
 * Em desenvolvimento: http://localhost:8000
 * Em produção: / (mesmo domínio)
 */
export const useApiUrl = () => {
    const getApiUrl = (path: string = ''): string => {
        if (typeof window === 'undefined') {
            return `http://localhost:8000${path}`;
        }

        // Em produção, usar o mesmo domínio (path relativo)
        const isDevelopment = window.location.hostname === 'localhost' && 
                             window.location.port === '5173';

        if (isDevelopment) {
            return `http://localhost:8000${path}`;
        }

        // Em produção, usar path relativo
        return path;
    };

    return {
        getApiUrl,
    };
};
