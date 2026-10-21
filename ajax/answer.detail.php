<?php

/**
 * Respostas de UMA submissão (o que determinada pessoa respondeu),
 * exibidas ao clicar em "Ver" na lista de respondentes.
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Npsphere\Answer;
use GlpiPlugin\Npsphere\AnswerValue;
use GlpiPlugin\Npsphere\MetricsCalculator;
use GlpiPlugin\Npsphere\Question;
use GlpiPlugin\Npsphere\QuestionTypeManager;
use GlpiPlugin\Npsphere\Survey;

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

Session::checkRight(Survey::$rightname, READ);

global $DB;

$answers_id = (int) ($_GET['answers_id'] ?? 0);

$answer = new Answer();
if ($answers_id <= 0 || !$answer->getFromDB($answers_id)) {
    http_response_code(404);
    echo '<div class="alert alert-warning">' . __('Resposta não encontrada.', 'npsphere') . '</div>';
    return;
}

$survey = new Survey();
// A pesquisa é a da RESPOSTA pedida: sem esta checagem, bastaria
// informar o ID de uma resposta alheia para ler o que alguém respondeu.
\GlpiPlugin\Npsphere\Survey::exigirGestao((int) $answer->fields['surveys_id']);

if (!$survey->getFromDB((int) $answer->fields['surveys_id'])) {
    http_response_code(404);
    return;
}

// Perguntas da pesquisa, indexadas por id, para casar com os valores
$questions = [];
foreach ($DB->request([
    'FROM'  => Question::getTable(),
    'WHERE' => ['surveys_id' => $survey->getID()],
    'ORDER' => 'position ASC',
]) as $row) {
    $questions[(int) $row['id']] = $row;
}

$types = QuestionTypeManager::getTypesForDropdown();

$items = [];
foreach ($DB->request([
    'FROM'  => AnswerValue::getTable(),
    'WHERE' => ['answers_id' => $answers_id],
]) as $value) {
    $qid = (int) $value['questions_id'];
    if (!isset($questions[$qid])) {
        continue; // pergunta excluída depois da resposta
    }
    $question = $questions[$qid];

    // Valor "cru" da resposta, no formato que o widget do tipo espera.
    // O numeric_value é decimal no banco (vem como "3.00"), mas as
    // opções dos widgets usam inteiros -- daí a normalização.
    $raw = null;
    if ($value['numeric_value'] !== null && $value['numeric_value'] !== '') {
        $raw = (string) (int) $value['numeric_value'];
    } elseif (!empty($value['option_value'])) {
        $raw = $value['option_value'];
    } elseif (!empty($value['text_value'])) {
        $raw = $value['text_value'];
    } else {
        $raw = (string) $value['value'];
    }

    // Múltiplas opções são gravadas separadas por " | "
    if ($question['type'] === 'multiple_choice' && $raw !== null) {
        $raw = array_filter(array_map('trim', explode('|', (string) $raw)));
    }

    // Renderiza a pergunta com o MESMO widget visual do formulário de
    // resposta (estrelas, emoticons, escala de NPS...), já marcado com
    // o que a pessoa escolheu -- em vez de exibir apenas "3.00", que
    // não diz nada sobre a escala nem sobre o significado da nota.
    $widget = null;
    $type_class = QuestionTypeManager::get($question['type']);
    if ($type_class !== null) {
        $configuration = json_decode($question['configuration'] ?? '{}', true);
        if (!is_array($configuration)) {
            $configuration = [];
        }
        $configuration = array_merge($type_class::getDefaultConfiguration(), $configuration);
        $widget = $type_class::renderAnswerField('detail_' . $answers_id . '_' . $qid, $configuration, $raw);
    }

    $items[] = [
        'position'   => (int) $question['position'],
        'name'       => $question['name'],
        'type'       => $question['type'],
        'type_label' => $types[$question['type']] ?? $question['type'],
        'value'      => is_array($raw) ? implode(', ', $raw) : (string) $raw,
        'widget'     => $widget,
    ];
}

usort($items, static fn($a, $b) => $a['position'] <=> $b['position']);

// Nota que esta pessoa deu (média das perguntas de NPS)
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

TemplateRenderer::getInstance()->display('@npsphere/answer_detail.html.twig', [
    'answer' => $answer,
    'survey' => $survey,
    'items'  => $items,
    'nps'    => MetricsCalculator::getAnswerNps($answers_id, $nps_question_ids, $nps_scale),
]);
