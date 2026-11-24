<?php

/**
 * Propostas - propostas comerciais no GLPI.
 *
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

use Glpi\Plugin\Hooks;
use GlpiPlugin\Propostas\Proposta;
use GlpiPlugin\Propostas\PropostaItem;

define('PLUGIN_PROPOSTAS_VERSION', '0.2.0');
define('PLUGIN_PROPOSTAS_MIN_GLPI', '11.0.0');
define('PLUGIN_PROPOSTAS_MAX_GLPI', '11.99.99');
define('PLUGIN_PROPOSTAS_RIGHT', 'plugin_propostas_proposta');

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

    Plugin::registerClass(Proposta::class);
    Plugin::registerClass(PropostaItem::class);

    // Ferramentas > Propostas. O GLPI só mostra o menu para quem tem o direito de leitura.
    $PLUGIN_HOOKS[Hooks::MENU_TOADD]['propostas'] = ['tools' => Proposta::class];

    // Configuração > Plug-ins > Propostas (endereço externo do link público).
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
