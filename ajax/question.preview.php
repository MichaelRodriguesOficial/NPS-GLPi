<?php

/**
 * Pré-visualização ao vivo de uma pergunta enquanto o administrador a
 * monta no builder (mesma renderização usada no formulário de resposta
 * real, via QuestionType::renderAnswerField() -- sem duplicar lógica).
 *
 * Implementado como GET (não POST): é uma operação de leitura pura,
 * sem qualquer efeito colateral no banco, então não deve exigir (nem
 * consumir) o token CSRF -- que é reservado para ações que alteram
 * dados. Usar POST aqui fazia as chamadas automáticas da pré-
 * visualização "gastarem" o token da página a cada tecla digitada,
 * invalidando o token que o formulário real de salvar a pergunta
 * dependia (causa raiz dos erros 403 relatados).
 */

use GlpiPlugin\Npsphere\Question;
use GlpiPlugin\Npsphere\QuestionTypeManager;

header('Content-Type: application/json');

Session::checkRight(Question::$rightname, READ);

$type = $_REQUEST['type'] ?? '';
$type_class = QuestionTypeManager::get($type);

if ($type_class === null) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_type']);
    exit;
}

$configuration = $type_class::validateConfiguration($_REQUEST);
$html = $type_class::renderAnswerField('preview_answer', $configuration, null);

echo json_encode([
    'name' => trim((string) ($_REQUEST['name'] ?? '')),
    'html' => $html,
]);
