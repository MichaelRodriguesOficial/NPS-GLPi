<?php

/**
 * Avaliação do chamado pelo LINK DO E-MAIL, sem entrar no GLPI.
 *
 * O convite do chamado (SurveyMailer::enviar) aponta para cá com uma
 * chave assinada do par chamado + pesquisa (TicketLink::chave). Com a
 * chave certa, o requerente responde direto -- clicar numa nota já
 * registra (ajax/ticket.oneclick.php) e mostra a confirmação.
 *
 * Sem a chave, ou com a chave de outro chamado, nada abre: trocar o
 * número do chamado na URL não permite avaliar o chamado de outra
 * pessoa.
 *
 * A página dentro do GLPI (front/ticket.php, com login) continua sendo a
 * da pesquisa externa configurada na entidade e a que aparece embutida
 * no próprio chamado.
 *
 * Liberado sem login no setup.php (Firewall::STRATEGY_NO_CHECK).
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Npsphere\Invite;
use GlpiPlugin\Npsphere\Question;
use GlpiPlugin\Npsphere\ResponseService;
use GlpiPlugin\Npsphere\TicketLink;

// v0.65.0: limite de requisições por IP nas rotas sem login.
\GlpiPlugin\Npsphere\RateLimit::proteger($_SERVER['REQUEST_METHOD'] === 'POST' ? \GlpiPlugin\Npsphere\RateLimit::ENVIO : \GlpiPlugin\Npsphere\RateLimit::PAGINA, false);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$exibir = static function (string $template, array $contexto): void {
    Html::nullHeader(__('Pesquisa de satisfação', 'npsphere'));
    // .npsphere-public-page: mesma moldura da página pública -- e é o que
    // faz o survey-builder.js não tentar renovar o token CSRF por um
    // endpoint que exige login.
    echo '<div class="npsphere-public-page npsphere-ticket-publica">';
    TemplateRenderer::getInstance()->display($template, $contexto);
    echo '</div>';
    Html::nullFooter();
};

$params = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$ctx = TicketLink::resolverPorChave($params);

if ($ctx['erro'] !== null) {
    $exibir('@npsphere/answer/blocked.html.twig', [
        'survey' => null,
        'reason' => $ctx['erro'],
    ]);
    return;
}

$ticket     = $ctx['ticket'];
$survey     = $ctx['survey'];
$tickets_id = $ticket->getID();
$sid        = $survey->getID();
$chave      = (string) $params['chave'];

// A resposta é gravada em nome do requerente: é ele quem recebeu o
// convite e é a avaliação dele que o chamado espera.
$requerente = TicketLink::getRequerenteId($tickets_id);
$existente  = ResponseService::getRespostaDoChamado($tickets_id);

$base = Plugin::getWebDir('npsphere', true);

// Token CSRF novo, pedido pelo formulário imediatamente antes de enviar
// (sem login, o token embutido na página era recusado no envio).
$token_url = $base . '/ajax/ticket.token.php?tickets_id=' . $tickets_id
    . '&surveys_id=' . $sid . '&chave=' . rawurlencode($chave);

if (isset($_POST['submit_answer'])) {
    $post = $_POST;
    $post['tickets_id'] = $tickets_id;

    $result = ResponseService::submit(
        $survey,
        $post,
        (int) ($_POST['_started_at'] ?? time()),
        [],
        ['answers_id' => $existente, 'users_id' => $requerente]
    );

    if ($result['success']) {
        $exibir('@npsphere/answer/thankyou.html.twig', ['survey' => $survey]);
        return;
    }

    $exibir('@npsphere/answer/form.html.twig', [
        'survey'     => $survey,
        'fields'     => Question::renderAnswerFields($sid, $_POST),
        'errors'     => $result['errors'],
        'started_at' => time(),
        'tickets_id' => $tickets_id,
        'chave'      => $chave,
        'token_url'  => $token_url,
        'js_url'     => $base . '/js/survey-builder.js',
        'css_url'    => $base . '/css/npsphere.css',
    ]);
    return;
}

// Opção clicada no e-mail (&pergunta=...&valor=...).
$clicado = Question::getPreenchimentoDoLink($sid, $_GET);

// Já avaliado e sem clique: só o agradecimento, como na página do GLPI.
if ($existente > 0 && $clicado === []) {
    $exibir('@npsphere/answer/thankyou.html.twig', [
        'survey'        => $survey,
        'ja_respondida' => true,
    ]);
    return;
}

// O GET não grava nada (filtros de e-mail abrem os links para
// verificá-los). Quem grava é o script da página, com um POST para
// ajax/ticket.oneclick.php -- e um novo clique troca a nota da mesma
// avaliação.
$um_clique = null;
if ($clicado !== []) {
    $assinatura = 'tickets_id=' . $tickets_id . '&surveys_id=' . $sid . '&chave=' . rawurlencode($chave);
    $um_clique = [
        'url'        => $base . '/ajax/ticket.oneclick.php',
        'csrf_url'   => $base . '/ajax/ticket.token.php?' . $assinatura,
        'surveys_id' => $sid,
        'tickets_id' => $tickets_id,
        'chave'      => $chave,
        'pergunta'   => (int) ($_GET['pergunta'] ?? 0),
        'valor'      => (string) ($_GET['valor'] ?? ''),
    ];
}

$exibir('@npsphere/answer/form.html.twig', [
    'survey'     => $survey,
    'fields'     => Question::renderAnswerFields(
        $sid,
        $clicado + ($existente > 0 ? Invite::getRespostasGravadas($existente, $sid) : [])
    ),
    'errors'     => [],
    'started_at' => time(),
    'tickets_id' => $tickets_id,
    'chave'      => $chave,
    'token_url'  => $token_url,
    'um_clique'  => $um_clique,
    'js_url'     => $base . '/js/survey-builder.js',
    'css_url'    => $base . '/css/npsphere.css',
]);
