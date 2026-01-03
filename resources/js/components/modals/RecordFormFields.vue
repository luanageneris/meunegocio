<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    variant: 'entrada' | 'saida';
    form: any;
    tiposEntrada?: string[];
    tiposSaida: string[];
    colaboradores: string[];
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
                <option v-for="tipo in (tiposEntrada || [])" :key="tipo" :value="tipo">
                    {{ tipo }}
                </option>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Tipo de Saída
            </label>
            <select
                v-model="model.tipoSaida"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:border-transparent"
            >
                <option value="">Selecione</option>
                <option v-for="tipo in tiposSaida" :key="tipo" :value="tipo">
                    {{ tipo }}
                </option>
            </select>
        </div>
    </div>

    <!-- Resumo e Data -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Resumo
            </label>
            <select
                v-model="model.categoria"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:border-transparent"
            >
                <option value="Serviço">Serviço</option>
                <option value="Produtos">Produtos</option>
                <option value="Diversos">Diversos</option>
                <option value="Recursos">Recursos</option>
                <option value="Energia">Energia</option>
                <option value="Comissão">Comissão</option>
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

        <div>
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
        <div>
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
                <option v-for="colab in colaboradores" :key="colab" :value="colab">
                    {{ colab }}
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
