<?php

/**
 * Grava a opção clicada no e-mail (pesquisa externa, convite pessoal).
 *
 * POR QUE UM POST SEPARADO, chamado pelo navegador, e não gravar ao
 * abrir o link: filtros de segurança de e-mail (Defender, Proofpoint,
 * Mimecast, o próprio Gmail) ABREM os links das mensagens para
 * verificá-los, antes de a pessoa ler. Se abrir o link gravasse, cada
 * convite poderia virar uma resposta que ninguém deu. Esses filtros leem
 * a página; quem executa o script dela e faz este POST é o navegador de
 * quem clicou.
 *
 * Regras:
 *  - exige o token da pesquisa E o código do convite desta pesquisa;
 *  - pesquisa que pede identificação (nome, e-mail, telefone) não grava
 *    por aqui: a pessoa precisa preencher os dados no formulário;
 *  - um convite, uma resposta: clicar de novo troca a opção;
 *  - pesquisa com várias perguntas: grava a clicada e as demais ficam
 *    para o formulário (gravação parcial, completada depois).
 *
 * Liberado sem login no setup.php (Firewall::STRATEGY_NO_CHECK), como a
 * página pública. O token CSRF vem de ajax/public.token.php.
 */

use GlpiPlugin\Npsphere\Invite;
use GlpiPlugin\Npsphere\PublicAccess;
use GlpiPlugin\Npsphere\Question;
use GlpiPlugin\Npsphere\RespondentIdentity;
use GlpiPlugin\Npsphere\ResponseService;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$responder = static function (int $status, array $dados): void {
    http_response_code($status);
    echo json_encode($dados);
};

$resolved = PublicAccess::resolve($_POST['token'] ?? null);
$survey   = $resolved['survey'];

if ($survey === null) {
    $responder(403, ['success' => false, 'message' => (string) $resolved['error']]);
    return;
}

$convite = Invite::resolver($survey, $_POST['convite'] ?? null);
if ($convite === null) {
    $responder(400, ['success' => false, 'message' => __('Convite inválido.', 'npsphere')]);
    return;
}

if (RespondentIdentity::isRequiredFor($survey)) {
    $responder(200, ['success' => false, 'message' => __('Esta pesquisa pede identificação.', 'npsphere')]);
    return;
}

$surveys_id = $survey->getID();

// Mesma interpretação do link usada para pré-marcar o formulário: a
// pergunta precisa ser desta pesquisa e o valor, compatível com ela.
$clicado = Question::getPreenchimentoDoLink($surveys_id, [
    'pergunta' => $_POST['pergunta'] ?? null,
    'valor'    => $_POST['valor'] ?? null,
]);

if ($clicado === []) {
    $responder(400, ['success' => false, 'message' => __('Opção inválida.', 'npsphere')]);
    return;
}

$perguntas = Question::getActiveQuestions($surveys_id);
$unica     = count($perguntas) === 1;

// O que a pessoa já tinha respondido por este convite, com a opção
// clicada por cima: trocar uma nota não apaga as outras respostas.
$dados = $clicado + Invite::getRespostasGravadas((int) $convite['answers_id'], $surveys_id);

// Nome e e-mail vêm da lista de destinatários -- é a pessoa do convite.
// Em pesquisa anônima, ResponseService descarta a identificação.
$identidade = [
    'respondent_name'  => (string) $convite['name'],
    'respondent_email' => (string) $convite['email'],
    'respondent_phone' => '',
];

$resultado = ResponseService::submit($survey, $dados, time(), $identidade, [
    'answers_id' => (int) $convite['answers_id'],
    'parcial'    => !$unica,
]);

if (!$resultado['success']) {
    $responder(200, [
        'success' => false,
        'message' => implode(' ', $resultado['errors'] ?? []),
    ]);
    return;
}

Invite::vincularResposta((int) $convite['id'], (int) $resultado['answers_id']);

if ($unica) {
    PublicAccess::markAnswered($surveys_id);
}

// Texto da opção para a confirmação ("Sua resposta: 9").
$campo    = array_key_first($clicado);
$qid      = (int) substr((string) $campo, strlen('answer_'));
$pergunta = $perguntas[$qid] ?? [];
$rotulo   = Question::rotuloDaResposta($pergunta, $clicado[$campo]);

$responder(200, [
    'success'  => true,
    'unica'    => $unica,
    'pergunta' => (string) ($pergunta['name'] ?? ''),
    'rotulo'   => $rotulo,
]);
