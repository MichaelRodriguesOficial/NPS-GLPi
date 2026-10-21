<?php

/**
 * Persiste a nova ordem das perguntas após reordenação por
 * "arrastar e soltar" no builder (seção 10 do escopo).
 *
 * Recebido como POST tradicional (application/x-www-form-urlencoded),
 * incluindo o campo padrão `_glpi_csrf_token`, para se beneficiar da
 * mesma verificação de CSRF usada pelos demais formulários do GLPI.
 *
 * Campos esperados:
 *   surveys_id : int
 *   order[]    : lista de IDs de pergunta, na nova ordem desejada
 */

use GlpiPlugin\Npsphere\Question;

// GLPI 11: o bootstrap (sessão, autoload, permissões básicas) já é feito
// automaticamente pelo LegacyFileLoadController antes deste arquivo ser
// incluído -- não é mais necessário (nem correto) incluir inc/includes.php
// manualmente. Ver: Upgrade guide GLPI 11.0, seção "Includes".

header('Content-Type: application/json');

Session::checkRight(Question::$rightname, UPDATE);

global $DB;

$surveys_id = (int) ($_POST['surveys_id'] ?? 0);

// Só quem criou a pesquisa (ou o administrador geral) a gerencia.
\GlpiPlugin\Npsphere\Survey::exigirGestao($surveys_id);
$order      = $_POST['order'] ?? [];

if ($surveys_id <= 0 || !is_array($order) || empty($order)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_payload']);
    exit;
}

$table = Question::getTable();

// Ordem anterior, para registrar no histórico só quando mudou de fato.
$antes = [];
foreach ($DB->request(['SELECT' => ['id'], 'FROM' => $table, 'WHERE' => ['surveys_id' => $surveys_id], 'ORDER' => 'position ASC']) as $r) {
    $antes[] = (int) $r['id'];
}

$position = 1;
foreach ($order as $questions_id) {
    $questions_id = (int) $questions_id;

    // Garante que a pergunta pertence de fato à pesquisa informada,
    // evitando que um usuário manipule perguntas de outra pesquisa/entidade.
    $belongs = $DB->request([
        'COUNT' => 'cpt',
        'FROM'  => $table,
        'WHERE' => ['id' => $questions_id, 'surveys_id' => $surveys_id],
    ])->current()['cpt'] > 0;

    // Atualiza a posição diretamente no banco, SEM passar pelo
    // pipeline de validação de Question::update() -- reordenar não deve
    // (e não precisa) revalidar tipo, configuração, obrigatoriedade ou
    // status ativo da pergunta.
    if ($belongs) {
        $DB->update($table, ['position' => $position], ['id' => $questions_id]);
    }
    $position++;
}

$depois = [];
foreach ($DB->request(['SELECT' => ['id'], 'FROM' => $table, 'WHERE' => ['surveys_id' => $surveys_id], 'ORDER' => 'position ASC']) as $r) {
    $depois[] = (int) $r['id'];
}
if ($antes !== $depois) {
    // Aba Histórico da pesquisa (v0.65.0).
    Log::history(
        $surveys_id,
        \GlpiPlugin\Npsphere\Survey::class,
        ['0', '', __('Ordem das perguntas alterada', 'npsphere')],
        Question::class,
        Log::HISTORY_UPDATE_SUBITEM
    );
}

echo json_encode(['success' => true]);
