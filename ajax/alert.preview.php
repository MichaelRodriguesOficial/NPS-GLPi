<?php

/**
 * Prévia do AVISO IMEDIATO de nota baixa, para o iframe da sub-aba
 * "Relatório e aviso".
 *
 * Mostra o aviso da resposta com nota baixa mais recente dos últimos 30
 * dias, com a nota de corte e a espera que estão na tela (antes de
 * salvar). A montagem é a de DailyReport, a MESMA do envio.
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

$corte  = max(0, min(9, (int) ($_POST['alert_nps_cut'] ?? 6)));
$espera = (int) ($_POST['alert_delay'] ?? 10);
if (!in_array($espera, DailyReport::ESPERAS, true)) {
    $espera = 10;
}

// Modelo como está no editor (antes de salvar).
$aviso = DailyReport::previaDoAviso(
    $survey,
    $corte,
    $espera,
    isset($_POST['mail_body']) ? (string) $_POST['mail_body'] : null,
    isset($_POST['assunto']) ? (string) $_POST['assunto'] : null
);

$corpo = $aviso !== null
    ? '<p style="margin:0;padding:12px 16px 0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#667382;">'
        . htmlspecialchars(__('Prévia com a nota baixa mais recente dos últimos 30 dias.', 'npsphere'))
        . '<br><strong>' . htmlspecialchars(__('Assunto:', 'npsphere')) . '</strong> '
        . htmlspecialchars($aviso['assunto']) . '</p>' . $aviso['html']
    : '<p style="margin:0;padding:24px;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#667382;">'
        . htmlspecialchars(__('Nenhuma resposta com nota baixa nos últimos 30 dias, com a nota de corte escolhida.', 'npsphere'))
        . '</p>';

echo '<!DOCTYPE html>' . "\n"
    . '<html lang="pt-BR"><head><meta charset="UTF-8">'
    . '<meta name="viewport" content="width=device-width, initial-scale=1">'
    . '<base target="_blank">'
    . '<title>' . htmlspecialchars((string) $survey->fields['name']) . '</title>'
    . '<style>html,body{margin:0;padding:0;background:#f5f7fb;}</style>'
    . '</head>' . "\n"
    . '<body>' . $corpo
    . '<script>(function(){'
    . 'function avisar(){parent.postMessage({npsphere:"altura",'
    . 'altura:Math.ceil(document.body.getBoundingClientRect().height)},"*");}'
    . 'window.addEventListener("load",avisar);'
    . 'if(window.ResizeObserver){new ResizeObserver(avisar).observe(document.body);}'
    . '})();</script>'
    . '</body>' . "\n"
    . '</html>';
