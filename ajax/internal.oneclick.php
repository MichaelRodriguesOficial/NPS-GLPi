<?php

/**
 * Grava a nota clicada no e-mail da pesquisa INTERNA, sem login.
 *
 * Chamado pelo script de front/internal.answer.php, no navegador de quem
 * clicou -- abrir o link não grava (filtros de e-mail abrem os links
 * para verificá-los). Autorizado pela chave assinada da pessoa
 * (Target::resolverPorChave); a resposta é gravada em nome dela. Sem
 * "múltiplas respostas", clicar de novo troca a nota.
 *
 * Liberado sem login no setup.php (Firewall::STRATEGY_NO_CHECK).
 */

use GlpiPlugin\Npsphere\Invite;
use GlpiPlugin\Npsphere\Question;
use GlpiPlugin\Npsphere\ResponseService;
use GlpiPlugin\Npsphere\Target;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$responder = static function (int $status, array $dados): void {
    http_response_code($status);
    echo json_encode($dados);
};

$ctx = Target::resolverPorChave($_POST);
if ($ctx['erro'] !== null) {
    $responder(403, ['success' => false, 'message' => $ctx['erro']]);
    return;
}

$survey   = $ctx['survey'];
$users_id = $ctx['users_id'];
$sid      = $survey->getID();

$clicado = Question::getPreenchimentoDoLink($sid, [
    'pergunta' => $_POST['pergunta'] ?? null,
    'valor'    => $_POST['valor'] ?? null,
]);
if ($clicado === []) {
    $responder(400, ['success' => false, 'message' => __('Opção inválida.', 'npsphere')]);
    return;
}

$perguntas = Question::getActiveQuestions($sid);
$unica     = count($perguntas) === 1;
$existente = empty($survey->fields['allow_multiple_answers'])
    ? ResponseService::getRespostaDoUsuario($sid, $users_id)
    : 0;

$dados = $clicado + Invite::getRespostasGravadas($existente, $sid);

$resultado = ResponseService::submit($survey, $dados, time(), [], [
    'answers_id' => $existente,
    'parcial'    => !$unica,
    'users_id'   => $users_id,
]);

if (!$resultado['success']) {
    $responder(200, ['success' => false, 'message' => implode(' ', $resultado['errors'] ?? [])]);
    return;
}

$campo = array_key_first($clicado);
$qid   = (int) substr((string) $campo, strlen('answer_'));

$responder(200, [
    'success'    => true,
    'unica'      => $unica,
    'answers_id' => (int) $resultado['answers_id'],
    'pergunta'   => (string) ($perguntas[$qid]['name'] ?? ''),
    'rotulo'     => Question::rotuloDaResposta($perguntas[$qid] ?? [], $clicado[$campo]),
]);
