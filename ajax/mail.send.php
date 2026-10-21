<?php

/**
 * Envia os convites de uma pesquisa que não é vinculada a chamado.
 *
 * Disparado manualmente pela tela da pesquisa: não há evento nativo
 * para "pesquisa interna criada", então quem decide a hora do envio é
 * quem administra a pesquisa.
 */

use GlpiPlugin\Npsphere\Survey;
use GlpiPlugin\Npsphere\SurveyMailer;

header('Content-Type: application/json');
header('Cache-Control: no-store');

Session::checkRight(Survey::$rightname, UPDATE);

$surveys_id = (int) ($_POST['surveys_id'] ?? 0);

// Só quem gerencia a pesquisa pode disparar o envio em nome dela.
Survey::exigirGestao($surveys_id);

$survey = new Survey();
if (!$survey->getFromDB($surveys_id)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => __('Pesquisa não encontrada.', 'npsphere')]);
    exit;
}

if (($survey->fields['type'] ?? '') === Survey::TYPE_TICKET) {
    echo json_encode([
        'success' => false,
        'message' => __('Pesquisas vinculadas a chamado são enviadas automaticamente no encerramento do chamado.', 'npsphere'),
    ]);
    exit;
}

if (empty($survey->fields['is_active'])) {
    // Enviar convite de pesquisa inativa geraria link que não abre.
    echo json_encode([
        'success' => false,
        'message' => __('Ative a pesquisa antes de enviar os convites.', 'npsphere'),
    ]);
    exit;
}

$r = SurveyMailer::enviarConvites($survey);

$partes = [sprintf(__('%d convite(s) enfileirado(s).', 'npsphere'), $r['enviados'])];

if ($r['ignorados'] > 0) {
    $partes[] = sprintf(__('%d ignorado(s) por já terem respondido.', 'npsphere'), $r['ignorados']);
}
if ($r['erros'] > 0) {
    $partes[] = sprintf(__('%d falha(s) — ver npsphere.log.', 'npsphere'), $r['erros']);
}
if ($r['enviados'] === 0 && $r['ignorados'] === 0 && $r['erros'] === 0) {
    $partes = [__('Nenhum destinatário encontrado. Verifique o público-alvo ou a lista de e-mails.', 'npsphere')];
}

echo json_encode(['success' => true, 'message' => implode(' ', $partes), 'resultado' => $r]);
