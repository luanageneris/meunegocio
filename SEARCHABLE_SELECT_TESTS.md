/**
 * TESTES - SearchableSelect Component
 * 
 * Exemplos de testes com Vitest + Vue Test Utils
 */

import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import SearchableSelect from '@/components/SearchableSelect.vue';

describe('SearchableSelect.vue', () => {
    // ============================================
    // TESTES DE RENDERIZAÇÃO
    // ============================================

    it('renderiza com label', () => {
        const wrapper = mount(SearchableSelect, {
            props: {
                label: 'Tipo de Entrada',
                modelValue: null,
                options: [],
                placeholder: 'Selecione...',
            },
        });

        expect(wrapper.find('label').text()).toContain('Tipo de Entrada');
    });

    it('marca como obrigatório com asterisco', () => {
        const wrapper = mount(SearchableSelect, {
            props: {
                label: 'Campo',
                modelValue: null,
                options: [],
                required: true,
            },
        });

        expect(wrapper.find('label span.text-red-500').exists()).toBe(true);
    });

    it('exibe placeholder correto', () => {
        const wrapper = mount(SearchableSelect, {
            props: {
                label: 'Teste',
                modelValue: null,
                options: [],
                placeholder: 'Digite algo...',
            },
        });

        const button = wrapper.find('button');
        expect(button.text()).toContain('Digite algo...');
    });

    it('exibe valor selecionado', () => {
        const wrapper = mount(SearchableSelect, {
            props: {
                label: 'Teste',
                modelValue: { id: 1, name: 'Opção 1' },
                options: [{ id: 1, name: 'Opção 1' }],
            },
        });

        const button = wrapper.find('button');
        expect(button.text()).toContain('Opção 1');
    });

    // ============================================
    // TESTES DE DROPDOWN
    // ============================================

    it('abre dropdown ao clicar no botão', async () => {
        const wrapper = mount(SearchableSelect, {
            props: {
                label: 'Teste',
                modelValue: null,
                options: [{ id: 1, name: 'Opção 1' }],
            },
        });

        const button = wrapper.find('button');
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);

        await button.trigger('click');
        expect(wrapper.vm.isOpen).toBe(true);
    });

    it('fecha dropdown ao clicar em uma opção', async () => {
        const wrapper = mount(SearchableSelect, {
            props: {
                label: 'Teste',
                modelValue: null,
                options: [{ id: 1, name: 'Opção 1' }],
            },
        });

        await wrapper.find('button').trigger('click');
        expect(wrapper.vm.isOpen).toBe(true);

        // Simular clique em opção
        await wrapper.vm.selectOption({ id: 1, name: 'Opção 1' });
        expect(wrapper.vm.isOpen).toBe(false);
    });

    // ============================================
    // TESTES DE FILTRO/BUSCA
    // ============================================

    it('filtra opções por nome', async () => {
        const wrapper = mount(SearchableSelect, {
            props: {
                label: 'Teste',
                modelValue: null,
                options: [
                    { id: 1, name: 'Venda' },
                    { id: 2, name: 'Devolução' },
                    { id: 3, name: 'Investimento' },
                ],
            },
        });

        wrapper.vm.searchInput = 'vend';
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.filteredOptions).toHaveLength(1);
        expect(wrapper.vm.filteredOptions[0].name).toBe('Venda');
    });

    it('filtra case-insensitive', async () => {
        const wrapper = mount(SearchableSelect, {
            props: {
                label: 'Teste',
                modelValue: null,
                options: [
                    { id: 1, name: 'Venda' },
                    { id: 2, name: 'VENDA' },
                ],
            },
        });

        wrapper.vm.searchInput = 'vend';
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.filteredOptions).toHaveLength(2);
    });

    it('filtra por CPF para colaboradores', async () => {
        const wrapper = mount(SearchableSelect, {
            props: {
                label: 'Teste',
                modelValue: null,
                entityType: 'collaborator',
                options: [
                    { id: 1, name: 'João', cpf: '123.456.789-00' },
                    { id: 2, name: 'Maria', cpf: '987.654.321-11' },
                ],
            },
        });

        wrapper.vm.searchInput = '123.456';
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.filteredOptions).toHaveLength(1);
        expect(wrapper.vm.filteredOptions[0].name).toBe('João');
    });

    it('retorna todas as opções com search vazio', async () => {
        const options = [
            { id: 1, name: 'Opção 1' },
            { id: 2, name: 'Opção 2' },
        ];

        const wrapper = mount(SearchableSelect, {
            props: {
                label: 'Teste',
                modelValue: null,
                options,
            },
        });

        wrapper.vm.searchInput = '';
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.filteredOptions).toEqual(options);
    });

    // ============================================
    // TESTES DE CRIAÇÃO DE NOVO ITEM
    // ============================================

    it('mostra botão de criar quando nenhum resultado', async () => {
        const wrapper = mount(SearchableSelect, {
            props: {
                label: 'Teste',
                modelValue: null,
                options: [{ id: 1, name: 'Venda' }],
            },
        });

        wrapper.vm.searchInput = 'xyz não existe';
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.noExactMatch).toBe(true);
        expect(wrapper.find('button:contains("Criar")').exists()).toBe(true);
    });

    it('abre modal ao clicar em "Criar novo item"', async () => {
        const wrapper = mount(SearchableSelect, {
            props: {
                label: 'Teste',
                modelValue: null,
                options: [{ id: 1, name: 'Venda' }],
            },
        });

        wrapper.vm.searchInput = 'Novo tipo';
        await wrapper.vm.$nextTick();

        wrapper.vm.openCreateModal();
        expect(wrapper.vm.showCreateModal).toBe(true);
        expect(wrapper.vm.createFormData.name).toBe('Novo tipo');
    });

    it('valida nome obrigatório ao criar', async () => {
        const wrapper = mount(SearchableSelect, {
            props: {
                label: 'Teste',
                modelValue: null,
                options: [],
            },
        });

        wrapper.vm.showCreateModal = true;
        wrapper.vm.createFormData.name = '';
        await wrapper.vm.$nextTick();

        const button = wrapper.find('button:contains("Criar")');
        expect(button.element.disabled).toBe(true);
    });

    it('valida CPF para colaboradores', async () => {
        const wrapper = mount(SearchableSelect, {
            props: {
                label: 'Teste',
                modelValue: null,
                options: [],
                entityType: 'collaborator',
            },
        });

        wrapper.vm.showCreateModal = true;
        wrapper.vm.createFormData.name = 'João';
        wrapper.vm.createFormData.cpf = '123'; // Inválido
        await wrapper.vm.$nextTick();

        const button = wrapper.find('button:contains("Criar")');
        expect(button.element.disabled).toBe(true);
    });

    it('emite evento create ao confirmar', async () => {
        const wrapper = mount(SearchableSelect, {
            props: {
                label: 'Teste',
                modelValue: null,
                options: [],
            },
        });

        wrapper.vm.showCreateModal = true;
        wrapper.vm.createFormData.name = 'Novo tipo';
        await wrapper.vm.$nextTick();

        await wrapper.vm.handleCreateConfirm();

        expect(wrapper.emitted('create')).toBeTruthy();
        expect(wrapper.emitted('create')[0][0]).toEqual({ name: 'Novo tipo' });
    });

    // ============================================
    // TESTES DE MÁSCARA CPF
    // ============================================

    it('mascara CPF automaticamente', async () => {
        const wrapper = mount(SearchableSelect, {
            props: {
                label: 'Teste',
                modelValue: null,
                options: [],
                entityType: 'collaborator',
            },
        });

        const result = wrapper.vm.maskCPF('12345678901');
        expect(result).toBe('123.456.789-01');
    });

    it('mascara CPF parcial', async () => {
        const wrapper = mount(SearchableSelect, {
            props: {
                label: 'Teste',
                modelValue: null,
                options: [],
                entityType: 'collaborator',
            },
        });

        expect(wrapper.vm.maskCPF('123')).toBe('123');
        expect(wrapper.vm.maskCPF('123456')).toBe('123.456');
        expect(wrapper.vm.maskCPF('123456789')).toBe('123.456.789');
    });

    // ============================================
    // TESTES DE SELEÇÃO
    // ============================================

    it('emite update:modelValue ao selecionar', async () => {
        const wrapper = mount(SearchableSelect, {
            props: {
                label: 'Teste',
                modelValue: null,
                options: [{ id: 1, name: 'Opção 1' }],
            },
        });

        wrapper.vm.selectOption({ id: 1, name: 'Opção 1' });
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('update:modelValue')).toBeTruthy();
        expect(wrapper.emitted('update:modelValue')[0][0]).toEqual({
            id: 1,
            name: 'Opção 1',
        });
    });

    it('limpa search após seleção', async () => {
        const wrapper = mount(SearchableSelect, {
            props: {
                label: 'Teste',
                modelValue: null,
                options: [{ id: 1, name: 'Opção 1' }],
            },
        });

        wrapper.vm.searchInput = 'teste';
        wrapper.vm.selectOption({ id: 1, name: 'Opção 1' });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.searchInput).toBe('');
    });

    // ============================================
    // TESTES DE TECLAS
    // ============================================

    it('abre modal ao pressionar Enter sem match', async () => {
        const wrapper = mount(SearchableSelect, {
            props: {
                label: 'Teste',
                modelValue: null,
                options: [{ id: 1, name: 'Venda' }],
            },
        });

        wrapper.vm.searchInput = 'Novo item';
        const event = new KeyboardEvent('keydown', { key: 'Enter' });
        const preventDefaultSpy = vi.spyOn(event, 'preventDefault');

        wrapper.vm.handleKeyDown(event);

        expect(preventDefaultSpy).toHaveBeenCalled();
        expect(wrapper.vm.showCreateModal).toBe(true);
    });

    it('fecha dropdown ao pressionar Escape', async () => {
        const wrapper = mount(SearchableSelect, {
            props: {
                label: 'Teste',
                modelValue: null,
                options: [{ id: 1, name: 'Opção 1' }],
            },
        });

        wrapper.vm.isOpen = true;
        const event = new KeyboardEvent('keydown', { key: 'Escape' });

        wrapper.vm.handleKeyDown(event);

        expect(wrapper.vm.isOpen).toBe(false);
    });

    // ============================================
    // TESTES DE DESABILITAÇÃO
    // ============================================

    it('desabilita campo quando disabled=true', async () => {
        const wrapper = mount(SearchableSelect, {
            props: {
                label: 'Teste',
                modelValue: null,
                options: [],
                disabled: true,
            },
        });

        const button = wrapper.find('button');
        expect(button.element.disabled).toBe(true);
    });

    // ============================================
    // TESTES DE ERRO
    // ============================================

    it('exibe mensagem de erro', async () => {
        const wrapper = mount(SearchableSelect, {
            props: {
                label: 'Teste',
                modelValue: null,
                options: [],
                error: 'Campo obrigatório',
            },
        });

        expect(wrapper.find('p.text-red-600').text()).toBe('Campo obrigatório');
    });

    it('aplica classe de erro ao input', async () => {
        const wrapper = mount(SearchableSelect, {
            props: {
                label: 'Teste',
                modelValue: null,
                options: [],
                error: 'Erro',
            },
        });

        expect(wrapper.find('button').classes()).toContain('ring-red-400');
    });
});

// ============================================
// TESTES DE INTEGRAÇÃO
// ============================================

describe('SearchableSelect Integration', () => {
    it('fluxo completo: criar novo item', async () => {
        const wrapper = mount(SearchableSelect, {
            props: {
                label: 'Teste',
                modelValue: null,
                options: [{ id: 1, name: 'Venda' }],
                entityType: 'entry_type',
            },
        });

        // Abrir dropdown
        await wrapper.find('button').trigger('click');
        expect(wrapper.vm.isOpen).toBe(true);

        // Digitar novo item
        wrapper.vm.searchInput = 'Novo tipo';
        await wrapper.vm.$nextTick();

        // Pressionar Enter
        const event = new KeyboardEvent('keydown', { key: 'Enter' });
        wrapper.vm.handleKeyDown(event);

        // Modal deve estar aberto
        expect(wrapper.vm.showCreateModal).toBe(true);

        // Confirmar criação
        await wrapper.vm.handleCreateConfirm();

        // Event emitido
        expect(wrapper.emitted('create')).toBeTruthy();
    });

    it('fluxo completo: selecionar e pesquisar colaborador', async () => {
        const collaborators = [
            { id: 1, name: 'João Silva', cpf: '123.456.789-00' },
            { id: 2, name: 'Maria Santos', cpf: '987.654.321-11' },
        ];

        const wrapper = mount(SearchableSelect, {
            props: {
                label: 'Colaborador',
                modelValue: null,
                options: collaborators,
                entityType: 'collaborator',
            },
        });

        // Abrir dropdown
        await wrapper.find('button').trigger('click');

        // Buscar por CPF
        wrapper.vm.searchInput = '123.456';
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.filteredOptions).toHaveLength(1);
        expect(wrapper.vm.filteredOptions[0].name).toBe('João Silva');

        // Selecionar
        await wrapper.vm.selectOption(wrapper.vm.filteredOptions[0]);

        // Validar evento
        expect(wrapper.emitted('update:modelValue')).toBeTruthy();
    });
});
