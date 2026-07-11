# Plano de Melhorias e Evolução do Sistema

Este documento organiza os pedidos de ajuste, melhorias e a nova funcionalidade de importação em fases executáveis, para permitir entrega incremental sem perder a consistência da arquitetura atual.

## Objetivos gerais

- Entregar as correções de UX mais urgentes sem quebrar o fluxo atual.
- Implementar a importação de Excel com suporte a múltiplas abas e defaults configuráveis.
- Garantir que os itens padrão "Não definido" existam e sejam protegidos.
- Melhorar a experiência em tablet e celular.
- Criar um dashboard inicial com leitura clara do desempenho mensal.

## Princípios de execução

- Priorizar backend quando houver regra de negócio ou integridade de dados.
- Priorizar frontend quando houver problema de usabilidade, responsividade ou clareza visual.
- Manter os textos visíveis ao usuário em português.
- Manter o código, nomes de variáveis, serviços e componentes em inglês.
- Evitar refatorações grandes fora do escopo da fase.
- Validar cada fase antes de avançar para a próxima.

## Fase 1. Base funcional e regras globais
feito

## Fase 2. Importação de Excel
feito

## Fase 3. Registros, histórico e formulários

### Escopo

- Corrigir o comportamento dos formulários de entrada e saída.
- Salvar drafts por modelo enquanto o usuário edita.
- Restaurar o draft ao voltar para o formulário.
- Limpar o draft ao deslogar.
- Exibir alerta antes de trocar de formulário quando houver dados não persistidos.
- Remover botões redundantes que duplicam a função de cancelar.
- Corrigir a exibição de entradas e saídas vazando na listagem.
- Ajustar a mensagem de validação de saída sem valor para "Informe um valor por favor".
- Corrigir sobreposição dos campos de data e valor em tablet.
- Corrigir o componente de data para manter o mesmo estilo dos demais inputs.
- Centralizar o valor total do formulário com destaque em fundo primary.
- Disponibilizar edição de entrada e saída em tablet.
- Corrigir a visualização do histórico:
  - remover o ícone lateral desnecessário
  - remover ID do título e subtítulo
  - esconder o campo interno Required Collaborator
  - traduzir labels para português
  - reorganizar totais e subtítulos
  - permitir limpar filtros rapidamente

### Entregáveis

- Draft store/cache por formulário.
- Ajustes de componentes de formulário e histórico.
- Regras de confirmação ao trocar de tipo de lançamento.
- Ajustes responsivos para tablet e telas menores.

### Critério de pronto

- O usuário consegue editar, alternar e cancelar formulários sem perder dados por acidente.

## Fase 4. Filtros, exportação e conferência financeira

### Escopo

- Implementar filtros consistentes em conferência financeira.
- Ao clicar em um tipo, aplicar o filtro e exibir indicação visual do filtro ativo.
- Permitir remover o filtro rapidamente.
- Repetir o comportamento para entradas, saídas e comissões.
- Em Registros, ocultar a opção "Todos" quando a visualização for de gráficos.
- Em filtros por mês ou ano, exibir botão para exportar os dados filtrados para Excel.
- Melhorar o comportamento da seção de totais clicáveis no histórico para filtrar os itens relacionados.

### Entregáveis

- Filtros com estado visual claro.
- Exportação por período.
- Ações de limpar filtro e reaplicar estado original.

### Critério de pronto

- O usuário entende claramente quando há filtro ativo e consegue removê-lo em um clique.

## Fase 5. Navegação, layout e acessibilidade visual

### Escopo

- Fixar o navbar em tablet e desktop.
- Centralizar os itens do menu quando o dispositivo for tablet.
- Adicionar botão de logout no lado oposto ao título.
- Mover "Ajuda e suporte" e "Política de Privacidade" para o footer.
- Corrigir o estado visual do menu para indicar item clicado e evitar múltiplos cliques repetidos.
- Ajustar a opção de esconder valores sensíveis para aparecer em Registros também no tablet.
- Alterar o rótulo do botão para "Opções".
- Corrigir o componente de data para manter proporção visual com os outros inputs.

### Entregáveis

- Layout responsivo revisado.
- Footer com links institucionais.
- Navbar fixa em telas médias e grandes.

### Critério de pronto

- A navegação fica estável e previsível em tablet e desktop.

## Fase 6. Minha conta e consistência de ações

### Escopo

- Impedir sucesso falso quando não houver alteração em "Minha conta".
- Desabilitar o botão de salvar até existir mudança real.
- Remover o botão de recarregar dados.
- Exibir feedback somente quando houver alteração persistida.

### Entregáveis

- Controle de estado de formulário com dirty tracking.
- Ajuste do feedback de sucesso.

### Critério de pronto

- O usuário só vê confirmação de sucesso quando realmente alterou algo.

## Fase 7. Dashboard inicial

### Escopo

- Transformar o dashboard na página inicial após login.
- Exibir boas-vindas com o nome da empresa.
- Alertar quando o usuário ainda não tiver registrado entrada ou saída no dia atual.
- Exibir calendário do mês atual com marcação dos dias com registro.
- Mostrar cores por dia conforme o resultado:
  - verde quando houver lucro
  - vermelho quando houver prejuízo
  - branco quando não houver movimento
- Permitir navegação por mês e ano.
- Exibir legendas para facilitar a leitura dos valores.
- Garantir responsividade para celular, tablet e desktop.

### Entregáveis

- Dashboard com calendário e resumos visuais.
- Filtro de mês/ano no topo.
- Legendas e regras de cor.

### Critério de pronto

- O usuário identifica rapidamente lucro, prejuízo e ausência de movimentação no mês.

## Fase 8. Relatórios, gráficos e ajuda de importação

### Escopo

- Mostrar abaixo dos períodos quais datas estão sendo consideradas nos gráficos.
- Criar modal com instruções de importação do template:
  - formato de datas
  - formato de preços
  - formato de arquivo
- Disponibilizar download de template nas telas de entrada e saída.
- Revisar textos auxiliares para manter tudo em português.

### Entregáveis

- Modal de ajuda contextual.
- Template de importação para o usuário.
- Indicação textual de intervalo de datas nos gráficos.

### Critério de pronto

- O usuário consegue preparar e importar dados sem depender de suporte externo.

## Fase 9. Validação final e testes

### Escopo

- Cobrir os fluxos críticos com testes de backend e frontend.
- Validar:
  - criação dos defaults
  - bloqueio de exclusão dos itens padrão
  - importação Excel com múltiplas abas
  - processamento parcial de linhas
  - drafts de formulário
  - filtros aplicados e removidos
  - dashboard por período
- Revisar mensagens de erro e confirmação em português.

### Entregáveis

- Suite de testes ajustada.
- Checklist de validação manual.

### Critério de pronto

- O conjunto de mudanças está estável para uso diário e pronto para evoluir.

## Ordem sugerida de implementação

1. Fase 1.
2. Fase 2.
3. Fase 3.
4. Fase 4.
5. Fase 5.
6. Fase 6.
7. Fase 7.
8. Fase 8.
9. Fase 9.

## Observações

- Este plano complementa o `DEVELOPMENT_CHECKLIST.md` e foca no backlog de melhorias descrito pelo usuário.
- Se necessário, o documento pode ser quebrado depois em epics menores por área: Registros, Dashboard, Importação e Navegação.