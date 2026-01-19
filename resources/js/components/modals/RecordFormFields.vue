<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    variant: 'entrada' | 'saida';
    form: any;
    tiposEntrada?: Array<string | { id: number; name: string }>;
    tiposSaida: Array<string | { id: number; name: string }>;
    colaboradores: Array<string | { id: number; name: string }>;
    simpleMode?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:form', value: any): void;
}>();

const model = computed({
    get: () => props.form,
    set: (val) => emit('update:form', val),
});
</script>

<template>
    <!-- Tipo de Entrada/Saída -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div v-if="variant === 'entrada'">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Tipo de Entrada
            </label>
            <select
                v-model="model.tipoEntrada"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:border-transparent"
            >
                <option
                    v-for="tipo in (tiposEntrada || [])"
                    :key="(tipo as any)?.id ?? (tipo as any)"
                    :value="(tipo as any)?.name ?? (tipo as any)"
                >
                    {{ (tipo as any)?.name ?? (tipo as any) }}
                </option>
            </select>
        </div>
    </div>

    <!-- Tipo de Saída e Data -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Tipo de Saída
            </label>
            <select
                v-model="model.tipoSaida"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:border-transparent"
            >
                <option value="">Selecione</option>
                <option
                    v-for="tipo in tiposSaida"
                    :key="(tipo as any)?.id ?? (tipo as any)"
                    :value="(tipo as any)?.name ?? (tipo as any)"
                >
                    {{ (tipo as any)?.name ?? (tipo as any) }}
                </option>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Data
            </label>
            <input
                v-model="model.data"
                type="date"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:border-transparent"
            />
        </div>
    </div>

    <!-- Descrição -->
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
            Descrição
        </label>
        <input
            v-model="model.descricao"
            type="text"
            placeholder="Descrição"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:border-transparent"
        />
    </div>

    <!-- Valor e Quantidade -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Valor R$
            </label>
            <input
                v-model.number="model.valor"
                type="number"
                step="0.01"
                min="0"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:border-transparent"
            />
        </div>

        <div v-if="!simpleMode">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Quantidade
            </label>
            <input
                v-model.number="model.quantidade"
                type="number"
                min="1"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:border-transparent"
            />
        </div>
    </div>

    <!-- Desconto e Colaborador -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div v-if="!simpleMode">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Desconto
            </label>
            <input
                v-model.number="model.desconto"
                type="number"
                step="0.01"
                min="0"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:border-transparent"
            />
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Colaborador
            </label>
            <select
                v-model="model.colaborador"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:border-transparent"
            >
                <option value="">Selecione</option>
                <option
                    v-for="colab in colaboradores"
                    :key="(colab as any)?.id ?? (colab as any)"
                    :value="(colab as any)?.name ?? (colab as any)"
                >
                    {{ (colab as any)?.name ?? (colab as any) }}
                </option>
            </select>
        </div>
    </div>

    <!-- Observação -->
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
            Observação
        </label>
        <input
            v-model="model.observacao"
            type="text"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:border-transparent"
        />
    </div>
</template>
