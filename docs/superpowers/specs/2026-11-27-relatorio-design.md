# Relatório de eficiência dos consultores — desenho

## Por quê

O gestor quer saber se os consultores da equipe estão sendo eficientes, e eficiência aqui é proposta **aceita** pela empresa cliente. O relatório mostra volume e conversão lado a lado, por consultor e por equipe, com o valor fechado.

## Objetivos

- Uma página **Ferramentas > Relatório de propostas**.
- Duas tabelas: **por consultor** e **por equipe** (soma dos consultores da equipe).
- Em cada linha, dois blocos:
  - **Conversão** (pela data de envio, `data_emissao`, no período): enviadas, aceitas, em aberto, taxa (aceitas ÷ enviadas).
  - **Fechamento** (pela data do aceite, `respostas.date_creation` com `tipo = 'aceite'`, no período): aceitas, R$ mensal (recorrente) e R$ pontual.
- Filtros: período (padrão: mês corrente) e equipe.
- Exportar em CSV.

```
Novembro   │ Conversão (enviadas no mês)        │ Fechamento (aceitas no mês)
           │ Enviadas Aceitas Em aberto  Taxa   │ Aceitas  R$ mensal  R$ pontual
Ana        │    10       3        4      30%    │    5     4.500,00   2.000,00
Equipe Sul │    25       9        7      36%    │   11    12.300,00   5.100,00
```

## Não objetivos

- Gráficos e painel (dashboard) nativo do GLPI: ficam para depois, se fizerem falta.
- Grafana.
- Relatório por empresa cliente, por modelo ou por item.

## Anti-objetivos

- Um gestor ver números de consultores fora das equipes que gere.
- Valor do relatório diferente do valor da proposta.
- Reabrir proposta (nova versão) inflar ou punir o consultor.

## Restrições

- GLPI 11, PHP 8.2; mesma estrutura do plugin (`front/`, `src/`, `templates/`), testes em PHPUnit para a lógica pura.
- Visibilidade pela regra existente: `Visibilidade::consultoresVisiveis` (próprias, da equipe, todas). Quem só lê as próprias vê só a própria linha.
- Valores pela função existente `Calculo::totais` sobre os itens; nada de refazer a conta em SQL.
- Repositório público: nenhum nome, domínio ou dado real nos exemplos e testes.

## Abordagens

1. **Página nova no plugin** — escolhida. Respeita a visibilidade e reaproveita a conta de valores. Custo: tabela, sem gráficos.
2. Cartões no painel nativo do GLPI — os cartões não filtram por "equipes que eu gerencio" e a taxa por consultor fica forçada.
3. Grafana — lê o banco direto, ignora a visibilidade e teria de refazer a conta de valores em SQL.

## Decisões

- Cada proposta conta uma vez (um registro por proposta; a versão é um campo), pelo estado atual.
- Consultor em mais de uma equipe entra em todas (a configuração já avisa desse caso).
- Proposta com exibição **por unidade** (`exibicao = 'unidade'`) conta como aceita, mas não entra na soma de R$; ao lado do valor aparece "+N por unidade, sem total". *(decisão do Claude)*
- Direito de acesso: o mesmo direito de leitura de *Propostas*; sem direito novo. *(decisão do Claude)*

## Para quem construir decidir

- **Em aberto:** enviada, visualizada, com dúvida, e reaberta que voltou a rascunho depois de enviada no período. *(decisão do Claude)*
- **Enviada:** todo rascunho já nasce com `data_emissao`, então "enviada" é status diferente de rascunho, ou rascunho com `versao > 1` (reaberta). Proposta aceita não pode ser cancelada; rascunho pode. Cancelada só conta como enviada se tem chamado (`tickets_id > 0`, aberto ao gerar o link) ou `versao > 1`. *(decisão do Claude; um rascunho com chamado vinculado à mão e cancelado sem envio ainda conta, caso raro)*
- Expiradas e canceladas enviadas contam como enviadas e não aceitas (baixam a taxa).
- Taxa com zero enviadas: mostrar "—", não 0%.
- Com equipes desligadas na configuração, a tabela por equipe não aparece.
- Formato do CSV: uma linha por consultor e uma por equipe, com as mesmas colunas da tela.
