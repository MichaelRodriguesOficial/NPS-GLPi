<?php

/**
 * CRUD de uma pergunta (Question) dentro do builder de uma pesquisa.
 *
 * Suporta dois modos:
 *  - Normal (navegação de página cheia): usado como alternativa/fallback
 *    caso o JavaScript não carregue por algum motivo.
 *  - AJAX (parâmetro "ajax=1", enviado pelo builder embutido na aba
 *    "Pergunta"): devolve apenas o fragmento HTML do formulário (GET)
 *    ou uma resposta JSON {success, errors} (POST), sem navegar para
 *    fora da aba.
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Npsphere\ConditionEvaluator;
use GlpiPlugin\Npsphere\Question;
use GlpiPlugin\Npsphere\QuestionTypeManager;
use GlpiPlugin\Npsphere\Survey;

// GLPI 11: o bootstrap (sessão, autoload, permissões básicas) já é feito
// automaticamente pelo LegacyFileLoadController antes deste arquivo ser
// incluído -- não é mais necessário (nem correto) incluir inc/includes.php
// manualmente. Ver: Upgrade guide GLPI 11.0, seção "Includes".

// Impede que o navegador guarde em cache esta página administrativa
// (o formulário e o JS embutido nela mudam a cada atualização do
// plugin -- servir uma cópia em cache do navegador reintroduziria
// bugs já corrigidos).
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$is_ajax = isset($_REQUEST['ajax']);

$question = new Question();

// -----------------------------------------------------------------
// A pesquisa a que esta requisição se refere, e se o usuário a gerencia
// -----------------------------------------------------------------
// Resolvida num ponto só, antes de qualquer ação. Para uma pergunta
// EXISTENTE, a pesquisa vem do banco (a da própria pergunta), e não do
// que o navegador enviou -- senão bastaria informar um surveys_id seu
// para editar a pergunta de outra pessoa.
$pesquisas_envolvidas = [];

$id_pergunta = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
if ($id_pergunta > 0) {
    $existente = new Question();
    if ($existente->getFromDB($id_pergunta)) {
        $pesquisas_envolvidas[] = (int) $existente->fields['surveys_id'];
    }
}

// surveys_id enviado: pergunta NOVA, ou tentativa de mover uma pergunta
// para outra pesquisa -- em ambos os casos o destino também precisa ser
// do usuário.
$destino = (int) ($_POST['surveys_id'] ?? $_GET['surveys_id'] ?? 0);
if ($destino > 0) {
    $pesquisas_envolvidas[] = $destino;
}

foreach (array_unique($pesquisas_envolvidas) as $sid) {
    Survey::exigirGestao($sid);
}

if (isset($_POST['add'])) {
    Session::checkRight(Question::$rightname, CREATE);

    $newID = $question->add($_POST);

    if ($is_ajax) {
        plugin_npsphere_json_response((bool) $newID, ['id' => $newID ?: null]);
    }

    Html::redirect(Survey::getFormURL() . '?id=' . (int) $_POST['surveys_id'] . '&forcetab=' . rawurlencode(Question::class . '$1'));
} elseif (isset($_POST['update'])) {
    Session::checkRight(Question::$rightname, UPDATE);

    $ok = $question->update($_POST);

    if ($is_ajax) {
        plugin_npsphere_json_response((bool) $ok);
    }

    Html::back();
} elseif (isset($_POST['duplicate']) && isset($_POST['id'])) {
    Session::checkRight(Question::$rightname, CREATE);

    if ($question->getFromDB($_POST['id'])) {
        $input = $question->fields;
        unset($input['id'], $input['date_creation'], $input['date_mod']);
        $input['name']        = sprintf(__('%s (cópia)', 'npsphere'), $input['name']);
        unset($input['position']);
        $question = new Question();
        $question->add($input);
    }
    Html::back();
} elseif (isset($_POST['delete'])) {
    Session::checkRight(Question::$rightname, PURGE);

    $question->delete($_POST, 1);
    Html::back();
} elseif (isset($_GET['id'])) {
    $ID = (int) $_GET['id'];

    Session::checkRight(Question::$rightname, READ);

    $question->getFromDB($ID);

    $context = [
        'item'          => $question,
        'is_new'        => false,
        // Com apenas READ o formulário abre em modo LEITURA: campos
        // desabilitados e sem botão de salvar. Antes ele vinha
        // totalmente editável, e só falhava ao tentar gravar.
        'can_edit'      => Session::haveRight(Question::$rightname, UPDATE),
        'surveys_id'    => $question->fields['surveys_id'],
        'types'         => QuestionTypeManager::getTypesForDropdown(),
        'configuration' => $question->getConfiguration(),
        'type_forms'    => plugin_npsphere_render_all_type_forms($question->getConfiguration()),
        'form_url'      => Plugin::getWebDir('npsphere', true) . '/front/question.form.php',
        'preview_url'   => Plugin::getWebDir('npsphere', true) . '/ajax/question.preview.php',
        'operators'     => ConditionEvaluator::getOperatorsForDropdown(),
        'trigger_candidates' => Question::getTriggerCandidates(
            (int) $question->fields['surveys_id'],
            $ID,
            (int) $question->fields['position']
        ),
        'trigger_choices' => Question::getTriggerChoices((int) $question->fields['surveys_id']),
    ];

    if ($is_ajax) {
        TemplateRenderer::getInstance()->display('@npsphere/question/form.html.twig', $context);
        exit;
    }

    Html::header(Question::getTypeName(1), $_SERVER['PHP_SELF'], 'admin', Survey::class, 'survey');
    TemplateRenderer::getInstance()->display('@npsphere/question/form.html.twig', $context);
    Html::footer();
} else {
    // Formulário de nova pergunta (surveys_id obrigatório via querystring)
    $surveys_id = (int) ($_GET['surveys_id'] ?? 0);

    Session::checkRight(Question::$rightname, CREATE);

    $question->getEmpty();
    $question->fields['surveys_id'] = $surveys_id;

    $context = [
        'item'          => $question,
        'is_new'        => true,
        'can_edit'      => true, // só chega aqui quem tem CREATE
        'surveys_id'    => $surveys_id,
        'types'         => QuestionTypeManager::getTypesForDropdown(),
        'configuration' => [],
        'type_forms'    => plugin_npsphere_render_all_type_forms([]),
        'form_url'      => Plugin::getWebDir('npsphere', true) . '/front/question.form.php',
        'preview_url'   => Plugin::getWebDir('npsphere', true) . '/ajax/question.preview.php',
        'operators'     => ConditionEvaluator::getOperatorsForDropdown(),
        // Numa pergunta nova (que entra no fim da lista), qualquer
        // pergunta já existente pode servir de gatilho.
        'trigger_candidates' => Question::getTriggerCandidates($surveys_id),
        'trigger_choices' => Question::getTriggerChoices($surveys_id),
    ];

    if ($is_ajax) {
        TemplateRenderer::getInstance()->display('@npsphere/question/form.html.twig', $context);
        exit;
    }

    Html::header(Question::getTypeName(1), $_SERVER['PHP_SELF'], 'admin', Survey::class, 'survey');
    TemplateRenderer::getInstance()->display('@npsphere/question/form.html.twig', $context);
    Html::footer();
}

/**
 * Pré-renderiza o HTML de configuração de TODOS os tipos de pergunta.
 * O JavaScript do builder alterna qual bloco fica visível conforme o
 * tipo escolhido no <select>, evitando um round-trip ao servidor.
 *
 * @return array<string, string> chave do tipo => HTML
 */
function plugin_npsphere_render_all_type_forms(array $current_configuration): array
{
    $html_by_type = [];
    foreach (QuestionTypeManager::getTypes() as $key => $class) {
        $config = array_merge($class::getDefaultConfiguration(), $current_configuration);
        $html_by_type[$key] = $class::renderConfigForm($config);
    }
    return $html_by_type;
}

/**
 * Responde em JSON para o modo AJAX (add/update), incluindo mensagens
 * de erro já produzidas via Session::addMessageAfterRedirect() -- para
 * que o builder embutido consiga mostrá-las sem precisar navegar para
 * uma página cheia.
 */
function plugin_npsphere_json_response(bool $success, array $extra = []): void
{
    $errors = [];
    if (!$success && !empty($_SESSION['MESSAGE_AFTER_REDIRECT'][ERROR])) {
        foreach ($_SESSION['MESSAGE_AFTER_REDIRECT'][ERROR] as $message) {
            $errors[] = is_array($message) ? ($message['text'] ?? (string) reset($message)) : (string) $message;
        }
        unset($_SESSION['MESSAGE_AFTER_REDIRECT'][ERROR]);
    }

    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $success, 'errors' => $errors], $extra));
    exit;
}
