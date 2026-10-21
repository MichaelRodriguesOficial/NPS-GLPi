<?php

namespace GlpiPlugin\Npsphere;

use CommonGLPI;
use Glpi\Application\View\TemplateRenderer;
use Session;

/**
 * Aba "Público-alvo" no formulário da Pesquisa (Fase 6).
 */
class SurveyTargets extends CommonGLPI
{
    public static function getTypeName($nb = 0)
    {
        return __('Público-alvo', 'npsphere');
    }

    public static function getIcon()
    {
        return 'ti ti-users';
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        // Só faz sentido em pesquisa interna: a externa é respondida por
        // link público (qualquer pessoa) e a de chamado tem o público
        // derivado do próprio chamado.
        if ($item instanceof Survey && $item->getID()
            && ($item->fields['type'] ?? '') === Survey::TYPE_INTERNAL) {
            $nb = countElementsInTable(Target::getTable(), ['surveys_id' => $item->getID()]);
            return self::createTabEntry(self::getTypeName(), $nb);
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof Survey && $item->getID()) {
            self::showForSurvey($item);
        }
        return true;
    }

    public static function showForSurvey(Survey $survey): void
    {
        $targets  = [];
        $excecoes = [];
        foreach (Target::getForSurvey($survey->getID()) as $row) {
            $itemtype = $row['itemtype'];
            $name = null;

            // Resolve o nome legível de cada destinatário. Um item que
            // tenha sido excluído do GLPI depois de cadastrado aparece
            // identificado como tal, em vez de sumir silenciosamente --
            // senão o administrador não entenderia por que alguém deixou
            // de receber a pesquisa.
            if (class_exists($itemtype)) {
                $item = new $itemtype();
                if ($item->getFromDB((int) $row['items_id'])) {
                    $name = $item->getFriendlyName();
                }
            }

            $linha = [
                'id'        => $row['id'],
                'itemtype'  => $itemtype,
                'items_id'  => $row['items_id'],
                'type_label' => Target::getTargetTypes()[$itemtype] ?? $itemtype,
                'name'      => $name,
                'missing'   => $name === null,
            ];

            if (!empty($row['is_excluded'])) {
                $excecoes[] = $linha;
            } else {
                $targets[] = $linha;
            }
        }

        // Um dropdown NATIVO do GLPI por tipo de destinatário.
        //
        // Usar o componente do próprio GLPI (em vez da busca própria que
        // eu havia escrito) traz de graça: caixa de pesquisa embutida
        // com resultados, respeito às entidades visíveis ao usuário,
        // paginação e a mesma aparência do resto do sistema.
        //
        // 'display' => false pede o HTML como retorno; algumas versões
        // ignoram esse parâmetro e imprimem direto, por isso o
        // ob_start() em volta -- assim funciona nos dois casos.
        // Dois conjuntos: um para os destinatários, outro para as exceções.
        $dropdowns = ['incluir' => [], 'excluir' => []];
        foreach (['incluir' => 'target_', 'excluir' => 'exclude_'] as $lista => $prefixo) {
            foreach (Target::getTargetTypes() as $itemtype => $label) {
                $options = [
                    'name'                => $prefixo . $itemtype,
                    'value'               => 0,
                    'width'               => '100%',
                    'display_emptychoice' => true,
                    'emptylabel'          => sprintf(__('Selecione %s…', 'npsphere'), mb_strtolower($label)),
                    'entity'              => -1,
                    'display'             => false,
                ];

                ob_start();
                if ($itemtype === Target::TYPE_USER) {
                    // Usuário tem dropdown PRÓPRIO no GLPI. O genérico
                    // (Dropdown::show) lista pelo campo "name", que é o
                    // login -- já User::dropdown() respeita o formato de
                    // nome configurado na instalação (nome + sobrenome) e
                    // permite pesquisar tanto pelo login quanto pelo nome
                    // real da pessoa.
                    //
                    // 'right' => 'all' inclui todos os usuários, e não
                    // apenas os que têm algum direito específico: quem
                    // responde uma pesquisa interna pode ser qualquer
                    // pessoa da organização.
                    $html = \User::dropdown($options + [
                        'right' => 'all',
                    ]);
                } else {
                    $html = \Dropdown::show($itemtype, $options);
                }
                $printed = ob_get_clean();

                $dropdowns[$lista][$itemtype] = is_string($html) && $html !== '' ? $html : $printed;
            }
        }

        TemplateRenderer::getInstance()->display('@npsphere/targets.html.twig', [
            'dropdowns'   => $dropdowns,
            'survey'      => $survey,
            'targets'     => $targets,
            'excecoes'    => $excecoes,
            'target_all'  => !empty($survey->fields['target_all']),
            'types'       => Target::getTargetTypes(),
            'can_edit'    => Session::haveRight(Target::$rightname, UPDATE),
            'save_url'    => \Plugin::getWebDir('npsphere', true) . '/ajax/target.save.php',
            'answer_url'  => \Plugin::getWebDir('npsphere', true) . '/front/answer.form.php',
        ]);
    }
}
