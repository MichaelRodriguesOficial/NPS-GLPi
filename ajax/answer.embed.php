<?php

/**
 * Formulário de resposta como fragmento, para ser exibido dentro do
 * aviso pós-login (sem sair da tela em que o usuário está).
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Npsphere\Question;
use GlpiPlugin\Npsphere\ResponseService;
use GlpiPlugin\Npsphere\Survey;
use GlpiPlugin\Npsphere\Target;

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

Session::checkLoginUser();

$surveys_id = (int) ($_GET['surveys_id'] ?? 0);

$survey = new Survey();
if ($surveys_id <= 0 || !$survey->getFromDB($surveys_id)) {
    http_response_code(404);
    exit;
}

// Mesmas travas do acesso direto: público-alvo e elegibilidade. O
// fragmento é uma porta a mais para o formulário, e portas a mais não
// podem ter fechaduras a menos.
if (
    ($survey->fields['type'] ?? '') === Survey::TYPE_INTERNAL
    && !Target::isUserTargeted($survey)
) {
    http_response_code(403);
    exit;
}

$eligibility = ResponseService::canRespond($survey);
if (!$eligibility['can']) {
    http_response_code(403);
    echo '<div class="alert alert-warning mb-0">' . $eligibility['reason'] . '</div>';
    exit;
}

TemplateRenderer::getInstance()->display('@npsphere/answer/embed.html.twig', [
    'survey'     => $survey,
    'fields'     => Question::renderAnswerFields($surveys_id),
    'started_at' => time(),
    'submit_url' => Plugin::getWebDir('npsphere', true) . '/ajax/answer.submit.php',
]);
