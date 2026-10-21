<?php

/**
 * Exportação dos resultados de uma pesquisa (seção 32 do escopo).
 *
 * Formatos (parâmetro "format"):
 *  - "csv"  : um arquivo por conteúdo, escolhido por "scope":
 *             "overview" (indicadores e métricas por pergunta) ou
 *             "answers" (uma linha por resposta);
 *  - "xlsx" / "ods" : uma planilha só, com as abas "Visão geral" e
 *             "Respostas".
 *
 * O filtro de entidades da tela vale para todos: o arquivo reproduz o
 * recorte que está sendo visto.
 */

use GlpiPlugin\Npsphere\Answer;
use GlpiPlugin\Npsphere\AnswerValue;
use GlpiPlugin\Npsphere\MetricsCalculator;
use GlpiPlugin\Npsphere\Question;
use GlpiPlugin\Npsphere\QuestionTypeManager;
use GlpiPlugin\Npsphere\SheetExport;
use GlpiPlugin\Npsphere\Survey;

Session::checkRight(Survey::$rightname, READ);

global $DB;

$surveys_id = (int) ($_GET['surveys_id'] ?? 0);

// Só quem criou a pesquisa (ou o administrador geral) a gerencia.
Survey::exigirGestao($surveys_id);

$scope   = ($_GET['scope'] ?? 'answers') === 'overview' ? 'overview' : 'answers';
$formato = SheetExport::formato($_GET['format'] ?? 'csv');

$survey = new Survey();
if ($surveys_id <= 0 || !$survey->getFromDB($surveys_id)) {
    http_response_code(404);
    exit;
}

// Filtro de entidades vindo da tela. Exportar a base inteira enquanto a
// tela mostra um subconjunto seria surpreendente -- e difícil de notar,
// porque o arquivo só é conferido depois.
$entidades = [];
foreach ((array) ($_GET['npsphere_entities'] ?? []) as $e) {
    if (is_numeric($e)) {
        $entidades[] = (int) $e;
    }
}
$entidades = array_values(array_unique($entidades));

$questions = [];
foreach ($DB->request([
    'FROM'  => Question::getTable(),
    'WHERE' => ['surveys_id' => $surveys_id],
    'ORDER' => 'position ASC',
]) as $row) {
    $questions[] = $row;
}

$sx = new SheetExport();

// -----------------------------------------------------------------
// Visão geral
// -----------------------------------------------------------------
$visaoGeral = static function () use ($sx, $survey, $surveys_id, $questions, $entidades): void {
    $summary = MetricsCalculator::getSummary($surveys_id, $questions, $entidades);

    $sx->aba(__('Visão geral', 'npsphere'));
    $sx->linha([__('Pesquisa', 'npsphere'), $survey->fields['name']]);
    $sx->linha([__('Total de respostas', 'npsphere'), SheetExport::num($summary['total_answers'])]);
    if ($summary['nps']) {
        $sx->linha([__('NPS', 'npsphere'), SheetExport::num($summary['nps']['score'])]);
        $sx->linha([__('Nota média', 'npsphere'), (float) $summary['nps']['average']]);
        $sx->linha([__('Promotores (%)', 'npsphere'), SheetExport::pct($summary['nps']['promoters_pct'])]);
        $sx->linha([__('Neutros (%)', 'npsphere'), SheetExport::pct($summary['nps']['passives_pct'])]);
        $sx->linha([__('Detratores (%)', 'npsphere'), SheetExport::pct($summary['nps']['detractors_pct'])]);
    }
    $sx->vazia();

    $types = QuestionTypeManager::getTypesForDropdown();
    $sx->cabecalho([
        __('Pergunta', 'npsphere'),
        __('Tipo', 'npsphere'),
        __('Responderam', 'npsphere'),
        __('Resultado', 'npsphere'),
        __('Detalhe', 'npsphere'),
    ]);

    foreach ($questions as $question) {
        $values  = MetricsCalculator::getValuesForQuestion($surveys_id, (int) $question['id'], $entidades);
        $metrics = MetricsCalculator::getQuestionMetrics($question, $values);

        $resultado = '';
        $detalhe   = '';
        switch ($metrics['kind'] ?? '') {
            case 'nps':
                $resultado = 'NPS ' . $metrics['score'];
                $detalhe = sprintf('%s%% / %s%% / %s%%', $metrics['promoters_pct'], $metrics['passives_pct'], $metrics['detractors_pct']);
                break;
            case 'stars':
            case 'emoticon':
                $resultado = $metrics['average'] . ' / ' . $metrics['max'];
                break;
            case 'yesno':
                $resultado = $metrics['yes_pct'] . '% ' . __('Yes');
                break;
            case 'choice':
                $resultado = $metrics['top_option'] ?? '';
                $partes = [];
                foreach ($metrics['distribution'] as $d) {
                    $partes[] = $d['label'] . ': ' . $d['pct'] . '%';
                }
                $detalhe = implode(' | ', $partes);
                break;
            case 'text':
                $resultado = $metrics['total'] . ' ' . __('comentários', 'npsphere');
                break;
        }

        $sx->linha([
            $question['position'] . '. ' . $question['name'],
            $types[$question['type']] ?? $question['type'],
            SheetExport::num($metrics['answered'] ?? 0),
            $resultado,
            $detalhe,
        ]);
    }
};

// -----------------------------------------------------------------
// Respostas: uma linha por resposta
// -----------------------------------------------------------------
$respostas = static function () use ($sx, $survey, $surveys_id, $questions, $entidades): void {
    global $DB;

    $nps_question_ids = [];
    $nps_scale = 10;
    foreach ($questions as $q) {
        if ($q['type'] === 'nps') {
            $nps_question_ids[] = (int) $q['id'];
            $cfg = json_decode($q['configuration'] ?? '{}', true);
            if (is_array($cfg) && !empty($cfg['scale'])) {
                $nps_scale = (int) $cfg['scale'];
            }
        }
    }

    $is_anonymous = !empty($survey->fields['is_anonymous']);

    $header = [__('ID', 'npsphere'), __('Data', 'npsphere')];
    if (!$is_anonymous) {
        // Numa pesquisa anônima esses campos nem existem no banco -- e
        // exportar colunas vazias sugeriria que a identidade foi guardada.
        $header[] = __('Nome', 'npsphere');
        $header[] = __('E-mail', 'npsphere');
        $header[] = __('Telefone', 'npsphere');
    }
    $header[] = __('Nota', 'npsphere');
    $header[] = __('Classificação', 'npsphere');
    foreach ($questions as $q) {
        $header[] = $q['position'] . '. ' . $q['name'];
    }

    $sx->aba(__('Respostas', 'npsphere'));
    $sx->cabecalho($header, true);

    $labels = ['promoter' => __('Promotor', 'npsphere'), 'passive' => __('Neutro', 'npsphere'), 'detractor' => __('Detrator', 'npsphere')];

    $where_export = ['surveys_id' => $surveys_id];
    if (!empty($entidades)) {
        $where_export['entities_id'] = $entidades;
    }

    foreach ($DB->request([
        'FROM'  => Answer::getTable(),
        'WHERE' => $where_export,
        'ORDER' => 'date_creation DESC',
    ]) as $answer) {
        $nps = MetricsCalculator::getAnswerNps((int) $answer['id'], $nps_question_ids, $nps_scale);

        $line = [(int) $answer['id'], SheetExport::data($answer['date_creation'])];
        if (!$is_anonymous) {
            $line[] = $answer['respondent_name'] ?? '';
            $line[] = $answer['respondent_email'] ?? '';
            $line[] = $answer['respondent_phone'] ?? '';
        }
        $line[] = $nps ? SheetExport::num($nps['score']) : '';
        $line[] = $nps ? ($labels[$nps['classification']] ?? '') : '';

        // Valores por pergunta, na mesma ordem do cabeçalho. Notas saem
        // como número (somam e filtram na planilha); texto e opções, como
        // texto.
        $by_question = [];
        foreach ($DB->request([
            'FROM'  => AnswerValue::getTable(),
            'WHERE' => ['answers_id' => $answer['id']],
        ]) as $v) {
            $display = $v['text_value'];
            if ($display === null || $display === '') {
                $display = $v['option_value'];
            }
            if ($display === null || $display === '') {
                $display = $v['numeric_value'] !== null ? (int) $v['numeric_value'] : '';
            }
            $by_question[(int) $v['questions_id']] = $display;
        }
        foreach ($questions as $q) {
            $line[] = $by_question[(int) $q['id']] ?? '';
        }

        $sx->linha($line);
    }
};

if ($formato === 'csv') {
    $scope === 'overview' ? $visaoGeral() : $respostas();
    $sx->enviar('csv', 'npsphere-' . $scope . '-' . $surveys_id . '-' . date('Ymd-Hi'));
}

$visaoGeral();
$respostas();
$sx->enviar($formato, 'npsphere-' . __('resultados', 'npsphere') . '-' . $surveys_id . '-' . date('Ymd-Hi'));
