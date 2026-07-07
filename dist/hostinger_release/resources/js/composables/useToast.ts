import { toast } from 'vue-sonner';

export function useToast() {
    const success = (title: string, options?: { description?: string }) => {
        toast.success(title, {
            duration: 5000,
            position: 'top-right',
            ...options,
        });
    };

    const error = (title: string, options?: { description?: string }) => {
        toast.error(title, {
            duration: 6000,
            position: 'top-right',
            ...options,
        });
    };

    const info = (title: string, options?: { description?: string }) => {
        toast.info(title, {
            duration: 5000,
            position: 'top-right',
            ...options,
        });
    };

    const warning = (title: string, options?: { description?: string }) => {
        toast.warning(title, {
            duration: 5000,
            position: 'top-right',
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
