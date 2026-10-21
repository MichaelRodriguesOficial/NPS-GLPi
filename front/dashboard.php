<?php

/**
 * Painel consolidado de satisfação (Fase 10).
 *
 * Toda a lógica -- escopo, filtros, cálculos -- está em
 * Dashboard::montar(), que é também a usada pela exportação. Assim o
 * arquivo exportado reproduz exatamente o que está na tela.
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Npsphere\Dashboard;
use GlpiPlugin\Npsphere\DashboardMenu;

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// Verificado aqui também, e não só no menu: esconder um link não impede
// ninguém de digitar a URL.
if (!DashboardMenu::temDireito()) {
    Html::displayRightError();
}

// O terceiro e o quarto argumentos dizem ao GLPI a qual item de menu a
// página pertence -- e ele o destaca e expande.
Html::header(
    __('Painel de satisfação', 'npsphere'),
    $_SERVER['PHP_SELF'],
    'management',
    DashboardMenu::class
);

$dados = Dashboard::montar($_GET);

// Link de cada linha de "Por pesquisa" direto para a aba Resultados da
// pesquisa. O "forcetab" é a forma do GLPI abrir o formulário já numa
// aba específica.
$dados['resultados_url'] = GlpiPlugin\Npsphere\Survey::getFormURL(true)
    . '?forcetab=' . rawurlencode(GlpiPlugin\Npsphere\SurveyResults::class . '$1')
    . '&id=';

// A exportação recebe exatamente os mesmos parâmetros da tela.
$dados['export_url'] = Plugin::getWebDir('npsphere', true) . '/ajax/dashboard.export.php?'
    . http_build_query(array_intersect_key($_GET, array_flip([
        'surveys', 'entities', 'requerentes', 'tecnicos', 'grupos', 'categorias', 'date_from', 'date_to',
    ])));

// v0.65.0: separador decimal das Preferências do usuário (Formato dos
// números), como no restante do GLPI.
$dados['sep_decimal'] = in_array((int) ($_SESSION['glpinumber_format'] ?? 0), [2, 4], true) ? ',' : '.';

TemplateRenderer::getInstance()->display('@npsphere/dashboard.html.twig', $dados);

Html::footer();
