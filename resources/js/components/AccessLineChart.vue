<script setup lang="ts">
import { onMounted, onUnmounted, ref, watch } from 'vue';
import {
    Chart,
    CategoryScale,
    LinearScale,
    LineController,
    LineElement,
    PointElement,
    Tooltip,
} from 'chart.js';

Chart.register(CategoryScale, LinearScale, LineController, LineElement, PointElement, Tooltip);

const props = defineProps<{
    title: string;
    labels: string[];
    counts: number[];
    minWidth: string;
}>();

const canvas = ref<HTMLCanvasElement | null>(null);
let chart: Chart<'line'> | null = null;

onMounted(() => {
    if (!canvas.value) return;

    chart = new Chart(canvas.value, {
        type: 'line',
        data: {
            labels: props.labels,
            datasets: [{
                label: 'Tentativas',
                data: props.counts,
                borderColor: '#ea580c',
                backgroundColor: '#ea580c',
                pointRadius: 3,
                pointHoverRadius: 5,
                tension: 0.2,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } },
                x: { ticks: { maxRotation: 0, autoSkip: false } },
            },
        },
    });
});

watch(() => [props.labels, props.counts], () => {
    if (!chart) return;
    chart.data.labels = props.labels;
    chart.data.datasets[0].data = props.counts;
    chart.update();
});

onUnmounted(() => chart?.destroy());
</script>

<template>
    <div>
        <div class="overflow-x-auto">
            <div class="h-72" :style="{ minWidth }">
                <canvas ref="canvas" role="img" :aria-label="title" />
            </div>
        </div>
        <details class="mt-3 text-sm text-gray-700 dark:text-slate-200">
            <summary class="cursor-pointer">Ver dados do gráfico</summary>
            <div class="mt-2 max-h-60 overflow-auto">
                <table class="w-full text-left">
                    <thead><tr><th>Período</th><th>Tentativas</th></tr></thead>
                    <tbody>
                        <tr v-for="(label, index) in labels" :key="index">
                            <td>{{ label }}</td><td>{{ counts[index] }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </details>
    </div>
</template>
