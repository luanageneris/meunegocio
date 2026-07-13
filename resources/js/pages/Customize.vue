<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, computed, onMounted, nextTick, watch } from 'vue';
import axios from 'axios';
import Sidebar from '@/components/Sidebar.vue';
import FixedPageHeaderTitle from '@/components/FixedPageHeaderTitle.vue';
import EntryTypeFormModal from '@/components/modals/EntryTypeFormModal.vue';
import ExitTypeFormModal from '@/components/modals/ExitTypeFormModal.vue';
import DeleteConfirmModal from '@/components/modals/DeleteConfirmModal.vue';
import { useAuth } from '@/composables/useAuth';
import { useToast } from '@/composables/useToast';
import { useInputMasks } from '@/composables/useInputMasks';
import Footer from '@/components/Footer.vue';
import LoadingSpinner from '@/components/LoadingSpinner.vue';

interface EntryType {
    id: number;
    name: string;
    created_at?: string;
    deleted_at?: string | null;
}

interface ExitType {
    id: number;
    name: string;
    created_at?: string;
    deleted_at?: string | null;
}

interface ServiceType {
    id: number;
    name: string;
    unit: string;
    service_value: string | number;
    commission_percentage: string | number;
    commission_value: string | number;
    resource_value: string | number;
    created_at?: string;
    deleted_at?: string | null;
}

type ServiceTypeFormData = Pick<
    ServiceType,
    'name' | 'unit' | 'service_value' | 'commission_percentage' | 'commission_value' | 'resource_value'
>;

const createEmptyServiceTypeForm = (): ServiceTypeFormData => ({
    name: '',
    unit: '',
    service_value: '',
    commission_percentage: '',
    commission_value: '',
    resource_value: '',
});

const auth = useAuth();
const toast = useToast();
const { formatCurrencyInput, parseCurrencyInput } = useInputMasks();

// Entry Types
const entryTypes = ref<EntryType[]>([]);
const entryTypeSearch = ref('');
const showEntryTypeModal = ref(false);
const showEntryTypeDeleteModal = ref(false);
const showNewEntryTypeInline = ref(false);
const showEditEntryTypeInline = ref(false);
const showDeleteConfirmEntryTypeInline = ref(false);
const entryTypeModalMode = ref<'create' | 'edit'>('create');
const editingEntryType = ref<EntryType | null>(null);
const editingEntryTypeInlineName = ref('');
const editingEntryTypeInlineError = ref('');
const editingEntryTypeInputRef = ref<HTMLInputElement | null>(null);
const entryTypeDeleteConfirmButtonRef = ref<HTMLButtonElement | null>(null);
const deletingEntryType = ref<EntryType | null>(null);
const restoringEntryType = ref<EntryType | null>(null);
const isEntryTypeModalLoading = ref(false);
const isEntryTypeDeleteLoading = ref(false);
const isEntryTypeRestoreLoading = ref(false);
const showEntryTypeRemoved = ref(false);
const newEntryTypeName = ref('');
const newEntryTypeError = ref('');
const newEntryTypeInput = ref<HTMLInputElement | null>(null);

// Exit Types
const exitTypes = ref<ExitType[]>([]);
const exitTypeSearch = ref('');
const showExitTypeModal = ref(false);
const showExitTypeDeleteModal = ref(false);
const showNewExitTypeInline = ref(false);
const showEditExitTypeInline = ref(false);
const showDeleteConfirmExitTypeInline = ref(false);
const exitTypeModalMode = ref<'create' | 'edit'>('create');
const editingExitType = ref<ExitType | null>(null);
const editingExitTypeInlineName = ref('');
const editingExitTypeInlineError = ref('');
const editingExitTypeInputRef = ref<HTMLInputElement | null>(null);
const exitTypeDeleteConfirmButtonRef = ref<HTMLButtonElement | null>(null);
const deletingExitType = ref<ExitType | null>(null);
const restoringExitType = ref<ExitType | null>(null);
const isExitTypeModalLoading = ref(false);
const isExitTypeDeleteLoading = ref(false);
const isExitTypeRestoreLoading = ref(false);
const showExitTypeRemoved = ref(false);
const newExitTypeName = ref('');
const newExitTypeError = ref('');
const newExitTypeInput = ref<HTMLInputElement | null>(null);

// Service Types
const serviceTypes = ref<ServiceType[]>([]);
const serviceTypeSearch = ref('');
const showNewServiceTypeInline = ref(false);
const showEditServiceTypeInline = ref(false);
const showDeleteConfirmServiceTypeInline = ref(false);
const editingServiceType = ref<ServiceType | null>(null);
const newServiceTypeForm = ref<ServiceTypeFormData>(createEmptyServiceTypeForm());
const newServiceTypeErrors = ref<Record<string, string[]>>({});
const editingServiceTypeInlineForm = ref<ServiceTypeFormData>(createEmptyServiceTypeForm());
const editingServiceTypeInlineErrors = ref<Record<string, string[]>>({});
const deletingServiceType = ref<ServiceType | null>(null);
const restoringServiceType = ref<ServiceType | null>(null);
const newServiceTypeInputRef = ref<HTMLInputElement | null>(null);
const editingServiceTypeInputRef = ref<HTMLInputElement | null>(null);
const serviceTypeDeleteConfirmButtonRef = ref<HTMLButtonElement | null>(null);
const isServiceTypeModalLoading = ref(false);
const isServiceTypeDeleteLoading = ref(false);
const isServiceTypeRestoreLoading = ref(false);
const showServiceTypeRemoved = ref(false);
const clearServiceTypeSearchOnSave = ref(false);

const isLoading = ref(false);
const errorMessage = ref('');
const company = ref<any>(null);
const savingSettings = ref(false);
const showServiceModeInfo = ref(false);
const serviceModeOn = computed(() => company.value?.use_service_types !== false);

const normalizeNullableString = (value: string | null | undefined) => value ?? '';
const currentSoftDeleteTimestamp = () => new Date().toISOString();
const applySoftDeleteLocally = <T extends { id: number; deleted_at?: string | null }>(
    items: T[],
    itemId: number,
    keepRemovedVisible: boolean,
) => {
    if (keepRemovedVisible) {
        return items.map((item) => (
            item.id === itemId
                ? { ...item, deleted_at: item.deleted_at ?? currentSoftDeleteTimestamp() }
                : item
        ));
    }

    return items.filter((item) => item.id !== itemId);
};
const sortByCreatedAtDesc = <T extends { created_at?: string }>(items: T[]) => (
    items.sort((a, b) => new Date(b.created_at ?? 0).getTime() - new Date(a.created_at ?? 0).getTime())
);
const applyRestoreLocally = <T extends { id: number; deleted_at?: string | null }>(items: T[], itemId: number) => (
    items.map((item) => (item.id === itemId ? { ...item, deleted_at: null } : item))
);
const validateServiceTypeForm = (form: ServiceTypeFormData) => {
    const errors: Record<string, string[]> = {};
    const serviceValue = parseCurrencyInput(form.service_value);
    const commissionPercentage = Number(form.commission_percentage);
    const commissionValue = parseCurrencyInput(form.commission_value);
    const resourceValue = parseCurrencyInput(form.resource_value);

    if (!form.name?.trim()) {
        errors.name = ['O nome é obrigatório'];
    }

    if (!form.unit?.trim()) {
        errors.unit = ['A unidade é obrigatória'];
    }

    if (form.service_value === '' || form.service_value === null || form.service_value === undefined) {
        errors.service_value = ['O valor do serviço é obrigatório'];
    } else if (serviceValue < 0) {
        errors.service_value = ['O valor deve ser positivo'];
    }

    if (form.commission_percentage === '' || form.commission_percentage === null || form.commission_percentage === undefined) {
        errors.commission_percentage = ['O percentual de comissão é obrigatório'];
    } else if (commissionPercentage < 0 || commissionPercentage > 100) {
        errors.commission_percentage = ['O percentual deve ser entre 0 e 100'];
    }

    if (form.commission_value !== '' && form.commission_value !== null && form.commission_value !== undefined && commissionValue < 0) {
        errors.commission_value = ['O valor deve ser positivo'];
    }

    if (form.resource_value !== '' && form.resource_value !== null && form.resource_value !== undefined && resourceValue < 0) {
        errors.resource_value = ['O valor deve ser positivo'];
    }

    return errors;
};
const buildServiceTypePayload = (form: ServiceTypeFormData) => ({
    name: form.name.trim(),
    unit: form.unit.trim(),
    service_value: parseCurrencyInput(form.service_value),
    commission_percentage: Number(form.commission_percentage),
    commission_value: parseCurrencyInput(form.commission_value),
    resource_value: parseCurrencyInput(form.resource_value),
});
const syncCommissionValueFromPercentage = (form: ServiceTypeFormData) => {
    const serviceValue = parseCurrencyInput(form.service_value);
    const percentage = Number(form.commission_percentage);

    if (serviceValue > 0 && !Number.isNaN(percentage)) {
        form.commission_value = formatCurrencyInput((serviceValue * (percentage / 100)).toFixed(2));
    }
};
const syncCommissionPercentageFromValue = (form: ServiceTypeFormData) => {
    const serviceValue = parseCurrencyInput(form.service_value);
    const commissionValue = parseCurrencyInput(form.commission_value);

    if (serviceValue > 0 && !Number.isNaN(commissionValue)) {
        form.commission_percentage = ((commissionValue / serviceValue) * 100).toFixed(2);
    }
};
const syncCommissionValueFromServiceValue = (form: ServiceTypeFormData) => {
    const serviceValue = parseCurrencyInput(form.service_value);
    const percentage = Number(form.commission_percentage);

    if (serviceValue > 0 && !Number.isNaN(percentage) && percentage > 0) {
        form.commission_value = formatCurrencyInput((serviceValue * (percentage / 100)).toFixed(2));
    }
};

const handleServiceTypeCurrencyInput = (
    form: ServiceTypeFormData,
    field: 'service_value' | 'resource_value' | 'commission_value',
    event: Event,
) => {
    const target = event.target as HTMLInputElement;
    form[field] = formatCurrencyInput(target.value);
};

watch(entryTypeSearch, (value) => {
    if (value === null || value === undefined) {
        entryTypeSearch.value = normalizeNullableString(value);
    }
});

watch(exitTypeSearch, (value) => {
    if (value === null || value === undefined) {
        exitTypeSearch.value = normalizeNullableString(value);
    }
});

watch(serviceTypeSearch, (value) => {
    if (value === null || value === undefined) {
        serviceTypeSearch.value = normalizeNullableString(value);
    }
});

watch(newEntryTypeName, (value) => {
    if (value === null || value === undefined) {
        newEntryTypeName.value = normalizeNullableString(value);
    }
});

watch(newExitTypeName, (value) => {
    if (value === null || value === undefined) {
        newExitTypeName.value = normalizeNullableString(value);
    }
});

watch(editingEntryTypeInlineName, (value) => {
    if (value === null || value === undefined) {
        editingEntryTypeInlineName.value = normalizeNullableString(value);
    }
});

watch(editingExitTypeInlineName, (value) => {
    if (value === null || value === undefined) {
        editingExitTypeInlineName.value = normalizeNullableString(value);
    }
});

// Computed
const filteredEntryTypes = computed(() => {
    let types = entryTypes.value;
    
    // Filtrar deletados se não estiver em modo "Ver removidos"
    if (!showEntryTypeRemoved.value) {
        types = types.filter((t) => !t.deleted_at);
    }
    
    // Filtrar por search
    if (!entryTypeSearch.value) return types;
    const q = entryTypeSearch.value.toLowerCase();
    return types.filter((t) => t.name.toLowerCase().includes(q));
});

const canSuggestCreateEntryTypeFromSearch = computed(() => {
    const query = entryTypeSearch.value.trim();

    return Boolean(
        query &&
        !showEntryTypeRemoved.value &&
        !showNewEntryTypeInline.value &&
        filteredEntryTypes.value.length === 0
    );
});

const filteredExitTypes = computed(() => {
    let types = exitTypes.value;
    
    // Filtrar deletados se não estiver em modo "Ver removidos"
    if (!showExitTypeRemoved.value) {
        types = types.filter((t) => !t.deleted_at);
    }
    
    // Filtrar por search
    if (!exitTypeSearch.value) return types;
    const q = exitTypeSearch.value.toLowerCase();
    return types.filter((t) => t.name.toLowerCase().includes(q));
});

const canSuggestCreateExitTypeFromSearch = computed(() => {
    const query = exitTypeSearch.value.trim();

    return Boolean(
        query &&
        !showExitTypeRemoved.value &&
        !showNewExitTypeInline.value &&
        filteredExitTypes.value.length === 0
    );
});

const filteredServiceTypes = computed(() => {
    let types = serviceTypes.value;
    
    // Filtrar deletados se não estiver em modo "Ver removidos"
    if (!showServiceTypeRemoved.value) {
        types = types.filter((t) => !t.deleted_at);
    }
    
    // Filtrar por search
    if (!serviceTypeSearch.value) return types;
    const q = serviceTypeSearch.value.toLowerCase();
    return types.filter((t) => t.name.toLowerCase().includes(q));
});

const canSuggestCreateServiceTypeFromSearch = computed(() => {
    const query = serviceTypeSearch.value.trim();

    return Boolean(
        serviceModeOn.value &&
        query &&
        !showServiceTypeRemoved.value &&
        !showNewServiceTypeInline.value &&
        filteredServiceTypes.value.length === 0
    );
});

const fetchCompany = async () => {
    const companyRes = await axios.get('/api/company');
    company.value = companyRes.data?.data ?? companyRes.data ?? null;
};

const fetchEntryTypes = async (showRemoved = showEntryTypeRemoved.value) => {
    const endpoint = showRemoved ? '/api/personalizar/entry-types/with-trashed' : '/api/personalizar/entry-types';
    const { data } = await axios.get(endpoint);
    entryTypes.value = sortByCreatedAtDesc(data || []);
};

const fetchExitTypes = async (showRemoved = showExitTypeRemoved.value) => {
    const endpoint = showRemoved ? '/api/personalizar/exit-types/with-trashed' : '/api/personalizar/exit-types';
    const { data } = await axios.get(endpoint);
    exitTypes.value = sortByCreatedAtDesc(data || []);
};

const fetchServiceTypes = async (showRemoved = showServiceTypeRemoved.value) => {
    const endpoint = showRemoved ? '/api/personalizar/service-types/with-trashed' : '/api/personalizar/service-types';
    const { data } = await axios.get(endpoint);
    serviceTypes.value = sortByCreatedAtDesc(data || []);
};

// Fetch data
const fetchData = async () => {
    if (!auth.isAuthenticated.value) {
        window.location.href = '/login';
        return;
    }

    isLoading.value = true;
    errorMessage.value = '';

    try {
        await Promise.all([
            fetchCompany(),
            fetchEntryTypes(),
            fetchExitTypes(),
            fetchServiceTypes(),
        ]);
    } catch (error: any) {
        errorMessage.value = error?.response?.data?.message || 'Erro ao carregar dados';
    } finally {
        isLoading.value = false;
    }
};

const toggleServiceMode = async () => {
    if (!company.value) return;
    savingSettings.value = true;
    try {
        const payload = { ...company.value, use_service_types: !company.value.use_service_types };
        const { data } = await axios.put('/api/company', payload);
        company.value = data?.data ?? data;
    } catch (error: any) {
        // noop toast here optional
    } finally {
        savingSettings.value = false;
    }
};

// Entry Type handlers
const closeAllInlineForms = () => {
    // Entry type
    showNewEntryTypeInline.value = false;
    newEntryTypeName.value = '';
    newEntryTypeError.value = '';
    showEditEntryTypeInline.value = false;
    editingEntryType.value = null;
    editingEntryTypeInlineName.value = '';
    editingEntryTypeInlineError.value = '';
    // Exit type
    showNewExitTypeInline.value = false;
    newExitTypeName.value = '';
    newExitTypeError.value = '';
    showEditExitTypeInline.value = false;
    editingExitType.value = null;
    editingExitTypeInlineName.value = '';
    editingExitTypeInlineError.value = '';
    // Service type
    showNewServiceTypeInline.value = false;
    newServiceTypeForm.value = createEmptyServiceTypeForm();
    newServiceTypeErrors.value = {};
    showEditServiceTypeInline.value = false;
    editingServiceType.value = null;
    editingServiceTypeInlineForm.value = createEmptyServiceTypeForm();
    editingServiceTypeInlineErrors.value = {};
};

const handleNewEntryType = () => {
    closeAllInlineForms();
    showNewEntryTypeInline.value = true;
    entryTypeModalMode.value = 'create';
    nextTick(() => newEntryTypeInput.value?.focus());
};

const cancelNewEntryType = () => {
    showNewEntryTypeInline.value = false;
    newEntryTypeName.value = '';
    newEntryTypeError.value = '';
};

const persistNewEntryType = async (name: string, clearSearch: boolean = false) => {
    const normalizedName = name.trim();

    if (!normalizedName) {
        newEntryTypeError.value = 'O nome é obrigatório';
        nextTick(() => newEntryTypeInput.value?.focus());
        return;
    }

    if (isEntryTypeModalLoading.value) return;

    isEntryTypeModalLoading.value = true;
    newEntryTypeError.value = '';

    try {
        const { data } = await axios.post('/api/personalizar/entry-types', { name: normalizedName });

        if (showEntryTypeRemoved.value) {
            showEntryTypeRemoved.value = false;
            await fetchEntryTypes(false);
        } else {
            entryTypes.value = [data, ...entryTypes.value];
        }

        if (clearSearch) {
            entryTypeSearch.value = '';
        }
        
        cancelNewEntryType();
    } catch (error: any) {
        const message = error?.response?.data?.message || 'Erro ao salvar tipo de entrada';
        newEntryTypeError.value = message;
        toast.error('Erro ao salvar', { description: message });
        nextTick(() => newEntryTypeInput.value?.focus());
    } finally {
        isEntryTypeModalLoading.value = false;
    }
};

const saveNewEntryType = async () => {
    await persistNewEntryType(newEntryTypeName.value);
};

const saveEntryTypeFromSearch = async () => {
    await persistNewEntryType(entryTypeSearch.value, true);
};

const handleEditEntryType = (type: EntryType) => {
    if (type.deleted_at) return;
    closeAllInlineForms();
    editingEntryType.value = { ...type };
    editingEntryTypeInlineName.value = type.name;
    showEditEntryTypeInline.value = true;
    nextTick(() => editingEntryTypeInputRef.value?.focus());
};

const cancelEditEntryType = () => {
    showEditEntryTypeInline.value = false;
    editingEntryType.value = null;
    editingEntryTypeInlineName.value = '';
    editingEntryTypeInlineError.value = '';
};

const saveEditEntryType = async () => {
    if (!editingEntryType.value) return;

    const name = editingEntryTypeInlineName.value.trim();

    if (!name) {
        editingEntryTypeInlineError.value = 'O nome é obrigatório';
        nextTick(() => editingEntryTypeInputRef.value?.focus());
        return;
    }

    if (isEntryTypeModalLoading.value) return;

    isEntryTypeModalLoading.value = true;
    editingEntryTypeInlineError.value = '';

    try {
        const { data } = await axios.put(`/api/personalizar/entry-types/${editingEntryType.value.id}`, { name });
        entryTypes.value = entryTypes.value.map((t) => (t.id === editingEntryType.value!.id ? data : t));
        cancelEditEntryType();
    } catch (error: any) {
        const message = error?.response?.data?.message || 'Erro ao atualizar tipo de entrada';
        editingEntryTypeInlineError.value = message;
        toast.error('Erro ao atualizar', { description: message });
        nextTick(() => editingEntryTypeInputRef.value?.focus());
    } finally {
        isEntryTypeModalLoading.value = false;
    }
};

const handleDeleteEntryType = (type: EntryType) => {
    if (type.deleted_at) return;
    deletingEntryType.value = type;
    showDeleteConfirmEntryTypeInline.value = true;
    nextTick(() => entryTypeDeleteConfirmButtonRef.value?.focus());
};

const cancelDeleteEntryType = () => {
    showDeleteConfirmEntryTypeInline.value = false;
    deletingEntryType.value = null;
};

const handleRestoreEntryType = async (type: EntryType) => {
    if (!type.deleted_at || isEntryTypeRestoreLoading.value) return;
    restoringEntryType.value = type;
    await confirmRestoreEntryType();
};

const confirmDeleteEntryType = async () => {
    if (!deletingEntryType.value) return;
    isEntryTypeDeleteLoading.value = true;
    try {
        const entryTypeId = deletingEntryType.value.id;
        await axios.delete(`/api/personalizar/entry-types/${entryTypeId}`);
        entryTypes.value = applySoftDeleteLocally(entryTypes.value, entryTypeId, showEntryTypeRemoved.value);
        showDeleteConfirmEntryTypeInline.value = false;
        deletingEntryType.value = null;
    } catch (error: any) {
        const message = error?.response?.data?.message || 'Erro ao remover tipo de entrada';
        toast.error('Erro ao remover', { description: message });
    } finally {
        isEntryTypeDeleteLoading.value = false;
    }
};

const confirmRestoreEntryType = async () => {
    if (!restoringEntryType.value) return;
    isEntryTypeRestoreLoading.value = true;
    try {
        const entryTypeId = restoringEntryType.value.id;
        await axios.post(`/api/personalizar/entry-types/${entryTypeId}/restore`);
        entryTypes.value = applyRestoreLocally(entryTypes.value, entryTypeId);
        restoringEntryType.value = null;
    } catch (error: any) {
        const message = error?.response?.data?.message || 'Erro ao restaurar tipo de entrada';
        toast.error('Erro ao restaurar', { description: message });
    } finally {
        isEntryTypeRestoreLoading.value = false;
    }
};

const toggleEntryTypeRemoved = async () => {
    showEntryTypeRemoved.value = !showEntryTypeRemoved.value;
    await fetchEntryTypes(showEntryTypeRemoved.value);
};

const handleSaveEntryType = async (payload: Partial<EntryType>) => {
    isEntryTypeModalLoading.value = true;
    try {
        if (entryTypeModalMode.value === 'edit' && editingEntryType.value) {
            const { data } = await axios.put(`/api/personalizar/entry-types/${editingEntryType.value.id}`, payload);
            entryTypes.value = entryTypes.value.map((t) => (t.id === editingEntryType.value!.id ? data : t));
        } else {
            const { data } = await axios.post('/api/personalizar/entry-types', payload);
            entryTypes.value = [data, ...entryTypes.value];
        }
        showEntryTypeModal.value = false;
        editingEntryType.value = null;
    } catch (error: any) {
        const message = error?.response?.data?.message || 'Erro ao salvar tipo de entrada';
        toast.error('Erro ao salvar', { description: message });
    } finally {
        isEntryTypeModalLoading.value = false;
    }
};

// Exit Type handlers
const handleNewExitType = () => {
    closeAllInlineForms();
    showNewExitTypeInline.value = true;
    exitTypeModalMode.value = 'create';
    nextTick(() => newExitTypeInput.value?.focus());
};

const cancelNewExitType = () => {
    showNewExitTypeInline.value = false;
    newExitTypeName.value = '';
    newExitTypeError.value = '';
};

const persistNewExitType = async (name: string, clearSearch: boolean = false) => {
    const normalizedName = name.trim();

    if (!normalizedName) {
        newExitTypeError.value = 'O nome é obrigatório';
        nextTick(() => newExitTypeInput.value?.focus());
        return;
    }

    if (isExitTypeModalLoading.value) return;

    isExitTypeModalLoading.value = true;
    newExitTypeError.value = '';

    try {
        const { data } = await axios.post('/api/personalizar/exit-types', { name: normalizedName });

        if (showExitTypeRemoved.value) {
            showExitTypeRemoved.value = false;
            await fetchExitTypes(false);
        } else {
            exitTypes.value = [data, ...exitTypes.value];
        }

        if (clearSearch) {
            exitTypeSearch.value = '';
        }
        
        cancelNewExitType();
    } catch (error: any) {
        const message = error?.response?.data?.message || 'Erro ao salvar tipo de saída';
        newExitTypeError.value = message;
        toast.error('Erro ao salvar', { description: message });
        nextTick(() => newExitTypeInput.value?.focus());
    } finally {
        isExitTypeModalLoading.value = false;
    }
};

const saveNewExitType = async () => {
    await persistNewExitType(newExitTypeName.value);
};

const saveExitTypeFromSearch = async () => {
    await persistNewExitType(exitTypeSearch.value, true);
};

const handleEditExitType = (type: ExitType) => {
    if (type.deleted_at) return;
    closeAllInlineForms();
    editingExitType.value = { ...type };
    editingExitTypeInlineName.value = type.name;
    showEditExitTypeInline.value = true;
    nextTick(() => editingExitTypeInputRef.value?.focus());
};

const cancelEditExitType = () => {
    showEditExitTypeInline.value = false;
    editingExitType.value = null;
    editingExitTypeInlineName.value = '';
    editingExitTypeInlineError.value = '';
};

const saveEditExitType = async () => {
    if (!editingExitType.value) return;

    const name = editingExitTypeInlineName.value.trim();

    if (!name) {
        editingExitTypeInlineError.value = 'O nome é obrigatório';
        nextTick(() => editingExitTypeInputRef.value?.focus());
        return;
    }

    if (isExitTypeModalLoading.value) return;

    isExitTypeModalLoading.value = true;
    editingExitTypeInlineError.value = '';

    try {
        const { data } = await axios.put(`/api/personalizar/exit-types/${editingExitType.value.id}`, { name });
        exitTypes.value = exitTypes.value.map((t) => (t.id === editingExitType.value!.id ? data : t));
        cancelEditExitType();
    } catch (error: any) {
        const message = error?.response?.data?.message || 'Erro ao atualizar tipo de saída';
        editingExitTypeInlineError.value = message;
        toast.error('Erro ao atualizar', { description: message });
        nextTick(() => editingExitTypeInputRef.value?.focus());
    } finally {
        isExitTypeModalLoading.value = false;
    }
};

const handleDeleteExitType = (type: ExitType) => {
    if (type.deleted_at) return;
    deletingExitType.value = type;
    showDeleteConfirmExitTypeInline.value = true;
    nextTick(() => exitTypeDeleteConfirmButtonRef.value?.focus());
};

const cancelDeleteExitType = () => {
    showDeleteConfirmExitTypeInline.value = false;
    deletingExitType.value = null;
};

const handleRestoreExitType = async (type: ExitType) => {
    if (!type.deleted_at || isExitTypeRestoreLoading.value) return;
    restoringExitType.value = type;
    await confirmRestoreExitType();
};

const handleSaveExitType = async (payload: Partial<ExitType>) => {
    isExitTypeModalLoading.value = true;
    try {
        if (exitTypeModalMode.value === 'edit' && editingExitType.value) {
            const { data } = await axios.put(`/api/personalizar/exit-types/${editingExitType.value.id}`, payload);
            exitTypes.value = exitTypes.value.map((t) => (t.id === editingExitType.value!.id ? data : t));
        } else {
            const { data } = await axios.post('/api/personalizar/exit-types', payload);
            exitTypes.value = [data, ...exitTypes.value];
        }
        showExitTypeModal.value = false;
        editingExitType.value = null;
    } catch (error: any) {
        const message = error?.response?.data?.message || 'Erro ao salvar tipo de saída';
        toast.error('Erro ao salvar', { description: message });
    } finally {
        isExitTypeModalLoading.value = false;
    }
};

const confirmDeleteExitType = async () => {
    if (!deletingExitType.value) return;
    isExitTypeDeleteLoading.value = true;
    try {
        const exitTypeId = deletingExitType.value.id;
        await axios.delete(`/api/personalizar/exit-types/${exitTypeId}`);
        exitTypes.value = applySoftDeleteLocally(exitTypes.value, exitTypeId, showExitTypeRemoved.value);
        showDeleteConfirmExitTypeInline.value = false;
        deletingExitType.value = null;
    } catch (error: any) {
        const message = error?.response?.data?.message || 'Erro ao remover tipo de saída';
        toast.error('Erro ao remover', { description: message });
    } finally {
        isExitTypeDeleteLoading.value = false;
    }
};

const confirmRestoreExitType = async () => {
    if (!restoringExitType.value) return;
    isExitTypeRestoreLoading.value = true;
    try {
        const exitTypeId = restoringExitType.value.id;
        await axios.post(`/api/personalizar/exit-types/${exitTypeId}/restore`);
        exitTypes.value = applyRestoreLocally(exitTypes.value, exitTypeId);
        restoringExitType.value = null;
    } catch (error: any) {
        const message = error?.response?.data?.message || 'Erro ao restaurar tipo de saída';
        toast.error('Erro ao restaurar', { description: message });
    } finally {
        isExitTypeRestoreLoading.value = false;
    }
};

const toggleExitTypeRemoved = async () => {
    showExitTypeRemoved.value = !showExitTypeRemoved.value;
    await fetchExitTypes(showExitTypeRemoved.value);
};

// Service Type handlers
const handleNewServiceType = (prefilledName = '', clearSearchAfterSave = false) => {
    if (!serviceModeOn.value) return;
    closeAllInlineForms();
    clearServiceTypeSearchOnSave.value = clearSearchAfterSave;
    showNewServiceTypeInline.value = true;
    newServiceTypeForm.value = {
        ...createEmptyServiceTypeForm(),
        name: prefilledName.trim(),
    };
    nextTick(() => newServiceTypeInputRef.value?.focus());
};

const cancelNewServiceType = () => {
    showNewServiceTypeInline.value = false;
    clearServiceTypeSearchOnSave.value = false;
    newServiceTypeForm.value = createEmptyServiceTypeForm();
    newServiceTypeErrors.value = {};
};

const saveNewServiceType = async () => {
    const errors = validateServiceTypeForm(newServiceTypeForm.value);

    if (Object.keys(errors).length > 0) {
        newServiceTypeErrors.value = errors;
        nextTick(() => newServiceTypeInputRef.value?.focus());
        return;
    }

    if (isServiceTypeModalLoading.value) return;

    isServiceTypeModalLoading.value = true;
    newServiceTypeErrors.value = {};

    try {
        const payload = buildServiceTypePayload(newServiceTypeForm.value);
        const { data } = await axios.post('/api/personalizar/service-types', payload);

        if (showServiceTypeRemoved.value) {
            showServiceTypeRemoved.value = false;
            await fetchServiceTypes(false);
        } else {
            serviceTypes.value = [data, ...serviceTypes.value];
        }

        if (clearServiceTypeSearchOnSave.value) {
            serviceTypeSearch.value = '';
        }

        cancelNewServiceType();
    } catch (error: any) {
        const message = error?.response?.data?.message || 'Erro ao salvar tipo de serviço';
        toast.error('Erro ao salvar', { description: message });
        nextTick(() => newServiceTypeInputRef.value?.focus());
    } finally {
        isServiceTypeModalLoading.value = false;
    }
};

const handleEditServiceType = (type: ServiceType) => {
    if (type.deleted_at || !serviceModeOn.value) return;
    closeAllInlineForms();
    editingServiceType.value = { ...type };
    showEditServiceTypeInline.value = true;
    editingServiceTypeInlineForm.value = {
        name: type.name,
        unit: type.unit,
        service_value: formatCurrencyInput(type.service_value || 0),
        commission_percentage: type.commission_percentage,
        commission_value: formatCurrencyInput(type.commission_value || 0),
        resource_value: formatCurrencyInput(type.resource_value || 0),
    };
    nextTick(() => editingServiceTypeInputRef.value?.focus());
};

const cancelEditServiceType = () => {
    showEditServiceTypeInline.value = false;
    editingServiceType.value = null;
    editingServiceTypeInlineForm.value = createEmptyServiceTypeForm();
    editingServiceTypeInlineErrors.value = {};
};

const saveEditServiceType = async () => {
    if (!editingServiceType.value) return;

    const errors = validateServiceTypeForm(editingServiceTypeInlineForm.value);

    if (Object.keys(errors).length > 0) {
        editingServiceTypeInlineErrors.value = errors;
        nextTick(() => editingServiceTypeInputRef.value?.focus());
        return;
    }

    if (isServiceTypeModalLoading.value) return;

    isServiceTypeModalLoading.value = true;
    editingServiceTypeInlineErrors.value = {};

    try {
        const payload = buildServiceTypePayload(editingServiceTypeInlineForm.value);
        const { data } = await axios.put(`/api/personalizar/service-types/${editingServiceType.value.id}`, payload);
        serviceTypes.value = serviceTypes.value.map((t) => (t.id === editingServiceType.value!.id ? data : t));
        cancelEditServiceType();
    } catch (error: any) {
        const message = error?.response?.data?.message || 'Erro ao salvar tipo de serviço';
        editingServiceTypeInlineErrors.value = { name: [message] };
        toast.error('Erro ao salvar', { description: message });
        nextTick(() => editingServiceTypeInputRef.value?.focus());
    } finally {
        isServiceTypeModalLoading.value = false;
    }
};

const handleDeleteServiceType = (type: ServiceType) => {
    if (type.deleted_at) return;
    deletingServiceType.value = type;
    showDeleteConfirmServiceTypeInline.value = true;
    nextTick(() => serviceTypeDeleteConfirmButtonRef.value?.focus());
};

const cancelDeleteServiceType = () => {
    showDeleteConfirmServiceTypeInline.value = false;
    deletingServiceType.value = null;
};

const handleRestoreServiceType = async (type: ServiceType) => {
    if (!type.deleted_at || isServiceTypeRestoreLoading.value) return;
    restoringServiceType.value = type;
    await confirmRestoreServiceType();
};

const confirmDeleteServiceType = async () => {
    if (!deletingServiceType.value) return;
    isServiceTypeDeleteLoading.value = true;
    try {
        const serviceTypeId = deletingServiceType.value.id;
        await axios.delete(`/api/personalizar/service-types/${serviceTypeId}`);
        serviceTypes.value = applySoftDeleteLocally(serviceTypes.value, serviceTypeId, showServiceTypeRemoved.value);
        showDeleteConfirmServiceTypeInline.value = false;
        deletingServiceType.value = null;
    } catch (error: any) {
        const message = error?.response?.data?.message || 'Erro ao remover tipo de serviço';
        toast.error('Erro ao remover', { description: message });
    } finally {
        isServiceTypeDeleteLoading.value = false;
    }
};

const confirmRestoreServiceType = async () => {
    if (!restoringServiceType.value) return;
    isServiceTypeRestoreLoading.value = true;
    try {
        const serviceTypeId = restoringServiceType.value.id;
        await axios.post(`/api/personalizar/service-types/${serviceTypeId}/restore`);
        serviceTypes.value = applyRestoreLocally(serviceTypes.value, serviceTypeId);
        restoringServiceType.value = null;
    } catch (error: any) {
        const message = error?.response?.data?.message || 'Erro ao restaurar tipo de serviço';
        toast.error('Erro ao restaurar', { description: message });
    } finally {
        isServiceTypeRestoreLoading.value = false;
    }
};

const toggleServiceTypeRemoved = async () => {
    showServiceTypeRemoved.value = !showServiceTypeRemoved.value;
    await fetchServiceTypes(showServiceTypeRemoved.value);
};

onMounted(fetchData);
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-amber-50 to-orange-100 dark:from-slate-950 dark:via-slate-900 dark:to-slate-950 flex overflow-x-hidden">
        <Head title="Personalizar - MEU NEGÓCIO" />
        
        <Sidebar active-route="personalizar" />

        <div class="flex-1 min-w-0 lg:ml-64 transition-all duration-300 overflow-x-hidden flex flex-col">
            <FixedPageHeaderTitle title="Personalizar" subtitle="Gerencie tipos de entrada, saída e serviços" />
            
            <!-- Main Content -->
            <main class="w-full min-w-0 max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-6 sm:py-8 overflow-x-hidden">
                <!-- Error Message -->
                <div v-if="errorMessage" class="mb-4 text-sm text-red-600 bg-red-50 border border-red-100 rounded-lg p-3">
                    {{ errorMessage }}
                </div>
                
                <LoadingSpinner v-if="isLoading" />

                <div v-else class="space-y-4 sm:space-y-6 min-w-0">
                    <!-- Entry Types Section -->
                        <div class="bg-white rounded-xl shadow-lg p-4 sm:p-6">
                            <div class="flex items-center gap-2 mb-3 sm:mb-4">
                                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m0 0h6" />
                                </svg>
                                <h2 class="text-xl sm:text-2xl font-bold text-gray-800">Tipos de Entradas</h2>
                            </div>

                            <div class="flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-2 mb-4">
                                <button
                                    @click="handleNewEntryType"
                                    class="w-full sm:w-auto px-5 py-2 bg-gradient-to-r from-green-400 to-green-500 text-white rounded-lg font-medium text-sm sm:text-base hover:shadow-lg transition-all"
                                >
                                    + Novo Tipo
                                </button>
                                <button
                                    @click="toggleEntryTypeRemoved"
                                    :class="[
                                        'w-full sm:w-auto px-5 py-2 border-2 rounded-lg font-medium text-sm sm:text-base transition-all',
                                        showEntryTypeRemoved
                                            ? 'border-red-300 text-red-600 bg-red-50 hover:bg-red-100'
                                            : 'border-green-300 text-green-500 hover:bg-green-50'
                                    ]"
                                >
                                    {{ showEntryTypeRemoved ? 'Ocultar removidos' : 'Ver removidos' }}
                                </button>
                            </div>

                            <div class="mb-4">
                                <input
                                    v-model="entryTypeSearch"
                                    type="text"
                                    placeholder="Pesquise tipos de entrada..."
                                    class="w-full px-4 py-3 border-2 border-green-200 rounded-lg text-sm sm:text-base focus:outline-none focus:ring-2 focus:ring-green-400 focus:border-transparent"
                                />
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 items-start min-w-0">
                                <div
                                    v-if="showNewEntryTypeInline"
                                    class="self-start border-2 border-green-300 rounded-lg p-4 shadow-sm bg-green-50/60 dark:border-green-500/60 dark:bg-slate-900/80"
                                >
                                    <div class="flex min-w-0 items-center gap-3">
                                        <input
                                            ref="newEntryTypeInput"
                                            v-model="newEntryTypeName"
                                            type="text"
                                            placeholder="Digite o nome do novo tipo de entrada"
                                            class="w-0 min-w-0 flex-1 bg-transparent text-base font-semibold text-gray-800 placeholder:text-gray-400 focus:outline-none dark:text-slate-100 dark:placeholder:text-slate-400"
                                            :disabled="isEntryTypeModalLoading"
                                            @keydown.enter.prevent="saveNewEntryType"
                                            @keydown.esc.prevent="cancelNewEntryType"
                                        />

                                        <div class="flex shrink-0 items-center gap-2">
                                            <button
                                                @click="saveNewEntryType"
                                                :disabled="isEntryTypeModalLoading"
                                                class="p-2 text-green-600 hover:bg-green-100 rounded-lg transition-colors disabled:opacity-50 dark:text-green-300 dark:hover:bg-slate-800"
                                                title="Salvar"
                                            >
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                </svg>
                                            </button>
                                            <button
                                                @click="cancelNewEntryType"
                                                :disabled="isEntryTypeModalLoading"
                                                class="p-2 text-gray-500 hover:bg-gray-100 rounded-lg transition-colors disabled:opacity-50 dark:text-slate-300 dark:hover:bg-slate-800"
                                                title="Cancelar"
                                            >
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                                    <p v-if="newEntryTypeError" class="mt-2 text-sm text-red-600">{{ newEntryTypeError }}</p>
                                </div>

                                <div
                                    v-for="type in filteredEntryTypes"
                                    :key="type.id"
                                    :class="[
                                        'self-start border rounded-lg p-4 shadow-sm transition-shadow',
                                        editingEntryType?.id === type.id
                                            ? 'border-2 border-blue-300 bg-blue-50/60 dark:border-blue-500/60 dark:bg-slate-900/80'
                                            : [
                                                'border-gray-200 dark:border-slate-700',
                                                type.deleted_at ? 'bg-gray-50 opacity-80 dark:bg-slate-900/60' : 'hover:shadow-md'
                                            ]
                                    ]"
                                >
                                    <!-- Modo edição inline -->
                                    <div v-if="editingEntryType?.id === type.id && showEditEntryTypeInline" class="space-y-3">
                                        <div class="flex min-w-0 items-center gap-3">
                                            <input
                                                ref="editingEntryTypeInputRef"
                                                v-model="editingEntryTypeInlineName"
                                                type="text"
                                                class="w-0 min-w-0 flex-1 bg-transparent text-base font-semibold text-gray-800 placeholder:text-gray-400 focus:outline-none border-b border-gray-300 dark:border-slate-600 dark:text-slate-100 dark:placeholder:text-slate-400"
                                                :disabled="isEntryTypeModalLoading"
                                                @keydown.enter.prevent="saveEditEntryType"
                                                @keydown.esc.prevent="cancelEditEntryType"
                                            />

                                            <div class="flex shrink-0 items-center gap-2">
                                                <button
                                                    @click="saveEditEntryType"
                                                    :disabled="isEntryTypeModalLoading"
                                                    class="p-2 text-blue-600 hover:bg-blue-100 rounded-lg transition-colors disabled:opacity-50 dark:text-blue-300 dark:hover:bg-slate-800"
                                                    title="Salvar"
                                                >
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                </button>
                                                <button
                                                    @click="cancelEditEntryType"
                                                    :disabled="isEntryTypeModalLoading"
                                                    class="p-2 text-gray-500 hover:bg-gray-100 rounded-lg transition-colors disabled:opacity-50 dark:text-slate-300 dark:hover:bg-slate-800"
                                                    title="Cancelar"
                                                >
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>

                                        <p v-if="editingEntryTypeInlineError" class="text-sm text-red-600">{{ editingEntryTypeInlineError }}</p>
                                    </div>

                                    <!-- Modo visualização normal -->
                                    <div v-else class="flex min-w-0 justify-between items-start gap-3">
                                        <div class="min-w-0 flex-1">
                                            <p class="font-semibold text-gray-800 break-words">{{ type.name }}</p>
                                            <p v-if="type.deleted_at" class="text-red-500 font-semibold text-xs mt-1">Removido</p>
                                        </div>
                                        <div class="flex shrink-0 gap-2" v-if="!type.deleted_at">
                                            <button
                                                @click="handleEditEntryType(type)"
                                                class="p-2 text-orange-500 hover:bg-orange-50 rounded-lg transition-colors cursor-pointer"
                                                title="Editar"
                                            >
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                            <button
                                                @click="handleDeleteEntryType(type)"
                                                class="p-2 text-red-500 hover:bg-orange-50 rounded-lg transition-colors cursor-pointer"
                                                title="Remover"
                                            >
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                        <div v-else class="flex shrink-0 gap-2">
                                            <button
                                                @click="handleRestoreEntryType(type)"
                                                :disabled="isEntryTypeRestoreLoading"
                                                :class="[
                                                    'p-2 rounded-lg transition-colors cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed',
                                                    restoringEntryType?.id === type.id && isEntryTypeRestoreLoading
                                                        ? 'text-green-600 bg-green-50'
                                                        : 'text-green-500 hover:bg-green-50'
                                                ]"
                                                :title="restoringEntryType?.id === type.id && isEntryTypeRestoreLoading ? 'Restaurando...' : 'Restaurar'"
                                            >
                                                <svg
                                                    :class="['w-5 h-5', restoringEntryType?.id === type.id && isEntryTypeRestoreLoading ? 'animate-spin' : '']"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Confirmação de deleção inline -->
                                    <div
                                        v-if="deletingEntryType?.id === type.id && showDeleteConfirmEntryTypeInline"
                                        class="border-l-4 border-green-500 bg-green-50/80 p-4 rounded mt-2 space-y-3 dark:border-green-500/60 dark:bg-green-950/30"
                                    >
                                        <p class="text-sm font-semibold text-gray-800 break-words dark:text-slate-100">
                                            Tem certeza que deseja remover "<span class="text-green-600 break-all">{{ type.name }}</span>"?
                                        </p>
                                        <div class="flex flex-col-reverse sm:flex-row gap-2 sm:justify-end">
                                            <button
                                                @click="cancelDeleteEntryType"
                                                :disabled="isEntryTypeDeleteLoading"
                                                class="w-full sm:w-auto px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors disabled:opacity-50 dark:border-slate-700 dark:bg-slate-950/80 dark:text-slate-200 dark:hover:bg-slate-800"
                                            >
                                                Cancelar
                                            </button>
                                            <button
                                                ref="entryTypeDeleteConfirmButtonRef"
                                                @click="confirmDeleteEntryType"
                                                :disabled="isEntryTypeDeleteLoading"
                                                class="w-full sm:w-auto px-4 py-2 text-sm font-medium text-white bg-green-500 rounded-lg hover:bg-green-600 transition-colors disabled:opacity-50"
                                            >
                                                {{ isEntryTypeDeleteLoading ? 'Removendo...' : 'Remover' }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="canSuggestCreateEntryTypeFromSearch"
                                class="mt-4 rounded-lg border border-dashed border-green-300 bg-green-50 p-4"
                            >
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-gray-800 break-words">
                                            Nenhum tipo encontrado para "{{ entryTypeSearch.trim() }}".
                                        </p>
                                        <p class="text-sm text-gray-600">
                                            Criar este tipo de entrada com um clique.
                                        </p>
                                    </div>

                                    <button
                                        @click="saveEntryTypeFromSearch"
                                        :disabled="isEntryTypeModalLoading"
                                        class="inline-flex w-full sm:w-auto max-w-full items-center justify-center rounded-lg bg-green-500 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-green-600 disabled:opacity-50"
                                    >
                                        <span class="block max-w-full truncate">{{ isEntryTypeModalLoading ? 'Criando...' : `Criar "${entryTypeSearch.trim()}"` }}</span>
                                    </button>
                                </div>
                            </div>

                            <div v-if="filteredEntryTypes.length === 0" class="text-center text-gray-500 py-8">
                                Nenhum tipo de entrada encontrado.
                            </div>
                        </div>

                        <!-- Exit Types Section -->
                        <div class="bg-white rounded-xl shadow-lg p-4 sm:p-6">
                            <div class="flex items-center gap-2 mb-3 sm:mb-4">
                                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18l8 0M12 18l-8 0M12 18V6" />
                                </svg>
                                <h2 class="text-xl sm:text-2xl font-bold text-gray-800">Tipos de Saídas</h2>
                            </div>

                            <div class="flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-2 mb-4">
                                <button
                                    @click="handleNewExitType"
                                    class="w-full sm:w-auto px-5 py-2 bg-gradient-to-r from-red-400 to-red-500 text-white rounded-lg font-medium text-sm sm:text-base hover:shadow-lg transition-all"
                                >
                                    + Novo Tipo
                                </button>
                                <button
                                    @click="toggleExitTypeRemoved"
                                    :class="[
                                        'w-full sm:w-auto px-5 py-2 border-2 rounded-lg font-medium text-sm sm:text-base transition-all',
                                        showExitTypeRemoved
                                            ? 'border-red-300 text-red-600 bg-red-50 hover:bg-red-100'
                                            : 'border-red-300 text-red-500 hover:bg-red-50'
                                    ]"
                                >
                                    {{ showExitTypeRemoved ? 'Ocultar removidos' : 'Ver removidos' }}
                                </button>
                            </div>

                            <div class="mb-4">
                                <input
                                    v-model="exitTypeSearch"
                                    type="text"
                                    placeholder="Pesquise tipos de saída..."
                                    class="w-full px-4 py-3 border-2 border-red-200 rounded-lg text-sm sm:text-base focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent"
                                />
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 items-start min-w-0">
                                <div
                                    v-if="showNewExitTypeInline"
                                    class="self-start border-2 border-red-300 rounded-lg p-4 shadow-sm bg-red-50/60 dark:border-red-500/60 dark:bg-slate-900/80"
                                >
                                    <div class="flex min-w-0 items-center gap-3">
                                        <input
                                            ref="newExitTypeInput"
                                            v-model="newExitTypeName"
                                            type="text"
                                            placeholder="Digite o nome do novo tipo de saída"
                                            class="w-0 min-w-0 flex-1 bg-transparent text-base font-semibold text-gray-800 placeholder:text-gray-400 focus:outline-none dark:text-slate-100 dark:placeholder:text-slate-400"
                                            :disabled="isExitTypeModalLoading"
                                            @keydown.enter.prevent="saveNewExitType"
                                            @keydown.esc.prevent="cancelNewExitType"
                                        />

                                        <div class="flex shrink-0 items-center gap-2">
                                            <button
                                                @click="saveNewExitType"
                                                :disabled="isExitTypeModalLoading"
                                                class="p-2 text-red-600 hover:bg-red-100 rounded-lg transition-colors disabled:opacity-50 dark:text-red-300 dark:hover:bg-slate-800"
                                                title="Salvar"
                                            >
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                </svg>
                                            </button>
                                            <button
                                                @click="cancelNewExitType"
                                                :disabled="isExitTypeModalLoading"
                                                class="p-2 text-gray-500 hover:bg-gray-100 rounded-lg transition-colors disabled:opacity-50 dark:text-slate-300 dark:hover:bg-slate-800"
                                                title="Cancelar"
                                            >
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                                    <p v-if="newExitTypeError" class="mt-2 text-sm text-red-600">{{ newExitTypeError }}</p>
                                </div>
                                <div
                                    v-for="type in filteredExitTypes"
                                    :key="type.id"
                                    :class="[
                                        'self-start border rounded-lg p-4 shadow-sm transition-shadow',
                                        editingExitType?.id === type.id
                                            ? 'border-2 border-blue-300 bg-blue-50/60 dark:border-blue-500/60 dark:bg-slate-900/80'
                                            : [
                                                'border-gray-200 dark:border-slate-700',
                                                type.deleted_at ? 'bg-gray-50 opacity-80 dark:bg-slate-900/60' : 'hover:shadow-md'
                                            ]
                                    ]"
                                >
                                    <!-- Modo edição inline -->
                                    <div v-if="editingExitType?.id === type.id && showEditExitTypeInline" class="space-y-3">
                                        <div class="flex min-w-0 items-center gap-3">
                                            <input
                                                ref="editingExitTypeInputRef"
                                                v-model="editingExitTypeInlineName"
                                                type="text"
                                                class="w-0 min-w-0 flex-1 bg-transparent text-base font-semibold text-gray-800 placeholder:text-gray-400 focus:outline-none border-b border-gray-300 dark:border-slate-600 dark:text-slate-100 dark:placeholder:text-slate-400"
                                                :disabled="isExitTypeModalLoading"
                                                @keydown.enter.prevent="saveEditExitType"
                                                @keydown.esc.prevent="cancelEditExitType"
                                            />

                                            <div class="flex shrink-0 items-center gap-2">
                                                <button
                                                    @click="saveEditExitType"
                                                    :disabled="isExitTypeModalLoading"
                                                    class="p-2 text-blue-600 hover:bg-blue-100 rounded-lg transition-colors disabled:opacity-50 dark:text-blue-300 dark:hover:bg-slate-800"
                                                    title="Salvar"
                                                >
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                </button>
                                                <button
                                                    @click="cancelEditExitType"
                                                    :disabled="isExitTypeModalLoading"
                                                    class="p-2 text-gray-500 hover:bg-gray-100 rounded-lg transition-colors disabled:opacity-50 dark:text-slate-300 dark:hover:bg-slate-800"
                                                    title="Cancelar"
                                                >
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>

                                        <p v-if="editingExitTypeInlineError" class="text-sm text-red-600">{{ editingExitTypeInlineError }}</p>
                                    </div>

                                    <!-- Modo visualização normal -->
                                    <div v-else class="flex min-w-0 justify-between items-start gap-3">
                                        <div class="min-w-0 flex-1">
                                            <p class="font-semibold text-gray-800 break-words">{{ type.name }}</p>
                                            <p v-if="type.deleted_at" class="text-red-500 font-semibold text-xs mt-1">Removido</p>
                                        </div>
                                        <div class="flex shrink-0 gap-2" v-if="!type.deleted_at">
                                            <button
                                                @click="handleEditExitType(type)"
                                                class="p-2 text-orange-500 hover:bg-orange-50 rounded-lg transition-colors cursor-pointer"
                                                title="Editar"
                                            >
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                            <button
                                                @click="handleDeleteExitType(type)"
                                                class="p-2 text-red-500 hover:bg-orange-50 rounded-lg transition-colors cursor-pointer"
                                                title="Remover"
                                            >
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                        <div v-else class="flex shrink-0 gap-2">
                                            <button
                                                @click="handleRestoreExitType(type)"
                                                :disabled="isExitTypeRestoreLoading"
                                                :class="[
                                                    'p-2 rounded-lg transition-colors cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed',
                                                    restoringExitType?.id === type.id && isExitTypeRestoreLoading
                                                        ? 'text-green-600 bg-green-50'
                                                        : 'text-green-500 hover:bg-green-50'
                                                ]"
                                                :title="restoringExitType?.id === type.id && isExitTypeRestoreLoading ? 'Restaurando...' : 'Restaurar'"
                                            >
                                                <svg
                                                    :class="['w-5 h-5', restoringExitType?.id === type.id && isExitTypeRestoreLoading ? 'animate-spin' : '']"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Confirmação de deleção inline -->
                                    <div
                                        v-if="deletingExitType?.id === type.id && showDeleteConfirmExitTypeInline"
                                        class="border-l-4 border-red-500 bg-red-50/80 p-4 rounded mt-2 space-y-3 dark:border-red-500/60 dark:bg-red-950/30"
                                    >
                                        <p class="text-sm font-semibold text-gray-800 break-words dark:text-slate-100">
                                            Tem certeza que deseja remover "<span class="text-red-600 break-all">{{ type.name }}</span>"?
                                        </p>
                                        <div class="flex flex-col-reverse sm:flex-row gap-2 sm:justify-end">
                                            <button
                                                @click="cancelDeleteExitType"
                                                :disabled="isExitTypeDeleteLoading"
                                                class="w-full sm:w-auto px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors disabled:opacity-50 dark:border-slate-700 dark:bg-slate-950/80 dark:text-slate-200 dark:hover:bg-slate-800"
                                            >
                                                Cancelar
                                            </button>
                                            <button
                                                ref="exitTypeDeleteConfirmButtonRef"
                                                @click="confirmDeleteExitType"
                                                :disabled="isExitTypeDeleteLoading"
                                                class="w-full sm:w-auto px-4 py-2 text-sm font-medium text-white bg-red-500 rounded-lg hover:bg-red-600 transition-colors disabled:opacity-50"
                                            >
                                                {{ isExitTypeDeleteLoading ? 'Removendo...' : 'Remover' }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="canSuggestCreateExitTypeFromSearch"
                                class="mt-4 rounded-lg border border-dashed border-red-300 bg-red-50 p-4"
                            >
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-gray-800 break-words">
                                            Nenhum tipo encontrado para "{{ exitTypeSearch.trim() }}".
                                        </p>
                                        <p class="text-sm text-gray-600">
                                            Criar este tipo de saída com um clique.
                                        </p>
                                    </div>

                                    <button
                                        @click="saveExitTypeFromSearch"
                                        :disabled="isExitTypeModalLoading"
                                        class="inline-flex w-full sm:w-auto max-w-full items-center justify-center rounded-lg bg-red-500 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-red-600 disabled:opacity-50"
                                    >
                                        <span class="block max-w-full truncate">{{ isExitTypeModalLoading ? 'Criando...' : `Criar "${exitTypeSearch.trim()}"` }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Settings Section -->
                        <div class="bg-white rounded-xl shadow-lg p-4 sm:p-6">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h2 class="text-xl font-bold text-gray-800">Configurações da Empresa</h2>
                                    <p class="text-sm text-gray-500">Alternar entre formulário simples e por tipo de serviço.</p>
                                </div>
                                <div class="flex w-full sm:w-auto items-center justify-between sm:justify-start gap-3">
                                    <div class="relative flex items-center gap-2">
                                        <span class="text-sm text-gray-600 leading-tight">Modo Tipo de Serviço</span>
                                        <button
                                            type="button"
                                            @click="showServiceModeInfo = !showServiceModeInfo"
                                            class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-blue-200 bg-blue-50 text-blue-600 transition-colors hover:bg-blue-100"
                                            aria-label="Informações sobre o Modo Tipo de Serviço"
                                            :aria-expanded="showServiceModeInfo"
                                        >
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </button>

                                        <div
                                            v-if="showServiceModeInfo"
                                            class="absolute right-0 top-8 z-20 w-72 rounded-lg border border-blue-100 bg-white p-3 text-left text-sm text-gray-600 shadow-xl"
                                        >
                                            <p>
                                                Ative esta opção para registrar entradas usando os tipos de serviço cadastrados, com unidade,
                                                valor e comissão configurados. Ao desativar, o sistema volta ao formulário simples e esses
                                                tipos deixam de ser usados nos registros.
                                            </p>
                                        </div>
                                    </div>
                                    <button
                                        @click="toggleServiceMode"
                                        :disabled="savingSettings"
                                        :class="[
                                            'relative inline-flex h-6 w-11 items-center rounded-full transition-colors',
                                            (company?.use_service_types !== false) ? 'bg-blue-600' : 'bg-gray-300',
                                            savingSettings ? 'opacity-50 cursor-not-allowed' : ''
                                        ]"
                                    >
                                        <span
                                            :class="[
                                                'inline-block h-4 w-4 transform rounded-full bg-white transition-transform',
                                                (company?.use_service_types !== false) ? 'translate-x-6' : 'translate-x-1'
                                            ]"
                                        />
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Service Types Section -->
                        <div :class="['bg-white rounded-xl shadow-lg p-4 sm:p-6', !serviceModeOn ? 'opacity-60' : '']">
                            <div class="flex items-center gap-2 mb-3 sm:mb-4">
                                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                                <h2 class="text-xl sm:text-2xl font-bold text-gray-800">Tipos de Serviços</h2>
                                <span
                                    v-if="!serviceModeOn"
                                    class="ml-2 inline-flex items-center rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-800 border border-yellow-200"
                                >
                                    Irrelevante (modo desativado)
                                </span>
                            </div>

                            <div v-if="!serviceModeOn" class="mb-4 rounded-lg border border-yellow-200 bg-yellow-50 p-3 text-yellow-800 text-sm">
                                O Modo Tipo de Serviço está desativado nas Configurações da Empresa. Este cadastro não é usado ao registrar entradas até ser reativado.
                            </div>

                            <div class="flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-2 mb-4">
                                <button
                                    @click="handleNewServiceType()"
                                    :disabled="!serviceModeOn"
                                    :class="[
                                        'w-full sm:w-auto px-5 py-2 rounded-lg font-medium text-sm sm:text-base transition-all',
                                        serviceModeOn
                                            ? 'bg-gradient-to-r from-blue-400 to-blue-500 text-white hover:shadow-lg'
                                            : 'bg-gray-200 text-gray-500 cursor-not-allowed'
                                    ]"
                                >
                                    + Novo Tipo
                                </button>
                                <button
                                    @click="toggleServiceTypeRemoved"
                                    :disabled="!serviceModeOn"
                                    :class="[
                                        'w-full sm:w-auto px-5 py-2 border-2 rounded-lg font-medium text-sm sm:text-base transition-all',
                                        !serviceModeOn
                                            ? 'border-gray-300 text-gray-400 bg-gray-100 cursor-not-allowed'
                                            : (showServiceTypeRemoved
                                                ? 'border-red-300 text-red-600 bg-red-50 hover:bg-red-100'
                                                : 'border-blue-300 text-blue-500 hover:bg-blue-50')
                                    ]"
                                >
                                    {{ showServiceTypeRemoved ? 'Ocultar removidos' : 'Ver removidos' }}
                                </button>
                            </div>

                            <div class="mb-4">
                                <input
                                    v-model="serviceTypeSearch"
                                    type="text"
                                    placeholder="Pesquise tipos de serviço..."
                                    class="w-full px-4 py-3 border-2 border-blue-200 rounded-lg text-sm sm:text-base focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent"
                                />
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 items-start min-w-0">
                                <div
                                    v-if="showNewServiceTypeInline"
                                    class="col-span-full self-start border-2 border-blue-300 rounded-lg p-4 shadow-sm bg-blue-50/60 dark:border-blue-500/60 dark:bg-slate-900/80"
                                >
                                    <div class="space-y-4" @keydown.esc.prevent="cancelNewServiceType">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500 mb-1 dark:text-slate-300">Nome do serviço</label>
                                            <input
                                                ref="newServiceTypeInputRef"
                                                v-model="newServiceTypeForm.name"
                                                type="text"
                                                placeholder="Nome do tipo de serviço"
                                                class="w-full bg-transparent text-base font-semibold text-gray-800 placeholder:text-gray-400 focus:outline-none border-b border-gray-300 dark:border-slate-600 dark:text-slate-100 dark:placeholder:text-slate-400"
                                                :disabled="isServiceTypeModalLoading"
                                            />
                                        </div>

                                        <div>
                                            <label class="block text-xs font-medium text-gray-500 mb-1 dark:text-slate-300">Unidade</label>
                                            <input
                                                v-model="newServiceTypeForm.unit"
                                                type="text"
                                                placeholder="Ex: Folha, Hora, Serviço"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent dark:border-slate-700 dark:bg-slate-950/80 dark:text-slate-100 dark:placeholder:text-slate-500"
                                                :disabled="isServiceTypeModalLoading"
                                            />
                                        </div>

                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-xs font-medium text-gray-500 mb-1 dark:text-slate-300">Valor do serviço (R$)</label>
                                                <input
                                                    v-model="newServiceTypeForm.service_value"
                                                    type="text"
                                                    inputmode="decimal"
                                                    placeholder="0,00"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent dark:border-slate-700 dark:bg-slate-950/80 dark:text-slate-100 dark:placeholder:text-slate-500"
                                                    :disabled="isServiceTypeModalLoading"
                                                    @input="(event) => { handleServiceTypeCurrencyInput(newServiceTypeForm, 'service_value', event); syncCommissionValueFromServiceValue(newServiceTypeForm); }"
                                                />
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-500 mb-1 dark:text-slate-300">Valor do recurso (R$)</label>
                                                <input
                                                    v-model="newServiceTypeForm.resource_value"
                                                    type="text"
                                                    inputmode="decimal"
                                                    placeholder="0,00"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent dark:border-slate-700 dark:bg-slate-950/80 dark:text-slate-100 dark:placeholder:text-slate-500"
                                                    :disabled="isServiceTypeModalLoading"
                                                    @input="(event) => handleServiceTypeCurrencyInput(newServiceTypeForm, 'resource_value', event)"
                                                />
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-xs font-medium text-gray-500 mb-1 dark:text-slate-300">Comissão (%)</label>
                                                <input
                                                    v-model="newServiceTypeForm.commission_percentage"
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    max="100"
                                                    placeholder="0"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent dark:border-slate-700 dark:bg-slate-950/80 dark:text-slate-100 dark:placeholder:text-slate-500"
                                                    :disabled="isServiceTypeModalLoading"
                                                    @input="syncCommissionValueFromPercentage(newServiceTypeForm)"
                                                />
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-500 mb-1 dark:text-slate-300">Comissão (R$)</label>
                                                <input
                                                    v-model="newServiceTypeForm.commission_value"
                                                    type="text"
                                                    inputmode="decimal"
                                                    placeholder="0,00"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent dark:border-slate-700 dark:bg-slate-950/80 dark:text-slate-100 dark:placeholder:text-slate-500"
                                                    :disabled="isServiceTypeModalLoading"
                                                    @input="(event) => { handleServiceTypeCurrencyInput(newServiceTypeForm, 'commission_value', event); syncCommissionPercentageFromValue(newServiceTypeForm); }"
                                                />
                                            </div>
                                        </div>

                                        <div v-if="Object.keys(newServiceTypeErrors).length > 0" class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg p-3 dark:border-red-800 dark:bg-red-950/40 dark:text-red-300">
                                            <div v-for="(messages, field) in newServiceTypeErrors" :key="field">
                                                <div v-for="msg in messages" :key="msg">{{ msg }}</div>
                                            </div>
                                        </div>

                                        <div class="flex justify-end gap-2 pt-1">
                                            <button
                                                @click="cancelNewServiceType"
                                                :disabled="isServiceTypeModalLoading"
                                                class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg transition-colors disabled:opacity-50 dark:text-slate-300 dark:hover:bg-slate-800"
                                            >Cancelar</button>
                                            <button
                                                @click="saveNewServiceType"
                                                :disabled="isServiceTypeModalLoading"
                                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors disabled:opacity-50"
                                            >Salvar</button>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    v-for="type in filteredServiceTypes"
                                    :key="type.id"
                                    :class="[
                                        'self-start border border-gray-200 rounded-lg p-4 shadow-sm transition-shadow dark:border-slate-700',
                                        editingServiceType?.id === type.id && showEditServiceTypeInline
                                            ? 'col-span-full border-2 border-blue-300 bg-blue-50/60 dark:border-blue-500/60 dark:bg-slate-900/80'
                                            : (type.deleted_at ? 'bg-gray-50 opacity-80 dark:bg-slate-900/60' : 'hover:shadow-md')
                                    ]"
                                >
                                    <div v-if="editingServiceType?.id === type.id && showEditServiceTypeInline" class="space-y-4" @keydown.esc.prevent="cancelEditServiceType">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500 mb-1 dark:text-slate-300">Nome do serviço</label>
                                            <input
                                                ref="editingServiceTypeInputRef"
                                                v-model="editingServiceTypeInlineForm.name"
                                                type="text"
                                                class="w-full bg-transparent text-base font-semibold text-gray-800 placeholder:text-gray-400 focus:outline-none border-b border-gray-300 dark:border-slate-600 dark:text-slate-100 dark:placeholder:text-slate-400"
                                                :disabled="isServiceTypeModalLoading"
                                            />
                                        </div>

                                        <div>
                                            <label class="block text-xs font-medium text-gray-500 mb-1 dark:text-slate-300">Unidade</label>
                                            <input
                                                v-model="editingServiceTypeInlineForm.unit"
                                                type="text"
                                                placeholder="Ex: Folha, Hora, Serviço"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent dark:border-slate-700 dark:bg-slate-950/80 dark:text-slate-100 dark:placeholder:text-slate-500"
                                                :disabled="isServiceTypeModalLoading"
                                            />
                                        </div>

                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-xs font-medium text-gray-500 mb-1 dark:text-slate-300">Valor do serviço (R$)</label>
                                                <input
                                                    v-model="editingServiceTypeInlineForm.service_value"
                                                    type="text"
                                                    inputmode="decimal"
                                                    placeholder="0,00"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent dark:border-slate-700 dark:bg-slate-950/80 dark:text-slate-100 dark:placeholder:text-slate-500"
                                                    :disabled="isServiceTypeModalLoading"
                                                    @input="(event) => { handleServiceTypeCurrencyInput(editingServiceTypeInlineForm, 'service_value', event); syncCommissionValueFromServiceValue(editingServiceTypeInlineForm); }"
                                                />
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-500 mb-1 dark:text-slate-300">Valor do recurso (R$)</label>
                                                <input
                                                    v-model="editingServiceTypeInlineForm.resource_value"
                                                    type="text"
                                                    inputmode="decimal"
                                                    placeholder="0,00"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent dark:border-slate-700 dark:bg-slate-950/80 dark:text-slate-100 dark:placeholder:text-slate-500"
                                                    :disabled="isServiceTypeModalLoading"
                                                    @input="(event) => handleServiceTypeCurrencyInput(editingServiceTypeInlineForm, 'resource_value', event)"
                                                />
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-xs font-medium text-gray-500 mb-1 dark:text-slate-300">Comissão (%)</label>
                                                <input
                                                    v-model="editingServiceTypeInlineForm.commission_percentage"
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    max="100"
                                                    placeholder="0"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent dark:border-slate-700 dark:bg-slate-950/80 dark:text-slate-100 dark:placeholder:text-slate-500"
                                                    :disabled="isServiceTypeModalLoading"
                                                    @input="syncCommissionValueFromPercentage(editingServiceTypeInlineForm)"
                                                />
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-500 mb-1 dark:text-slate-300">Comissão (R$)</label>
                                                <input
                                                    v-model="editingServiceTypeInlineForm.commission_value"
                                                    type="text"
                                                    inputmode="decimal"
                                                    placeholder="0,00"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent dark:border-slate-700 dark:bg-slate-950/80 dark:text-slate-100 dark:placeholder:text-slate-500"
                                                    :disabled="isServiceTypeModalLoading"
                                                    @input="(event) => { handleServiceTypeCurrencyInput(editingServiceTypeInlineForm, 'commission_value', event); syncCommissionPercentageFromValue(editingServiceTypeInlineForm); }"
                                                />
                                            </div>
                                        </div>

                                        <div v-if="Object.keys(editingServiceTypeInlineErrors).length > 0" class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg p-3 dark:border-red-800 dark:bg-red-950/40 dark:text-red-300">
                                            <div v-for="(messages, field) in editingServiceTypeInlineErrors" :key="field">
                                                <div v-for="msg in messages" :key="msg">{{ msg }}</div>
                                            </div>
                                        </div>

                                        <div class="flex justify-end gap-2 pt-1">
                                            <button
                                                @click="cancelEditServiceType"
                                                :disabled="isServiceTypeModalLoading"
                                                class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg transition-colors disabled:opacity-50 dark:text-slate-300 dark:hover:bg-slate-800"
                                            >Cancelar</button>
                                            <button
                                                @click="saveEditServiceType"
                                                :disabled="isServiceTypeModalLoading"
                                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors disabled:opacity-50"
                                            >Salvar</button>
                                        </div>
                                    </div>

                                    <div v-else class="flex min-w-0 justify-between items-start gap-3">
                                        <div class="min-w-0 flex-1">
                                            <p class="font-semibold text-gray-800 break-words">{{ type.name }}</p>
                                            <p class="text-xs text-gray-500 mt-1">
                                                Unidade: <span class="font-semibold">{{ type.unit }}</span>
                                            </p>
                                            <p class="text-xs text-gray-500 mt-1">
                                                Valor: R$ <span class="font-semibold">{{ parseFloat(String(type.service_value || '0')).toFixed(2) }}</span>
                                            </p>
                                            <p class="text-xs text-gray-500 mt-1">
                                                Comissão: <span class="font-semibold">{{ type.commission_percentage }}%</span>
                                            </p>
                                            <p v-if="type.deleted_at" class="text-red-500 font-semibold text-xs mt-1">Removido</p>
                                        </div>
                                        <div class="flex shrink-0 gap-2" v-if="!type.deleted_at">
                                            <button
                                                @click="handleEditServiceType(type)"
                                                :disabled="!serviceModeOn"
                                                :class="[
                                                    'p-2 rounded-lg transition-colors cursor-pointer',
                                                    serviceModeOn ? 'text-orange-500 hover:bg-orange-50' : 'text-gray-400 cursor-not-allowed'
                                                ]"
                                                title="Editar"
                                            >
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                            <button
                                                @click="handleDeleteServiceType(type)"
                                                :disabled="!serviceModeOn"
                                                :class="[
                                                    'p-2 rounded-lg transition-colors cursor-pointer',
                                                    serviceModeOn ? 'text-red-500 hover:bg-orange-50' : 'text-gray-400 cursor-not-allowed'
                                                ]"
                                                title="Remover"
                                            >
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                        <div v-else class="flex shrink-0 gap-2">
                                            <button
                                                @click="handleRestoreServiceType(type)"
                                                :disabled="isServiceTypeRestoreLoading"
                                                :class="[
                                                    'p-2 rounded-lg transition-colors cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed',
                                                    restoringServiceType?.id === type.id && isServiceTypeRestoreLoading
                                                        ? 'text-green-600 bg-green-50'
                                                        : 'text-green-500 hover:bg-green-50'
                                                ]"
                                                :title="restoringServiceType?.id === type.id && isServiceTypeRestoreLoading ? 'Restaurando...' : 'Restaurar'"
                                            >
                                                <svg
                                                    :class="['w-5 h-5', restoringServiceType?.id === type.id && isServiceTypeRestoreLoading ? 'animate-spin' : '']"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                                    <div
                                        v-if="deletingServiceType?.id === type.id && showDeleteConfirmServiceTypeInline"
                                        class="border-l-4 border-blue-500 bg-blue-50/80 p-4 rounded mt-2 space-y-3 dark:border-blue-500/60 dark:bg-blue-950/30"
                                    >
                                        <p class="text-sm font-semibold text-gray-800 break-words dark:text-slate-100">
                                            Tem certeza que deseja remover "<span class="text-blue-600 break-all">{{ type.name }}</span>"?
                                        </p>
                                        <div class="flex flex-col-reverse sm:flex-row gap-2 sm:justify-end">
                                            <button
                                                @click="cancelDeleteServiceType"
                                                :disabled="isServiceTypeDeleteLoading"
                                                class="w-full sm:w-auto px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors disabled:opacity-50 dark:border-slate-700 dark:bg-slate-950/80 dark:text-slate-200 dark:hover:bg-slate-800"
                                            >
                                                Cancelar
                                            </button>
                                            <button
                                                ref="serviceTypeDeleteConfirmButtonRef"
                                                @click="confirmDeleteServiceType"
                                                :disabled="isServiceTypeDeleteLoading"
                                                class="w-full sm:w-auto px-4 py-2 text-sm font-medium text-white bg-blue-500 rounded-lg hover:bg-blue-600 transition-colors disabled:opacity-50"
                                            >
                                                {{ isServiceTypeDeleteLoading ? 'Removendo...' : 'Remover' }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="canSuggestCreateServiceTypeFromSearch"
                                class="mt-4 rounded-lg border border-dashed border-blue-300 bg-blue-50 p-4"
                            >
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-gray-800 break-words">
                                            Nenhum tipo encontrado para "{{ serviceTypeSearch.trim() }}".
                                        </p>
                                        <p class="text-sm text-gray-600">
                                            Abrir cadastro inline com este nome preenchido.
                                        </p>
                                    </div>

                                    <button
                                        @click="handleNewServiceType(serviceTypeSearch, true)"
                                        :disabled="isServiceTypeModalLoading"
                                        class="inline-flex w-full sm:w-auto max-w-full items-center justify-center rounded-lg bg-blue-500 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-600 disabled:opacity-50"
                                    >
                                        <span class="block max-w-full truncate">Criar "{{ serviceTypeSearch.trim() }}"</span>
                                    </button>
                                </div>
                            </div>

                            <div v-if="filteredServiceTypes.length === 0" class="text-center text-gray-500 py-8">
                                Nenhum tipo de serviço encontrado.
                            </div>
                        </div>
                </div>
            </main>
            
            <Footer />
        </div>

        <!-- Modals -->
        <EntryTypeFormModal
            :show="showEntryTypeModal"
            :entry-type="editingEntryType"
            :mode="entryTypeModalMode"
            :is-loading="isEntryTypeModalLoading"
            @close="showEntryTypeModal = false; editingEntryType = null"
            @save="handleSaveEntryType"
        />

        <ExitTypeFormModal
            :show="showExitTypeModal"
            :exit-type="editingExitType"
            :mode="exitTypeModalMode"
            :is-loading="isExitTypeModalLoading"
            @close="showExitTypeModal = false; editingExitType = null"
            @save="handleSaveExitType"
        />

        <DeleteConfirmModal
            :show="showEntryTypeDeleteModal"
            :record="deletingEntryType"
            :is-loading="isEntryTypeDeleteLoading"
            item-type="entry-type"
            @close="showEntryTypeDeleteModal = false; deletingEntryType = null"
            @confirm="confirmDeleteEntryType"
        />

        <DeleteConfirmModal
            :show="showExitTypeDeleteModal"
            :record="deletingExitType"
            :is-loading="isExitTypeDeleteLoading"
            item-type="exit-type"
            @close="showExitTypeDeleteModal = false; deletingExitType = null"
            @confirm="confirmDeleteExitType"
        />

    </div>
</template>
