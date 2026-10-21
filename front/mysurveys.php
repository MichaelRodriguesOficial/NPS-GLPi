<?php

/**
 * "Minhas pesquisas": lista as pesquisas internas que o usuário
 * autenticado pode responder agora.
 *
 * Sem esta tela o público-alvo não teria serventia prática -- definir
 * quem pode responder não adianta se a pessoa não encontra a pesquisa.
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Npsphere\MySurveysMenu;
use GlpiPlugin\Npsphere\Target;

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// Exige o direito "Minhas pesquisas" -- e não o administrativo.
// A verificação é feita aqui também, e não só no menu: esconder um
// link não impede ninguém de digitar a URL.
//
// Não usamos Session::checkRight(): ele encerra a requisição com a
// mensagem genérica "Você não tem permissão para executar essa ação",
// que na interface simplificada aparece numa tela sem contexto nenhum.
// Aqui explicamos o que fazer -- e, principalmente, montamos a página
// com o cabeçalho CORRETO para a interface em uso.
$is_helpdesk = (Session::getCurrentInterface() === 'helpdesk');

$abrir_pagina = static function (string $titulo) use ($is_helpdesk): void {
    if ($is_helpdesk) {
        Html::helpHeader($titulo);
    } else {
        Html::header($titulo, $_SERVER['PHP_SELF'], 'tools');
    }
};

$fechar_pagina = static function () use ($is_helpdesk): void {
    if ($is_helpdesk) {
        Html::helpFooter();
    } else {
        Html::footer();
    }
};

if (!MySurveysMenu::hasAnswerRight()) {
    $abrir_pagina(__('Minhas pesquisas', 'npsphere'));

    echo "<div class='alert alert-warning'>"
        . __('Seu perfil não tem o direito "Minhas pesquisas". Peça a um administrador para habilitá-lo em Administração > Perfis > (seu perfil) > Pesquisas de satisfação.', 'npsphere')
        . "</div>";

    // Diagnóstico: separa "o direito não foi concedido" de "foi
    // concedido mas a sessão ainda não o carregou" -- as duas
    // situações produzem exatamente a mesma tela, e sem isso a
    // investigação vira adivinhação.
    global $DB;
    $profiles_id = (int) ($_SESSION['glpiactiveprofile']['id'] ?? 0);

    $no_banco = null;
    if ($DB->tableExists('glpi_profilerights')) {
        foreach ($DB->request([
            'SELECT' => ['rights'],
            'FROM'   => 'glpi_profilerights',
            'WHERE'  => ['profiles_id' => $profiles_id, 'name' => MySurveysMenu::$rightname],
        ]) as $row) {
            $no_banco = (int) $row['rights'];
        }
    }

    $na_sessao = $_SESSION['glpiactiveprofile'][MySurveysMenu::$rightname] ?? null;

    echo "<div class='alert alert-info'><strong>" . __('Diagnóstico', 'npsphere') . "</strong><ul>";
    echo "<li>" . __('Perfil', 'npsphere') . ": #{$profiles_id} ("
        . htmlspecialchars((string) ($_SESSION['glpiactiveprofile']['name'] ?? '?')) . ")</li>";
    echo "<li>" . __('Valor no banco', 'npsphere') . ": "
        . ($no_banco === null ? __('SEM REGISTRO — clique em Atualizar na tela de Plugins', 'npsphere') : $no_banco)
        . "</li>";
    echo "<li>" . __('Valor na sessão', 'npsphere') . ": "
        . ($na_sessao === null ? __('ausente — encerre e reabra a sessão', 'npsphere') : (int) $na_sessao)
        . "</li>";
    echo "</ul></div>";

    $fechar_pagina();
    return;
}

$abrir_pagina(__('Minhas pesquisas', 'npsphere'));

TemplateRenderer::getInstance()->display('@npsphere/mysurveys.html.twig', [
    'surveys'    => Target::getSurveysForUser(),
    'answer_url' => Plugin::getWebDir('npsphere', true) . '/front/answer.form.php',
]);

$fechar_pagina();
