# Relatório de eficiência dos consultores — plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Página **Ferramentas > Relatório de propostas** com conversão (pela data de envio) e fechamento (pela data do aceite, com valor) por consultor e por equipe, respeitando a visibilidade.

**Architecture:** A conta fica numa classe pura `Eficiencia` (sem GLPI, testada em PHPUnit), que recebe linhas de proposta já carregadas. A classe `Relatorio` (CommonGLPI) carrega as propostas visíveis do banco, monta essas linhas com `Calculo::totais` e entra no menu. `front/relatorio.php` lê os filtros, chama as duas e mostra um template Twig ou devolve CSV.

**Tech Stack:** PHP 8.2+, GLPI 11 (`CommonGLPI`, `$DB->request`, `TemplateRenderer`), Twig, PHPUnit 11.

**Spec:** `docs/superpowers/specs/2026-11-27-relatorio-design.md`

## Global Constraints

- GLPI 11.x, PHP 8.2 ou superior; nenhuma dependência nova.
- Visibilidade só por `Proposta::criteriosVisibilidade()` + `getEntitiesRestrictCriteria(...)`, como `Proposta::subconsultaVisiveis()` já faz.
- Valores só por `Calculo::totais()`; nada de soma de itens em SQL.
- Proposta com `exibicao = 'unidade'` (`Calculo::EXIBICAO_UNIDADE`) não entra na soma de R$; é contada em `por_unidade`.
- Repositório público: nenhum nome real, domínio, IP ou empresa em código, testes, docs ou commits.
- Textos de tela em português, com `__('...', 'propostas')`. Commits em inglês, Conventional Commits.
- Branch `feat/relatorio`; não fazer push sem o usuário pedir.

## Review Focus

1. **Rascunho cancelado sem envio** — não pode contar como enviada (baixaria a taxa). Teste em `EficienciaTest::testCanceladaSemEnvioNaoContaComoEnviada`.
2. **Período com datas inválidas ou invertidas na URL** (`?inicio=abc`, `inicio > fim`) — volta ao mês corrente, sem erro de SQL. Teste em `EficienciaTest::testPeriodoInvalidoVoltaAoMesCorrente`.
3. **Gestor só do nível "da equipe"** — vê só os consultores das equipes que gere e só essas equipes; a equipe de outro gestor não aparece nem pelo filtro `?equipe=<id>` na URL. Garantido em `Relatorio::equipesVisiveis()` (Task 2) e conferido no passo manual da Task 3.
4. **Aceite no período de proposta enviada antes** — conta em Fechamento, não em Conversão. Teste em `EficienciaTest::testAceiteNoPeriodoDeEnvioAnterior`.
5. **Nome de consultor ou equipe começando com `=`, `+`, `-`, `@` no CSV** — sai prefixado com `'` para a planilha não executar fórmula. Teste em `EficienciaTest::testCelulaCsvNeutralizaFormula`.

---

### Task 1: Conta pura (`Eficiencia`)

**Files:**
- Create: `src/Eficiencia.php`
- Create: `tests/EficienciaTest.php`
- Modify: `phpunit.xml.dist` (incluir `src/Eficiencia.php` em `<source>`)

**Interfaces:**
- Produces:
  - Linha de proposta (entrada), array com chaves: `consultor` int, `status` string, `versao` int, `tickets_id` int, `data_emissao` `'Y-m-d'`, `data_aceite` `?'Y-m-d'` (data do aceite, só quando aceita), `por_unidade` bool, `recorrente` float, `pontual` float.
  - Linha de resultado: `['enviadas'=>int, 'aceitas_envio'=>int, 'em_aberto'=>int, 'taxa'=>?float, 'aceitas'=>int, 'recorrente'=>float, 'pontual'=>float, 'por_unidade'=>int]`.
  - `Eficiencia::periodo(?string $inicio, ?string $fim, \DateTimeImmutable $hoje): array{0:string,1:string}`
  - `Eficiencia::enviada(array $p): bool`
  - `Eficiencia::porConsultor(array $propostas, string $inicio, string $fim): array<int, array>` (chave = id do consultor)
  - `Eficiencia::porEquipe(array $por_consultor, array $membros_por_equipe): array<int, array>` (`$membros_por_equipe`: `array<int, list<int>>`, chave = id do grupo)
  - `Eficiencia::taxa(int $aceitas, int $enviadas): ?float`
  - `Eficiencia::celulaCsv(string $valor): string`

Regras (copiar no PHPDoc):
- `enviada`: status ≠ `rascunho` e ≠ `cancelada` → true; `rascunho` → `versao > 1`; `cancelada` → `tickets_id > 0 || versao > 1`. Um comentário `// shortcut: rascunho com chamado vinculado à mão e cancelado sem envio conta como enviado; guardar a data de envio numa coluna se isso incomodar`.
- Conversão: proposta `enviada` com `data_emissao` em `[inicio, fim]` → `enviadas++`; se `status = aceita` → `aceitas_envio++`; se status em `enviada|visualizada|duvida|rascunho` → `em_aberto++`.
- Fechamento: `status = aceita` com `data_aceite` em `[inicio, fim]` → `aceitas++`; se `por_unidade` → `por_unidade++`, senão soma `recorrente` e `pontual`.
- `taxa`: `null` quando `enviadas = 0`, senão `aceitas / enviadas` (fração 0–1, arredondar só na tela).
- `porEquipe`: soma as linhas dos membros (consultor em duas equipes entra nas duas); `taxa` recalculada das somas; equipe sem nenhum membro com linha ainda aparece, zerada.
- `periodo`: aceita só `Y-m-d` válido (`DateTimeImmutable::createFromFormat('!Y-m-d')` e reformatar igual); se algum faltar, for inválido ou `inicio > fim`, devolve primeiro e último dia do mês de `$hoje`.
- `celulaCsv`: prefixa `'` quando começa com `=`, `+`, `-`, `@`, tab ou CR.

- [ ] **Step 1: Escrever os testes que falham** em `tests/EficienciaTest.php` (namespace `GlpiPlugin\Propostas\Tests`, `final class EficienciaTest extends TestCase`, com um helper `p(array $campos): array` que preenche os defaults `versao 1, tickets_id 0, data_aceite null, por_unidade false, recorrente 0.0, pontual 0.0`). Período usado: `'2026-11-01'`–`'2026-11-30'`.

```php
public function testConversaoEFechamentoDoConsultor(): void
{
    $r = E::porConsultor([
        $this->p(['consultor' => 10, 'status' => 'aceita', 'versao' => 1, 'tickets_id' => 5, 'data_emissao' => '2026-11-03', 'data_aceite' => '2026-11-05', 'recorrente' => 1000.0, 'pontual' => 500.0]),
        $this->p(['consultor' => 10, 'status' => 'visualizada', 'tickets_id' => 6, 'data_emissao' => '2026-11-10']),
        $this->p(['consultor' => 10, 'status' => 'expirada', 'tickets_id' => 7, 'data_emissao' => '2026-11-02']),
    ], '2026-11-01', '2026-11-30');
    $this->assertSame(['enviadas' => 3, 'aceitas_envio' => 1, 'em_aberto' => 1, 'taxa' => 1 / 3, 'aceitas' => 1, 'recorrente' => 1000.0, 'pontual' => 500.0, 'por_unidade' => 0], $r[10]);
}

public function testRascunhoNuncaEnviadoNaoConta(): void
{
    $this->assertFalse(E::enviada($this->p(['status' => 'rascunho', 'versao' => 1, 'data_emissao' => '2026-11-03'])));
    $this->assertTrue(E::enviada($this->p(['status' => 'rascunho', 'versao' => 2, 'data_emissao' => '2026-11-03'])));
}

public function testCanceladaSemEnvioNaoContaComoEnviada(): void
{
    $this->assertFalse(E::enviada($this->p(['status' => 'cancelada', 'versao' => 1, 'tickets_id' => 0, 'data_emissao' => '2026-11-03'])));
    $this->assertTrue(E::enviada($this->p(['status' => 'cancelada', 'versao' => 1, 'tickets_id' => 9, 'data_emissao' => '2026-11-03'])));
}

public function testAceiteNoPeriodoDeEnvioAnterior(): void
{
    $r = E::porConsultor([$this->p(['consultor' => 10, 'status' => 'aceita', 'tickets_id' => 5, 'data_emissao' => '2026-10-28', 'data_aceite' => '2026-11-02', 'recorrente' => 800.0])], '2026-11-01', '2026-11-30');
    $this->assertSame(0, $r[10]['enviadas']);
    $this->assertNull($r[10]['taxa']);
    $this->assertSame(1, $r[10]['aceitas']);
    $this->assertSame(800.0, $r[10]['recorrente']);
}

public function testPorUnidadeNaoSomaValor(): void
{
    $r = E::porConsultor([$this->p(['consultor' => 10, 'status' => 'aceita', 'tickets_id' => 5, 'data_emissao' => '2026-11-03', 'data_aceite' => '2026-11-04', 'por_unidade' => true, 'recorrente' => 12.0])], '2026-11-01', '2026-11-30');
    $this->assertSame(1, $r[10]['aceitas']);
    $this->assertSame(0.0, $r[10]['recorrente']);
    $this->assertSame(1, $r[10]['por_unidade']);
}

public function testEquipeSomaMembrosERecalculaTaxa(): void
{
    $linha = static fn (int $env, int $ac): array => ['enviadas' => $env, 'aceitas_envio' => $ac, 'em_aberto' => 0, 'taxa' => E::taxa($ac, $env), 'aceitas' => $ac, 'recorrente' => 100.0 * $ac, 'pontual' => 0.0, 'por_unidade' => 0];
    $r = E::porEquipe([10 => $linha(40, 12), 11 => $linha(10, 6)], [1 => [10, 11], 2 => [11], 3 => [99]]);
    $this->assertSame(50, $r[1]['enviadas']);
    $this->assertSame(18 / 50, $r[1]['taxa']);
    $this->assertSame(1800.0, $r[1]['recorrente']);
    $this->assertSame(10, $r[2]['enviadas']);  // consultor em duas equipes entra nas duas
    $this->assertSame(0, $r[3]['enviadas']);   // equipe sem linhas aparece zerada
    $this->assertNull($r[3]['taxa']);
}

public function testPeriodoInvalidoVoltaAoMesCorrente(): void
{
    $hoje = new \DateTimeImmutable('2026-11-27');
    $this->assertSame(['2026-11-01', '2026-11-30'], E::periodo(null, null, $hoje));
    $this->assertSame(['2026-11-01', '2026-11-30'], E::periodo('abc', '2026-11-30', $hoje));
    $this->assertSame(['2026-11-01', '2026-11-30'], E::periodo('2026-12-01', '2026-11-01', $hoje));
    $this->assertSame(['2026-11-01', '2026-11-30'], E::periodo('2026-02-30', '2026-11-30', $hoje)); // data inexistente
    $this->assertSame(['2026-10-01', '2026-10-31'], E::periodo('2026-10-01', '2026-10-31', $hoje));
}

public function testCelulaCsvNeutralizaFormula(): void
{
    $this->assertSame("'=SOMA(A1)", E::celulaCsv('=SOMA(A1)'));
    $this->assertSame("'-1", E::celulaCsv('-1'));
    $this->assertSame('Equipe A', E::celulaCsv('Equipe A'));
}
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `vendor/bin/phpunit tests/EficienciaTest.php`
Expected: FAIL — `Class "GlpiPlugin\Propostas\Eficiencia" not found`.

- [ ] **Step 3: Implementar `final class Eficiencia` em `src/Eficiencia.php`** com as assinaturas e regras acima (cabeçalho de arquivo e licença como em `src/Visibilidade.php`; `Proposta::STATUS_*` não pode ser usada nos testes porque `Proposta` depende do GLPI; repetir os literais de status como `private const` em `Eficiencia`, com um comentário apontando para `Proposta`). Incluir `src/Eficiencia.php` em `phpunit.xml.dist`.

- [ ] **Step 4: Rodar a suíte inteira**

Run: `vendor/bin/phpunit`
Expected: OK, todos os testes passam (os antigos e os 8 novos).

- [ ] **Step 5: Commit**

```bash
git switch -c feat/relatorio
git add src/Eficiencia.php tests/EficienciaTest.php phpunit.xml.dist docs/superpowers
git commit -m "feat: efficiency calculation for the proposals report"
```

---

### Task 2: Carregar os dados (`Relatorio` e `Equipe::membrosPorEquipe`)

**Files:**
- Create: `src/Relatorio.php`
- Modify: `src/Equipe.php` (novo método público ao lado de `membrosDasEquipes()`)
- Modify: `setup.php:74` (incluir `Relatorio::class` em `MENU_TOADD` `tools`, depois de `Proposta::class`)

**Interfaces:**
- Consumes: `Eficiencia` (Task 1), `Proposta::criteriosVisibilidade()`, `Calculo::totais()`, `Calculo::EXIBICAO_UNIDADE`, `Resposta::TIPO_ACEITE`, `Equipe::ativas()`, `Equipe::equipes()`, `Equipe::equipesGeridas()`, `Equipe::usuarioAtual()`, `Equipe::direitosAtuais()`, `Visibilidade::TODAS`.
- Produces:
  - `Equipe::membrosPorEquipe(): array<int, list<int>>` — grupo → usuários, via o `vinculos()` privado.
  - `final class Relatorio extends \CommonGLPI` com `public static $rightname = 'plugin_propostas_proposta'`, `getTypeName()` = `__('Relatório de propostas', 'propostas')`, `getIcon()` = `'ti ti-chart-bar'`, `canView()` igual a `Proposta::canView()`.
  - `Relatorio::propostas(string $inicio, string $fim): list<array>` — linhas de entrada de `Eficiencia`.
  - `Relatorio::equipesVisiveis(): list<int>` — `[]` com equipes desligadas; `Equipe::equipes()` com `TODAS`; senão `Equipe::equipesGeridas(usuarioAtual)` só se o perfil tem `Visibilidade::EQUIPE`.

- [ ] **Step 1: Implementar `Equipe::membrosPorEquipe()`** agrupando `self::vinculos()` por `groups_id`.

- [ ] **Step 2: Implementar `Relatorio::propostas()`**
  1. Uma consulta em `glpi_plugin_propostas_propostas` com `WHERE` = `Proposta::criteriosVisibilidade() + getEntitiesRestrictCriteria(<tabela>, '', '', true)` e `OR` entre `data_emissao BETWEEN inicio AND fim` e `id IN (subconsulta de respostas com tipo = 'aceite' e DATE(date_creation) BETWEEN inicio AND fim)`. Campos: `id, users_id_consultor, status, versao, tickets_id, data_emissao, exibicao`.
  2. Uma consulta em `glpi_plugin_propostas_respostas` (`tipo = 'aceite'`, `plugin_propostas_propostas_id IN` os ids aceitos) com `MAX(date_creation)` por proposta → `data_aceite` (só a parte `Y-m-d`).
  3. Uma consulta em `glpi_plugin_propostas_propostaitems` para os ids aceitos que não são por unidade; `Calculo::totais()` por proposta.
  4. Montar as linhas no formato da Task 1. Sem consulta por proposta (nada de N+1).

- [ ] **Step 3: Registrar no menu** (`setup.php:74`).

- [ ] **Step 4: Lint**

Run: `composer lint`
Expected: `No syntax errors detected` em todos os arquivos.

- [ ] **Step 5: Commit**

```bash
git add src/Relatorio.php src/Equipe.php setup.php
git commit -m "feat: load visible proposals for the report"
```

---

### Task 3: Página, CSV e documentação

**Files:**
- Create: `front/relatorio.php`
- Create: `templates/relatorio.html.twig`
- Modify: `README.md` (item **Relatório** em Funcionalidades), `CHANGELOG.md` (em `## [Unreleased]`, seção `### Added`)

**Interfaces:**
- Consumes: `Relatorio::propostas()`, `Relatorio::equipesVisiveis()`, `Eficiencia::periodo/porConsultor/porEquipe/celulaCsv`, `Equipe::membrosPorEquipe()`.

- [ ] **Step 1: `front/relatorio.php`**
  - `Session::checkRightsOr(Proposta::$rightname, Visibilidade::LEITURA);`
  - `[$inicio, $fim] = Eficiencia::periodo($_GET['inicio'] ?? null, $_GET['fim'] ?? null, new DateTimeImmutable('today'));`
  - Equipes: `Relatorio::equipesVisiveis()`; `$_GET['equipe']` (int) só vale se estiver nessa lista, senão ignora. Com equipe escolhida, a tabela por consultor mostra só os membros dela.
  - Nomes: `getUserName($id)` para consultores, `Dropdown::getDropdownName('glpi_groups', $id)` para equipes; ordenar por nome.
  - `?csv=1`: `Content-Type: text/csv; charset=UTF-8`, `Content-Disposition: attachment; filename="relatorio-propostas-<inicio>-<fim>.csv"`, BOM UTF-8, `fputcsv` com `;`; colunas: `Tipo;Nome;Enviadas;Aceitas (das enviadas);Em aberto;Taxa;Aceitas no período;R$ mensal;R$ pontual;Por unidade`; nomes passam por `Eficiencia::celulaCsv`; números com vírgula decimal.
  - Senão: `Html::header(Relatorio::getTypeName(), '', 'tools', Relatorio::class)`, `TemplateRenderer::getInstance()->display('@propostas/relatorio.html.twig', [...])`, `Html::footer()`.

- [ ] **Step 2: `templates/relatorio.html.twig`** — formulário GET (início, fim, equipe, botão Filtrar, link Exportar CSV com os mesmos filtros); duas tabelas no layout da spec (cabeçalho com os grupos *Conversão (enviadas no período)* e *Fechamento (aceitas no período)*); taxa `null` mostra `—`; valores em `R$ 1.234,56`; quando `por_unidade > 0`, nota `+N por unidade, sem total` na célula de valor; tabela por equipe só quando há equipes visíveis. Classes de tabela do GLPI (`table table-hover`) como em `templates/acessos.html.twig`.

- [ ] **Step 3: README e CHANGELOG** — README: um item **Relatório de propostas** em Funcionalidades (o que mostra, as duas datas, visibilidade, por unidade fora da soma, CSV). CHANGELOG: entrada em `### Added` sob `[Unreleased]`.

- [ ] **Step 4: Lint e testes**

Run: `composer lint` e `vendor/bin/phpunit`
Expected: sem erros de sintaxe; suíte OK.

- [ ] **Step 5: Conferência manual no GLPI de testes** (sem dados reais no repositório)
  - Perfil *Ler todas*: o menu aparece em Ferramentas; consultores e todas as equipes listados; totais batem com as propostas abertas uma a uma.
  - Perfil *Ler da equipe*: só as equipes geridas; `?equipe=<id de outra equipe>` não mostra nada dela.
  - Perfil *Ler as próprias*: só a própria linha, sem tabela de equipes.
  - `?inicio=abc` abre no mês corrente; CSV abre no Excel com acentos certos.

- [ ] **Step 6: Commit**

```bash
git add front/relatorio.php templates/relatorio.html.twig README.md CHANGELOG.md
git commit -m "feat: proposals efficiency report page with CSV export"
```
