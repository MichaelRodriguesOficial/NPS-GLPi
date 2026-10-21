<?php

/**
 * Grava a opção clicada no e-mail -- pesquisa INTERNA e de CHAMADO.
 *
 * Mesmo princípio de ajax/public.oneclick.php (pesquisa externa): quem
 * grava é o script da página, no navegador de quem clicou, com este
 * POST. Abrir o link do e-mail não grava nada, então os filtros de
 * segurança que abrem os links para verificá-los não geram respostas.
 *
 * Aqui a pessoa é o usuário LOGADO no GLPI (script com login exigido,
 * padrão do GLPI para ajax/), então não há código de convite:
 *
 *  - chamado: só o requerente; um chamado, uma avaliação -- clicar de
 *    novo troca a nota da mesma avaliação (e da satisfação do GLPI);
 *  - interna: só quem é do público-alvo; sem "múltiplas respostas",
 *    clicar de novo troca a resposta em vez de criar outra.
 *
 * Pesquisa com várias perguntas: grava a clicada (resposta parcial) e
 * devolve o id, para o formulário completar a MESMA resposta.
 */

use GlpiPlugin\Npsphere\Invite;
use GlpiPlugin\Npsphere\Question;
use GlpiPlugin\Npsphere\ResponseService;
use GlpiPlugin\Npsphere\Survey;
use GlpiPlugin\Npsphere\Target;
use GlpiPlugin\Npsphere\TicketLink;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

Session::checkLoginUser();

$responder = static function (int $status, array $dados): void {
    http_response_code($status);
    echo json_encode($dados);
};

$falhar = static function (string $mensagem, int $status = 200) use ($responder): void {
    $responder($status, ['success' => false, 'message' => $mensagem]);
};

$surveys_id = (int) ($_POST['surveys_id'] ?? 0);
$tickets_id = (int) ($_POST['tickets_id'] ?? 0);
$users_id   = (int) Session::getLoginUserID();

$survey = null;
$existente = 0;

if ($tickets_id > 0) {
    // --------------------------------------------------- chamado
    $ticket = new Ticket();
    if (!$ticket->getFromDB($tickets_id)) {
        $falhar(__('Chamado não encontrado.', 'npsphere'), 404);
        return;
    }
    if (!TicketLink::podeResponder($ticket)) {
        $falhar(__('Apenas o requerente do chamado pode responder esta pesquisa.', 'npsphere'), 403);
        return;
    }

    // A mesma resolução da página do chamado: a pesquisa da URL, se
    // houver, senão a da entidade do chamado.
    $survey = TicketLink::resolveSurvey((int) $ticket->fields['entities_id'], $surveys_id ?: null);
    if ($survey === null) {
        $falhar(__('Não há pesquisa de satisfação configurada para este chamado.', 'npsphere'));
        return;
    }

    $existente = ResponseService::getRespostaDoChamado($tickets_id);
} else {
    // --------------------------------------------------- interna
    $survey = new Survey();
    if ($surveys_id <= 0 || !$survey->getFromDB($surveys_id)) {
        $falhar(__('Pesquisa não encontrada.', 'npsphere'), 404);
        return;
    }
    if (($survey->fields['type'] ?? '') !== Survey::TYPE_INTERNAL) {
        $falhar(__('Opção inválida.', 'npsphere'), 400);
        return;
    }
    if (!\GlpiPlugin\Npsphere\MySurveysMenu::hasAnswerRight() || !Target::isUserTargeted($survey)) {
        $falhar(__('Esta pesquisa não está disponível para você.', 'npsphere'), 403);
        return;
    }

    if (empty($survey->fields['allow_multiple_answers'])) {
        $existente = ResponseService::getRespostaDoUsuario($survey->getID(), $users_id);
    }
}

$sid = $survey->getID();

$clicado = Question::getPreenchimentoDoLink($sid, [
    'pergunta' => $_POST['pergunta'] ?? null,
    'valor'    => $_POST['valor'] ?? null,
]);
if ($clicado === []) {
    $falhar(__('Opção inválida.', 'npsphere'), 400);
    return;
}

$perguntas = Question::getActiveQuestions($sid);
$unica     = count($perguntas) === 1;

// O que já estava respondido, com a opção clicada por cima.
$dados = $clicado + Invite::getRespostasGravadas($existente, $sid);
if ($tickets_id > 0) {
    $dados['tickets_id'] = $tickets_id;
}

$resultado = ResponseService::submit($survey, $dados, time(), [], [
    'answers_id' => $existente,
    'parcial'    => !$unica,
]);

if (!$resultado['success']) {
    $falhar(implode(' ', $resultado['errors'] ?? []));
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
