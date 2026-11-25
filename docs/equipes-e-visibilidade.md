# Equipes e visibilidade das propostas

Como funcionam as equipes, desde a versão 1.3.0. Os casos de teste no fim foram verificados num GLPI 11 de homologação, menos o envio real de notificações.

## Objetivo

Sem equipes, quem tem o direito de leitura de *Propostas* veria todas as propostas das entidades a que tem acesso. As equipes limitam a visão por pessoa:

- o **consultor** vê só as propostas em que ele é o consultor;
- o **gestor de equipe** vê também as dos consultores da(s) equipe(s) dele, e pode agir nelas;
- um nível **"todas"** vê todas as propostas das entidades a que tem acesso.

Os chamados das propostas seguem a mesma lógica pelos direitos nativos do GLPI: o gestor entra como observador no chamado.

## Conceitos

| Termo | Definição |
|---|---|
| Grupo pai das equipes | Grupo escolhido em **Configuração > Plug-ins > Propostas**. Sem ele configurado, as regras de equipe ficam desligadas |
| Equipe | Grupo **filho direto** do grupo pai |
| Gestor | Membro de uma equipe marcado como **Responsável** (`glpi_groups_users.is_manager = 1`). Pode gerir mais de uma equipe |
| Consultor | Membro de uma equipe que não é Responsável. Pertence a **uma** equipe só |
| Equipe atual | A visão é sempre calculada pelos grupos **no momento** da consulta, não pela equipe da época da proposta |

## Direitos

O direito `plugin_propostas_proposta` ganha níveis de leitura, no mesmo modelo dos chamados do GLPI:

| Nível (na tela de perfis) | Bit | Vê |
|---|---|---|
| Ler as próprias | `READ` (1, `Visibilidade::MINHAS`) | Propostas em que `users_id_consultor` é o usuário |
| Ler da equipe | 2048 (`Visibilidade::EQUIPE`) | As próprias + as dos consultores das equipes em que o usuário é Responsável |
| Ler todas | 1024 (`Visibilidade::TODAS`) | Todas as propostas das entidades do perfil (o comportamento anterior) |

Qualquer um dos três dá acesso ao menu e inclui as propostas próprias. `CREATE`, `UPDATE`, `DELETE` e `PURGE` continuam como antes, mas valem só sobre as propostas que o usuário vê. As regras puras ficam em `src/Visibilidade.php` (com testes em `tests/VisibilidadeTest.php`); as consultas às equipes, em `src/Equipe.php`.

### Atualização de versões anteriores

Na migração (uma vez só), todo perfil que tem `READ` no direito Propostas ganha também o nível *Ler todas*. Assim, nada muda para ninguém até alguém ajustar os perfis.

## Onde o filtro vale

O filtro tem que valer em todos os caminhos, não só na lista:

1. Lista e busca: função `plugin_propostas_addDefaultWhere()` em `hook.php`.
2. Formulário e ações: `Proposta::canViewItem()`, `canUpdateItem()`, `canDeleteItem()` e `canPurgeItem()`.
3. Abas e objetos filhos (itens, blocos de texto, respostas, acessos): conferem a visão da proposta pai.
4. **Duplicar:** só uma proposta que o usuário vê.
5. **Vincular chamado existente:** só um chamado que o usuário vê (isso já é validado hoje).
6. Links e dropdowns que listam propostas.
7. Exportações da busca (CSV/PDF): seguem o filtro da busca.

## Bloqueio de consultor sem equipe

Vale só quando o grupo pai está configurado.

- **Criar ou duplicar:** só se o usuário está em alguma equipe (como consultor ou Responsável) ou tem o nível *Todas*. Caso contrário, o plugin mostra uma mensagem para procurar o administrador.
- **Campo Consultor:** o usuário escolhido tem que estar em uma equipe ou ter o nível *Todas* na entidade da proposta.
- **Trocar o consultor:**
  - nível *Minhas*: não pode;
  - nível *Da equipe*: só para membros das equipes que ele gere (ou para ele mesmo);
  - nível *Todas*: para qualquer usuário válido pela regra acima.
- **Consultor que sai da equipe:** continua vendo e trabalhando nas propostas em que é o consultor, pelo nível *Minhas*. Não cria novas. O gestor anterior deixa de vê-las. O nível *Todas* pode reatribuí-las.

## Avisos de configuração

O plugin mostra um aviso na sua página de configuração quando encontra:

- consultor em mais de uma equipe;
- equipe com mais de um Responsável;
- equipe sem Responsável.

Quando um consultor está em duas equipes, as duas valem para a visão. O aviso existe para corrigir o cadastro. O botão **Sincronizar observadores** na mesma página refaz o acerto de todos os chamados abertos.

## Gestor como observador no chamado

- Quando o plugin abre o chamado da proposta, ou quando um chamado existente é vinculado, o gestor da equipe do consultor entra como **usuário observador**. Se o consultor for o próprio gestor, nada é adicionado.
- O plugin guarda quais observadores ele mesmo adicionou, numa tabela nova `glpi_plugin_propostas_observadores` (`tickets_id` + `users_id`). Assim, a sincronização nunca remove um observador incluído à mão.

### Sincronização

Ela roda quando:

- um membro entra numa equipe, sai dela ou muda de equipe (`Group_User` adicionado ou removido);
- o Responsável muda (`Group_User.is_manager` alterado);
- um grupo passa a ser, ou deixa de ser, filho do grupo pai;
- o consultor de uma proposta muda.

O que ela faz: nos chamados **ainda abertos** (status diferente de solucionado e fechado) das propostas afetadas, remove o gestor antigo (só se foi o plugin que o adicionou) e adiciona o novo. Os chamados solucionados e fechados não mudam, para preservar o histórico.

## Notificações

- Novo destinatário **"Gestor da equipe do consultor"** (`NotificationTargetProposta::GESTOR = 7802`), calculado na hora do envio pela equipe atual.
- Novo evento **"Proposta expirada"**, disparado pela ação automática `expirar`.
- Para avisar um nível acima do gestor não é preciso código: o administrador marca como destinatário o **perfil** correspondente, um tipo de destinatário nativo do GLPI.

## Entidade padrão das propostas

Nova configuração opcional. Na criação, se o usuário tem acesso a essa entidade, a proposta nasce nela em vez de na entidade ativa. Isso evita que quem tem várias entidades crie a proposta no lugar errado.

## Casos de teste

| # | Situação | Esperado |
|---|---|---|
| 1 | Consultor A abre a lista | Só as propostas de A |
| 2 | Consultor A abre pela URL o formulário de uma proposta de B | Acesso negado |
| 3 | Gestor G (equipes 1 e 2) abre a lista | As propostas dele + as dos membros das equipes 1 e 2 |
| 4 | Gestor G edita, reabre e cancela uma proposta de um membro da equipe | Permitido |
| 5 | Gestor G tenta ver uma proposta da equipe 3 | Negado |
| 6 | Usuário com nível *Todas* | Tudo da entidade |
| 7 | Usuário sem equipe e sem *Todas* tenta criar ou duplicar | Bloqueado, com mensagem |
| 8 | Consultor A muda da equipe 1 para a 2 | G1 deixa de ver as propostas de A, G2 passa a ver; nos chamados abertos, G1 sai e G2 entra; nos fechados, nada muda |
| 9 | A equipe 1 troca de Responsável | Nos chamados abertos da equipe, o observador muda |
| 10 | Observador adicionado à mão no chamado | Nunca é removido pela sincronização |
| 11 | Gestor troca o consultor por um usuário de fora das equipes dele | Recusado |
| 12 | Grupo pai não configurado | Comportamento igual ao da versão anterior |
| 13 | Atualização de uma versão anterior | Perfis com `READ` ganham "Ler todas" e ninguém perde acesso |
| 14 | Busca e exportação CSV com o nível *Minhas* | Só as linhas próprias |
| 15 | Aceite de uma proposta da equipe | O e-mail vai para o consultor e para o gestor atual |
| 16 | Ação `expirar` | Evento "Proposta expirada" para os destinatários configurados |
