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

    // 0.3.0: notificações para o consultor (aberta, aceita, dúvida). Só cria o que não existir.
    GlpiPlugin\Propostas\NotificationTargetProposta::instalar();

    // Direito próprio, criado só na primeira instalação (reinstalar não mexe no que foi
    // ajustado em Administração > Perfis). Perfis que administram a configuração recebem
    // acesso total; os outros começam sem acesso.
    if (countElementsInTable('glpi_profilerights', ['name' => PLUGIN_PROPOSTAS_RIGHT]) === 0) {
        plugin_propostas_criar_direito();
    }

    // Colunas padrão da lista (Ferramentas > Propostas), só se ainda não houver nenhuma.
    // Números = ids do rawSearchOptions(): 2 ID, 3 Status, 4 Emissão, 5 Consultor, 6 Chamado, 7 Solicitado por, 19 Última atualização.
    $itemtype = 'GlpiPlugin\\Propostas\\Proposta';
    if (countElementsInTable('glpi_displaypreferences', ['itemtype' => $itemtype, 'users_id' => 0]) === 0) {
        foreach ([2, 3, 4, 7, 5, 6, 19] as $rank => $num) {
            $DB->insert('glpi_displaypreferences', [
                'itemtype'  => $itemtype,
                'num'       => $num,
                'rank'      => $rank + 1,
                'users_id'  => 0,
                'interface' => 'central',
            ]);
        }
    }

    $migration->executeMigration();
    return true;
}

function plugin_propostas_criar_direito(): void
{
    global $DB;

    ProfileRight::addProfileRights([PLUGIN_PROPOSTAS_RIGHT]);

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
        ['name' => PLUGIN_PROPOSTAS_RIGHT, 'profiles_id' => $admin_profiles]
    );
    $active = (int) ($_SESSION['glpiactiveprofile']['id'] ?? 0);
    if (in_array($active, $admin_profiles, true)) {
        $_SESSION['glpiactiveprofile'][PLUGIN_PROPOSTAS_RIGHT] = ALLSTANDARDRIGHT;
    }
}

function plugin_propostas_uninstall(): bool
{
    global $DB;

    GlpiPlugin\Propostas\NotificationTargetProposta::desinstalar();

    $tabelas = [
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

    $itemtypes = ['GlpiPlugin\\Propostas\\Proposta', 'GlpiPlugin\\Propostas\\PropostaItem'];
    $DB->delete('glpi_displaypreferences', ['itemtype' => $itemtypes]);
    $DB->delete('glpi_logs', ['itemtype' => $itemtypes]);

    ProfileRight::deleteProfileRights([PLUGIN_PROPOSTAS_RIGHT]);
    unset($_SESSION['glpiactiveprofile'][PLUGIN_PROPOSTAS_RIGHT]);

    Config::deleteConfigurationValues('plugin:propostas', ['url_publica']);

    return true;
}
