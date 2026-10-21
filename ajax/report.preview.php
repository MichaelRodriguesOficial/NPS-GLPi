<?php

/**
 * Prévia do RELATÓRIO DIÁRIO, para o iframe da sub-aba "Relatório".
 *
 * Usa a nota de corte que está na tela (antes de salvar) e as
 * respostas das últimas 24 horas. Sem nenhuma nesse período, mostra as
 * dos últimos 30 dias, para dar o que ver numa pesquisa nova.
 *
 * A montagem é a de DailyReport::montar(), a MESMA do envio.
 */

use GlpiPlugin\Npsphere\DailyReport;
use GlpiPlugin\Npsphere\Survey;

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

$corte = max(0, min(9, (int) ($_POST['report_nps_cut'] ?? 6)));

$agora = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
$aviso = __('Prévia com as respostas das últimas 24 horas.', 'npsphere');

// Modelo como está no editor (antes de salvar).
$corpo   = isset($_POST['mail_body']) ? (string) $_POST['mail_body'] : null;
$assunto = isset($_POST['assunto']) ? (string) $_POST['assunto'] : null;

$relatorio = DailyReport::montar($survey, date('Y-m-d H:i:s', strtotime($agora . ' -1 day')), $agora, $corte, $corpo, $assunto);
if ($relatorio['respostas'] === 0) {
    $relatorio = DailyReport::montar($survey, date('Y-m-d H:i:s', strtotime($agora . ' -30 days')), $agora, $corte, $corpo, $assunto);
    $aviso = __('Nenhuma resposta nas últimas 24 horas: a prévia mostra as dos últimos 30 dias.', 'npsphere');
}

$corpo = $relatorio['respostas'] > 0
    ? '<p style="margin:0;padding:12px 16px 0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#667382;">'
        . htmlspecialchars($aviso) . '<br><strong>' . htmlspecialchars(__('Assunto:', 'npsphere')) . '</strong> '
        . htmlspecialchars($relatorio['assunto']) . '</p>' . $relatorio['html']
    : '<p style="margin:0;padding:24px;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#667382;">'
        . htmlspecialchars(__('Nenhuma resposta nos últimos 30 dias. O relatório só é enviado nos dias em que há respostas.', 'npsphere'))
        . '</p>';

echo '<!DOCTYPE html>' . "\n"
    . '<html lang="pt-BR"><head><meta charset="UTF-8">'
    . '<meta name="viewport" content="width=device-width, initial-scale=1">'
    . '<base target="_blank">'
    . '<title>' . htmlspecialchars((string) $survey->fields['name']) . '</title>'
    . '<style>html,body{margin:0;padding:0;background:#f5f7fb;}</style>'
    . '</head>' . "\n"
    . '<body>' . $corpo
    // Mesma medição de altura da prévia do convite (ajax/mail.preview.php).
    . '<script>(function(){'
    . 'function avisar(){parent.postMessage({npsphere:"altura",'
    . 'altura:Math.ceil(document.body.getBoundingClientRect().height)},"*");}'
    . 'window.addEventListener("load",avisar);'
    . 'if(window.ResizeObserver){new ResizeObserver(avisar).observe(document.body);}'
    . '})();</script>'
    . '</body>' . "\n"
    . '</html>';
