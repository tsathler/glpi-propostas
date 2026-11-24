<?php

/**
 * Propostas - modelos de proposta (Ferramentas > Modelos de proposta).
 *
 * O modelo é escolhido na criação da proposta e tem o conteúdo copiado para ela: validade, aviso
 * prévio, condições comerciais, itens e blocos de texto. Depois disso a proposta é independente.
 *
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace GlpiPlugin\Propostas;

use CommonDBTM;
use Glpi\Application\View\TemplateRenderer;
use Log;

class Modelo extends CommonDBTM
{
    public static $rightname = 'plugin_propostas_modelo';

    public $dohistory = true;

    public static function getTypeName($nb = 0): string
    {
        return _n('Modelo de proposta', 'Modelos de proposta', $nb, 'propostas');
    }

    public static function getIcon(): string
    {
        return 'ti ti-template';
    }

    public function defineTabs($options = []): array
    {
        $tabs = [];
        $this->addDefaultFormTab($tabs);
        $this->addStandardTab(ModeloItem::class, $tabs, $options);
        $this->addStandardTab(ModeloBloco::class, $tabs, $options);
        $this->addStandardTab(Log::class, $tabs, $options);

        return $tabs;
    }

    public function post_getEmpty(): void
    {
        $this->fields['is_active']         = 1;
        $this->fields['is_recursive']      = 1;
        $this->fields['validade_dias']     = 15;
        $this->fields['aviso_previo_dias'] = 60;
        $this->fields['ordem']             = 0;
        $this->fields['desconto_especial_pct'] = 0;
        $this->fields['exibicao']          = Calculo::EXIBICAO_TOTAL;
    }

    public function prepareInputForAdd($input)
    {
        return $this->normalizarInput($input);
    }

    public function prepareInputForUpdate($input)
    {
        return $this->normalizarInput($input);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function normalizarInput(array $input): array
    {
        if (array_key_exists('desconto_especial_pct', $input)) {
            $input['desconto_especial_pct'] = Calculo::percentual($input['desconto_especial_pct']);
        }
        if (array_key_exists('exibicao', $input)) {
            $input['exibicao'] = Calculo::exibicaoValida($input['exibicao']);
        }

        return $input;
    }

    /** Desconto especial oferecido na criação da proposta (0 = sem a caixa). */
    public function getDescontoEspecial(): float
    {
        return Calculo::percentual($this->fields['desconto_especial_pct'] ?? 0);
    }

    /**
     * @return array<string, string>
     */
    public static function getOpcoesExibicao(): array
    {
        return [
            Calculo::EXIBICAO_TOTAL   => __('Quantidade, subtotal e totais', 'propostas'),
            Calculo::EXIBICAO_UNIDADE => __('Preço por unidade (de/por), sem totais', 'propostas'),
        ];
    }

    public function showForm($ID, array $options = []): bool
    {
        $this->initForm($ID, $options);
        TemplateRenderer::getInstance()->display('@propostas/modelo.form.html.twig', [
            'item'      => $this,
            'params'    => $options,
            'exibicoes' => self::getOpcoesExibicao(),
        ]);

        return true;
    }

    public function cleanDBonPurge(): void
    {
        $this->deleteChildrenAndRelationsFromDb([ModeloItem::class, ModeloBloco::class]);
    }

    /**
     * Copia validade, aviso prévio (campo antigo, fora da tela) e condições do modelo para o input de uma proposta nova.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function aplicarNoInput(array $input): array
    {
        $input['validade_dias']     = $this->fields['validade_dias'];
        $input['aviso_previo_dias'] = $this->fields['aviso_previo_dias'];
        $input['condicoes']         = $this->fields['condicoes'];
        $input['exibicao']          = Calculo::exibicaoValida($this->fields['exibicao'] ?? null);

        return $input;
    }

    /**
     * Copia itens e blocos do modelo para a proposta.
     *
     * @param array<int|string, mixed> $valores valor digitado na criação, por id do item do modelo
     * @param float|null $desconto desconto (%) para todos os itens; null mantém o de cada item
     */
    public function copiarParaProposta(int $propostas_id, array $valores = [], ?float $desconto = null): void
    {
        foreach (Calculo::itensDaCopia(ModeloItem::getItensDoModelo($this->getID()), $valores, $desconto) as $item) {
            (new PropostaItem())->add([
                PropostaItem::$items_id => $propostas_id,
                'tipo'                  => $item['tipo'],
                'name'                  => $item['name'],
                'quantidade'            => $item['quantidade'],
                'valor_unitario'        => $item['valor_unitario'],
                'desconto_pct'          => $item['desconto_pct'],
                'ordem'                 => $item['ordem'],
            ]);
        }
        foreach (ModeloBloco::getBlocosDoModelo($this->getID()) as $bloco) {
            (new PropostaBloco())->add([
                PropostaBloco::$items_id => $propostas_id,
                'name'                   => $bloco['name'],
                'conteudo'               => $bloco['conteudo'],
                'ordem'                  => $bloco['ordem'],
                'depois_dos_valores'     => (int) ($bloco['depois_dos_valores'] ?? 0),
            ]);
        }
    }

    public function rawSearchOptions(): array
    {
        return [
            ['id' => 'common', 'name' => __('Characteristics')],
            ['id' => 1, 'table' => self::getTable(), 'field' => 'name', 'name' => __('Name'), 'datatype' => 'itemlink', 'massiveaction' => false],
            ['id' => 2, 'table' => self::getTable(), 'field' => 'id', 'name' => __('ID'), 'datatype' => 'number', 'massiveaction' => false],
            ['id' => 3, 'table' => self::getTable(), 'field' => 'is_active', 'name' => __('Active'), 'datatype' => 'bool'],
            ['id' => 4, 'table' => self::getTable(), 'field' => 'ordem', 'name' => __('Ordem', 'propostas'), 'datatype' => 'integer'],
            ['id' => 5, 'table' => self::getTable(), 'field' => 'validade_dias', 'name' => __('Validade (dias)', 'propostas'), 'datatype' => 'integer'],
            ['id' => 6, 'table' => self::getTable(), 'field' => 'desconto_especial_pct', 'name' => __('Desconto sugerido (%)', 'propostas'), 'datatype' => 'decimal'],
            ['id' => 16, 'table' => self::getTable(), 'field' => 'comment', 'name' => __('Comments'), 'datatype' => 'text'],
            ['id' => 19, 'table' => self::getTable(), 'field' => 'date_mod', 'name' => __('Last update'), 'datatype' => 'datetime', 'massiveaction' => false],
            ['id' => 121, 'table' => self::getTable(), 'field' => 'date_creation', 'name' => __('Creation date'), 'datatype' => 'datetime', 'massiveaction' => false],
            ['id' => 80, 'table' => 'glpi_entities', 'field' => 'completename', 'name' => \Entity::getTypeName(1), 'datatype' => 'dropdown'],
            ['id' => 86, 'table' => self::getTable(), 'field' => 'is_recursive', 'name' => __('Child entities'), 'datatype' => 'bool'],
        ];
    }
}
