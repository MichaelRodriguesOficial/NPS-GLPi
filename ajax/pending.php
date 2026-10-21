<?php

/**
 * Pesquisas obrigatórias pendentes para o usuário autenticado.
 *
 * Consultado pelo JavaScript carregado em todas as páginas do GLPI,
 * logo após o login. Responde o mínimo necessário -- nome, descrição,
 * link e se bloqueia -- sem expor nada sobre outros usuários.
 */

use GlpiPlugin\Npsphere\ResponseService;
use GlpiPlugin\Npsphere\Survey;
use GlpiPlugin\Npsphere\Target;

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (!Session::getLoginUserID()) {
    echo json_encode(['surveys' => []]);
    exit;
}

$answer_url = Plugin::getWebDir('npsphere', true) . '/front/answer.form.php';

$out = [];
foreach (Target::getPendingMandatory() as $item) {
    $survey = $item['survey'];
    $out[] = [
        'id'          => $survey->getID(),
        'name'        => $survey->fields['name'],
        'description' => $survey->fields['description'] ?? '',
        'blocking'    => (bool) $item['blocking'],
        'deadline'    => $survey->fields['date_end'] ?? null,
        // v0.65.0: já no formato de data do usuário (Preferências).
        'deadline_txt' => !empty($survey->fields['date_end'])
            ? Html::convDate(substr((string) $survey->fields['date_end'], 0, 10))
            : '',
        'url'         => $answer_url . '?surveys_id=' . $survey->getID(),
    ];
}

// Diagnóstico: com ?debug=1 a resposta explica POR QUE cada pesquisa
// interna foi ou não incluída. Ajuda a distinguir "o plugin quebrou" de
// "a pesquisa realmente não se aplica a este usuário" -- as duas coisas
// se parecem na tela (nada acontece), mas têm causas bem diferentes.
if (!empty($_GET['debug'])) {
    global $DB;

    $checks = [];
    foreach ($DB->request([
        'FROM'  => Survey::getTable(),
        'WHERE' => ['type' => Survey::TYPE_INTERNAL],
    ]) as $row) {
        $s = new Survey();
        $s->fields = $row;

        $eligibility = ResponseService::canRespond($s);

        $checks[] = [
            'id'             => (int) $row['id'],
            'nome'           => $row['name'],
            'ativa'          => (bool) $row['is_active'],
            'obrigatoria'    => (bool) ($row['is_mandatory'] ?? 0),
            'no_publico'     => Target::isUserTargeted($s),
            'ja_respondeu'   => Target::hasAnswered($s, (int) Session::getLoginUserID()),
            'anonima'        => (bool) ($row['is_anonymous'] ?? 0),
            'concluida'      => \GlpiPlugin\Npsphere\Completion::hasCompleted((int) $row['id']),
            'ultimo_dia'     => Target::isLastDay($s),
            'pode_responder' => $eligibility['can'],
            'motivo'         => $eligibility['reason'],
        ];
    }

    echo json_encode([
        'usuario' => (int) Session::getLoginUserID(),
        'hoje'    => date('Y-m-d'),
        'surveys' => $out,
        'analise' => $checks,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['surveys' => $out]);
