<script setup lang="ts">
import axios from 'axios';
import { ref } from 'vue';
import { Sun } from 'lucide-vue-next';
import SleepIcon from '@/components/SleepIcon.vue';

const props = defineProps<{ kind: string; id: number; active: boolean }>();
const emit = defineEmits<{ updated: [active: boolean] }>();
const loading = ref(false);
const error = ref('');

const toggle = async () => {
    loading.value = true;
    error.value = '';
    try {
        await axios.patch(`/api/personalizar/${props.kind}/${props.id}/status`, { active: !props.active });
        emit('updated', !props.active);
    } catch (e) {
        error.value = axios.isAxiosError(e) ? (e.response?.data?.message || 'Não foi possível alterar o estado.') : 'Não foi possível alterar o estado.';
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <div>
        <button type="button" :disabled="loading" @click="toggle" :aria-label="loading ? 'Aguarde' : active ? 'Colocar para dormir' : 'Ativar'" :title="loading ? 'Aguarde' : active ? 'Colocar para dormir' : 'Ativar'" class="rounded-lg p-2 text-amber-700 hover:bg-amber-50 disabled:opacity-50 cursor-pointer disabled:cursor-not-allowed">
            <SleepIcon v-if="active" class="h-5 w-5" />
            <Sun v-else class="h-5 w-5" aria-hidden="true" />
        </button>
        <p v-if="error" role="alert" class="text-xs text-red-600">{{ error }}</p>
    </div>
</template>
