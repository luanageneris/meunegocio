<script setup lang="ts">
import axios from 'axios';
import { ref } from 'vue';
import SleepIcon from '@/components/SleepIcon.vue';

interface Option {
    id: number;
    name: string;
}

const props = defineProps<{ kind: string; id: number; name: string }>();
const emit = defineEmits<{ deleted: []; sleeping: []; cancel: [] }>();
const loading = ref(false);
const error = ref('');
const needsReplacement = ref(false);
const replacementId = ref<number | ''>('');
const options = ref<Option[]>([]);

const loadOptions = async () => {
    try {
        const { data } = await axios.get(`/api/personalizar/${props.kind}`, { params: { include_defaults: 1 } });
        options.value = (data.data ?? data).filter((option: Option) => option.id !== props.id);
    } catch {
        error.value = 'Não foi possível carregar os substitutos. Tente novamente ou coloque o item para dormir.';
    }
};

const retryOptions = async () => {
    loading.value = true;
    error.value = '';
    await loadOptions();
    loading.value = false;
};

const remove = async () => {
    if (needsReplacement.value && !replacementId.value) {
        error.value = 'Selecione um item substituto ou coloque este item para dormir.';
        return;
    }
    loading.value = true;
    error.value = '';
    try {
        await axios.delete(`/api/personalizar/${props.kind}/${props.id}`, {
            data: replacementId.value ? { replacement_id: replacementId.value } : {},
        });
        emit('deleted');
    } catch (e) {
        if (axios.isAxiosError(e) && e.response?.data?.code === 'replacement_required') {
            needsReplacement.value = true;
            error.value = e.response.data.message;
            await loadOptions();
        } else {
            error.value = axios.isAxiosError(e)
                ? (e.response?.data?.errors?.replacement_id?.[0] || e.response?.data?.message || 'Não foi possível remover o item.')
                : 'Não foi possível remover o item.';
        }
    } finally {
        loading.value = false;
    }
};

const sleep = async () => {
    loading.value = true;
    error.value = '';
    try {
        await axios.patch(`/api/personalizar/${props.kind}/${props.id}/status`, { active: false });
        emit('sleeping');
    } catch (e) {
        error.value = axios.isAxiosError(e) ? (e.response?.data?.message || 'Não foi possível colocar o item para dormir.') : 'Não foi possível colocar o item para dormir.';
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <div class="mt-2 space-y-3 rounded border-l-4 border-red-500 bg-red-50/80 p-4 dark:bg-red-950/30">
        <p class="text-sm font-semibold">Tem certeza que deseja remover "{{ name }}"?</p>
        <p v-if="error" role="alert" class="text-sm text-red-700 dark:text-red-300">{{ error }}</p>
        <label v-if="needsReplacement" class="block text-sm">
            Substituto para todos os registros:
            <select v-model="replacementId" class="mt-1 w-full rounded border border-gray-300 bg-white p-2 text-gray-900" :disabled="loading">
                <option value="">Selecione um substituto</option>
                <option v-for="option in options" :key="option.id" :value="option.id">{{ option.name }}</option>
            </select>
        </label>
        <button v-if="needsReplacement && !options.length" type="button" :disabled="loading" @click="retryOptions" class="text-sm text-blue-700 underline">Recarregar substitutos</button>
        <div class="flex flex-wrap justify-end gap-2">
            <button type="button" :disabled="loading" @click="emit('cancel')" class="rounded border px-3 py-2 text-sm disabled:opacity-50">Cancelar</button>
            <button type="button" :disabled="loading" @click="sleep" aria-label="Colocar para dormir" title="Colocar para dormir" class="rounded border border-amber-500 px-3 py-2 text-sm text-amber-700 disabled:opacity-50 cursor-pointer disabled:cursor-not-allowed">
                <SleepIcon class="h-5 w-5" />
            </button>
            <button type="button" :disabled="loading" @click="remove" class="rounded bg-red-600 px-3 py-2 text-sm text-white disabled:opacity-50">{{ loading ? 'Aguarde...' : 'Remover' }}</button>
        </div>
    </div>
</template>
