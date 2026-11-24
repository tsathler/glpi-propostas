<?php

/**
 * Propostas - instalação e desinstalação.
 *
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

function plugin_propostas_install(): bool
{
    global $DB;

    $charset   = DBConnection::getDefaultCharset();
    $collation = DBConnection::getDefaultCollation();
    $sign      = DBConnection::getDefaultPrimaryKeySignOption();
    $migration = new Migration(PLUGIN_PROPOSTAS_VERSION);

    // Proposta (cabeçalho). Os campos do token já existem para a etapa 2 (link público).
    $table = 'glpi_plugin_propostas_propostas';
    if (!$DB->tableExists($table)) {
        $DB->doQuery("CREATE TABLE `{$table}` (
            `id`                 INT {$sign} NOT NULL AUTO_INCREMENT,
            `entities_id`        INT {$sign} NOT NULL DEFAULT 0,
            `is_recursive`       TINYINT NOT NULL DEFAULT 0,
            `name`               VARCHAR(255) DEFAULT NULL,
            `versao`             INT NOT NULL DEFAULT 1,
            `status`             VARCHAR(20) NOT NULL DEFAULT 'rascunho',
            `solicitante`        VARCHAR(255) DEFAULT NULL,
            `users_id_consultor` INT {$sign} NOT NULL DEFAULT 0,
            `tickets_id`         INT {$sign} NOT NULL DEFAULT 0,
            `data_emissao`       DATE DEFAULT NULL,
            `validade_dias`      INT NOT NULL DEFAULT 15,
            `aviso_previo_dias`  INT NOT NULL DEFAULT 60,
            `condicoes`          LONGTEXT,
            `comment`            TEXT,
            `token_hash`         CHAR(64) DEFAULT NULL,
            `token_expira`       TIMESTAMP NULL DEFAULT NULL,
            `date_creation`      TIMESTAMP NULL DEFAULT NULL,
            `date_mod`           TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `entities_id` (`entities_id`),
            KEY `is_recursive` (`is_recursive`),
            KEY `name` (`name`),
            KEY `status` (`status`),
            KEY `users_id_consultor` (`users_id_consultor`),
            KEY `tickets_id` (`tickets_id`),
            UNIQUE KEY `token_hash` (`token_hash`),
            KEY `date_creation` (`date_creation`),
            KEY `date_mod` (`date_mod`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC");
    }

    // 0.2.0: token do link guardado cifrado com a chave do GLPI, para o consultor poder copiar o link de novo.
    // A busca pelo link usa só o token_hash.
    $migration->addField($table, 'token_cifrado', 'text', ['after' => 'token_hash']);
    $migration->migrationOneTable($table);

    // Itens da proposta (recorrentes ou pontuais).
    $table = 'glpi_plugin_propostas_propostaitems';
    if (!$DB->tableExists($table)) {
        $DB->doQuery("CREATE TABLE `{$table}` (
            `id`                            INT {$sign} NOT NULL AUTO_INCREMENT,
            `plugin_propostas_propostas_id` INT {$sign} NOT NULL DEFAULT 0,
            `tipo`                          VARCHAR(20) NOT NULL DEFAULT 'recorrente',
            `name`                          VARCHAR(255) DEFAULT NULL,
            `quantidade`                    DECIMAL(15,2) NOT NULL DEFAULT 1.00,
            `valor_unitario`                DECIMAL(20,4) NOT NULL DEFAULT 0.0000,
            `desconto_pct`                  DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            `ordem`                         INT NOT NULL DEFAULT 0,
            `date_creation`                 TIMESTAMP NULL DEFAULT NULL,
            `date_mod`                      TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `plugin_propostas_propostas_id` (`plugin_propostas_propostas_id`),
            KEY `tipo` (`tipo`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC");
    }

    // 0.3.0: respostas do cliente (aceite e dúvida). CPF guardado cifrado com a chave do GLPI.
    $table = 'glpi_plugin_propostas_respostas';
    if (!$DB->tableExists($table)) {
        $DB->doQuery("CREATE TABLE `{$table}` (
            `id`                            INT {$sign} NOT NULL AUTO_INCREMENT,
            `plugin_propostas_propostas_id` INT {$sign} NOT NULL DEFAULT 0,
            `tipo`                          VARCHAR(20) NOT NULL DEFAULT '',
            `versao`                        INT NOT NULL DEFAULT 1,
            `nome`                          VARCHAR(255) DEFAULT NULL,
            `cpf_cifrado`                   TEXT,
            `cpf_mascarado`                 VARCHAR(20) DEFAULT NULL,
            `mensagem`                      TEXT,
            `hash_valores`                  CHAR(64) DEFAULT NULL,
            `ip`                            VARCHAR(45) DEFAULT NULL,
            `user_agent`                    VARCHAR(255) DEFAULT NULL,
            `date_creation`                 TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `plugin_propostas_propostas_id` (`plugin_propostas_propostas_id`),
            KEY `tipo` (`tipo`),
            KEY `date_creation` (`date_creation`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC");
    }

    // 0.3.0: cada abertura do link por quem não está logado no GLPI.
    $table = 'glpi_plugin_propostas_acessos';
    if (!$DB->tableExists($table)) {
        $DB->doQuery("CREATE TABLE `{$table}` (
            `id`                            INT {$sign} NOT NULL AUTO_INCREMENT,
            `plugin_propostas_propostas_id` INT {$sign} NOT NULL DEFAULT 0,
            `versao`                        INT NOT NULL DEFAULT 1,
            `ip`                            VARCHAR(45) DEFAULT NULL,
            `user_agent`                    VARCHAR(255) DEFAULT NULL,
            `date_creation`                 TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `plugin_propostas_propostas_id` (`plugin_propostas_propostas_id`),
            KEY `date_creation` (`date_creation`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC");
    }

    // 0.3.0: envios (aceite/dúvida) por IP, para o limite por hora. Só o hash do IP; linhas com
    // mais de 1 hora são apagadas a cada envio.
    $table = 'glpi_plugin_propostas_tentativas';
    if (!$DB->tableExists($table)) {
        $DB->doQuery("CREATE TABLE `{$table}` (
            `id`            INT {$sign} NOT NULL AUTO_INCREMENT,
            `ip_hash`       CHAR(64) NOT NULL,
            `date_creation` TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `ip_data` (`ip_hash`, `date_creation`),
            KEY `date_creation` (`date_creation`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC");
    }

    // 0.4.0: ação automática diária que marca como Expirada a proposta enviada com o link vencido.
    // register() não duplica se já existir.
    CronTask::register('GlpiPlugin\\Propostas\\Proposta', 'expirar', DAY_TIMESTAMP, [
        'comment' => 'Marca como expiradas as propostas enviadas que passaram da validade',
        'mode'    => CronTask::MODE_EXTERNAL,
        'state'   => CronTask::STATE_WAITING,
    ]);

    // 0.3.0: notificações para o consultor (aberta, aceita, dúvida). Só cria o que não existir.
    GlpiPlugin\Propostas\NotificationTargetProposta::instalar();

    // 1.1.0: modelo de origem e empresa cliente na proposta; cargo e formato do hash no aceite.
    $table = 'glpi_plugin_propostas_propostas';
    $migration->addField($table, 'plugin_propostas_modelos_id', "int {$sign} NOT NULL DEFAULT 0", ['after' => 'tickets_id']);
    $migration->addField($table, 'empresa_razao_social', 'string', ['after' => 'solicitante']);
    $migration->addField($table, 'empresa_nome_fantasia', 'string', ['after' => 'empresa_razao_social']);
    $migration->addField($table, 'empresa_cnpj', 'varchar(20) DEFAULT NULL', ['after' => 'empresa_nome_fantasia']);
    $migration->addKey($table, 'plugin_propostas_modelos_id');
    $migration->addKey($table, 'empresa_cnpj');
    $migration->migrationOneTable($table);

    $table = 'glpi_plugin_propostas_respostas';
    $migration->addField($table, 'cargo', 'string', ['after' => 'cpf_mascarado']);
    // Aceites antigos ficam com 1 (hash sem empresa, blocos e cargo); os novos gravam 2.
    $migration->addField($table, 'hash_formato', 'int NOT NULL DEFAULT 1', ['after' => 'hash_valores']);
    $migration->migrationOneTable($table);

    // 1.1.0: modelos de proposta (dados, condições, itens e blocos copiados na criação da proposta).
    $table = 'glpi_plugin_propostas_modelos';
    if (!$DB->tableExists($table)) {
        $DB->doQuery("CREATE TABLE `{$table}` (
            `id`                INT {$sign} NOT NULL AUTO_INCREMENT,
            `entities_id`       INT {$sign} NOT NULL DEFAULT 0,
            `is_recursive`      TINYINT NOT NULL DEFAULT 1,
            `name`              VARCHAR(255) DEFAULT NULL,
            `is_active`         TINYINT NOT NULL DEFAULT 1,
            `ordem`             INT NOT NULL DEFAULT 0,
            `validade_dias`     INT NOT NULL DEFAULT 15,
            `aviso_previo_dias` INT NOT NULL DEFAULT 60,
            `condicoes`         LONGTEXT,
            `comment`           TEXT,
            `date_creation`     TIMESTAMP NULL DEFAULT NULL,
            `date_mod`          TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `entities_id` (`entities_id`),
            KEY `is_recursive` (`is_recursive`),
            KEY `name` (`name`),
            KEY `is_active` (`is_active`),
            KEY `date_creation` (`date_creation`),
            KEY `date_mod` (`date_mod`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC");
    }

    $table = 'glpi_plugin_propostas_modeloitems';
    if (!$DB->tableExists($table)) {
        $DB->doQuery("CREATE TABLE `{$table}` (
            `id`                          INT {$sign} NOT NULL AUTO_INCREMENT,
            `plugin_propostas_modelos_id` INT {$sign} NOT NULL DEFAULT 0,
            `tipo`                        VARCHAR(20) NOT NULL DEFAULT 'recorrente',
            `name`                        VARCHAR(255) DEFAULT NULL,
            `quantidade`                  DECIMAL(15,2) NOT NULL DEFAULT 1.00,
            `valor_unitario`              DECIMAL(20,4) NOT NULL DEFAULT 0.0000,
            `desconto_pct`                DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            `ordem`                       INT NOT NULL DEFAULT 0,
            `date_creation`               TIMESTAMP NULL DEFAULT NULL,
            `date_mod`                    TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `plugin_propostas_modelos_id` (`plugin_propostas_modelos_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC");
    }

    // 1.1.0: biblioteca de blocos de texto, os blocos que cada modelo usa e os blocos copiados para a proposta.
    $table = 'glpi_plugin_propostas_blocos';
    if (!$DB->tableExists($table)) {
        $DB->doQuery("CREATE TABLE `{$table}` (
            `id`            INT {$sign} NOT NULL AUTO_INCREMENT,
            `entities_id`   INT {$sign} NOT NULL DEFAULT 0,
            `is_recursive`  TINYINT NOT NULL DEFAULT 1,
            `name`          VARCHAR(255) DEFAULT NULL,
            `conteudo`      LONGTEXT,
            `is_active`     TINYINT NOT NULL DEFAULT 1,
            `comment`       TEXT,
            `date_creation` TIMESTAMP NULL DEFAULT NULL,
            `date_mod`      TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `entities_id` (`entities_id`),
            KEY `is_recursive` (`is_recursive`),
            KEY `name` (`name`),
            KEY `is_active` (`is_active`),
            KEY `date_creation` (`date_creation`),
            KEY `date_mod` (`date_mod`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC");
    }

    $table = 'glpi_plugin_propostas_modelos_blocos';
    if (!$DB->tableExists($table)) {
        $DB->doQuery("CREATE TABLE `{$table}` (
            `id`                          INT {$sign} NOT NULL AUTO_INCREMENT,
            `plugin_propostas_modelos_id` INT {$sign} NOT NULL DEFAULT 0,
            `plugin_propostas_blocos_id`  INT {$sign} NOT NULL DEFAULT 0,
            `ordem`                       INT NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unicity` (`plugin_propostas_modelos_id`, `plugin_propostas_blocos_id`),
            KEY `plugin_propostas_blocos_id` (`plugin_propostas_blocos_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC");
    }

    $table = 'glpi_plugin_propostas_propostablocos';
    if (!$DB->tableExists($table)) {
        $DB->doQuery("CREATE TABLE `{$table}` (
            `id`                            INT {$sign} NOT NULL AUTO_INCREMENT,
            `plugin_propostas_propostas_id` INT {$sign} NOT NULL DEFAULT 0,
            `name`                          VARCHAR(255) DEFAULT NULL,
            `conteudo`                      LONGTEXT,
            `ordem`                         INT NOT NULL DEFAULT 0,
            `date_creation`                 TIMESTAMP NULL DEFAULT NULL,
            `date_mod`                      TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `plugin_propostas_propostas_id` (`plugin_propostas_propostas_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC");
    }

    // 1.1.0: busca de CNPJ (ligada por padrão; a URL usa {cnpj} no lugar do número).
    Config::setConfigurationValues('plugin:propostas', array_diff_key(
        ['cnpj_consulta_ativa' => '1', 'cnpj_consulta_url' => 'https://brasilapi.com.br/api/cnpj/v1/{cnpj}'],
        Config::getConfigurationValues('plugin:propostas', ['cnpj_consulta_ativa', 'cnpj_consulta_url'])
    ));

    // Direitos próprios, criados só se ainda não existirem (reinstalar não mexe no que foi
    // ajustado em Administração > Perfis > Propostas). Perfis que administram a configuração
    // recebem acesso total; os outros começam sem acesso.
    foreach ([PLUGIN_PROPOSTAS_RIGHT, PLUGIN_PROPOSTAS_RIGHT_MODELO] as $direito) {
        if (countElementsInTable('glpi_profilerights', ['name' => $direito]) === 0) {
            plugin_propostas_criar_direito($direito);
        }
    }

    // Níveis de leitura (as próprias, da equipe, todas). Uma vez só: quem já lia propostas
    // continua vendo todas, até alguém ajustar o perfil em Administração > Perfis > Propostas.
    $marca = Config::getConfigurationValues('plugin:propostas', ['instalado_niveis_leitura']);
    if (empty($marca['instalado_niveis_leitura'])) {
        foreach ($DB->request(['FROM' => 'glpi_profilerights', 'WHERE' => ['name' => PLUGIN_PROPOSTAS_RIGHT]]) as $row) {
            $rights = (int) $row['rights'];
            if (($rights & READ) === READ) {
                $DB->update('glpi_profilerights', ['rights' => $rights | GlpiPlugin\Propostas\Visibilidade::TODAS], ['id' => $row['id']]);
                if ((int) ($_SESSION['glpiactiveprofile']['id'] ?? 0) === (int) $row['profiles_id']) {
                    $_SESSION['glpiactiveprofile'][PLUGIN_PROPOSTAS_RIGHT] = $rights | GlpiPlugin\Propostas\Visibilidade::TODAS;
                }
            }
        }
        Config::setConfigurationValues('plugin:propostas', ['instalado_niveis_leitura' => '1']);
    }

    // Gestores que o próprio plugin pôs como observadores nos chamados das propostas
    // (a sincronização só remove esses; observador incluído à mão fica).
    $table = GlpiPlugin\Propostas\Equipe::TABELA_OBSERVADORES;
    if (!$DB->tableExists($table)) {
        $DB->doQuery("CREATE TABLE `{$table}` (
            `id`            INT {$sign} NOT NULL AUTO_INCREMENT,
            `tickets_id`    INT {$sign} NOT NULL DEFAULT 0,
            `users_id`      INT {$sign} NOT NULL DEFAULT 0,
            `date_creation` TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unicity` (`tickets_id`, `users_id`),
            KEY `users_id` (`users_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC");
    }

    // 1.4.0: desconto especial do modelo (caixa na criação da proposta) e exibição dos valores ao
    // cliente: 'total' (quantidade, subtotal e totais, o padrão) ou 'unidade' (preço por unidade, de/por).
    $table = 'glpi_plugin_propostas_modelos';
    $migration->addField($table, 'desconto_especial_pct', 'decimal(5,2) NOT NULL DEFAULT 0.00', ['after' => 'aviso_previo_dias']);
    $migration->addField($table, 'exibicao', "varchar(20) NOT NULL DEFAULT 'total'", ['after' => 'desconto_especial_pct']);
    $migration->migrationOneTable($table);

    $table = 'glpi_plugin_propostas_propostas';
    $migration->addField($table, 'exibicao', "varchar(20) NOT NULL DEFAULT 'total'", ['after' => 'aviso_previo_dias']);
    $migration->migrationOneTable($table);

    // 1.4.0: bloco de texto mostrado depois dos valores e das condições (ex.: agradecimento, assinatura).
    foreach (['glpi_plugin_propostas_blocos', 'glpi_plugin_propostas_propostablocos'] as $table) {
        $migration->addField($table, 'depois_dos_valores', 'tinyint NOT NULL DEFAULT 0', ['after' => 'conteudo']);
        $migration->migrationOneTable($table);
    }

    // Colunas padrão das listas, só se a lista ainda não tiver nenhuma.
    // Números = ids do rawSearchOptions() de cada classe.
    $colunas = [
        // 2 ID, 3 Status, 10 Razão social, 4 Emissão, 7 Solicitado por, 5 Consultor, 6 Chamado, 19 Última atualização
        'GlpiPlugin\\Propostas\\Proposta' => [2, 3, 10, 4, 7, 5, 6, 19],
        // 2 ID, 3 Ativo, 4 Ordem, 19 Última atualização
        'GlpiPlugin\\Propostas\\Modelo'   => [2, 3, 4, 19],
        // 2 ID, 3 Ativo, 19 Última atualização
        'GlpiPlugin\\Propostas\\Bloco'    => [2, 3, 19],
    ];
    foreach ($colunas as $itemtype => $numeros) {
        if (countElementsInTable('glpi_displaypreferences', ['itemtype' => $itemtype, 'users_id' => 0]) > 0) {
            continue;
        }
        foreach ($numeros as $rank => $num) {
            $DB->insert('glpi_displaypreferences', [
                'itemtype'  => $itemtype,
                'num'       => $num,
                'rank'      => $rank + 1,
                'users_id'  => 0,
                'interface' => 'central',
            ]);
        }
    }
    // 1.1.0: quem atualiza ganha a coluna "Razão social" na lista de propostas. Uma vez só:
    // se o administrador tirar a coluna depois, reinstalar não a devolve.
    $marca = Config::getConfigurationValues('plugin:propostas', ['instalado_colunas_1_1']);
    if (empty($marca['instalado_colunas_1_1'])) {
        $proposta = 'GlpiPlugin\\Propostas\\Proposta';
        if (countElementsInTable('glpi_displaypreferences', ['itemtype' => $proposta, 'users_id' => 0, 'num' => 10]) === 0) {
            $DB->insert('glpi_displaypreferences', [
                'itemtype' => $proposta, 'num' => 10, 'rank' => 99, 'users_id' => 0, 'interface' => 'central',
            ]);
        }
        Config::setConfigurationValues('plugin:propostas', ['instalado_colunas_1_1' => '1']);
    }

    $migration->executeMigration();
    return true;
}

function plugin_propostas_criar_direito(string $direito): void
{
    global $DB;

    ProfileRight::addProfileRights([$direito]);

    $admin_profiles = [];
    foreach ($DB->request(['FROM' => 'glpi_profilerights', 'WHERE' => ['name' => 'config']]) as $row) {
        if (((int) $row['rights'] & UPDATE) === UPDATE) {
            $admin_profiles[] = (int) $row['profiles_id'];
        }
    }
    if ($admin_profiles === []) {
        return;
    }

    $DB->update(
        'glpi_profilerights',
        ['rights' => ALLSTANDARDRIGHT],
        ['name' => $direito, 'profiles_id' => $admin_profiles]
    );
    $active = (int) ($_SESSION['glpiactiveprofile']['id'] ?? 0);
    if (in_array($active, $admin_profiles, true)) {
        $_SESSION['glpiactiveprofile'][$direito] = ALLSTANDARDRIGHT;
    }
}

function plugin_propostas_uninstall(): bool
{
    global $DB;

    GlpiPlugin\Propostas\NotificationTargetProposta::desinstalar();
    CronTask::unregister('propostas');

    $tabelas = [
        'glpi_plugin_propostas_observadores',
        'glpi_plugin_propostas_propostablocos',
        'glpi_plugin_propostas_modelos_blocos',
        'glpi_plugin_propostas_blocos',
        'glpi_plugin_propostas_modeloitems',
        'glpi_plugin_propostas_modelos',
        'glpi_plugin_propostas_tentativas',
        'glpi_plugin_propostas_acessos',
        'glpi_plugin_propostas_respostas',
        'glpi_plugin_propostas_propostaitems',
        'glpi_plugin_propostas_propostas',
    ];
    foreach ($tabelas as $table) {
        if ($DB->tableExists($table)) {
            $DB->dropTable($table);
        }
    }

    $itemtypes = array_map(static fn (string $c): string => 'GlpiPlugin\\Propostas\\' . $c, [
        'Proposta', 'PropostaItem', 'PropostaBloco', 'Modelo', 'ModeloItem', 'ModeloBloco', 'Bloco',
    ]);
    $DB->delete('glpi_displaypreferences', ['itemtype' => $itemtypes]);
    $DB->delete('glpi_logs', ['itemtype' => $itemtypes]);

    // Pesquisas salvas das listas do plugin (e o que depende delas).
    $pesquisas = array_column(iterator_to_array($DB->request([
        'SELECT' => ['id'],
        'FROM'   => 'glpi_savedsearches',
        'WHERE'  => ['itemtype' => $itemtypes],
    ]), false), 'id');
    if ($pesquisas !== []) {
        foreach (['glpi_savedsearches_users', 'glpi_savedsearches_alerts'] as $tabela) {
            if ($DB->tableExists($tabela)) {
                $DB->delete($tabela, ['savedsearches_id' => $pesquisas]);
            }
        }
        $DB->delete('glpi_savedsearches', ['id' => $pesquisas]);
    }

    ProfileRight::deleteProfileRights([PLUGIN_PROPOSTAS_RIGHT, PLUGIN_PROPOSTAS_RIGHT_MODELO]);
    unset($_SESSION['glpiactiveprofile'][PLUGIN_PROPOSTAS_RIGHT], $_SESSION['glpiactiveprofile'][PLUGIN_PROPOSTAS_RIGHT_MODELO]);

    Config::deleteConfigurationValues('plugin:propostas', [
        'url_publica', 'cnpj_consulta_ativa', 'cnpj_consulta_url', 'chamado_itilcategories_id', 'chamado_groups_id',
        'instalado_notificacoes', 'instalado_colunas_1_1',
        'equipes_groups_id', 'propostas_entities_id', 'instalado_niveis_leitura', 'instalado_notificacoes_equipes',
    ]);

    return true;
}

/**
 * Filtro da lista e da busca de propostas pelo nível de leitura do perfil
 * (as próprias, as da equipe ou todas). Chamado pelo GLPI para os tipos do plugin.
 *
 * @return array<string, mixed>
 */
function plugin_propostas_addDefaultWhere($itemtype): array
{
    if ($itemtype !== GlpiPlugin\Propostas\Proposta::class) {
        return [];
    }

    return GlpiPlugin\Propostas\Proposta::criteriosVisibilidade();
}
