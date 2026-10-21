<?php

/**
 * Envio de uma resposta de pesquisa (modo embutido, aba "Visualizar").
 *
 * Vive em ajax/ -- e não em front/ -- de propósito: nesta instalação,
 * POSTs para ajax/*.php (ex.: question.reorder.php) funcionam de forma
 * confiável, enquanto o mesmo POST para front/answer.form.php retornava
 * 403 consistentemente. Como o comportamento de front/ vs ajax/ difere
 * na prática, o envio embutido usa o caminho comprovadamente estável.
 *
 * front/answer.form.php continua existindo e funcionando para o acesso
 * direto/standalone (navegação de página cheia, sem JavaScript).
 *
 * Devolve sempre JSON: {success: bool, errors: string[]}
 */

use GlpiPlugin\Npsphere\ResponseService;
use GlpiPlugin\Npsphere\Survey;

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// Qualquer usuário autenticado pode responder (não exige o direito
// administrativo plugin_npsphere_survey, que é só para gerenciar
// pesquisas).
Session::checkLoginUser();

if (!\GlpiPlugin\Npsphere\MySurveysMenu::hasAnswerRight()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'errors'  => [__('Você não tem permissão para responder pesquisas.', 'npsphere')],
    ]);
    exit;
}

$surveys_id = (int) ($_POST['surveys_id'] ?? 0);

$survey = new Survey();
if (!$surveys_id || !$survey->getFromDB($surveys_id)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'errors' => [__('Pesquisa não encontrada.', 'npsphere')]]);
    exit;
}

$started_at = (int) ($_POST['_started_at'] ?? time());
$result = ResponseService::submit($survey, $_POST, $started_at);

echo json_encode([
    'success' => $result['success'],
    'errors'  => $result['errors'],
    // Repassa a marca de pré-visualização para a interface poder
    // avisar que nada foi gravado.
    'preview' => !empty($result['preview']),
]);
