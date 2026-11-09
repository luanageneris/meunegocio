<script setup lang="ts">
import { computed, ref } from 'vue';
import { getLocalTimeZone, parseDate, today, type DateValue } from '@internationalized/date';
import { CalendarDays, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import {
    DatePickerCalendar, DatePickerCell, DatePickerCellTrigger, DatePickerContent,
    DatePickerField, DatePickerGrid, DatePickerGridBody, DatePickerGridHead,
    DatePickerGridRow, DatePickerHeadCell, DatePickerHeader, DatePickerHeading,
    DatePickerInput, DatePickerNext, DatePickerPrev, DatePickerRoot, DatePickerTrigger,
} from 'reka-ui';

defineOptions({ inheritAttrs: false });

const props = withDefaults(defineProps<{
    modelValue: string;
    disabled?: boolean;
    autoOpen?: boolean;
    inlineEditor?: boolean;
}>(), { disabled: false, autoOpen: false, inlineEditor: false });
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();
const open = ref(props.autoOpen);

const date = computed<DateValue | undefined>({
    get: () => props.modelValue ? parseDate(props.modelValue) : undefined,
    set: (value) => emit('update:modelValue', value?.toString() ?? ''),
});

function selectToday() {
    date.value = today(getLocalTimeZone());
    open.value = false;
}

function clear() {
    date.value = undefined;
    open.value = false;
}

</script>

<template>
    <DatePickerRoot v-model="date" v-model:open="open" locale="pt-BR" :disabled="disabled" close-on-select prevent-deselect>
        <div>
            <DatePickerField v-slot="{ segments }" v-bind="$attrs" class="flex items-center gap-0 focus-within:outline-none">
                <template v-for="segment in segments" :key="segment.part">
                    <span v-if="segment.part === 'literal'">{{ segment.value }}</span>
                    <DatePickerInput v-else :part="segment.part" :aria-label="segment.part === 'day' ? 'Dia' : segment.part === 'month' ? 'Mês' : 'Ano'" class="rounded-sm outline-none focus:bg-primary focus:text-primary-foreground">{{ segment.value }}</DatePickerInput>
                </template>
                <DatePickerTrigger type="button" aria-label="Abrir calendário" class="ml-auto flex h-4 w-4 shrink-0 items-center justify-center rounded p-0 text-gray-600 hover:text-primary focus-visible:outline-2 focus-visible:outline-primary disabled:opacity-50 dark:text-slate-300" @keydown.enter.stop>
                    <CalendarDays class="h-3 w-3" aria-hidden="true" />
                </DatePickerTrigger>
            </DatePickerField>
        </div>
        <DatePickerContent align="start" :side-offset="4" :data-record-date-calendar="inlineEditor ? '' : undefined" class="z-[100] rounded-lg border border-border bg-popover p-3 text-popover-foreground shadow-lg">
            <DatePickerCalendar v-slot="{ grid, weekDays }" calendar-label="Calendário">
                <DatePickerHeader class="mb-2 flex items-center justify-between gap-2">
                    <DatePickerPrev aria-label="Mês anterior" class="rounded p-1 hover:bg-accent focus-visible:outline-2 focus-visible:outline-primary"><ChevronLeft class="h-4 w-4" /></DatePickerPrev>
                    <DatePickerHeading class="text-sm font-semibold capitalize" />
                    <DatePickerNext aria-label="Próximo mês" class="rounded p-1 hover:bg-accent focus-visible:outline-2 focus-visible:outline-primary"><ChevronRight class="h-4 w-4" /></DatePickerNext>
                </DatePickerHeader>
                <DatePickerGrid v-for="month in grid" :key="month.value.toString()" class="border-collapse">
                    <DatePickerGridHead>
                        <DatePickerGridRow>
                            <DatePickerHeadCell v-for="(day, index) in weekDays" :key="index" class="h-8 w-9 text-center text-xs text-muted-foreground">{{ day }}</DatePickerHeadCell>
                        </DatePickerGridRow>
                    </DatePickerGridHead>
                    <DatePickerGridBody>
                        <DatePickerGridRow v-for="(week, index) in month.rows" :key="index">
                            <DatePickerCell v-for="day in week" :key="day.toString()" :date="day">
                                <DatePickerCellTrigger :day="day" :month="month.value" class="date-cell flex h-9 w-9 items-center justify-center rounded text-sm hover:bg-accent focus-visible:outline-2 focus-visible:outline-primary">{{ day.day }}</DatePickerCellTrigger>
                            </DatePickerCell>
                        </DatePickerGridRow>
                    </DatePickerGridBody>
                </DatePickerGrid>
                <div class="mt-2 flex justify-between border-t border-border pt-2 text-sm text-primary">
                    <button type="button" class="rounded px-1 hover:underline focus-visible:outline-2 focus-visible:outline-primary" @click="clear">Limpar</button>
                    <button type="button" class="rounded px-1 hover:underline focus-visible:outline-2 focus-visible:outline-primary" @click="selectToday">Hoje</button>
                </div>
            </DatePickerCalendar>
        </DatePickerContent>
    </DatePickerRoot>
</template>

<style scoped>
.date-cell[data-selected] {
    background-color: var(--primary);
    color: var(--primary-foreground);
}

.date-cell[data-outside-view]:not([data-selected]) {
    color: var(--muted-foreground);
}

.date-cell[data-disabled] {
    opacity: 0.4;
}
</style>
