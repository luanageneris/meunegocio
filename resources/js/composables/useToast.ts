import { toast } from 'vue-sonner';

export function useToast() {
    const success = (title: string, options?: { description?: string }) => {
        toast.success(title, {
            duration: 4000,
            ...options,
        });
    };

    const error = (title: string, options?: { description?: string }) => {
        toast.error(title, {
            duration: 5000,
            ...options,
        });
    };

    const info = (title: string, options?: { description?: string }) => {
        toast.info(title, {
            duration: 4000,
            ...options,
        });
    };

    const warning = (title: string, options?: { description?: string }) => {
        toast.warning(title, {
            duration: 4000,
            ...options,
        });
    };

    return {
        success,
        error,
        info,
        warning,
    };
}
