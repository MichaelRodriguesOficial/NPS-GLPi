<?php

/**
 * Prévia HTML5 da mensagem do convite, para o iframe da aba "Template".
 *
 * Recebe o texto que está no editor -- e não o gravado no banco -- para
 * a prévia acompanhar a edição antes de salvar.
 *
 * Devolve um documento HTML5 completo porque o destino é um iframe: o
 * e-mail é montado com tabelas e estilos embutidos, e injetar isso
 * direto na página faria o CSS do GLPI mudar a aparência do que a
 * pessoa está conferindo. O iframe isola as duas folhas de estilo.
 *
 * A montagem é a de SurveyMailer::montarPrevia(), a MESMA do envio
 * real -- prévia com montagem própria mostraria uma coisa e enviaria
 * outra.
 */

use GlpiPlugin\Npsphere\Survey;
use GlpiPlugin\Npsphere\SurveyMailer;

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

Session::checkRight(Survey::$rightname, UPDATE);

$surveys_id = (int) ($_POST['surveys_id'] ?? 0);

Survey::exigirGestao($surveys_id);

$survey = new Survey();
if ($surveys_id <= 0 || !$survey->getFromDB($surveys_id)) {
    http_response_code(404);
    exit;
}

// Lista de destinatários como está na tela: o {{destinatario}} da prévia
// é o primeiro dela, mesmo antes de salvar.
$lista_na_tela = isset($_POST['mail_recipients']) ? (string) $_POST['mail_recipients'] : null;

// lembrete=1: prévia do editor da sub-aba "Lembrete" -- em branco, usa o
// texto padrão do lembrete, não o do convite.
$corpo = SurveyMailer::montarPrevia(
    $survey,
    (string) ($_POST['mail_body'] ?? ''),
    $lista_na_tela,
    !empty($_POST['lembrete'])
);

echo '<!DOCTYPE html>' . "\n"
    . '<html lang="' . \GlpiPlugin\Npsphere\JsI18n::langHtml() . '">' . "\n"
    . '<head><meta charset="UTF-8">'
    . '<meta name="viewport" content="width=device-width, initial-scale=1">'
    // Links da prévia abrem fora do iframe, sem trocar a prévia pela
    // página da pesquisa.
    . '<base target="_blank">'
    . '<title>' . htmlspecialchars((string) $survey->fields['name']) . '</title>'
    . '<style>html,body{margin:0;padding:0;background:#f5f7fb;}</style>'
    . '</head>' . "\n"
    . '<body>' . $corpo
    // O iframe roda numa origem opaca (sandbox sem allow-same-origin), e a
    // página do GLPI não consegue medir a altura dele. A prévia informa a
    // própria altura por postMessage -- só um número, nada da sessão.
    . '<script>(function(){'
    . 'function avisar(){parent.postMessage({npsphere:"altura",'
    . 'altura:Math.ceil(document.body.getBoundingClientRect().height)},"*");}'
    . 'window.addEventListener("load",avisar);'
    . 'if(window.ResizeObserver){new ResizeObserver(avisar).observe(document.body);}'
    . '})();</script>'
    . '</body>' . "\n"
    . '</html>';
