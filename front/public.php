<?php

/**
 * Página pública de resposta a uma pesquisa (acesso por token, sem login).
 *
 * Este script é liberado para acesso não autenticado no setup.php, via
 * Firewall::STRATEGY_NO_CHECK -- mecanismo oficial do GLPI 11. Toda a
 * autorização é feita por PublicAccess::resolve(), a partir do token.
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Npsphere\Invite;
use GlpiPlugin\Npsphere\PublicAccess;
use GlpiPlugin\Npsphere\Question;
use GlpiPlugin\Npsphere\RespondentIdentity;
use GlpiPlugin\Npsphere\ResponseService;

// v0.65.0: limite de requisições por IP nas rotas sem login.
\GlpiPlugin\Npsphere\RateLimit::proteger($_SERVER['REQUEST_METHOD'] === 'POST' ? \GlpiPlugin\Npsphere\RateLimit::ENVIO : \GlpiPlugin\Npsphere\RateLimit::PAGINA, false);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$resolved = PublicAccess::resolve($_GET['token'] ?? $_POST['token'] ?? null);
$survey   = $resolved['survey'];

/**
 * Renderiza uma página autônoma (sem o menu do GLPI, já que o visitante
 * não está autenticado e não teria acesso a ele).
 */
function plugin_npsphere_public_page(string $template, array $context): void
{
    Html::nullHeader(__('Pesquisa de satisfação', 'npsphere'));
    TemplateRenderer::getInstance()->display($template, $context);
    Html::nullFooter();
}

if ($survey === null) {
    plugin_npsphere_public_page('@npsphere/public/message.html.twig', [
        'title'   => __('Não foi possível abrir a pesquisa', 'npsphere'),
        'message' => $resolved['error'],
        'icon'    => 'ti ti-lock',
    ]);
    return;
}

$surveys_id = $survey->getID();

// Convite pessoal (link do e-mail, &convite=...). Com ele a resposta é
// da pessoa da lista, e abrir o link de novo ALTERA a resposta -- por
// isso o bloqueio por sessão abaixo não vale para quem tem convite.
$convite = Invite::resolver($survey, $_GET['convite'] ?? $_POST['convite'] ?? null);
$convite_token = $convite !== null ? (string) $convite['token'] : '';
$ja_respondeu  = $convite !== null && (int) $convite['answers_id'] > 0;

// Já respondeu nesta sessão e a pesquisa não permite múltiplas respostas
if (
    $convite === null
    && empty($survey->fields['allow_multiple_answers'])
    && PublicAccess::hasAnsweredInSession($surveys_id)
) {
    plugin_npsphere_public_page('@npsphere/public/message.html.twig', [
        'title'   => __('Você já respondeu', 'npsphere'),
        'message' => __('Sua resposta para esta pesquisa já foi registrada. Obrigado!', 'npsphere'),
        'icon'    => 'ti ti-circle-check',
    ]);
    return;
}

$requires_identity = RespondentIdentity::isRequiredFor($survey);

if (isset($_POST['submit_answer'])) {
    $started_at = (int) ($_POST['_started_at'] ?? time());

    $identity = [];
    $identity_errors = [];
    if ($requires_identity) {
        $check = RespondentIdentity::validate($_POST);
        $identity = $check['data'];
        $identity_errors = $check['errors'];

        // Barreira real contra responder duas vezes: o controle por
        // sessão não resiste a uma aba anônima ou outro navegador, então
        // aqui conferimos a identidade contra o que já está no banco.
        if (
            $identity_errors === []
            && $convite === null
            && empty($survey->fields['allow_multiple_answers'])
            && PublicAccess::hasAnsweredByIdentity(
                $surveys_id,
                $identity['respondent_email'] ?? '',
                $identity['respondent_phone'] ?? ''
            )
        ) {
            $identity_errors[] = __('Já existe uma resposta registrada com este e-mail ou telefone. Esta pesquisa aceita apenas uma resposta por pessoa.', 'npsphere');
        }
    }

    // Convite sem pedido de identificação: nome e e-mail da lista.
    if (!$requires_identity && $convite !== null) {
        $identity = [
            'respondent_name'  => (string) $convite['name'],
            'respondent_email' => (string) $convite['email'],
            'respondent_phone' => '',
        ];
    }

    $result = $identity_errors === []
        ? ResponseService::submit(
            $survey,
            $_POST,
            $started_at,
            $identity,
            $convite !== null ? ['answers_id' => (int) $convite['answers_id']] : []
        )
        : ['success' => false, 'errors' => $identity_errors, 'answers_id' => null];

    if ($result['success']) {
        if ($convite !== null) {
            Invite::vincularResposta((int) $convite['id'], (int) $result['answers_id']);
        }
        PublicAccess::markAnswered($surveys_id);
        plugin_npsphere_public_page('@npsphere/public/message.html.twig', [
            'title'   => __('Obrigado pela sua avaliação!', 'npsphere'),
            'message' => __('Sua resposta foi registrada com sucesso.', 'npsphere'),
            'icon'    => 'ti ti-circle-check',
            'success' => true,
        ]);
        return;
    }

    plugin_npsphere_public_page('@npsphere/public/form.html.twig', [
        'survey'            => $survey,
        'token'             => $survey->fields['public_token'],
        'fields'            => Question::renderAnswerFields($surveys_id, $_POST),
        'errors'            => $result['errors'],
        'started_at'        => time(),
        'requires_identity' => $requires_identity,
        'identity_check_url' => Plugin::getWebDir('npsphere', true) . '/ajax/public.identity.check.php',
    'token_url'          => Plugin::getWebDir('npsphere', true) . '/ajax/public.token.php',
        'token_url'          => Plugin::getWebDir('npsphere', true) . '/ajax/public.token.php',
        'countries'         => RespondentIdentity::getCountriesForForm(),
        'identity'          => $_POST,
        'convite'           => $convite_token,
    ]);
    return;
}

// Respostas para pré-marcar: o que a pessoa já respondeu por este
// convite, com a opção clicada no e-mail por cima.
$clicado = Question::getPreenchimentoDoLink($surveys_id, $_GET);
$preenchido = $clicado + ($ja_respondeu
    ? Invite::getRespostasGravadas((int) $convite['answers_id'], $surveys_id)
    : []);

// Opção clicada no e-mail, com convite e sem pedido de identificação:
// a página grava sozinha (ver ajax/public.oneclick.php). O GET em si não
// grava nada -- é o script da página, no navegador da pessoa, que faz o
// POST. Filtros de e-mail que só abrem o link não disparam a gravação.
$um_clique = null;
if ($convite !== null && $clicado !== [] && !$requires_identity) {
    $um_clique = [
        'url'      => Plugin::getWebDir('npsphere', true) . '/ajax/public.oneclick.php',
        'pergunta' => (int) ($_GET['pergunta'] ?? 0),
        'valor'    => (string) ($_GET['valor'] ?? ''),
    ];
}

// Identificação exigida: nome e e-mail já vêm da lista do convite.
$identity_inicial = [];
if ($convite !== null) {
    $identity_inicial = [
        'respondent_name'  => (string) $convite['name'],
        'respondent_email' => (string) $convite['email'],
    ];
}

plugin_npsphere_public_page('@npsphere/public/form.html.twig', [
    'survey'            => $survey,
    'token'             => $survey->fields['public_token'],
    'fields'            => Question::renderAnswerFields($surveys_id, $preenchido),
    'errors'            => [],
    'started_at'        => time(),
    'requires_identity' => $requires_identity,
    'identity_check_url' => Plugin::getWebDir('npsphere', true) . '/ajax/public.identity.check.php',
    'token_url'          => Plugin::getWebDir('npsphere', true) . '/ajax/public.token.php',
    'countries'         => RespondentIdentity::getCountriesForForm(),
    'identity'          => $identity_inicial,
    'convite'           => $convite_token,
    'um_clique'         => $um_clique,
    'ja_respondeu'      => $ja_respondeu,
]);
