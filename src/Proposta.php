<?php

/**
 * Propostas - propostas comerciais no GLPI.
 *
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace GlpiPlugin\Propostas;

use CommonDBTM;
use Dropdown;
use Glpi\Application\View\TemplateRenderer;
use Html;
use Log;
use Session;

class Proposta extends CommonDBTM
{
    public static $rightname = 'plugin_propostas_proposta';

    public $dohistory = true;

    public const STATUS_RASCUNHO   = 'rascunho';
    public const STATUS_ENVIADA    = 'enviada';
    public const STATUS_VISUALIZADA = 'visualizada';
    public const STATUS_ACEITA     = 'aceita';
    public const STATUS_DUVIDA     = 'duvida';
    public const STATUS_EXPIRADA   = 'expirada';
    public const STATUS_CANCELADA  = 'cancelada';

    public static function getTypeName($nb = 0): string
    {
        return _n('Proposta', 'Propostas', $nb, 'propostas');
    }

    public static function getIcon(): string
    {
        return 'ti ti-file-dollar';
    }

    /**
     * @return array<string, string>
     */
    public static function getStatusList(): array
    {
        return [
            self::STATUS_RASCUNHO    => __('Rascunho', 'propostas'),
            self::STATUS_ENVIADA     => __('Enviada', 'propostas'),
            self::STATUS_VISUALIZADA => __('Visualizada', 'propostas'),
            self::STATUS_ACEITA      => __('Aceita', 'propostas'),
            self::STATUS_DUVIDA      => __('Com dúvida', 'propostas'),
            self::STATUS_EXPIRADA    => __('Expirada', 'propostas'),
            self::STATUS_CANCELADA   => __('Cancelada', 'propostas'),
        ];
    }

    public function defineTabs($options = []): array
    {
        $tabs = [];
        $this->addDefaultFormTab($tabs);
        $this->addStandardTab(PropostaItem::class, $tabs, $options);
        $this->addStandardTab(Log::class, $tabs, $options);

        return $tabs;
    }

    public function post_getEmpty(): void
    {
        // O GLPI não traz os DEFAULT do banco para o formulário de item novo.
        $this->fields['versao']             = 1;
        $this->fields['validade_dias']      = 15;
        $this->fields['aviso_previo_dias']  = 60;
        $this->fields['data_emissao']       = date('Y-m-d');
        $this->fields['users_id_consultor'] = Session::getLoginUserID();
        $this->fields['status']             = self::STATUS_RASCUNHO;
    }

    public function prepareInputForAdd($input)
    {
        if (empty($input['data_emissao'])) {
            $input['data_emissao'] = date('Y-m-d');
        }
        $input['status'] = self::STATUS_RASCUNHO;

        return $this->validarStatus($input);
    }

    public function prepareInputForUpdate($input)
    {
        return $this->validarStatus($input);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>|false
     */
    private function validarStatus(array $input): array|false
    {
        if (isset($input['status']) && !array_key_exists($input['status'], self::getStatusList())) {
            Session::addMessageAfterRedirect(__('Status inválido.', 'propostas'), false, ERROR);
            return false;
        }

        return $input;
    }

    public function showForm($ID, array $options = []): bool
    {
        $this->initForm($ID, $options);

        TemplateRenderer::getInstance()->display('@propostas/proposta.form.html.twig', [
            'item'        => $this,
            'params'      => $options,
            'numero'      => $this->getNumero(),
            'status_list' => self::getStatusList(),
            'totais'      => $this->isNewItem() ? null : $this->getTotaisFormatados(),
        ]);

        return true;
    }

    /**
     * Número exibido ao cliente (ex.: 00069).
     */
    public function getNumero(): string
    {
        return $this->isNewItem() ? '' : sprintf('%05d', $this->getID());
    }

    /**
     * Soma os subtotais dos itens por tipo.
     *
     * @return array{recorrente: float, pontual: float}
     */
    public function getTotais(): array
    {
        $totais = [PropostaItem::TIPO_RECORRENTE => 0.0, PropostaItem::TIPO_PONTUAL => 0.0];
        foreach (PropostaItem::getItensDaProposta($this->getID()) as $item) {
            $totais[$item['tipo']] = ($totais[$item['tipo']] ?? 0.0) + PropostaItem::calcularSubtotal($item);
        }

        return $totais;
    }

    /**
     * @return array{recorrente: string, pontual: string}
     */
    public function getTotaisFormatados(): array
    {
        return array_map(static fn (float $v): string => Html::formatNumber($v), $this->getTotais());
    }

    public function cleanDBonPurge(): void
    {
        $this->deleteChildrenAndRelationsFromDb([PropostaItem::class]);
    }

    public function rawSearchOptions(): array
    {
        $tab = [];

        $tab[] = [
            'id'   => 'common',
            'name' => __('Characteristics'),
        ];

        $tab[] = [
            'id'            => 1,
            'table'         => self::getTable(),
            'field'         => 'name',
            'name'          => __('Name'),
            'datatype'      => 'itemlink',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'            => 2,
            'table'         => self::getTable(),
            'field'         => 'id',
            'name'          => __('ID'),
            'datatype'      => 'number',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'         => 3,
            'table'      => self::getTable(),
            'field'      => 'status',
            'name'       => __('Status'),
            'datatype'   => 'specific',
            'searchtype' => ['equals', 'notequals'],
        ];

        $tab[] = [
            'id'       => 4,
            'table'    => self::getTable(),
            'field'    => 'data_emissao',
            'name'     => __('Emissão', 'propostas'),
            'datatype' => 'date',
        ];

        $tab[] = [
            'id'        => 5,
            'table'     => 'glpi_users',
            'field'     => 'name',
            'linkfield' => 'users_id_consultor',
            'name'      => __('Consultor', 'propostas'),
            'datatype'  => 'dropdown',
            'right'     => 'all',
        ];

        $tab[] = [
            'id'        => 6,
            'table'     => 'glpi_tickets',
            'field'     => 'name',
            'linkfield' => 'tickets_id',
            'name'      => __('Chamado vinculado', 'propostas'),
            'datatype'  => 'dropdown',
        ];

        $tab[] = [
            'id'       => 7,
            'table'    => self::getTable(),
            'field'    => 'solicitante',
            'name'     => __('Solicitado por', 'propostas'),
            'datatype' => 'string',
        ];

        $tab[] = [
            'id'       => 8,
            'table'    => self::getTable(),
            'field'    => 'versao',
            'name'     => __('Versão', 'propostas'),
            'datatype' => 'integer',
        ];

        $tab[] = [
            'id'       => 9,
            'table'    => self::getTable(),
            'field'    => 'validade_dias',
            'name'     => __('Validade (dias)', 'propostas'),
            'datatype' => 'integer',
        ];

        $tab[] = [
            'id'       => 16,
            'table'    => self::getTable(),
            'field'    => 'comment',
            'name'     => __('Comments'),
            'datatype' => 'text',
        ];

        $tab[] = [
            'id'            => 19,
            'table'         => self::getTable(),
            'field'         => 'date_mod',
            'name'          => __('Last update'),
            'datatype'      => 'datetime',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'            => 121,
            'table'         => self::getTable(),
            'field'         => 'date_creation',
            'name'          => __('Creation date'),
            'datatype'      => 'datetime',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'       => 80,
            'table'    => 'glpi_entities',
            'field'    => 'completename',
            'name'     => \Entity::getTypeName(1),
            'datatype' => 'dropdown',
        ];

        return $tab;
    }

    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        if ($field === 'status') {
            return htmlescape(self::getStatusList()[$values[$field]] ?? (string) $values[$field]);
        }

        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    public static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        $options['display'] = false;
        if ($field === 'status') {
            $options['value'] = $values[$field];
            return Dropdown::showFromArray($name, self::getStatusList(), $options);
        }

        return parent::getSpecificValueToSelect($field, $name, $values, $options);
    }
}
