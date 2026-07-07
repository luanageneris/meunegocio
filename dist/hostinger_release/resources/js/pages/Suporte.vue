<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import axios from 'axios';
import Sidebar from '@/components/Sidebar.vue';
import PageHeaderTitle from '@/components/PageHeaderTitle.vue';
import { useToast } from '@/composables/useToast';
import Footer from '@/components/Footer.vue';
import { Info, MessageCircle } from 'lucide-vue-next';

interface SupportRequest {
    subject: string;
    message: string;
    email?: string;
}

const page = usePage();
const toast = useToast();
const isLoading = ref(false);

const isAuthenticated = computed(() => Boolean((page.props as Record<string, any>)?.auth?.user));

const form = ref<SupportRequest>({
    subject: '',
    message: '',
    email: '',
});

const handleSubmit = async () => {
    if (!form.value.subject.trim()) {
        toast.error('Campo obrigatório', { description: 'Assunto é obrigatório' });
        return;
    }

    if (!form.value.message.trim()) {
        toast.error('Campo obrigatório', { description: 'Mensagem é obrigatória' });
        return;
    }

    if (!isAuthenticated.value && !form.value.email?.trim()) {
        toast.error('Campo obrigatório', { description: 'E-mail é obrigatório' });
        return;
    }

    isLoading.value = true;
    try {
        await axios.post('/api/support', {
            subject: form.value.subject,
            message: form.value.message,
        });

        toast.success('Mensagem enviada com sucesso!', {
            description: 'Responderemos o mais breve possível.',
        });

        form.value = {
            subject: '',
            message: '',
            email: '',
        };
    } catch (error: any) {
        const message = error?.response?.data?.message || 'Erro ao enviar mensagem';
        toast.error('Erro ao enviar', { description: message });
    } finally {
        isLoading.value = false;
    }
};

const handleKeydown = (e: KeyboardEvent) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        handleSubmit();
    }
};

const goBack = () => {
    if (window.history.length > 1) {
        window.history.back();
        return;
    }

    window.location.href = '/';
};
</script>

<template>
    <div v-if="isAuthenticated" class="min-h-screen bg-gradient-to-br from-amber-50 to-orange-100 flex overflow-x-hidden">
        <Head title="Ajuda e Suporte - MEU NEGÓCIO" />

        <Sidebar active-route="suporte" />

        <div class="flex-1 min-w-0 lg:ml-64 transition-all duration-300 overflow-x-hidden">
            <PageHeaderTitle 
                title="Ajuda e Suporte" 
                subtitle="Conte-nos o seu problema, nos responderemos o mais breve possível."
            />

            <main class="w-full min-w-0 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8 overflow-x-hidden">
                <div class="bg-white rounded-xl shadow-lg p-8">

                    <form @submit.prevent="handleSubmit" class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Assunto</label>
                            <input
                                v-model="form.subject"
                                type="text"
                                @keydown.enter.prevent="handleSubmit"
                                placeholder="Descreva brevemente o assunto"
                                class="w-full px-4 py-3 border-2 border-amber-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Mensagem</label>
                            <textarea
                                v-model="form.message"
                                @keydown="handleKeydown"
                                placeholder="Descreva seu problema em detalhes..."
                                rows="8"
                                class="w-full px-4 py-3 border-2 border-amber-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent resize-none"
                            ></textarea>
                            <p class="mt-1 text-xs text-gray-500">Use Ctrl+Enter ou Cmd+Enter para enviar</p>
                        </div>

                        <button
                            type="submit"
                            :disabled="isLoading"
                            class="w-full px-6 py-3 bg-gradient-to-r from-amber-400 to-orange-500 text-white rounded-lg font-medium hover:shadow-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {{ isLoading ? 'Enviando...' : 'Enviar' }}
                        </button>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <!-- Versão pública para usuários não autenticados -->
    <div v-else class="min-h-screen bg-gradient-to-br from-amber-50 via-white to-orange-100 text-gray-900">
        <Head title="Ajuda e Suporte - MEU NEGÓCIO" />

        <header class="max-w-6xl mx-auto px-6 py-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="h-12 w-12 rounded-2xl bg-orange-500 text-white flex items-center justify-center text-2xl font-black shadow-lg">MN</div>
                <div>
                    <p class="text-sm uppercase tracking-[0.2em] text-orange-600">Meu Negocio</p>
                    <p class="text-lg font-semibold text-gray-800">Ajuda e Suporte</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    @click="goBack"
                    class="px-4 py-2 rounded-full font-semibold border border-orange-200 text-orange-700 hover:border-orange-400 hover:text-orange-800 transition"
                >
                    Voltar
                </button>
                <Link
                    v-if="!isAuthenticated"
                    href="/login"
                    class="px-5 py-2 rounded-full font-semibold bg-orange-500 text-white shadow-md hover:bg-orange-600 transition"
                >
                    Fazer login
                </Link>
            </div>
        </header>

        <main class="w-full min-w-0 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 pb-16 overflow-x-hidden">
            <div class="bg-white rounded-xl shadow-lg p-8 space-y-6">
                <div class="text-center">
                    <h2 class="text-3xl font-bold text-orange-600 mb-2">Ajuda e Suporte</h2>
                    <p class="text-gray-600">
                        Para enviar uma mensagem de suporte, faça login na sua conta.
                    </p>
                </div>

                <div class="bg-orange-50 border border-orange-200 rounded-lg p-6 space-y-4">
                    <p class="font-semibold text-gray-900 flex items-center gap-2">
                        <Info class="h-5 w-5 text-orange-600 shrink-0" />
                        <span>Acesso ao suporte</span>
                    </p>
                    <p class="text-sm text-gray-700">
                        Use sua conta para abrir solicitações de suporte. Se ainda não tiver cadastro, crie uma conta para começar.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <Link
                            href="/login"
                            class="px-5 py-2 rounded-lg bg-orange-500 text-white font-semibold hover:bg-orange-600 transition text-sm text-center"
                        >
                            Fazer login
                        </Link>
                        <Link
                            href="/register"
                            class="px-5 py-2 rounded-lg border border-orange-300 text-orange-700 font-semibold hover:border-orange-500 hover:text-orange-800 transition text-sm text-center"
                        >
                            Criar conta
                        </Link>
                    </div>
                    <p class="text-sm text-gray-700 flex items-center gap-2">
                        <MessageCircle class="h-4 w-4 text-orange-600 shrink-0" />
                        <span>Contato alternativo: suporte@meunegocio.app</span>
                    </p>
                </div>
            </div>
            <Footer />
        </main>
    </div>
</template>

