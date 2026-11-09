<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import Sidebar from '@/components/Sidebar.vue';
import FixedPageHeaderTitle from '@/components/FixedPageHeaderTitle.vue';
import LoadingSpinner from '@/components/LoadingSpinner.vue';
import Footer from '@/components/Footer.vue';
import { useAuth } from '@/composables/useAuth';
import { useToast } from '@/composables/useToast';
import { useInputMasks } from '@/composables/useInputMasks';
import AppInlineNotice from '@/components/AppInlineNotice.vue';
import logoUrl from '../../img/icon-logo.svg';

type UserProfileForm = {
    name: string;
    email: string;
};

type CompanyForm = {
    name: string;
    email: string;
    phone: string;
    document: string;
    address: string;
    city: string;
    state: string;
    postal_code: string;
};

type ReminderCondition = 'missing_entry' | 'missing_exit' | 'missing_entry_or_exit' | 'missing_both';

type ReminderSettingsForm = {
    daily_enabled: boolean;
    daily_condition: ReminderCondition;
    daily_days_of_week: number[];
    send_time: string;
    monthly_enabled: boolean;
    yearly_enabled: boolean;
    pdf_pin: string;
};

const page = usePage();
const auth = useAuth();
const toast = useToast();
const { formatPhone, formatCpfCnpj } = useInputMasks();
const company = ref<any>(null);
const form = ref<CompanyForm>({
    name: '',
    email: '',
    phone: '',
    document: '',
    address: '',
    city: '',
    state: '',
    postal_code: '',
});
const initialForm = ref<CompanyForm | null>(null);
const errors = ref<Record<string, string[]>>({});
const isLoading = ref(true);
const isSaving = ref(false);
const showDeleteModal = ref(false);
const deleteStep = ref<1 | 2>(1);
const deleteConfirmInput = ref('');
const isDeleting = ref(false);
const deleteError = ref<string | null>(null);

const showClearRecordsModal = ref(false);
const clearRecordsStep = ref<1 | 2>(1);
const clearRecordsConfirmInput = ref('');
const isClearingRecords = ref(false);
const clearRecordsError = ref<string | null>(null);
const clearRecordsSuccess = ref<string | null>(null);
const saveSuccessMessage = ref('');
const userProfile = ref<UserProfileForm>({
    name: '',
    email: '',
});
const initialUserProfile = ref<UserProfileForm | null>(null);
const userProfileErrors = ref<Record<string, string[]>>({});
const userProfileSaving = ref(false);
const userProfileSuccess = ref('');
const passwordForm = ref({
    current_password: '',
    password: '',
    password_confirmation: '',
});
const passwordFormErrors = ref<Record<string, string[]>>({});
const passwordFormSuccess = ref('');
const passwordFormSaving = ref(false);
const reminderSettings = ref<ReminderSettingsForm>({
    daily_enabled: false,
    daily_condition: 'missing_entry_or_exit',
    daily_days_of_week: [1, 2, 3, 4, 5],
    send_time: '18:00',
    monthly_enabled: false,
    yearly_enabled: false,
    pdf_pin: '',
});
const initialReminderSettings = ref<Omit<ReminderSettingsForm, 'pdf_pin'> | null>(null);
const reminderErrors = ref<Record<string, string[]>>({});
const reminderSettingsLoading = ref(false);
const reminderSettingsSaving = ref(false);

const weekdays = [
    { value: 0, label: 'Dom' },
    { value: 1, label: 'Seg' },
    { value: 2, label: 'Ter' },
    { value: 3, label: 'Qua' },
    { value: 4, label: 'Qui' },
    { value: 5, label: 'Sex' },
    { value: 6, label: 'S?b' },
];

const getCsrfToken = (): string => {
    const csrfFromProps = (page.props as any).csrf_token;
    if (csrfFromProps) return csrfFromProps;
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
};

const canEdit = computed(() => {
    const role = auth.user.value?.role;
    return role === 'root' || role === 'owner';
});

const hasChanges = computed(() => {
    if (!initialForm.value) return false;

    return JSON.stringify(form.value) !== JSON.stringify(initialForm.value);
});

const userProfileHasChanges = computed(() => {
    if (!initialUserProfile.value) return false;

    return JSON.stringify(userProfile.value) !== JSON.stringify(initialUserProfile.value);
});

const createdAtText = computed(() => {
    const date = company.value?.created_at;
    if (!date) return '-';
    try {
        return new Date(date).toLocaleString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return '-';
    }
});

const hasReminderChanges = computed(() => {
    if (!initialReminderSettings.value) return false;

    const normalizeDays = (days: number[]) => [...days].sort((a, b) => a - b);
    const current = {
        daily_enabled: reminderSettings.value.daily_enabled,
        daily_condition: reminderSettings.value.daily_condition,
        daily_days_of_week: normalizeDays(reminderSettings.value.daily_days_of_week),
        send_time: reminderSettings.value.send_time,
        monthly_enabled: reminderSettings.value.monthly_enabled,
        yearly_enabled: reminderSettings.value.yearly_enabled,
    };

    const initial = {
        ...initialReminderSettings.value,
        daily_days_of_week: normalizeDays(initialReminderSettings.value.daily_days_of_week),
    };

    const baseChanged = JSON.stringify(current) !== JSON.stringify(initial);
    const pinChanged = reminderSettings.value.pdf_pin.trim().length > 0;

    return baseChanged || pinChanged;
});

const fillForm = (data: any) => {
    if (!data) return;
    company.value = data;
    const nextForm: CompanyForm = {
        name: data.name ?? '',
        email: data.email ?? '',
        phone: formatPhone(data.phone ?? ''),
        document: formatCpfCnpj(data.document ?? ''),
        address: data.address ?? '',
        city: data.city ?? '',
        state: data.state ?? '',
        postal_code: data.postal_code ?? '',
    };

    form.value = nextForm;
    initialForm.value = { ...nextForm };
};

const handlePhoneInput = (event: Event) => {
    const target = event.target as HTMLInputElement;
    form.value.phone = formatPhone(target.value);
};

const handleDocumentInput = (event: Event) => {
    const target = event.target as HTMLInputElement;
    form.value.document = formatCpfCnpj(target.value);
};

const fillUserProfile = (data: any) => {
    if (!data) return;
    const nextProfile: UserProfileForm = {
        name: data.name ?? '',
        email: data.email ?? '',
    };

    userProfile.value = nextProfile;
    initialUserProfile.value = { ...nextProfile };
};

const fetchUserProfile = async () => {
    try {
        const response = await fetch('/api/auth/profile', {
            headers: {
                'Authorization': `Bearer ${auth.token.value}`,
                'Accept': 'application/json',
            },
        });

        if (!response.ok) {
            if (auth.user.value) {
                fillUserProfile(auth.user.value);
            }
            return;
        }

        const json = await response.json();
        fillUserProfile(json.data);
    } catch (error: any) {
        if (auth.user.value) {
            fillUserProfile(auth.user.value);
        }
    }
};

const saveUserProfile = async () => {
    if (!canEdit.value) {
        toast.info('Acesso somente leitura', { description: 'Voc? n?o tem permiss?o para editar seu perfil.' });
        return;
    }

    userProfileSaving.value = true;
    userProfileErrors.value = {};
    userProfileSuccess.value = '';

    if (!userProfileHasChanges.value) {
        userProfileSaving.value = false;
        return;
    }

    try {
        const response = await fetch('/api/auth/profile', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': `Bearer ${auth.token.value}`,
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify(userProfile.value),
        });

        const payload = await response.json();

        if (!response.ok) {
            userProfileErrors.value = payload?.errors || {};
            throw new Error(payload?.message || 'Erro ao salvar perfil do usu?rio.');
        }

        fillUserProfile(payload.data);
        userProfileSuccess.value = 'Seu perfil foi atualizado com sucesso.';
        
        setTimeout(() => {
            userProfileSuccess.value = '';
        }, 5000);

        toast.success('Perfil atualizado', { description: 'Suas informa??es pessoais foram salvas com sucesso.' });
    } catch (error: any) {
        toast.error('Erro ao atualizar perfil', { description: error?.message || 'Tente novamente em instantes.' });
    } finally {
        userProfileSaving.value = false;
    }
};

const fetchCompany = async () => {
    isLoading.value = true;
    try {
        const response = await fetch('/api/company', {
            headers: {
                'Authorization': `Bearer ${auth.token.value}`,
                'Accept': 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error('N?o foi poss?vel carregar os dados da empresa.');
        }

        const json = await response.json();
        fillForm(json.data);
    } catch (error: any) {
        toast.error('Erro ao carregar dados', { description: error?.message || 'Tente novamente em instantes.' });
    } finally {
        isLoading.value = false;
    }
};

const fetchReminderSettings = async () => {
    reminderSettingsLoading.value = true;
    reminderErrors.value = {};

    try {
        const response = await fetch('/api/company/reminder-settings', {
            headers: {
                'Authorization': `Bearer ${auth.token.value}`,
                'Accept': 'application/json',
            },
        });

        const payload = await response.json();

        if (!response.ok) {
            throw new Error(payload?.message || 'N?o foi poss?vel carregar as configura??es de lembrete.');
        }

        reminderSettings.value = {
            daily_enabled: Boolean(payload?.data?.daily_enabled),
            daily_condition: payload?.data?.daily_condition || 'missing_entry_or_exit',
            daily_days_of_week: Array.isArray(payload?.data?.daily_days_of_week) ? payload.data.daily_days_of_week : [1, 2, 3, 4, 5],
            send_time: payload?.data?.send_time || '18:00',
            monthly_enabled: Boolean(payload?.data?.monthly_enabled),
            yearly_enabled: Boolean(payload?.data?.yearly_enabled),
            pdf_pin: '',
        };

        initialReminderSettings.value = {
            daily_enabled: reminderSettings.value.daily_enabled,
            daily_condition: reminderSettings.value.daily_condition,
            daily_days_of_week: [...reminderSettings.value.daily_days_of_week],
            send_time: reminderSettings.value.send_time,
            monthly_enabled: reminderSettings.value.monthly_enabled,
            yearly_enabled: reminderSettings.value.yearly_enabled,
        };
    } catch (error: any) {
        toast.error('Erro ao carregar lembretes', { description: error?.message || 'Tente novamente em instantes.' });
    } finally {
        reminderSettingsLoading.value = false;
    }
};

const toggleWeekday = (day: number) => {
    const current = reminderSettings.value.daily_days_of_week;
    if (current.includes(day)) {
        reminderSettings.value.daily_days_of_week = current.filter((item) => item !== day);
        return;
    }

    reminderSettings.value.daily_days_of_week = [...current, day].sort((a, b) => a - b);
};

const saveReminderSettings = async () => {
    if (!canEdit.value) {
        toast.info('Acesso somente leitura', { description: 'Somente o usu?rio root pode editar os lembretes.' });
        return;
    }

    reminderSettingsSaving.value = true;
    reminderErrors.value = {};

    if (!hasReminderChanges.value) {
        reminderSettingsSaving.value = false;
        return;
    }

    try {
        const response = await fetch('/api/company/reminder-settings', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': `Bearer ${auth.token.value}`,
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify(reminderSettings.value),
        });

        const payload = await response.json();

        if (!response.ok) {
            reminderErrors.value = payload?.errors || {};
            throw new Error(payload?.message || 'Erro ao salvar configura??es de lembrete.');
        }

        reminderSettings.value = {
            daily_enabled: Boolean(payload?.data?.daily_enabled),
            daily_condition: payload?.data?.daily_condition || 'missing_entry_or_exit',
            daily_days_of_week: Array.isArray(payload?.data?.daily_days_of_week) ? payload.data.daily_days_of_week : [],
            send_time: payload?.data?.send_time || '18:00',
            monthly_enabled: Boolean(payload?.data?.monthly_enabled),
            yearly_enabled: Boolean(payload?.data?.yearly_enabled),
            pdf_pin: '',
        };

        initialReminderSettings.value = {
            daily_enabled: reminderSettings.value.daily_enabled,
            daily_condition: reminderSettings.value.daily_condition,
            daily_days_of_week: [...reminderSettings.value.daily_days_of_week],
            send_time: reminderSettings.value.send_time,
            monthly_enabled: reminderSettings.value.monthly_enabled,
            yearly_enabled: reminderSettings.value.yearly_enabled,
        };

        toast.success('Lembretes atualizados', { description: 'Suas prefer?ncias de notifica??o foram salvas.' });
    } catch (error: any) {
        toast.error('Falha ao salvar lembretes', { description: error?.message || 'Tente novamente em instantes.' });
    } finally {
        reminderSettingsSaving.value = false;
    }
};

const save = async () => {
    if (!canEdit.value) {
        toast.info('Acesso somente leitura', { description: 'Somente o usu?rio root pode editar a conta.' });
        return;
    }

    if (!hasChanges.value) {
        return;
    }

    isSaving.value = true;
    errors.value = {};
    saveSuccessMessage.value = '';

    try {
        const response = await fetch('/api/company', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': `Bearer ${auth.token.value}`,
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify(form.value),
        });

        const payload = await response.json();

        if (!response.ok) {
            errors.value = payload?.errors || {};
            throw new Error(payload?.message || 'Erro ao salvar');
        }

        fillForm(payload.data);

        // Atualiza o usu?rio em mem?ria para manter company sincronizado
        const currentUser = auth.user.value || {};
        auth.setUser({ ...currentUser, company: payload.data });

        saveSuccessMessage.value = 'Informa??es salvas com sucesso.';
    } catch (error: any) {
        toast.error('N?o foi poss?vel salvar', { description: error?.message || 'Revise os campos e tente novamente.' });
    } finally {
        isSaving.value = false;
    }
};

const submitPasswordChange = async () => {
    passwordFormErrors.value = {};
    passwordFormSuccess.value = '';

    if (!passwordForm.value.current_password) {
        passwordFormErrors.value = {
            current_password: ['Informe sua senha atual.'],
        };
        return;
    }

    if (!passwordForm.value.password || !passwordForm.value.password_confirmation) {
        passwordFormErrors.value = {
            password: ['Informe a nova senha e a confirma??o.'],
        };
        return;
    }

    if (passwordForm.value.password !== passwordForm.value.password_confirmation) {
        passwordFormErrors.value = {
            password_confirmation: ['A confirma??o da nova senha n?o corresponde.'],
        };
        return;
    }

    passwordFormSaving.value = true;

    try {
        const response = await fetch('/settings/password', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': `Bearer ${auth.token.value}`,
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({
                current_password: passwordForm.value.current_password || '',
                password: passwordForm.value.password,
                password_confirmation: passwordForm.value.password_confirmation,
            }),
        });

        const payload = await response.json();

        if (!response.ok) {
            passwordFormErrors.value = payload?.errors || {};
            throw new Error(payload?.message || 'N?o foi poss?vel atualizar a senha.');
        }

        passwordForm.value = {
            current_password: '',
            password: '',
            password_confirmation: '',
        };
        passwordFormSuccess.value = 'Senha atualizada com sucesso.';
        toast.success('Senha atualizada', { description: 'Sua senha foi alterada com sucesso.' });
    } catch (error: any) {
        toast.error('Erro ao atualizar senha', { description: error?.message || 'Verifique os dados e tente novamente.' });
    } finally {
        passwordFormSaving.value = false;
    }
};

const dismissSaveSuccessMessage = () => {
    saveSuccessMessage.value = '';
};

const openDeleteModal = () => {
    if (!canEdit.value) {
        toast.info('Acesso somente leitura', { description: 'Somente o usu?rio root pode apagar a conta.' });
        return;
    }
    showDeleteModal.value = true;
    deleteStep.value = 1;
    deleteConfirmInput.value = '';
    deleteError.value = null;
};

const closeDeleteModal = () => {
    showDeleteModal.value = false;
    deleteStep.value = 1;
    deleteConfirmInput.value = '';
    deleteError.value = null;
};

const openClearRecordsModal = () => {
    if (!canEdit.value) {
        toast.info('Acesso somente leitura', { description: 'Somente o usu?rio root pode limpar registros.' });
        return;
    }
    showClearRecordsModal.value = true;
    clearRecordsStep.value = 1;
    clearRecordsConfirmInput.value = '';
    clearRecordsError.value = null;
};

const closeClearRecordsModal = () => {
    showClearRecordsModal.value = false;
    clearRecordsStep.value = 1;
    clearRecordsConfirmInput.value = '';
    clearRecordsError.value = null;
};

const handleClearRecordsConfirm = async () => {
    clearRecordsError.value = null;
    clearRecordsSuccess.value = null;

    if (clearRecordsStep.value === 1) {
        clearRecordsStep.value = 2;
        clearRecordsConfirmInput.value = '';
        return;
    }

    if (clearRecordsConfirmInput.value.trim().toUpperCase() !== 'LIMPAR') {
        clearRecordsError.value = 'Digite LIMPAR para confirmar a exclus?o de todos os registros.';
        return;
    }

    isClearingRecords.value = true;

    try {
        const response = await fetch('/api/registros/financial-records/clear-all', {
            method: 'DELETE',
            headers: {
                'Authorization': `Bearer ${auth.token.value}`,
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
        });

        const payload = await response.json();

        if (!response.ok) {
            throw new Error(payload?.message || 'Erro ao limpar registros');
        }

        clearRecordsSuccess.value = `${payload.financial_records_count} registros financeiros e ${payload.imported_commissions_count} comiss?es importadas foram removidos com sucesso.`;

        toast.success('Registros limpos', {
            description: `${payload.financial_records_count} registros financeiros e ${payload.imported_commissions_count} comiss?es importadas foram removidos com sucesso.`,
        });
        closeClearRecordsModal();
    } catch (error: any) {
        clearRecordsError.value = error?.message || 'Erro ao limpar registros. Tente novamente.';
    } finally {
        isClearingRecords.value = false;
    }
};

const handleDeleteConfirm = async () => {
    deleteError.value = null;

    if (deleteStep.value === 1) {
        deleteStep.value = 2;
        deleteConfirmInput.value = '';
        return;
    }

    if (deleteConfirmInput.value.trim().toUpperCase() !== 'APAGAR') {
        deleteError.value = 'Digite APAGAR para confirmar a exclus?o definitiva.';
        return;
    }

    isDeleting.value = true;

    try {
        const response = await fetch('/api/company', {
            method: 'DELETE',
            headers: {
                'Authorization': `Bearer ${auth.token.value}`,
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
        });

        const payload = await response.json();

        if (!response.ok) {
            throw new Error(payload?.message || 'Erro ao apagar conta');
        }

        toast.success('Conta apagada', { description: 'Todos os dados foram removidos.' });
        auth.logout();
        window.location.href = '/login?account_deleted=1';
    } catch (error: any) {
        deleteError.value = error?.message || 'Erro ao apagar conta. Tente novamente.';
    } finally {
        isDeleting.value = false;
    }
};

onMounted(async () => {
    if (!auth.isAuthenticated.value) {
        window.location.href = '/login';
        return;
    }

    // Pr?-preenche com dados existentes (ex.: ap?s login)
    if (auth.user.value?.company) {
        fillForm(auth.user.value.company);
    }

    if (auth.user.value) {
        fillUserProfile(auth.user.value);
    }

    await fetchCompany();
    await fetchUserProfile();
    await fetchReminderSettings();
});
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-amber-50 to-orange-100 dark:from-slate-950 dark:via-slate-900 dark:to-slate-950 flex flex-col overflow-x-hidden">
        <Head title="Minha Conta - MEU NEG?CIO" />

        <Sidebar active-route="conta" />

        <div class="flex-1 min-w-0 lg:ml-64 transition-all duration-300 overflow-x-hidden flex flex-col">
            <FixedPageHeaderTitle
                title="Minha Conta"
                subtitle="Gerencie as informa??es da sua empresa e acesso"
            />
            
            <main class="w-full min-w-0 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 overflow-x-hidden">
                <AppInlineNotice
                    v-if="saveSuccessMessage"
                    class="mb-6"
                    title="Dados atualizados"
                    :message="saveSuccessMessage"
                    variant="success"
                    @dismiss="dismissSaveSuccessMessage"
                />

                <div class="bg-white rounded-xl shadow-lg border border-blue-200/70 overflow-hidden">

                    <div class="p-6 sm:p-10">
                        <LoadingSpinner v-if="isLoading" message="Carregando informa??es da conta..." />

                        <div v-else class="space-y-8">
                            <!-- User Profile Section -->
                            <section class="bg-blue-50 dark:bg-slate-800 border border-blue-100 dark:border-slate-700 rounded-xl p-6 shadow-sm overflow-hidden">
                                <div class="flex items-center justify-between mb-4">
                                    <div>
                                        <p class="text-xs uppercase tracking-wide text-blue-500 dark:text-blue-400 font-semibold">Seu Perfil</p>
                                        <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100">Informa??es Pessoais</h3>
                                    </div>
                                    <span v-if="!canEdit" class="text-xs font-medium text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-slate-700 px-3 py-1 rounded-full">Somente leitura</span>
                                </div>

                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nome completo</label>
                                        <input
                                            v-model="userProfile.name"
                                            type="text"
                                            :disabled="!canEdit"
                                            placeholder="Digite seu nome completo"
                                            class="w-full px-3 sm:px-4 py-2.5 sm:py-3 border-2 border-blue-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent disabled:bg-gray-100"
                                        />
                                        <p v-if="userProfileErrors.name" class="text-sm text-red-600 dark:text-red-400 mt-1">{{ userProfileErrors.name[0] }}</p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                                        <input
                                            v-model="userProfile.email"
                                            type="email"
                                            :disabled="!canEdit"
                                            placeholder="seu.email@exemplo.com"
                                            class="w-full px-3 sm:px-4 py-2.5 sm:py-3 border-2 border-blue-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent disabled:bg-gray-100"
                                        />
                                        <p v-if="userProfileErrors.email" class="text-sm text-red-600 dark:text-red-400 mt-1">{{ userProfileErrors.email[0] }}</p>
                                    </div>

                                    <div v-if="userProfileSuccess" class="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 p-3 mt-4">
                                        <p class="text-sm text-green-700 dark:text-green-400">{{ userProfileSuccess }}</p>
                                    </div>

                                    <button
                                        v-if="canEdit && userProfileHasChanges"
                                        @click="saveUserProfile"
                                        :disabled="userProfileSaving"
                                        class="w-full mt-4 px-4 py-2.5 bg-blue-500 dark:bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-600 dark:hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                    >
                                        {{ userProfileSaving ? 'Salvando...' : 'Salvar Perfil' }}
                                    </button>
                                </div>
                            </section>

                            <!-- Company Info Section -->
                            <div class="grid gap-6 lg:grid-cols-2 overflow-hidden">
                                <section class="bg-orange-50 border border-orange-100 rounded-xl p-6 shadow-sm overflow-hidden">
                                    <div class="flex items-center justify-between mb-4">
                                        <div>
                                            <p class="text-xs uppercase tracking-wide text-orange-500 font-semibold">Informa??es da Conta</p>
                                            <h3 class="text-lg font-bold text-gray-800">Dados da empresa</h3>
                                        </div>
                                        <span v-if="!canEdit" class="text-xs font-medium text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-slate-700 px-3 py-1 rounded-full">Somente leitura</span>
                                    </div>

                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nome da empresa *</label>
                                            <input
                                                v-model="form.name"
                                                type="text"
                                                :disabled="!canEdit"
                                                placeholder="Ex: Xerografia & Cia"
                                                class="w-full px-3 sm:px-4 py-2.5 sm:py-3 border-2 border-amber-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent disabled:bg-gray-100"
                                            />
                                            <p v-if="errors.name" class="text-sm text-red-600 mt-1">{{ errors.name[0] }}</p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Email de contato</label>
                                            <input
                                                v-model="form.email"
                                                type="email"
                                                :disabled="!canEdit"
                                                placeholder="contato@empresa.com"
                                                class="w-full px-3 sm:px-4 py-2.5 sm:py-3 border-2 border-amber-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent disabled:bg-gray-100"
                                            />
                                            <p v-if="errors.email" class="text-sm text-red-600 mt-1">{{ errors.email[0] }}</p>
                                        </div>

                                        <div class="grid gap-4 sm:grid-cols-2">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Telefone</label>
                                                <input
                                                    v-model="form.phone"
                                                    @input="handlePhoneInput"
                                                    type="tel"
                                                    :disabled="!canEdit"
                                                    placeholder="(11) 99999-9999"
                                                    class="w-full px-3 sm:px-4 py-2.5 sm:py-3 border-2 border-amber-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent disabled:bg-gray-100"
                                                />
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">CNPJ/CPF</label>
                                                <input
                                                    v-model="form.document"
                                                    @input="handleDocumentInput"
                                                    type="text"
                                                    :disabled="!canEdit"
                                                    placeholder="00.000.000/0000-00"
                                                    class="w-full px-3 sm:px-4 py-2.5 sm:py-3 border-2 border-amber-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent disabled:bg-gray-100"
                                                />
                                                <p v-if="errors.document" class="text-sm text-red-600 mt-1">{{ errors.document[0] }}</p>
                                            </div>
                                        </div>
                                    </div>

                                </section>

                                <section class="bg-white border border-amber-100 rounded-xl p-6 shadow-sm overflow-hidden">
                                    <p class="text-xs uppercase tracking-wide text-orange-500 font-semibold">Endere?o (opcional)</p>
                                    <h3 class="text-lg font-bold text-gray-800 mb-4">Onde sua empresa fica?</h3>

                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Endere?o</label>
                                            <input
                                                v-model="form.address"
                                                type="text"
                                                :disabled="!canEdit"
                                                placeholder="Rua, n?mero, bairro"
                                                class="w-full px-3 sm:px-4 py-2.5 sm:py-3 border-2 border-amber-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent disabled:bg-gray-100"
                                            />
                                        </div>

                                        <div class="grid gap-4 sm:grid-cols-2">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Cidade</label>
                                                <input
                                                    v-model="form.city"
                                                    type="text"
                                                    :disabled="!canEdit"
                                                    placeholder="Cidade"
                                                    class="w-full px-3 sm:px-4 py-2.5 sm:py-3 border-2 border-amber-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent disabled:bg-gray-100"
                                                />
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">UF</label>
                                                <input
                                                    v-model="form.state"
                                                    type="text"
                                                    maxlength="2"
                                                    :disabled="!canEdit"
                                                    placeholder="UF"
                                                    class="w-full px-3 sm:px-4 py-2.5 sm:py-3 border-2 border-amber-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent uppercase disabled:bg-gray-100"
                                                />
                                            </div>
                                        </div>

                                        <div class="grid gap-4 sm:grid-cols-2">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">CEP</label>
                                                <input
                                                    v-model="form.postal_code"
                                                    type="text"
                                                    :disabled="!canEdit"
                                                    placeholder="00000-000"
                                                    class="w-full px-3 sm:px-4 py-2.5 sm:py-3 border-2 border-amber-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent disabled:bg-gray-100"
                                                />
                                            </div>
                                            <div class="flex items-center gap-3 text-sm text-gray-600 bg-orange-50 border border-orange-100 rounded-lg px-3 py-2">
                                                <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z" />
                                                </svg>
                                                <span>Endere?o ? opcional e pode ser usado em relat?rios futuros.</span>
                                            </div>
                                        </div>
                                    </div>
                                </section>
                            </div>
                            <div class="flex flex-col sm:flex-row flex-wrap gap-3 justify-end">
                                <button
                                    type="button"
                                    :disabled="isSaving || !canEdit || !hasChanges"
                                    @click="save"
                                    class="w-full sm:w-auto px-5 py-3 bg-gradient-to-r from-amber-400 to-orange-500 text-white rounded-lg font-medium hover:shadow-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    {{ isSaving ? 'Salvando...' : 'Salvar altera??es' }}
                                </button>
                            </div>


                            <section class="bg-white border border-amber-100 rounded-xl p-6 shadow-sm overflow-hidden">
                                <div class="flex items-center justify-between gap-4 mb-4">
                                    <div>
                                        <p class="text-xs uppercase tracking-wide text-orange-500 font-semibold">Notifica??es por e-mail</p>
                                        <h3 class="text-lg font-bold text-gray-800">Lembretes e relat?rios autom?ticos</h3>
                                    </div>
                                </div>

                                <LoadingSpinner v-if="reminderSettingsLoading" message="Carregando configura??es de lembrete..." />

                                <div v-else class="space-y-5">
                                    <p class="text-sm text-gray-600">
                                        Voc? pode cancelar a qualquer momento os envios autom?ticos nesta se??o.
                                        Os e-mails de relat?rio n?o exibem dados sens?veis no corpo da mensagem.
                                    </p>

                                    <div class="grid gap-4 lg:grid-cols-2">
                                        <div class="space-y-3 border border-amber-100 rounded-lg p-4 bg-amber-50/40">
                                            <label class="inline-flex items-center gap-2 text-sm font-semibold text-gray-800">
                                                <input v-model="reminderSettings.daily_enabled" type="checkbox" :disabled="!canEdit" class="rounded border-gray-300 text-amber-500 focus:ring-amber-400" />
                                                Ativar lembrete di?rio
                                            </label>

                                            <div class="space-y-2">
                                                <label class="block text-sm font-medium text-gray-700">Condi??o para envio di?rio</label>
                                                <select
                                                    v-model="reminderSettings.daily_condition"
                                                    :disabled="!canEdit || !reminderSettings.daily_enabled"
                                                    class="w-full px-3 py-2.5 border-2 border-amber-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-400 disabled:bg-gray-100"
                                                >
                                                    <option value="missing_entry_or_exit">Quando faltar entrada ou sa?da</option>
                                                    <option value="missing_both">Quando faltar entrada e sa?da</option>
                                                    <option value="missing_entry">Quando faltar somente entrada</option>
                                                    <option value="missing_exit">Quando faltar somente sa?da</option>
                                                </select>
                                            </div>

                                            <div class="space-y-2">
                                                <label class="block text-sm font-medium text-gray-700">Dias da semana</label>
                                                <div class="flex flex-wrap gap-2">
                                                    <button
                                                        v-for="weekday in weekdays"
                                                        :key="weekday.value"
                                                        type="button"
                                                        :disabled="!canEdit || !reminderSettings.daily_enabled"
                                                        @click="toggleWeekday(weekday.value)"
                                                        :class="[
                                                            'px-3 py-1.5 text-xs rounded-full border transition',
                                                            reminderSettings.daily_days_of_week.includes(weekday.value)
                                                                ? 'bg-amber-500 text-white border-amber-500'
                                                                : 'bg-white text-gray-700 border-amber-200',
                                                            (!canEdit || !reminderSettings.daily_enabled) ? 'opacity-50 cursor-not-allowed' : 'hover:bg-amber-100'
                                                        ]"
                                                    >
                                                        {{ weekday.label }}
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="space-y-3 border border-amber-100 rounded-lg p-4 bg-white">
                                            <div class="space-y-2">
                                                <label class="block text-sm font-medium text-gray-700">Hor?rio de envio</label>
                                                <input
                                                    v-model="reminderSettings.send_time"
                                                    type="time"
                                                    :disabled="!canEdit"
                                                    class="w-full px-3 py-2.5 border-2 border-amber-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-400 disabled:bg-gray-100"
                                                />
                                            </div>

                                            <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700">
                                                <input v-model="reminderSettings.monthly_enabled" type="checkbox" :disabled="!canEdit" class="rounded border-gray-300 text-amber-500 focus:ring-amber-400" />
                                                Enviar relat?rio mensal (dia 1, m?s anterior)
                                            </label>

                                            <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700">
                                                <input v-model="reminderSettings.yearly_enabled" type="checkbox" :disabled="!canEdit" class="rounded border-gray-300 text-amber-500 focus:ring-amber-400" />
                                                Enviar relat?rio anual (01/01, ano anterior)
                                            </label>

                                            <div class="space-y-2">
                                                <label class="block text-sm font-medium text-gray-700">PIN do PDF (4 caracteres alfanum?ricos)</label>
                                                <input
                                                    v-model="reminderSettings.pdf_pin"
                                                    type="text"
                                                    maxlength="4"
                                                    autocomplete="off"
                                                    :disabled="!canEdit"
                                                    placeholder="Ex: A1B2"
                                                    class="w-full px-3 py-2.5 border-2 border-amber-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-400 disabled:bg-gray-100 uppercase"
                                                />
                                                <p class="text-xs text-gray-500">Deixe em branco para manter o PIN atual.</p>
                                            </div>
                                        </div>
                                    </div>

                                    <p v-if="reminderErrors.pdf_pin" class="text-sm text-red-600">{{ reminderErrors.pdf_pin[0] }}</p>
                                    <p v-if="reminderErrors.send_time" class="text-sm text-red-600">{{ reminderErrors.send_time[0] }}</p>

                                    <div class="flex justify-end">
                                        <button
                                            type="button"
                                            :disabled="reminderSettingsSaving || !canEdit || !hasReminderChanges"
                                            @click="saveReminderSettings"
                                            class="w-full sm:w-auto px-5 py-3 bg-gradient-to-r from-amber-400 to-orange-500 text-white rounded-lg font-medium hover:shadow-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                                        >
                                            {{ reminderSettingsSaving ? 'Salvando lembretes...' : 'Salvar lembretes' }}
                                        </button>
                                    </div>
                                </div>
                            </section>

                            <div class="grid gap-6 lg:grid-cols-2 overflow-hidden">
                                <section class="bg-white border border-amber-100 rounded-xl p-6 shadow-sm overflow-hidden">
                                    <p class="text-xs uppercase tracking-wide text-orange-500 font-semibold">Acesso</p>
                                    <h3 class="text-lg font-bold text-gray-800 mb-4">Alterar senha</h3>

                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Senha atual *</label>
                                            <input
                                                v-model="passwordForm.current_password"
                                                type="password"
                                                autocomplete="current-password"
                                                placeholder="Digite sua senha atual"
                                                class="w-full px-3 sm:px-4 py-2.5 sm:py-3 border-2 border-amber-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent"
                                            />
                                            <p v-if="passwordFormErrors.current_password" class="text-sm text-red-600 mt-1">{{ passwordFormErrors.current_password[0] }}</p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Nova senha *</label>
                                            <input
                                                v-model="passwordForm.password"
                                                type="password"
                                                autocomplete="new-password"
                                                placeholder="Digite a nova senha"
                                                class="w-full px-3 sm:px-4 py-2.5 sm:py-3 border-2 border-amber-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent"
                                            />
                                            <p v-if="passwordFormErrors.password" class="text-sm text-red-600 mt-1">{{ passwordFormErrors.password[0] }}</p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Confirmar nova senha *</label>
                                            <input
                                                v-model="passwordForm.password_confirmation"
                                                type="password"
                                                autocomplete="new-password"
                                                placeholder="Confirme a nova senha"
                                                class="w-full px-3 sm:px-4 py-2.5 sm:py-3 border-2 border-amber-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent"
                                            />
                                            <p v-if="passwordFormErrors.password_confirmation" class="text-sm text-red-600 mt-1">{{ passwordFormErrors.password_confirmation[0] }}</p>
                                        </div>

                                        <button
                                            type="button"
                                            :disabled="passwordFormSaving"
                                            @click="submitPasswordChange"
                                            class="w-full sm:w-auto px-5 py-3 bg-gradient-to-r from-amber-400 to-orange-500 text-white rounded-lg font-medium hover:shadow-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                                        >
                                            {{ passwordFormSaving ? 'Atualizando...' : 'Atualizar senha' }}
                                        </button>

                                        <p v-if="passwordFormSuccess" class="text-sm font-medium text-emerald-700">{{ passwordFormSuccess }}</p>
                                    </div>
                                </section>

                                <section class="bg-white border border-amber-100 rounded-xl p-6 shadow-sm flex flex-col gap-4 overflow-hidden">
                                    <p class="text-xs uppercase tracking-wide text-orange-500 font-semibold">Identidade visual</p>
                                    <div class="flex flex-col sm:flex-row items-center gap-4">
                                        <div class="w-24 h-24 rounded-full border-2 border-orange-200 bg-orange-50 flex items-center justify-center overflow-hidden shadow">
                                            <img :src="logoUrl" alt="Logomarca" class="w-16 h-16 object-contain" />
                                        </div>
                                        <div class="space-y-1">
                                            <h3 class="text-lg font-bold text-gray-800">Logomarca ou foto</h3>
                                            <p class="text-sm text-gray-600">Envio de logo estar? dispon?vel em breve.</p>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        disabled
                                        class="w-full sm:w-auto px-4 py-3 border border-amber-200 text-orange-600 rounded-lg font-medium bg-orange-50 cursor-not-allowed"
                                    >
                                        Upload dispon?vel em breve
                                    </button>
                                </section>
                            </div>

                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-orange-50 border border-orange-100 rounded-xl px-4 py-3 text-center sm:text-left">
                                <div class="w-full sm:w-auto">
                                    <p class="text-sm text-gray-600">Conta criada em</p>
                                    <p class="text-lg font-semibold text-gray-800">{{ createdAtText }}</p>
                                </div>
                                <div class="flex flex-col sm:flex-row gap-3">
                                    <button
                                        type="button"
                                        :disabled="!canEdit || isClearingRecords"
                                        @click="openClearRecordsModal"
                                        class="w-full sm:w-auto px-5 py-3 bg-orange-50 text-orange-600 border border-orange-300 rounded-lg font-medium hover:bg-orange-100 transition disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        Limpar registros
                                    </button>
                                    <button
                                        type="button"
                                        :disabled="!canEdit || isDeleting"
                                        @click="openDeleteModal"
                                        class="w-full sm:w-auto px-5 py-3 bg-red-50 text-red-600 border border-red-200 rounded-lg font-medium hover:bg-red-100 transition disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        Apagar conta
                                    </button>
                                </div>
                            </div>

                            <div v-if="clearRecordsSuccess" class="-mt-2 bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-3">
                                <p class="text-sm font-medium text-emerald-700">{{ clearRecordsSuccess }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
            
            <Footer />
        </div>

        <Transition name="fade">
            <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/60"></div>
                <div class="relative w-full max-w-3xl bg-white rounded-xl sm:rounded-2xl shadow-2xl border-2 border-blue-200 overflow-hidden max-h-[90vh] overflow-y-auto">
                    <div class="px-4 sm:px-6 lg:px-10 py-4 sm:py-6 border-b border-amber-100 bg-gradient-to-r from-amber-50 to-orange-50">
                        <p class="text-sm font-semibold text-amber-600 uppercase tracking-wide">Destruindo conta</p>
                        <h3 class="text-xl sm:text-2xl lg:text-3xl font-bold text-gray-900 mt-2">{{ deleteStep === 1 ? 'Apagando conta' : 'Confirma??o final' }}</h3>
                    </div>
                    <div class="px-4 sm:px-6 lg:px-10 py-4 sm:py-6 space-y-4 sm:space-y-6">
                        <p class="text-gray-700 text-base sm:text-lg leading-relaxed sm:leading-7">
                            Voc? est? prestes a apagar sua conta e todos os dados da empresa. Esta a??o ? permanente e n?o pode ser desfeita.
                        </p>

                        <ul class="list-disc list-inside text-gray-700 space-y-2">
                            <li>Todas as informa??es (entradas, sa?das, colaboradores, configura??es) ser?o removidas.</li>
                            <li>Os usu?rios vinculados perder?o o acesso imediatamente.</li>
                            <li>N?o ser? poss?vel recuperar os dados ap?s a confirma??o.</li>
                        </ul>

                        <div v-if="deleteStep === 2" class="space-y-3">
                            <p class="text-sm text-gray-700 font-medium">Digite <span class="font-bold text-red-600">APAGAR</span> para confirmar.</p>
                            <input
                                v-model="deleteConfirmInput"
                                type="text"
                                placeholder="APAGAR"
                                class="w-full px-3 sm:px-4 py-2.5 sm:py-3 border-2 border-red-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-400"
                                :disabled="isDeleting"
                            />
                        </div>

                        <p v-if="deleteError" class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">{{ deleteError }}</p>

                        <div class="flex flex-col sm:flex-row gap-3 sm:justify-end">
                            <button
                                type="button"
                                class="w-full sm:w-auto px-5 py-3 rounded-lg font-semibold border border-amber-300 bg-amber-50 text-amber-700 hover:bg-amber-100 transition"
                                @click="closeDeleteModal"
                                :disabled="isDeleting"
                            >
                                Cancelar
                            </button>
                            <button
                                type="button"
                                class="w-full sm:w-auto px-5 py-3 rounded-lg font-semibold border border-red-300 text-white bg-gradient-to-r from-red-500 to-orange-500 hover:from-red-600 hover:to-orange-600 transition disabled:opacity-50 disabled:cursor-not-allowed"
                                @click="handleDeleteConfirm"
                                :disabled="isDeleting"
                            >
                                {{ deleteStep === 1 ? 'Continuar' : (isDeleting ? 'Apagando...' : 'Apagar conta') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Modal de Limpar Registros -->
        <Transition name="fade">
            <div v-if="showClearRecordsModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/60"></div>
                <div class="relative w-full max-w-3xl bg-white rounded-xl sm:rounded-2xl shadow-2xl border-2 border-orange-200 overflow-hidden max-h-[90vh] overflow-y-auto">
                    <div class="px-4 sm:px-6 lg:px-10 py-4 sm:py-6 border-b border-orange-100 bg-gradient-to-r from-orange-50 to-yellow-50">
                        <p class="text-sm font-semibold text-orange-600 uppercase tracking-wide">Limpeza de dados</p>
                        <h3 class="text-xl sm:text-2xl lg:text-3xl font-bold text-gray-900 mt-2">{{ clearRecordsStep === 1 ? 'Limpar todos os registros' : 'Confirma??o final' }}</h3>
                    </div>
                    <div class="px-4 sm:px-6 lg:px-10 py-4 sm:py-6 space-y-4 sm:space-y-6">
                        <p class="text-gray-700 text-base sm:text-lg leading-relaxed sm:leading-7">
                            Voc? est? prestes a limpar todos os registros financeiros e as comiss?es importadas da sua empresa. Esta a??o ? permanente e n?o pode ser desfeita.
                        </p>

                        <ul class="list-disc list-inside text-gray-700 space-y-2">
                            <li>Todos os registros de entradas ser?o removidos.</li>
                            <li>Todos os registros de sa?das ser?o removidos.</li>
                            <li>Comiss?es importadas por planilha ser?o removidas.</li>
                            <li>Comiss?es normais, turnos e fechamentos di?rios permanecer?o intactos.</li>
                            <li>Colaboradores e tipos (entrada/sa?da/servi?o) n?o ser?o afetados.</li>
                            <li>N?o ser? poss?vel recuperar os dados ap?s a confirma??o.</li>
                        </ul>

                        <div v-if="clearRecordsStep === 2" class="space-y-3">
                            <p class="text-sm text-gray-700 font-medium">Digite <span class="font-bold text-orange-600">LIMPAR</span> para confirmar.</p>
                            <input
                                v-model="clearRecordsConfirmInput"
                                type="text"
                                placeholder="LIMPAR"
                                class="w-full px-3 sm:px-4 py-2.5 sm:py-3 border-2 border-orange-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400"
                                :disabled="isClearingRecords"
                            />
                        </div>

                        <p v-if="clearRecordsError" class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">{{ clearRecordsError }}</p>

                        <div class="flex flex-col sm:flex-row gap-3 sm:justify-end">
                            <button
                                type="button"
                                class="w-full sm:w-auto px-5 py-3 rounded-lg font-semibold border border-gray-300 bg-gray-50 text-gray-700 hover:bg-gray-100 transition"
                                @click="closeClearRecordsModal"
                                :disabled="isClearingRecords"
                            >
                                Cancelar
                            </button>
                            <button
                                type="button"
                                class="w-full sm:w-auto px-5 py-3 rounded-lg font-semibold border border-orange-300 text-white bg-gradient-to-r from-orange-500 to-yellow-500 hover:from-orange-600 hover:to-yellow-600 transition disabled:opacity-50 disabled:cursor-not-allowed"
                                @click="handleClearRecordsConfirm"
                                :disabled="isClearingRecords"
                            >
                                {{ clearRecordsStep === 1 ? 'Continuar' : (isClearingRecords ? 'Limpando...' : 'Limpar registros') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>
