import { useAuth } from '@/composables/useAuth';

export const authenticateRoute = (to: any, from: any, next: any) => {
    const auth = useAuth();
    
    if (auth.isAuthenticated.value) {
        next();
    } else {
        window.location.href = '/login';
    }
};
