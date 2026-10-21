<?php

namespace GlpiPlugin\Npsphere;

use CommonGLPI;
use Glpi\Application\View\TemplateRenderer;

/**
 * Aba "Visualizar" no formulário da Pesquisa: mostra como o formulário
 * de resposta vai aparecer para o respondente, sem precisar abrir uma
 * nova janela/aba do navegador.
 *
 * Não é um itemtype persistido (não estende CommonDBTM) -- existe
 * apenas para fornecer conteúdo de aba sobre um Survey já existente,
 * reaproveitando a mesma renderização usada no formulário de resposta
 * real (Question::renderAnswerFields()) para nunca divergir dele.
 */
class SurveyPreview extends CommonGLPI
{
    public static function getTypeName($nb = 0)
    {
        return __('Visualizar', 'npsphere');
    }

    public static function getIcon()
    {
        return 'ti ti-eye';
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof Survey && $item->getID()) {
            return self::createTabEntry(self::getTypeName());
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
        // A pré-visualização SEMPRE mostra o formulário.
        //
        // Antes ela reaproveitava canRespond(), a mesma verificação do
        // fluxo real -- e por isso exibia "Você já respondeu esta
        // pesquisa" assim que o administrador testava uma vez, ou
        // quando a pesquisa estava fora do prazo. O propósito da aba é
        // conferir COMO a pesquisa ficou, e isso independe de o
        // administrador poder ou não respondê-la de verdade (aliás,
        // desde a v0.36.0 ele nem responde: nada aqui é gravado).
        //
        // O motivo do bloqueio ainda é informado como AVISO, para o
        // administrador saber que, naquele estado, o respondente veria
        // outra coisa.
        $eligibility = ResponseService::canRespond($survey);

        TemplateRenderer::getInstance()->display('@npsphere/answer/preview_tab.html.twig', [
            'survey'            => $survey,
            'fields'            => Question::renderAnswerFields($survey->getID()),
            'eligible'          => true,
            'reason'            => null,
            'aviso'             => $eligibility['can'] ? null : $eligibility['reason'],
            'answer_form_url'   => \Plugin::getWebDir('npsphere', true) . '/front/answer.form.php',
            'answer_submit_url' => \Plugin::getWebDir('npsphere', true) . '/ajax/answer.submit.php',
            'preview_refresh_url' => \Plugin::getWebDir('npsphere', true) . '/ajax/survey.preview.php?surveys_id=' . $survey->getID(),
        ]);
    }
}
