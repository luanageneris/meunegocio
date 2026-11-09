/**
 * PROTEÇÃO DE ITENS "NÃO DEFINIDO"
 * 
 * Estratégia para proteger contra exclusão/renomeação de itens padrão
 * que devem existir em todas as contas
 */

// ============================================
// 1. Backend - Proteção em Controller
// ============================================

// App/Http/Controllers/EntryTypeController.php
export class EntryTypeController {
    public function destroy(EntryType $entryType): Response {
        // Proteção: Não permitir deletar "Não definido"
        if ($entryType->name === 'Não definido') {
            return response()->json([
                'message' => 'Não é permitido deletar o tipo de entrada "Não definido"',
            ], 403);
        }

        $entryType->delete();
        return response()->json(['message' => 'Tipo de entrada removido']);
    }

    public function update(Request $request, EntryType $entryType): Response {
        // Proteção: Não permitir renomear "Não definido"
        if ($entryType->name === 'Não definido' && $request->input('name') !== 'Não definido') {
            return response()->json([
                'message' => 'Não é permitido renomear o tipo de entrada "Não definido"',
            ], 403);
        }

        $entryType->update($request->validated());
        return response()->json(['data' => $entryType]);
    }
}

// Mesmo padrão para:
// - App/Http/Controllers/ExitTypeController.php
// - App/Http/Controllers/CollaboratorController.php


// ============================================
// 2. Database - Seeder para Criação de Padrão
// ============================================

// database/seeders/CreateDefaultItemsSeeder.php
export class CreateDefaultItemsSeeder extends Seeder {
    public function run(): void {
        foreach (Company::all() as $company) {
            // Criar tipo de entrada "Não definido"
            EntryType::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'name' => 'Não definido',
                ],
                ['name' => 'Não definido']
            );

            // Criar tipo de saída "Não definido"
            ExitType::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'name' => 'Não definido',
                ],
                ['name' => 'Não definido']
            );

            // Criar colaborador "Não definido"
            Collaborator::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'name' => 'Não definido',
                ],
                [
                    'name' => 'Não definido',
                    'cpf' => '000.000.000-00', // CPF placeholder
                    'active' => true,
                ]
            );
        }
    }
}

// Executar na criação de nova empresa:
// - CompanyService::create() ou similar


// ============================================
// 3. Frontend - Desabilitar Botões de Delete
// ============================================

// Em qualquer componente que lista esses itens:

// Template
<template>
    <div v-for="item in items" :key="item.id" class="flex gap-2">
        <span>{{ item.name }}</span>
        
        <!-- Desabilitar delete para "Não definido" -->
        <button
            @click="deleteItem(item.id)"
            :disabled="item.name === 'Não definido'"
            type="button"
            class="text-red-600 hover:text-red-800 disabled:opacity-50 disabled:cursor-not-allowed"
            :title="item.name === 'Não definido' ? 'Item padrão não pode ser removido' : 'Remover'"
        >
            Deletar
        </button>
    </div>
</template>

// Script
const deleteItem = async (itemId: number) => {
    try {
        await axios.delete(`/api/entry-types/${itemId}`);
        // Atualizar lista
    } catch (error: any) {
        if (error.response?.status === 403) {
            toast.error(error.response.data.message);
        }
    }
};


// ============================================
// 4. SearchableSelect - Já Desabilita "Não definido"
// ============================================

// O componente SearchableSelect não permite criar item vazio
// e a validação no backend nega exclusão
// Mas você pode adicionar uma verificação extra:

const canDeleteItem = (item: SelectOption): boolean => {
    return item.name !== 'Não definido';
};


// ============================================
// 5. User Onboarding - Criar Padrões Automaticamente
// ============================================

// resources/js/composables/useOnboarding.ts
export const useOnboarding = () => {
    const setupDefaultItems = async (companyId: number) => {
        try {
            // Verificar se já existem
            const response = await axios.get(`/api/companies/${companyId}/default-items-status`);
            
            if (!response.data.has_defaults) {
                // Criar padrões
                await axios.post(`/api/companies/${companyId}/setup-defaults`);
                console.log('Itens padrão criados');
            }
        } catch (error) {
            console.error('Erro ao setup de padrões:', error);
        }
    };

    return { setupDefaultItems };
};


// ============================================
// 6. Validação no Modal - Bloquear Renomear
// ============================================

// Em EntryTypeFormModal.vue ou similar:

const handleSave = () => {
    errors.value = {};

    // ← NOVO: Proteção contra renomear "Não definido"
    if (
        props.mode === 'edit' &&
        props.entryType?.name === 'Não definido' &&
        formData.value.name !== 'Não definido'
    ) {
        errors.value.name = [
            'Não é permitido renomear o tipo "Não definido"'
        ];
        return;
    }

    if (!formData.value.name?.trim()) {
        errors.value.name = ['O nome é obrigatório'];
        return;
    }

    emit('save', {
        name: formData.value.name.trim(),
    });
};


// ============================================
// 7. Estado Inicial - Sempre com "Não definido"
// ============================================

// Quando carregar a lista de tipos, garantir que sempre tem "Não definido"

const loadEntryTypes = async () => {
    try {
        const response = await axios.get('/api/entry-types');
        let types = response.data.data;

        // Garantir que "Não definido" existe
        const hasUndefined = types.some((t: any) => t.name === 'Não definido');
        if (!hasUndefined) {
            // Criar se não existir
            const undefinedRes = await axios.post('/api/entry-types', {
                name: 'Não definido'
            });
            types.unshift(undefinedRes.data.data);
        }

        entryTypes.value = types;
    } catch (error) {
        console.error('Erro ao carregar tipos:', error);
    }
};

// ============================================
// 8. Testes - Verificar Proteção
// ============================================

test('não permite deletar tipo "Não definido"', async () => {
    const undefinedType = await EntryType.findByName('Não definido');
    
    const response = await axios.delete(`/api/entry-types/${undefinedType.id}`, {
        headers: { Authorization: `Bearer ${token}` }
    });

    expect(response.status).toBe(403);
    expect(response.data.message).toContain('Não definido');
});

test('não permite renomear tipo "Não definido"', async () => {
    const undefinedType = await EntryType.findByName('Não definido');
    
    const response = await axios.patch(
        `/api/entry-types/${undefinedType.id}`,
        { name: 'Novo Nome' },
        { headers: { Authorization: `Bearer ${token}` } }
    );

    expect(response.status).toBe(403);
});

test('permite deletar outros tipos de entrada', async () => {
    const response = await axios.delete(`/api/entry-types/${otherType.id}`, {
        headers: { Authorization: `Bearer ${token}` }
    });

    expect(response.status).toBe(200);
});
