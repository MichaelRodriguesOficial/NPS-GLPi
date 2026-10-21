<?php

/**
 * Formulário de resposta a uma pesquisa, para usuário autenticado do
 * GLPI (Fase 3). O acesso público/anônimo sem login será tratado numa
 * sub-fase dedicada, após validação contra a arquitetura real de
 * roteamento do GLPI 11.
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Npsphere\Question;
use GlpiPlugin\Npsphere\ResponseService;
use GlpiPlugin\Npsphere\Survey;
use GlpiPlugin\Npsphere\Target;

// Qualquer usuário autenticado pode responder (não exige o direito
// administrativo plugin_npsphere_survey, que é só para gerenciar
// pesquisas). Basta estar logado no GLPI.
Session::checkLoginUser();

// Exige o direito de RESPONDER (e não o de gerenciar). Usa
// hasAnswerRight(), que consulta o banco quando a sessão não traz o
// direito -- ver o comentário em MySurveysMenu::hasAnswerRight().
if (!\GlpiPlugin\Npsphere\MySurveysMenu::hasAnswerRight()) {
    Html::displayRightError();
}

// A interface SIMPLIFICADA (autoatendimento) não monta o cabeçalho da
// interface padrão -- e é justamente por ela que o respondente chega
// aqui, vindo de "Minhas pesquisas". Sem este cuidado, clicar numa
// pesquisa levava a uma tela quebrada.
$is_helpdesk = (Session::getCurrentInterface() === 'helpdesk');

$abrir_pagina = static function () use ($is_helpdesk): void {
    if ($is_helpdesk) {
        Html::helpHeader(Survey::getTypeName(1));
        return;
    }

    // Interface padrão: o cabeçalho aponta para FERRAMENTAS, e não para
    // Administração.
    //
    // Esta tela é de quem RESPONDE -- e quem responde normalmente não
    // tem o direito de gerenciar pesquisas. Apontar para o menu
    // Administração fazia o GLPI recusar a montagem da página, com o
    // erro genérico "Ocorreu um erro inesperado" ao abrir a pesquisa
    // pelo menu Ferramentas.
    Html::header(
        Survey::getTypeName(1),
        $_SERVER['PHP_SELF'],
        'tools',
        \GlpiPlugin\Npsphere\MySurveysMenu::class
    );
};

$fechar_pagina = static function () use ($is_helpdesk): void {
    if ($is_helpdesk) {
        Html::helpFooter();
        return;
    }

    Html::footer();
};

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$surveys_id = (int) ($_GET['surveys_id'] ?? $_POST['surveys_id'] ?? 0);

$survey = new Survey();
if (!$surveys_id || !$survey->getFromDB($surveys_id)) {
    Html::displayNotFoundError();
    exit;
}

// Resposta deste usuário a SUBSTITUIR, se houver: a gravada pelo clique
// no e-mail (o formulário devolve o id para completar a mesma) ou, sem
// "múltiplas respostas", a que ele já tinha. Só vale resposta DELE nesta
// pesquisa -- um id qualquer vindo do formulário não troca a de outro.
$users_id  = (int) Session::getLoginUserID();
$existente = 0;
if (($survey->fields['type'] ?? '') === Survey::TYPE_INTERNAL && empty($_POST['preview'])) {
    $ultima = ResponseService::getRespostaDoUsuario($surveys_id, $users_id);
    $pedida = (int) ($_POST['answers_id'] ?? 0);

    if ($pedida > 0 && $pedida === $ultima) {
        $existente = $pedida;
    } elseif (empty($survey->fields['allow_multiple_answers'])) {
        $existente = $ultima;
    }
}

if (isset($_POST['submit_answer'])) {
    $started_at = (int) ($_POST['_started_at'] ?? time());
    $result = ResponseService::submit(
        $survey,
        $_POST,
        $started_at,
        [],
        $existente > 0 ? ['answers_id' => $existente] : []
    );

    if (isset($_POST['ajax'])) {
        // Modo embutido (aba "Visualizar"): nunca navega para outra
        // tela -- devolve JSON e o próprio JS troca o conteúdo do
        // formulário pela mensagem de agradecimento, sem sair da aba.
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $result['success'],
            'errors'  => $result['errors'],
            'preview' => !empty($result['preview']),
        ]);
        exit;
    }

    $abrir_pagina();

    if ($result['success']) {
        TemplateRenderer::getInstance()->display('@npsphere/answer/thankyou.html.twig', [
            'survey' => $survey,
        ]);
    } else {
        TemplateRenderer::getInstance()->display('@npsphere/answer/form.html.twig', [
            'survey'     => $survey,
            'fields'     => Question::renderAnswerFields($surveys_id, $_POST),
            'errors'     => $result['errors'],
            'started_at' => time(),
        // Assets carregados pela própria página: a interface
        // simplificada não inclui os do plugin automaticamente.
        'js_url'     => Plugin::getWebDir('npsphere', true) . '/js/survey-builder.js',
        'css_url'    => Plugin::getWebDir('npsphere', true) . '/css/npsphere.css',
        ]);
    }

    $fechar_pagina();
    exit;
}

// Público-alvo: numa pesquisa interna, quem não faz parte do público
// não pode responder nem digitando a URL diretamente. O portão é o
// mesmo usado para montar "Minhas pesquisas", então não há como as duas
// telas discordarem.
if (
    ($survey->fields['type'] ?? '') === Survey::TYPE_INTERNAL
    && !Target::isUserTargeted($survey)
) {
    $abrir_pagina();
    TemplateRenderer::getInstance()->display('@npsphere/answer/blocked.html.twig', [
        'survey' => $survey,
        'reason' => __('Esta pesquisa não está disponível para você.', 'npsphere'),
    ]);
    $fechar_pagina();
    exit;
}

// Opção clicada no e-mail: a página grava sozinha (ajax/answer.oneclick.php),
// e trocar a nota já dada também vale -- por isso, com clique, "já
// respondeu" não bloqueia a tela.
$clicado = Question::getPreenchimentoDoLink($surveys_id, $_GET);

$eligibility = ResponseService::canRespond($survey, $clicado !== [] && $existente > 0);

$um_clique = null;
if ($clicado !== [] && ($survey->fields['type'] ?? '') === Survey::TYPE_INTERNAL) {
    $um_clique = [
        'url'        => Plugin::getWebDir('npsphere', true) . '/ajax/answer.oneclick.php',
        'csrf_url'   => Plugin::getWebDir('npsphere', true) . '/ajax/csrf.token.php',
        'surveys_id' => $surveys_id,
        'tickets_id' => 0,
        'pergunta'   => (int) ($_GET['pergunta'] ?? 0),
        'valor'      => (string) ($_GET['valor'] ?? ''),
    ];
}

$abrir_pagina();

if (!$eligibility['can']) {
    TemplateRenderer::getInstance()->display('@npsphere/answer/blocked.html.twig', [
        'survey' => $survey,
        'reason' => $eligibility['reason'],
    ]);
} else {
    TemplateRenderer::getInstance()->display('@npsphere/answer/form.html.twig', [
        'survey'     => $survey,
        // Opção clicada por cima do que já estava respondido.
        'fields'     => Question::renderAnswerFields(
            $surveys_id,
            $clicado + ($existente > 0 && $clicado !== []
                ? \GlpiPlugin\Npsphere\Invite::getRespostasGravadas($existente, $surveys_id)
                : [])
        ),
        'um_clique'  => $um_clique,
        'errors'     => [],
        'started_at' => time(),
        // Assets carregados pela própria página: a interface
        // simplificada não inclui os do plugin automaticamente.
        'js_url'     => Plugin::getWebDir('npsphere', true) . '/js/survey-builder.js',
        'css_url'    => Plugin::getWebDir('npsphere', true) . '/css/npsphere.css',
    ]);
}

$fechar_pagina();
