<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import axios from 'axios';
import Sidebar from '@/components/Sidebar.vue';
import PageHeader from '@/components/PageHeader.vue';
import { useAuth } from '@/composables/useAuth';
import { useToast } from '@/composables/useToast';

interface SupportRequest {
    subject: string;
    message: string;
}

const auth = useAuth();
const toast = useToast();
const isLoading = ref(false);
const form = ref<SupportRequest>({
    subject: '',
    message: '',
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
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-amber-50 to-orange-100 flex">
        <Head title="Ajuda e Suporte - MEU NEGÓCIO" />

        <Sidebar active-route="suporte" />

        <div class="flex-1 lg:ml-64 transition-all duration-300">
            <PageHeader title="Ajuda e Suporte" container-class="max-w-6xl" />

            <main class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div class="bg-white rounded-xl shadow-lg p-8">
                    <h2 class="text-2xl font-bold text-center text-orange-600 mb-2">Ajuda e Suporte</h2>
                    <p class="text-center text-gray-600 mb-8">
                        Conte-nos o seu problema, nos e responderemos o mais breve possível!
                    </p>

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
</template>
