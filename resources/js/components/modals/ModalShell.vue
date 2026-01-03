<script setup lang="ts">
import { ref, watch, nextTick } from 'vue';

const props = defineProps<{
    show: boolean;
    widthClass?: string;
    panelClass?: string;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const overlayRef = ref<HTMLDivElement | null>(null);

watch(
    () => props.show,
    (val) => {
        if (val) {
            nextTick(() => overlayRef.value?.focus());
        }
    }
);

const handleClose = () => emit('close');
</script>

<template>
    <Teleport to="body">
        <Transition name="modal-fade">
            <div
                v-if="show"
                ref="overlayRef"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gradient-to-b from-white/80 via-amber-50/70 to-orange-50/60 backdrop-blur-sm"
                @click.self="handleClose"
                @keydown.esc.prevent="handleClose"
                tabindex="0"
                role="dialog"
                aria-modal="true"
            >
                <div
                    class="w-full max-h-[90vh] overflow-y-auto bg-white rounded-lg shadow-xl"
                    :class="[widthClass || 'max-w-2xl', panelClass]"
                    @click.stop
                >
                    <slot />
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.modal-fade-enter-active,
.modal-fade-leave-active {
    transition: opacity 0.2s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
    opacity: 0;
}

.modal-fade-enter-active > div,
.modal-fade-leave-active > div {
    transition: transform 0.2s ease;
}

.modal-fade-enter-from > div {
    transform: scale(0.97);
}

.modal-fade-leave-to > div {
    transform: scale(0.97);
}
</style>
