<?php

/**
 * Pesquisa INTERNA respondida pelo link do e-mail, sem entrar no GLPI.
 *
 * O convite (SurveyMailer::enviarConvites) aponta para cá com uma chave
 * assinada de "esta pessoa, esta pesquisa" (Target::chave). Com a chave
 * certa, a pessoa responde direto: clicar numa nota já registra
 * (ajax/internal.oneclick.php) e mostra a confirmação. A resposta é
 * gravada em nome dela -- some dos pendentes e do aviso ao entrar no
 * GLPI, como se tivesse respondido por lá.
 *
 * Sem a chave, ou com a chave de outra pessoa, nada abre. Quem saiu do
 * público-alvo depois do envio também não responde por um link antigo.
 *
 * Dentro do GLPI nada muda: "Minhas pesquisas" e o aviso pós-login
 * continuam usando front/answer.form.php, com login.
 *
 * Liberado sem login no setup.php (Firewall::STRATEGY_NO_CHECK).
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Npsphere\Invite;
use GlpiPlugin\Npsphere\Question;
use GlpiPlugin\Npsphere\ResponseService;
use GlpiPlugin\Npsphere\Target;

// v0.65.0: limite de requisições por IP nas rotas sem login.
\GlpiPlugin\Npsphere\RateLimit::proteger($_SERVER['REQUEST_METHOD'] === 'POST' ? \GlpiPlugin\Npsphere\RateLimit::ENVIO : \GlpiPlugin\Npsphere\RateLimit::PAGINA, false);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$exibir = static function (string $template, array $contexto): void {
    Html::nullHeader(__('Pesquisa de satisfação', 'npsphere'));
    // .npsphere-public-page: mesma moldura da página pública -- e é o que
    // faz o survey-builder.js não tentar renovar o token CSRF por um
    // endpoint que exige login.
    echo '<div class="npsphere-public-page npsphere-interna-publica">';
    TemplateRenderer::getInstance()->display($template, $contexto);
    echo '</div>';
    Html::nullFooter();
};

$params = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$ctx = Target::resolverPorChave($params);

if ($ctx['erro'] !== null) {
    $exibir('@npsphere/answer/blocked.html.twig', [
        'survey' => null,
        'reason' => $ctx['erro'],
    ]);
    return;
}

$survey   = $ctx['survey'];
$users_id = $ctx['users_id'];
$sid      = $survey->getID();
$chave    = (string) $params['chave'];
$base     = Plugin::getWebDir('npsphere', true);

// Sem "múltiplas respostas": a resposta desta pessoa é UMA -- um novo
// clique ou um novo envio troca a mesma.
$existente = empty($survey->fields['allow_multiple_answers'])
    ? ResponseService::getRespostaDoUsuario($sid, $users_id)
    : 0;

// Com "múltiplas respostas", o formulário pode completar a resposta que
// o clique acabou de gravar (o id volta num campo oculto) -- desde que
// seja mesmo desta pessoa.
$pedida = (int) ($_POST['answers_id'] ?? 0);
if ($pedida > 0 && $pedida === ResponseService::getRespostaDoUsuario($sid, $users_id)) {
    $existente = $pedida;
}

$contexto_form = [
    'survey'     => $survey,
    // Token CSRF novo, pedido pelo formulário imediatamente antes de
    // enviar (sem login, o token embutido na página era recusado).
    'token_url'  => $base . '/ajax/internal.token.php?surveys_id=' . $sid
        . '&usuario=' . $users_id . '&chave=' . rawurlencode($chave),
    'started_at' => time(),
    'chave'      => $chave,
    'usuario'    => $users_id,
    'js_url'     => $base . '/js/survey-builder.js',
    'css_url'    => $base . '/css/npsphere.css',
];

if (isset($_POST['submit_answer'])) {
    $result = ResponseService::submit(
        $survey,
        $_POST,
        (int) ($_POST['_started_at'] ?? time()),
        [],
        ['answers_id' => $existente, 'users_id' => $users_id]
    );

    if ($result['success']) {
        $exibir('@npsphere/answer/thankyou.html.twig', ['survey' => $survey]);
        return;
    }

    $exibir('@npsphere/answer/form.html.twig', $contexto_form + [
        'fields' => Question::renderAnswerFields($sid, $_POST),
        'errors' => $result['errors'],
    ]);
    return;
}

// Opção clicada no e-mail (&pergunta=...&valor=...).
$clicado = Question::getPreenchimentoDoLink($sid, $_GET);

// Já respondeu e abriu sem clicar numa opção: só o agradecimento.
if ($existente > 0 && $clicado === []) {
    $exibir('@npsphere/answer/thankyou.html.twig', [
        'survey'        => $survey,
        'ja_respondida' => true,
    ]);
    return;
}

// O GET não grava nada (filtros de e-mail abrem os links para
// verificá-los). Quem grava é o script da página, com um POST para
// ajax/internal.oneclick.php.
$um_clique = null;
if ($clicado !== []) {
    $assinatura = 'surveys_id=' . $sid . '&usuario=' . $users_id . '&chave=' . rawurlencode($chave);
    $um_clique = [
        'url'        => $base . '/ajax/internal.oneclick.php',
        'csrf_url'   => $base . '/ajax/internal.token.php?' . $assinatura,
        'surveys_id' => $sid,
        'tickets_id' => 0,
        'usuario'    => $users_id,
        'chave'      => $chave,
        'pergunta'   => (int) ($_GET['pergunta'] ?? 0),
        'valor'      => (string) ($_GET['valor'] ?? ''),
    ];
}

$exibir('@npsphere/answer/form.html.twig', $contexto_form + [
    'fields'    => Question::renderAnswerFields(
        $sid,
        $clicado + ($existente > 0 ? Invite::getRespostasGravadas($existente, $sid) : [])
    ),
    'errors'    => [],
    'um_clique' => $um_clique,
]);
