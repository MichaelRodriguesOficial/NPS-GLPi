<?php

/**
 * Exportação dos resultados de uma pesquisa em CSV (seção 32 do escopo).
 *
 * Dois conteúdos, conforme o parâmetro "scope":
 *  - "answers"  : uma linha por resposta, com identificação e nota;
 *  - "overview" : os indicadores consolidados e as métricas por pergunta.
 *
 * O separador segue a preferência do próprio usuário no GLPI
 * ($_SESSION['glpicsv_delimiter']), para o arquivo abrir corretamente
 * no Excel configurado no idioma dele -- é o mesmo valor que o GLPI usa
 * nas suas próprias exportações.
 */

use GlpiPlugin\Npsphere\MetricsCalculator;
use GlpiPlugin\Npsphere\Question;
use GlpiPlugin\Npsphere\QuestionTypeManager;
use GlpiPlugin\Npsphere\Survey;

Session::checkRight(Survey::$rightname, READ);

global $DB;

$surveys_id = (int) ($_GET['surveys_id'] ?? 0);

// Só quem criou a pesquisa (ou o administrador geral) a gerencia.
\GlpiPlugin\Npsphere\Survey::exigirGestao($surveys_id);
$scope      = $_GET['scope'] ?? 'answers';

$survey = new Survey();
if ($surveys_id <= 0 || !$survey->getFromDB($surveys_id)) {
    http_response_code(404);
    exit;
}

$delimiter = $_SESSION['glpicsv_delimiter'] ?? ';';

// Filtro de entidades vindo da tela: a exportação reproduz o recorte
// que está sendo visto. Exportar a base inteira enquanto a tela mostra
// um subconjunto seria surpreendente -- e difícil de notar, porque o
// arquivo só é conferido depois.
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

$filename = 'npsphere-' . $scope . '-' . $surveys_id . '-' . date('Ymd-Hi') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$out = fopen('php://output', 'w');
// BOM UTF-8: sem isso o Excel no Windows exibe acentuação quebrada.
fwrite($out, "\xEF\xBB\xBF");

if ($scope === 'overview') {
    $summary = MetricsCalculator::getSummary($surveys_id, $questions, $entidades);

    fputcsv($out, [__('Pesquisa', 'npsphere'), $survey->fields['name']], $delimiter);
    fputcsv($out, [__('Total de respostas', 'npsphere'), $summary['total_answers']], $delimiter);
    if ($summary['nps']) {
        fputcsv($out, [__('NPS', 'npsphere'), $summary['nps']['score']], $delimiter);
        fputcsv($out, [__('Nota média', 'npsphere'), $summary['nps']['average']], $delimiter);
        fputcsv($out, [__('Promotores (%)', 'npsphere'), $summary['nps']['promoters_pct']], $delimiter);
        fputcsv($out, [__('Neutros (%)', 'npsphere'), $summary['nps']['passives_pct']], $delimiter);
        fputcsv($out, [__('Detratores (%)', 'npsphere'), $summary['nps']['detractors_pct']], $delimiter);
    }
    fputcsv($out, [], $delimiter);

    $types = QuestionTypeManager::getTypesForDropdown();
    fputcsv($out, [
        __('Pergunta', 'npsphere'),
        __('Tipo', 'npsphere'),
        __('Responderam', 'npsphere'),
        __('Resultado', 'npsphere'),
        __('Detalhe', 'npsphere'),
    ], $delimiter);

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
                $resultado = $metrics['yes_pct'] . '% ' . __('Sim');
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

        fputcsv($out, [
            $question['position'] . '. ' . $question['name'],
            $types[$question['type']] ?? $question['type'],
            $metrics['answered'] ?? 0,
            $resultado,
            $detalhe,
        ], $delimiter);
    }

    fclose($out);
    exit;
}

// -----------------------------------------------------------------
// scope = "answers": uma linha por resposta
// -----------------------------------------------------------------
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
fputcsv($out, $header, $delimiter);

$labels = ['promoter' => __('Promotor', 'npsphere'), 'passive' => __('Neutro', 'npsphere'), 'detractor' => __('Detrator', 'npsphere')];

$where_export = ['surveys_id' => $surveys_id];
if (!empty($entidades)) {
    $where_export['entities_id'] = $entidades;
}

foreach ($DB->request([
    'FROM'  => \GlpiPlugin\Npsphere\Answer::getTable(),
    'WHERE' => $where_export,
    'ORDER' => 'date_creation DESC',
]) as $answer) {
    $nps = MetricsCalculator::getAnswerNps((int) $answer['id'], $nps_question_ids, $nps_scale);

    $line = [$answer['id'], $answer['date_creation']];
    if (!$is_anonymous) {
        $line[] = $answer['respondent_name'] ?? '';
        $line[] = $answer['respondent_email'] ?? '';
        $line[] = $answer['respondent_phone'] ?? '';
    }
    $line[] = $nps ? $nps['score'] : '';
    $line[] = $nps ? ($labels[$nps['classification']] ?? '') : '';

    // Valores por pergunta, na mesma ordem do cabeçalho
    $by_question = [];
    foreach ($DB->request([
        'FROM'  => \GlpiPlugin\Npsphere\AnswerValue::getTable(),
        'WHERE' => ['answers_id' => $answer['id']],
    ]) as $v) {
        $display = $v['text_value'];
        if ($display === null || $display === '') {
            $display = $v['option_value'];
        }
        if ($display === null || $display === '') {
            $display = $v['numeric_value'] !== null ? (string) (int) $v['numeric_value'] : '';
        }
        $by_question[(int) $v['questions_id']] = $display;
    }
    foreach ($questions as $q) {
        $line[] = $by_question[(int) $q['id']] ?? '';
    }

    fputcsv($out, $line, $delimiter);
}

fclose($out);
