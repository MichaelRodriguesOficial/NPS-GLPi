<?php

namespace GlpiPlugin\Npsphere;

use CommonGLPI;
use Glpi\Application\View\TemplateRenderer;

/**
 * Aba "Resultados" no formulário da Pesquisa (Fase 4): NPS consolidado
 * e métricas por pergunta, calculados sob demanda.
 */
class SurveyResults extends CommonGLPI
{
    public static function getTypeName($nb = 0)
    {
        return __('Resultados', 'npsphere');
    }

    public static function getIcon()
    {
        return 'ti ti-chart-bar';
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
        global $DB, $CFG_GLPI;

        $surveys_id = $survey->getID();

        // Filtro de entidades da VISÃO GERAL.
        //
        // Lista EXATA: selecionar uma entidade-pai não arrasta as
        // filhas. Quem quer avaliar o atendimento de uma unidade
        // precisa dos números dela, e não da estrutura inteira -- e
        // quem quer a estrutura inteira simplesmente não filtra.
        //
        // Não se aplica à pesquisa EXTERNA: ali quem responde vem por
        // link público, sem vínculo com entidade, e a tela permanece
        // como está.
        $filtra_entidades = ($survey->fields['type'] ?? '') !== Survey::TYPE_EXTERNAL;

        $entidades_filtro = [];
        if ($filtra_entidades && !empty($_GET['npsphere_entities'])) {
            foreach ((array) $_GET['npsphere_entities'] as $e) {
                $e = (int) $e;
                if ($e > 0 || $e === 0) {
                    $entidades_filtro[] = $e;
                }
            }
            $entidades_filtro = array_values(array_unique($entidades_filtro));
        }

        $questions = [];
        $iterator = $DB->request([
            'FROM'  => Question::getTable(),
            'WHERE' => ['surveys_id' => $surveys_id],
            'ORDER' => 'position ASC',
        ]);
        foreach ($iterator as $row) {
            $questions[] = $row;
        }

        $total_answers = MetricsCalculator::getTotalAnswers($surveys_id, $entidades_filtro);

        $results = [];
        foreach ($questions as $question) {
            $values = MetricsCalculator::getValuesForQuestion($surveys_id, (int) $question['id'], $entidades_filtro);
            $results[] = [
                'question'    => $question,
                'type_label'  => QuestionTypeManager::getTypesForDropdown()[$question['type']] ?? $question['type'],
                'conditional' => !empty($question['condition_questions_id']),
                'metrics'     => MetricsCalculator::getQuestionMetrics($question, $values),
            ];
        }

        $nps_questions = 0;
        foreach ($questions as $q) {
            if ($q['type'] === 'nps') {
                $nps_questions++;
            }
        }

        // Identificação dos respondentes (só existe em pesquisa pública
        // NÃO anônima com identificação exigida). Nunca é exibida em
        // pesquisa anônima -- ali o campo sequer é gravado.
        // Perguntas de NPS da pesquisa, para calcular a nota individual
        // de cada resposta (exibida na lista e no detalhe).
        // Perguntas que geram nota: NPS, estrelas E emoticons.
        $score_questions = MetricsCalculator::getScoreQuestions($questions);

        $respondents = [];
        // fieldExists(): defesa contra a tela inteira quebrar caso a
        // migração das colunas de identificação ainda não tenha rodado
        // (ex.: arquivos atualizados mas sem clicar em "Atualizar" na
        // tela de Plugins). Melhor a seção não aparecer do que derrubar
        // a aba Resultados com erro 500.
        if (
            empty($survey->fields['is_anonymous'])
            && $DB->fieldExists(Answer::getTable(), 'respondent_name')
        ) {
            // O MESMO filtro de entidades da visão geral. Antes a lista
            // era filtrada só na tela, e as duas abas podiam acabar
            // mostrando recortes diferentes dos mesmos dados.
            $where_resp = ['surveys_id' => $surveys_id, ['NOT' => ['respondent_name' => '']]];
            if (!empty($entidades_filtro)) {
                $where_resp['entities_id'] = $entidades_filtro;
            }

            foreach ($DB->request([
                'FROM'  => Answer::getTable(),
                'WHERE' => $where_resp,
                'ORDER' => 'date_creation DESC',
            ]) as $row) {
                $row['nps'] = MetricsCalculator::getAnswerScore(
                    (int) $row['id'],
                    $score_questions
                );

                // Nome da entidade para exibir e filtrar na lista
                $row['entity_name'] = \Dropdown::getDropdownName('glpi_entities', (int) $row['entities_id']);

                $respondents[] = $row;
            }
        }

        // Entidades que REALMENTE têm resposta nesta pesquisa.
        //
        // Listar a árvore inteira do GLPI encheria o seletor de opções
        // que não filtram nada; aqui só aparece o que existe.
        $entidades_disponiveis = [];
        if ($filtra_entidades) {
            foreach ($DB->request([
                'SELECT'   => ['entities_id'],
                'DISTINCT' => true,
                'FROM'     => Answer::getTable(),
                'WHERE'    => ['surveys_id' => $surveys_id],
            ]) as $row) {
                $eid = (int) $row['entities_id'];
                $entidades_disponiveis[$eid] = \Dropdown::getDropdownName('glpi_entities', $eid);
            }
            asort($entidades_disponiveis);
        }

        TemplateRenderer::getInstance()->display('@npsphere/results.html.twig', [
            'filtra_entidades'      => $filtra_entidades,
            'entidades_disponiveis' => $entidades_disponiveis,
            'entidades_filtro'      => $entidades_filtro,
            'is_ticket'             => ($survey->fields['type'] ?? '') === Survey::TYPE_TICKET,
            'ticket_url'            => $CFG_GLPI['root_doc'] . '/front/ticket.form.php',
            'respondents'   => $respondents,
            'detail_url'    => \Plugin::getWebDir('npsphere', true) . '/ajax/answer.detail.php',
            // A exportação carrega o filtro ativo: exportar a base
            // inteira enquanto a tela mostra um recorte seria um
            // resultado surpreendente -- e difícil de notar, porque o
            // arquivo só é conferido depois.
            'export_url'    => \Plugin::getWebDir('npsphere', true) . '/ajax/export.csv.php',
            'export_params' => $entidades_filtro,
            'survey'        => $survey,
            'total_answers' => $total_answers,
            'nps'           => MetricsCalculator::getSurveyNps($surveys_id, $questions, $entidades_filtro),
            'overall'       => MetricsCalculator::getOverallScore($surveys_id, $questions, $entidades_filtro),
            'nps_questions' => $nps_questions,
            'results'       => $results,
            // Mesma mecânica da aba "Visualizar": o GLPI mantém o
            // conteúdo já carregado das abas no DOM, então sem isto os
            // números ficariam congelados no estado de quando a aba foi
            // aberta pela primeira vez -- justamente numa tela cujo
            // propósito é mostrar dados atualizados.
            'refresh_url'   => \Plugin::getWebDir('npsphere', true) . '/ajax/survey.results.php?surveys_id=' . $surveys_id,
        ]);
    }
}
