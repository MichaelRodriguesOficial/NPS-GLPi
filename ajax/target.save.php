<?php

/**
 * Adiciona, remove ou altera o público-alvo de uma pesquisa.
 *
 * Implementado como GET por decisão prática: POST para caminhos de
 * plugin retorna 403 nesta instalação (o mesmo motivo que levou a
 * pré-visualização de perguntas e o envio de respostas a usarem GET).
 * A proteção real aqui é a verificação de permissão abaixo.
 */

use GlpiPlugin\Npsphere\Survey;
use GlpiPlugin\Npsphere\Target;

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

Session::checkRight(Target::$rightname, UPDATE);

$surveys_id = (int) ($_GET['surveys_id'] ?? 0);

// Só quem criou a pesquisa (ou o administrador geral) a gerencia.
\GlpiPlugin\Npsphere\Survey::exigirGestao($surveys_id);
$action     = $_GET['action'] ?? '';

$survey = new Survey();
if ($surveys_id <= 0 || !$survey->getFromDB($surveys_id)) {
    http_response_code(404);
    echo json_encode(['success' => false]);
    exit;
}

$target = new Target();

switch ($action) {
    case 'add':
        $itemtype = $_GET['itemtype'] ?? '';
        $items_id = (int) ($_GET['items_id'] ?? 0);
        $excecao  = !empty($_GET['excluded']) ? 1 : 0;

        if (!array_key_exists($itemtype, Target::getTargetTypes()) || $items_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => __('Destinatário inválido.', 'npsphere')]);
            exit;
        }

        // A tabela tem índice único (surveys_id, itemtype, items_id),
        // mas checar antes evita um erro de banco por duplicidade e
        // permite responder de forma amigável. O mesmo item não pode ser
        // destinatário e exceção ao mesmo tempo: a resposta diz em qual
        // das duas listas ele já está.
        if ($target->getFromDBByCrit([
            'surveys_id' => $surveys_id,
            'itemtype'   => $itemtype,
            'items_id'   => $items_id,
        ])) {
            echo json_encode([
                'success'   => true,
                'duplicate' => true,
                'em'        => !empty($target->fields['is_excluded']) ? 'excecoes' : 'destinatarios',
            ]);
            exit;
        }

        $target->add([
            'surveys_id'  => $surveys_id,
            'itemtype'    => $itemtype,
            'items_id'    => $items_id,
            'is_excluded' => $excecao,
        ]);
        break;

    case 'delete':
        $id = (int) ($_GET['id'] ?? 0);
        // Confere que o destinatário pertence mesmo a esta pesquisa,
        // para ninguém apagar público-alvo de outra pesquisa pela URL.
        if ($id > 0 && $target->getFromDB($id) && (int) $target->fields['surveys_id'] === $surveys_id) {
            $target->delete(['id' => $id], 1);
        }
        break;

    case 'toggle_all':
        $survey->update([
            'id'         => $surveys_id,
            'target_all' => !empty($_GET['value']) ? 1 : 0,
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false]);
        exit;
}

echo json_encode(['success' => true]);
