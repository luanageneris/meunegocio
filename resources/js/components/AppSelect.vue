<script setup lang="ts" generic="T extends string | number">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { ChevronDown } from 'lucide-vue-next';

export type AppSelectOption<T extends string | number = string> = {
    value: T;
    label: string;
};

defineOptions({ inheritAttrs: false });

const props = defineProps<{
    modelValue: T;
    options: AppSelectOption<T>[];
    label: string;
    searchable?: boolean;
    createInputOption?: (query: string) => AppSelectOption<T> | null;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: T];
}>();

const root = ref<HTMLElement | null>(null);
const isOpen = ref(false);
const activeIndex = ref(0);
const query = ref('');
const selectedOption = computed(
    () => props.options.find((option) => option.value === props.modelValue) ?? props.options[0],
);
const availableOptions = computed(() => {
    const option = props.createInputOption?.(query.value.trim());
    if (!option || props.options.some((item) => item.value === option.value)) {
        return props.options;
    }
    return [...props.options, option];
});
const filteredOptions = computed(() => {
    const search = query.value.trim().toLocaleLowerCase('pt-BR');
    return search
        ? availableOptions.value.filter((option) =>
              `${option.label} ${option.value}`.toLocaleLowerCase('pt-BR').includes(search),
          )
        : availableOptions.value;
});

function toggle() {
    if (!isOpen.value) {
        query.value = '';
        activeIndex.value = Math.max(
            0,
            props.options.findIndex((option) => option.value === props.modelValue),
        );
    }
    isOpen.value = !isOpen.value;
}

function close() {
    isOpen.value = false;
    query.value = '';
}

function openSearch() {
    if (isOpen.value) return;
    query.value = '';
    activeIndex.value = Math.max(
        0,
        props.options.findIndex((option) => option.value === props.modelValue),
    );
    isOpen.value = true;
}

function handleSearchInput() {
    if (!isOpen.value) isOpen.value = true;
    activeIndex.value = 0;
}

function updateQuery(event: Event) {
    query.value = (event.target as HTMLInputElement).value;
    handleSearchInput();
}

function selectOption(option: AppSelectOption<T>) {
    emit('update:modelValue', option.value);
    close();
}

function handleKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        close();
        return;
    }

    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        if (!isOpen.value) {
            if (props.searchable) openSearch();
            else toggle();
            return;
        }

        const options = props.searchable ? filteredOptions.value : props.options;
        if (options.length === 0) return;
        const direction = event.key === 'ArrowDown' ? 1 : -1;
        activeIndex.value =
            (activeIndex.value + direction + options.length) % options.length;
        return;
    }

    if (event.key === 'Enter' && props.searchable) {
        event.preventDefault();
        if (!query.value.trim()) {
            close();
            return;
        }
        if (props.createInputOption) {
            const exactMatch = availableOptions.value.find(
                (option) =>
                    option.label.toLocaleLowerCase('pt-BR') === query.value.trim().toLocaleLowerCase('pt-BR') ||
                    String(option.value) === query.value.trim(),
            );
            if (exactMatch) {
                selectOption(exactMatch);
                return;
            }
            close();
            return;
        }
        const option = filteredOptions.value[0];
        if (option) selectOption(option);
        else close();
        return;
    }

    if (isOpen.value && (event.key === 'Enter' || (!props.searchable && event.key === ' '))) {
        event.preventDefault();
        const options = props.searchable ? filteredOptions.value : props.options;
        const option = options[activeIndex.value];
        if (option) selectOption(option);
    }
}

function handleDocumentClick(event: MouseEvent) {
    if (event.target instanceof Node && !root.value?.contains(event.target)) {
        close();
    }
}

onMounted(() => document.addEventListener('click', handleDocumentClick));
onUnmounted(() => document.removeEventListener('click', handleDocumentClick));
</script>

<template>
    <div ref="root" class="relative">
        <button
            v-if="!searchable"
            v-bind="$attrs"
            type="button"
            class="app-select-control inline-flex cursor-pointer items-center justify-between gap-5 text-left text-xs transition-colors hover:bg-orange-50 focus:outline-none dark:hover:bg-slate-800"
            :aria-label="label"
            aria-haspopup="listbox"
            :aria-expanded="isOpen"
            @click="toggle"
            @keydown="handleKeydown"
        >
            <span>{{ selectedOption?.label ?? '' }}</span>
            <ChevronDown
                class="h-4 w-4 shrink-0 text-primary transition-transform"
                :class="{ 'rotate-180': isOpen }"
                aria-hidden="true"
            />
        </button>
        <div v-else class="relative">
            <input
                v-bind="$attrs"
                :value="isOpen ? query : selectedOption?.label ?? ''"
                type="text"
                :aria-label="label"
                aria-haspopup="listbox"
                :aria-expanded="isOpen"
                autocomplete="off"
                class="app-select-control w-full pr-9 text-left text-xs"
                :placeholder="selectedOption?.label"
                @focus="openSearch"
                @input="updateQuery"
                @keydown="handleKeydown"
            />
            <ChevronDown
                class="pointer-events-none absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 text-primary transition-transform"
                :class="{ 'rotate-180': isOpen }"
                aria-hidden="true"
            />
        </div>

        <div
            v-if="isOpen"
            role="listbox"
            :aria-label="label"
            class="app-select-menu absolute left-0 top-full z-50 mt-1 min-w-full overflow-hidden p-1"
        >
            <button
                v-for="(option, index) in (searchable ? filteredOptions : availableOptions)"
                :key="option.value"
                type="button"
                role="option"
                :aria-selected="option.value === modelValue"
                class="app-select-option block w-full cursor-pointer whitespace-nowrap px-2 py-1.5 text-left text-xs"
                :class="{
                    'app-select-option-highlighted': index === activeIndex,
                    'app-select-option-selected': option.value === modelValue,
                }"
                @mouseenter="activeIndex = index"
                @click="selectOption(option)"
            >
                {{ option.label }}
            </button>
            <p
                v-if="searchable && query.trim() && filteredOptions.length === 0"
                class="px-2 py-1.5 text-xs text-muted-foreground"
            >
                Nenhuma opção válida encontrada.
            </p>
        </div>
    </div>
</template>
