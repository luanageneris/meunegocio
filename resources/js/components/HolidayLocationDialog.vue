<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import AppSelect from '@/components/AppSelect.vue';
import type { HolidayLocation } from '@/types/holidays';

type Name = { language: string; text: string };
type Subdivision = { code: string; name: Name[]; children?: Subdivision[] };
type Country = { isoCode: string; name: Name[] };
type Preview = { city_verified: boolean; status: string; holiday_count: number; source: string | null; year: number; month: number };
const props = defineProps<{ location: HolidayLocation | null; year?: number; month?: number }>();
const emit = defineEmits<{ saved: [location: HolidayLocation]; close: [] }>();
const dialog = ref<HTMLDialogElement | null>(null);
const countries = ref<Country[]>([]);
const regions = ref<Subdivision[]>([]);
const cities = ref<Subdivision[]>([]);
const countryCode = ref(props.location?.country_code || '');
const regionCode = ref(props.location?.region_code || '');
const regionName = ref(props.location?.region_name || '');
const cityCode = ref(props.location?.city_code || '');
const cityName = ref(props.location?.city_name || '');
const consent = ref(false);
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const preview = ref<Preview | null>(null);
let regionRequest = 0;
let cityRequest = 0;
let previewRequest = 0;

const label = (names: Name[]) => names.find((name) => name.language === 'PT')?.text || names[0]?.text || '';
const countryLabel = (country: Country) => new Intl.DisplayNames(['pt-BR'], { type: 'region' }).of(country.isoCode) || label(country.name);
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
const countryOptions = computed(() => [
    { value: '', label: 'Selecione um país' },
    ...countries.value.map((country) => ({ value: country.isoCode, label: countryLabel(country) })),
]);
const regionOptions = computed(() => [
    { value: '', label: 'Selecione uma região' },
    ...regions.value.map((region) => ({ value: region.code, label: label(region.name) })),
]);
const cityOptions = computed(() => [
    { value: '', label: 'Selecione um município' },
    ...cities.value.map((city) => ({ value: city.code, label: label(city.name) })),
]);

function selectCountry(value: string) {
    if (value === countryCode.value) return;
    countryCode.value = value;
    void loadRegions(true);
}

function selectRegion(value: string) {
    if (value === regionCode.value) return;
    regionCode.value = value;
    void loadCities(true);
}

async function loadRegions(reset: boolean) {
    const requestId = ++regionRequest;
    if (reset) {
        regionCode.value = '';
        regionName.value = '';
        cityCode.value = '';
        cityName.value = '';
    }
    regions.value = [];
    cities.value = [];
    ++cityRequest;
    if (!countryCode.value) {
        loading.value = false;
        return;
    }
    loading.value = true;
    error.value = '';
    try {
        const response = await fetch(`/api/holidays/subdivisions?country=${encodeURIComponent(countryCode.value)}`, { credentials: 'include', headers: { Accept: 'application/json' } });
        const payload = await response.json();
        if (requestId !== regionRequest) return;
        if (!response.ok) throw new Error(payload.message || 'Não foi possível carregar as regiões.');
        regions.value = payload.data;
        if (!reset && !regionCode.value && regionName.value) {
            const oldName = regionName.value;
            regionCode.value = regions.value.find((region) =>
                label(region.name).localeCompare(oldName, 'pt-BR', { sensitivity: 'base' }) === 0 ||
                region.code === `BR-${oldName.toUpperCase()}`,
            )?.code || '';
        }
        if (!reset && regionCode.value) await loadCities(false);
    } catch (exception) {
        if (requestId === regionRequest) error.value = exception instanceof Error ? exception.message : 'Não foi possível carregar as regiões.';
    } finally {
        if (requestId === regionRequest) loading.value = false;
    }
}

async function loadCities(reset: boolean) {
    const requestId = ++cityRequest;
    if (reset) {
        cityCode.value = '';
        cityName.value = '';
    }
    cities.value = [];
    if (!regionCode.value) return;
    loading.value = true;
    error.value = '';
    try {
        const params = new URLSearchParams({ country: countryCode.value, region: regionCode.value });
        const response = await fetch(`/api/holidays/cities?${params}`, { credentials: 'include', headers: { Accept: 'application/json' } });
        const payload = await response.json();
        if (requestId !== cityRequest) return;
        if (!response.ok) throw new Error(payload.message || 'Não foi possível carregar os municípios.');
        cities.value = payload.data;
        if (!reset && !cityCode.value && cityName.value) {
            cityCode.value = cities.value.find((city) => label(city.name).localeCompare(cityName.value, 'pt-BR', { sensitivity: 'base' }) === 0)?.code || '';
        }
    } catch (exception) {
        if (requestId === cityRequest) error.value = exception instanceof Error ? exception.message : 'Não foi possível carregar os municípios.';
    } finally {
        if (requestId === cityRequest) loading.value = false;
    }
}

function locationPayload() {
    return {
        consent: true, country_code: countryCode.value, region_code: regionCode.value,
        region_name: regions.value.length ? '' : regionName.value,
        city_code: cityCode.value, city_name: cities.value.length ? '' : cityName.value,
    };
}

async function submit() {
    if (!countryCode.value) {
        error.value = 'Selecione um país.';
        return;
    }
    if (!consent.value) {
        error.value = 'Confirme o consentimento antes de continuar.';
        return;
    }
    saving.value = true;
    error.value = '';
    try {
        const requestId = ++previewRequest;
        const checking = !preview.value;
        const now = new Date();
        const response = await fetch(checking ? '/api/holidays/preview' : '/api/holidays/location', {
            method: checking ? 'POST' : 'PUT',
            credentials: 'include',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify({
                ...locationPayload(),
                ...(checking ? { year: props.year ?? now.getFullYear(), month: props.month ?? now.getMonth() + 1 } : {}),
            }),
        });
        const payload = await response.json();
        if (requestId !== previewRequest) return;
        if (!response.ok) throw new Error(Object.values(payload.errors || {}).flat().join(' ') || payload.message || 'Não foi possível salvar a localização.');
        if (checking) {
            preview.value = payload.data;
            return;
        }
        window.localStorage.setItem('holiday-preference-changed', String(Date.now()));
        emit('saved', payload.data);
    } catch (exception) {
        error.value = exception instanceof Error ? exception.message : 'Não foi possível salvar a localização.';
    } finally {
        saving.value = false;
    }
}

watch([countryCode, regionCode, regionName, cityCode, cityName, consent, () => props.year, () => props.month], () => {
    preview.value = null;
    ++previewRequest;
});

onMounted(async () => {
    dialog.value?.showModal();
    loading.value = true;
    try {
        const response = await fetch('/api/holidays/countries', { credentials: 'include', headers: { Accept: 'application/json' } });
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.message || 'Não foi possível carregar os países.');
        countries.value = payload.data;
        if (countryCode.value) await loadRegions(false);
    } catch (exception) {
        error.value = exception instanceof Error ? exception.message : 'Não foi possível carregar os países.';
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <Teleport to="body">
        <dialog ref="dialog" class="m-auto w-[min(94vw,36rem)] rounded-2xl bg-white p-0 text-gray-900 shadow-xl backdrop:bg-black/50 dark:bg-slate-900 dark:text-slate-100" aria-labelledby="holiday-location-title" @cancel.prevent="!saving && emit('close')">
            <form class="space-y-4 p-6" @submit.prevent="submit">
                <h2 id="holiday-location-title" class="text-xl font-bold">Feriados da sua localização</h2>
                <p class="text-sm">Você pode retirar seu consentimento em <Link href="/minha-conta" class="text-orange-700 underline hover:text-orange-600 dark:text-orange-300 dark:hover:text-orange-200">Minha Conta</Link>.</p>
                <p class="text-sm">Feriados municipais podem não estar disponíveis.</p>
                <div>
                    <label for="holiday-country" class="block text-sm font-semibold">País</label>
                    <AppSelect id="holiday-country" :model-value="countryCode" :options="countryOptions" label="País" searchable :disabled="loading || saving" class="mt-1 w-full text-sm" @update:model-value="selectCountry" />
                </div>
                <div>
                    <label for="holiday-region" class="block text-sm font-semibold">Estado ou região <span class="font-normal">(opcional)</span></label>
                    <AppSelect v-if="regions.length" id="holiday-region" :model-value="regionCode" :options="regionOptions" label="Estado ou região" searchable :disabled="loading || saving" class="mt-1 w-full text-sm" @update:model-value="selectRegion" />
                    <input v-else id="holiday-region" v-model.trim="regionName" maxlength="100" :disabled="loading || saving" class="mt-1 w-full rounded-lg border border-gray-300 p-2 dark:bg-slate-800" />
                </div>
                <div>
                    <label for="holiday-city" class="block text-sm font-semibold">Cidade ou município <span class="font-normal">(opcional)</span></label>
                    <AppSelect v-if="cities.length" id="holiday-city" v-model="cityCode" :options="cityOptions" label="Cidade ou município" searchable :disabled="loading || saving" class="mt-1 w-full text-sm" />
                    <input v-else id="holiday-city" v-model.trim="cityName" maxlength="100" :disabled="loading || saving" class="mt-1 w-full rounded-lg border border-gray-300 p-2 dark:bg-slate-800" />
                </div>
                <p v-if="countryCode === 'BR'" class="text-xs text-gray-600 dark:text-slate-400">Municípios: <a href="https://github.com/kelvins/Municipios-Brasileiros" target="_blank" rel="noopener noreferrer" class="underline">Municípios Brasileiros</a> (Kelvin S. do Prado, <a href="/licenses/municipios-brasileiros.txt" target="_blank" rel="noopener noreferrer" class="underline">licença MIT</a>).</p>
                <div class="flex items-start gap-2 text-sm leading-5">
                    <input id="holiday-consent" v-model="consent" type="checkbox" :disabled="loading || saving" class="mt-1 shrink-0 cursor-pointer disabled:cursor-not-allowed" />
                    <div class="space-y-1">
                        <label for="holiday-consent" :class="loading || saving ? 'cursor-not-allowed' : 'cursor-pointer'">Autorizo o armazenamento e uso desta localização para mostrar feriados, conforme a LGPD.</label>
                        <p>Saiba mais na <Link href="/politica-de-privacidade" class="text-orange-700 underline hover:text-orange-600 dark:text-orange-300 dark:hover:text-orange-200">Política de Privacidade</Link>.</p>
                    </div>
                </div>
                <div v-if="preview" role="status" class="space-y-1 rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-900 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-100">
                    <p v-if="!cityCode && !cityName">País verificado.</p>
                    <p v-else-if="preview.city_verified">Cidade e região verificadas.</p>
                    <p v-else>Não foi possível verificar a cidade no catálogo disponível. Confira os dados antes de continuar.</p>
                    <p v-if="preview.status === 'failed'">Não foi possível consultar os feriados deste período. Você pode confirmar e tentar novamente mais tarde.</p>
                    <p v-else-if="preview.holiday_count === 0">Não foram encontrados feriados disponíveis para {{ String(preview.month).padStart(2, '0') }}/{{ preview.year }}. Você pode continuar.</p>
                    <p v-else>{{ preview.holiday_count }} {{ preview.holiday_count === 1 ? 'data encontrada' : 'datas encontradas' }} para {{ String(preview.month).padStart(2, '0') }}/{{ preview.year }}.</p>
                    <p v-if="preview.status === 'partial'">Cobertura parcial: somente datas nacionais, com classificação não informada.</p>
                    <p v-if="preview.status === 'stale'">Não foi possível atualizar as datas; a consulta usa dados salvos anteriormente.</p>
                </div>
                <p v-if="loading" role="status" class="text-sm">Carregando localidades...</p>
                <p v-if="error" role="alert" class="text-sm text-red-600">{{ error }}</p>
                <div class="flex justify-end gap-3">
                    <button type="button" class="cursor-pointer rounded-lg border px-4 py-2 transition-colors hover:bg-gray-100 active:bg-gray-200 disabled:cursor-not-allowed disabled:opacity-50 dark:hover:bg-slate-800 dark:active:bg-slate-700" :disabled="saving" @click="emit('close')">Cancelar</button>
                    <button type="submit" class="cursor-pointer rounded-lg bg-orange-600 px-4 py-2 font-semibold text-white transition-colors hover:bg-orange-700 active:bg-orange-800 disabled:cursor-not-allowed disabled:opacity-50" :disabled="loading || saving">{{ saving ? (preview ? 'Salvando...' : 'Verificando...') : (preview ? 'Confirmar' : 'Verificar') }}</button>
                </div>
            </form>
        </dialog>
    </Teleport>
</template>
