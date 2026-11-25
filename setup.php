<?php

/**
 * Propostas - propostas comerciais no GLPI.
 *
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

use Glpi\Plugin\Hooks;
use GlpiPlugin\Propostas\Acesso;
use GlpiPlugin\Propostas\Bloco;
use GlpiPlugin\Propostas\Equipe;
use GlpiPlugin\Propostas\Modelo;
use GlpiPlugin\Propostas\ModeloBloco;
use GlpiPlugin\Propostas\ModeloItem;
use GlpiPlugin\Propostas\Profile;
use GlpiPlugin\Propostas\Proposta;
use GlpiPlugin\Propostas\PropostaBloco;
use GlpiPlugin\Propostas\PropostaItem;
use GlpiPlugin\Propostas\Resposta;

define('PLUGIN_PROPOSTAS_VERSION', '1.7.2');
define('PLUGIN_PROPOSTAS_MIN_GLPI', '11.0.0');
define('PLUGIN_PROPOSTAS_MAX_GLPI', '11.99.99');
define('PLUGIN_PROPOSTAS_RIGHT', 'plugin_propostas_proposta');
define('PLUGIN_PROPOSTAS_RIGHT_MODELO', 'plugin_propostas_modelo');

function plugin_version_propostas(): array
{
    return [
        'name'         => 'Propostas',
        'version'      => PLUGIN_PROPOSTAS_VERSION,
        'author'       => 'Equipe de TI',
        'license'      => 'GPLv3+',
        'homepage'     => 'https://github.com/tsathler/glpi-propostas',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_PROPOSTAS_MIN_GLPI,
                'max' => PLUGIN_PROPOSTAS_MAX_GLPI,
            ],
            'php'  => [
                'min' => '8.2',
            ],
        ],
    ];
}

function plugin_init_propostas(): void
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS[Hooks::CSRF_COMPLIANT]['propostas'] = true;

    // Campos cifrados com a chave do GLPI: entram na troca de chave (glpi:security:change_key).
    $PLUGIN_HOOKS[Hooks::SECURED_FIELDS]['propostas'] = [
        'glpi_plugin_propostas_propostas.token_cifrado',
        'glpi_plugin_propostas_respostas.cpf_cifrado',
    ];

    Plugin::registerClass(Proposta::class, ['notificationtemplates_types' => true]);
    Plugin::registerClass(PropostaItem::class);
    Plugin::registerClass(PropostaBloco::class);
    Plugin::registerClass(Resposta::class);
    Plugin::registerClass(Acesso::class);
    Plugin::registerClass(Modelo::class);
    Plugin::registerClass(ModeloItem::class);
    Plugin::registerClass(ModeloBloco::class);
    Plugin::registerClass(Bloco::class);
    // Administração > Perfis > Propostas: direitos "Propostas" e "Gerenciar modelos".
    Plugin::registerClass(Profile::class, ['addtabon' => \Profile::class]);

    // Ferramentas > Propostas, Modelos de proposta e Blocos de texto.
    // O GLPI só mostra cada entrada para quem tem o direito de leitura dela.
    $PLUGIN_HOOKS[Hooks::MENU_TOADD]['propostas'] = ['tools' => [Proposta::class, Modelo::class, Bloco::class]];

    // Equipes: entrada/saída de membro, troca de Responsável ou de grupo pai acertam o
    // gestor observador nos chamados abertos das propostas.
    foreach ([Hooks::ITEM_ADD, Hooks::ITEM_UPDATE, Hooks::ITEM_PURGE] as $hook) {
        $PLUGIN_HOOKS[$hook]['propostas'] = [
            \Group_User::class => [Equipe::class, 'aoMudarMembro'],
            \Group::class      => [Equipe::class, 'aoMudarGrupo'],
        ];
    }

    // Configuração > Plug-ins > Propostas (endereço externo do link e busca de CNPJ).
    if (Session::haveRight('config', UPDATE)) {
        $PLUGIN_HOOKS[Hooks::CONFIG_PAGE]['propostas'] = 'front/config.form.php';
    }
}

function plugin_propostas_check_prerequisites(): bool
{
    return true;
}

function plugin_propostas_check_config($verbose = false): bool
{
    return true;
}
