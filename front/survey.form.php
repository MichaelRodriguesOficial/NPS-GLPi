<?php

/**
 * CRUD (criar / editar / duplicar / ativar / desativar / excluir /
 * restaurar / remover definitivamente) de uma Pesquisa de satisfação.
 */

use GlpiPlugin\Npsphere\Survey;

// GLPI 11: o bootstrap (sessão, autoload, permissões básicas) já é feito
// automaticamente pelo LegacyFileLoadController antes deste arquivo ser
// incluído -- não é mais necessário (nem correto) incluir inc/includes.php
// manualmente. Ver: Upgrade guide GLPI 11.0, seção "Includes".

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$survey = new Survey();

if (isset($_POST['add'])) {
    Session::checkRight(Survey::$rightname, CREATE);

    $newID = $survey->add($_POST);
    Html::back();

    if ($_SESSION['glpibackcreated'] ?? false) {
        Html::redirect(Survey::getFormURL() . '?id=' . $newID);
    }
    Html::back();
} elseif (isset($_POST['update'])) {
    Session::checkRight(Survey::$rightname, UPDATE);
    // O direito do perfil diz SE pode alterar pesquisas; esta linha diz
    // QUAIS -- apenas as próprias (ou todas, para o administrador geral).
    Survey::exigirGestaoNaPagina((int) ($_POST['id'] ?? 0));

    $survey->update($_POST);
    Html::back();
} elseif (isset($_POST['duplicate']) && isset($_POST['id'])) {
    Session::checkRight(Survey::$rightname, CREATE);

    // Só duplica o que pode ver: duplicar é uma forma de LER a pesquisa
    // inteira, e não pode servir de atalho para copiar a de outra pessoa.
    Survey::exigirGestaoNaPagina((int) $_POST['id']);

    if ($survey->getFromDB($_POST['id'])) {
        $input = $survey->fields;
        // creator_id também sai: a cópia pertence a quem duplicou.
        // Mantê-lo faria a cópia continuar sendo do dono original -- e,
        // com a restrição por criador, sumir da lista de quem a criou.
        unset($input['id'], $input['date_creation'], $input['date_mod'],
              $input['public_token'], $input['creator_id']);

        // Datas de envio são da ORIGINAL: a cópia nasce como pesquisa nova
        // (sem isso, ela pulava o relatório do dia e o aviso contava a
        // partir da data da original). v0.62.1.
        unset($input['date_last_report'], $input['date_report_sent'], $input['date_last_alert']);
        $input['alert_since'] = !empty($input['alert_active'])
            ? ($_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'))
            : null;
        $input['name'] = sprintf(__('%s (cópia)', 'npsphere'), $input['name']);
        $newID = $survey->add($input);
        Html::redirect(Survey::getFormURL() . '?id=' . $newID);
    }
    Html::back();
} elseif (isset($_POST['delete'])) {
    Session::checkRight(Survey::$rightname, DELETE);
    Survey::exigirGestaoNaPagina((int) ($_POST['id'] ?? 0));

    $survey->delete($_POST);
    $survey->redirectToList();
} elseif (isset($_POST['restore'])) {
    Session::checkRight(Survey::$rightname, DELETE);
    Survey::exigirGestaoNaPagina((int) ($_POST['id'] ?? 0));

    $survey->restore($_POST);
    $survey->redirectToList();
} elseif (isset($_POST['purge'])) {
    Session::checkRight(Survey::$rightname, PURGE);
    Survey::exigirGestaoNaPagina((int) ($_POST['id'] ?? 0));

    $survey->delete($_POST, 1);
    $survey->redirectToList();
} else {
    $ID = isset($_GET['id']) ? (int) $_GET['id'] : 0;

    Session::checkRight(Survey::$rightname, READ);

    // Abrir uma pesquisa existente pela URL: a listagem já esconde as
    // alheias, mas o ID digitado direto passaria sem esta checagem. ID 0
    // é o formulário de criação, que não tem dono ainda.
    if ($ID > 0) {
        Survey::exigirGestaoNaPagina($ID);
    }

    Html::header(
        Survey::getTypeName(1),
        $_SERVER['PHP_SELF'],
        'admin',
        Survey::class,
        'survey'
    );

    // Abrir já numa aba específica (ex.: "abrir os resultados" do
    // painel). O parâmetro chegava na URL mas não era repassado ao
    // formulário, e ele abria sempre na primeira aba.
    //
    // Aplicado pelos dois caminhos que o GLPI consulta: a aba ativa
    // guardada na sessão e a opção passada ao display().
    $opcoes = ['id' => $ID];

    $aba = $_GET['forcetab'] ?? '';
    if (is_string($aba) && preg_match('/^[A-Za-z0-9_\\\\]+\$\d+$/', $aba)) {
        Session::setActiveTab(Survey::class, $aba);
        $opcoes['forcetab'] = $aba;
    }

    $survey->display($opcoes);

    Html::footer();
}
