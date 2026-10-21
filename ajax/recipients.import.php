<?php

/**
 * Importa um CSV para a lista de destinatários da pesquisa externa.
 *
 * O arquivo esperado é o que ajax/recipients.template.php entrega:
 * `email;nome`, uma pessoa por linha. A linha de cabeçalho não precisa
 * ser removida -- "email" não é um endereço válido, então ela cai fora
 * sozinha na validação.
 *
 * A importação ACUMULA: os endereços do arquivo entram junto dos que já
 * estavam no campo, sem repetir. Substituir a lista inteira a cada
 * importação apagaria sem aviso um trabalho manual anterior; quem quiser
 * trocar tudo limpa o campo antes.
 *
 * Devolve a lista já formatada para o <textarea>, para a tela atualizar
 * sem recarregar a aba e perder o que estiver sendo digitado no e-mail.
 */

use GlpiPlugin\Npsphere\Survey;
use GlpiPlugin\Npsphere\SurveyMailer;

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

Session::checkRight(Survey::$rightname, UPDATE);

$surveys_id = (int) ($_POST['surveys_id'] ?? 0);

// Só quem gerencia a pesquisa pode mexer na lista dela.
Survey::exigirGestao($surveys_id);

$survey = new Survey();
if ($surveys_id <= 0 || !$survey->getFromDB($surveys_id)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => __('Pesquisa não encontrada.', 'npsphere')]);
    exit;
}

if (($survey->fields['type'] ?? '') !== Survey::TYPE_EXTERNAL) {
    echo json_encode([
        'success' => false,
        'message' => __('A lista de e-mails existe apenas na pesquisa externa (link público).', 'npsphere'),
    ]);
    exit;
}

$arquivo = $_FILES['csv'] ?? null;

if (!is_array($arquivo) || ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    // UPLOAD_ERR_INI_SIZE e UPLOAD_ERR_FORM_SIZE são os casos comuns:
    // uma planilha grande salva como CSV passa fácil do limite do PHP.
    echo json_encode([
        'success' => false,
        'message' => (($arquivo['error'] ?? null) === UPLOAD_ERR_INI_SIZE
                      || ($arquivo['error'] ?? null) === UPLOAD_ERR_FORM_SIZE)
            ? __('O arquivo é maior do que o limite de upload do servidor.', 'npsphere')
            : __('Selecione um arquivo CSV.', 'npsphere'),
    ]);
    exit;
}

$fh = fopen($arquivo['tmp_name'], 'r');
if ($fh === false) {
    echo json_encode(['success' => false, 'message' => __('Não foi possível ler o arquivo.', 'npsphere')]);
    exit;
}

// BOM UTF-8: o Excel grava e o fgetcsv devolve junto do primeiro campo,
// o que estragaria o primeiro e-mail da lista.
$inicio = fread($fh, 3);
if ($inicio !== "\xEF\xBB\xBF") {
    rewind($fh);
}

$registros = [];
$linhas    = 0;

while (($campos = fgetcsv($fh, 0, ';')) !== false) {
    if ($campos === [null] || $campos === false) {
        continue; // linha em branco
    }

    $linhas++;

    // Planilha configurada com vírgula grava tudo num campo só. Em vez
    // de recusar o arquivo, separa de novo -- o usuário normalmente não
    // sabe qual separador a instalação do Excel dele usa.
    if (count($campos) === 1 && strpos((string) $campos[0], ',') !== false) {
        $campos = str_getcsv((string) $campos[0], ',');
    }

    $email = trim((string) ($campos[0] ?? ''));
    $nome  = trim((string) ($campos[1] ?? ''));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        continue; // cabeçalho, linha vazia ou endereço inválido
    }

    $registros[] = $email . ($nome !== '' ? ';' . $nome : '');
}

fclose($fh);

if ($registros === []) {
    echo json_encode([
        'success' => false,
        'message' => sprintf(
            __('Nenhum e-mail válido no arquivo (%d linha(s) lida(s)). Use o modelo: email;nome.', 'npsphere'),
            $linhas
        ),
    ]);
    exit;
}

// A mesma função que lê o campo lê o arquivo: as duas origens passam
// pelas mesmas regras de validação e de remoção de repetidos.
//
// A base é o que está NA TELA (campo "atual"), não o que está gravado:
// quem digitou endereços e ainda não salvou perderia esse trabalho se a
// importação partisse do banco. Sem o campo, usa o gravado.
$antes  = isset($_POST['atual'])
    ? SurveyMailer::parseLista((string) $_POST['atual'])
    : SurveyMailer::getListaExterna($survey);
$depois = SurveyMailer::parseLista(
    SurveyMailer::formatarLista($antes) . "\n" . implode("\n", $registros)
);

$texto = SurveyMailer::formatarLista($depois);

if (!$survey->update(['id' => $surveys_id, 'mail_recipients' => $texto])) {
    echo json_encode(['success' => false, 'message' => __('Não foi possível gravar a lista.', 'npsphere')]);
    exit;
}

$novos      = count($depois) - count($antes);
$repetidos  = count($registros) - $novos;
$partes     = [sprintf(__('%d e-mail(s) adicionado(s).', 'npsphere'), max(0, $novos))];

if ($repetidos > 0) {
    $partes[] = sprintf(__('%d já estava(m) na lista.', 'npsphere'), $repetidos);
}

echo json_encode([
    'success' => true,
    'message' => implode(' ', $partes),
    'lista'     => $texto,
    // Para a tela de destinatários (Nome / E-mail) se refazer sozinha.
    'registros' => SurveyMailer::paraTela($depois),
    'total'     => count($depois),
]);
