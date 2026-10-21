<?php

/**
 * Exportação do painel consolidado em CSV, Excel (.xlsx) ou LibreOffice
 * (.ods). Na planilha, cada recorte fica numa aba.
 *
 * Usa Dashboard::montar() com os MESMOS parâmetros da tela: escopo por
 * criador, período e filtros. Uma consulta própria aqui inevitavelmente
 * divergiria da tela com o tempo.
 */

use GlpiPlugin\Npsphere\Dashboard;
use GlpiPlugin\Npsphere\DashboardMenu;
use GlpiPlugin\Npsphere\SheetExport;

if (!DashboardMenu::temDireito()) {
    http_response_code(403);
    exit;
}

$dados   = Dashboard::montar($_GET);
$formato = SheetExport::formato($_GET['format'] ?? 'csv');

$sx = new SheetExport();

// --- Resumo: o recorte exportado e os indicadores ---
$sx->aba(__('Resumo', 'npsphere'));
$sx->linha([__('Painel de satisfação', 'npsphere')]);
$sx->linha([__('Período', 'npsphere'), $dados['date_from'] . ' a ' . $dados['date_to']]);
$sx->linha([__('Gerado em', 'npsphere'), date('Y-m-d H:i')]);
if (!$dados['ver_todas']) {
    $sx->linha([__('Escopo', 'npsphere'), __('Apenas as pesquisas criadas por você', 'npsphere')]);
}
$sx->vazia();

$r = $dados['resumo'];
$c = $dados['comparacao'];
$num = static fn($v) => $v === null ? '' : SheetExport::num($v);
// Nota média sempre com casas decimais (8 vira 8,00 na planilha).
$media = static fn($v) => $v === null || $v === '' ? '' : (float) $v;

$sx->linha([__('Indicadores', 'npsphere')]);
// Colunas: valor atual | período anterior | variação
$sx->cabecalho(['', __('Atual', 'npsphere'), __('Período anterior', 'npsphere'), __('Variação', 'npsphere')]);
$sx->linha([__('NPS', 'npsphere'), $num($r['nps']), $num($c['nps_antes']), $num($c['nps'])]);
$sx->linha([__('Nota média', 'npsphere'), $media($r['media']), $media($c['media_antes']), $media($c['media'])]);
$sx->linha([__('Respostas', 'npsphere'), $num($r['total']), $num($c['total_antes']), $c['tem_base'] ? $num($c['total']) : '']);
$sx->linha([__('Período anterior', 'npsphere'), $c['de'] . ' a ' . $c['ate']]);
$sx->vazia();
$sx->linha([__('Promotores', 'npsphere'), SheetExport::pct($r['promotores_pct']), $num($r['promotores'])]);
$sx->linha([__('Neutros', 'npsphere'), SheetExport::pct($r['neutros_pct']), $num($r['neutros'])]);
$sx->linha([__('Detratores', 'npsphere'), SheetExport::pct($r['detratores_pct']), $num($r['detratores'])]);

// --- Recortes: uma aba cada na planilha ---
// Título e cabeçalho de cada recorte escritos por extenso -- montar o
// título a partir do rótulo exigiria conversão de caixa, e traduções
// nem sempre seguem a mesma regra de maiúsculas.
$recortes = [
    'por_pesquisa'   => [__('Por pesquisa', 'npsphere'),   __('Pesquisa', 'npsphere')],
    'por_entidade'   => [__('Por entidade', 'npsphere'),   __('Entidade', 'npsphere')],
    'por_requerente' => [__('Por requerente', 'npsphere'), __('Requerente', 'npsphere')],
    'por_tecnico'    => [__('Por técnico', 'npsphere'),    __('Técnico', 'npsphere')],
    'por_grupo'      => [__('Por grupo técnico', 'npsphere'), __('Grupo técnico', 'npsphere')],
    'por_categoria'  => [__('Por categoria', 'npsphere'),  __('Categoria', 'npsphere')],
];

foreach ($recortes as $chave => [$titulo, $rotulo]) {
    $sx->aba($titulo, true);
    $sx->cabecalho([
        $rotulo,
        __('Respostas', 'npsphere'),
        __('NPS', 'npsphere'),
        __('Nota média', 'npsphere'),
        __('Promotores %', 'npsphere'),
        __('Detratores %', 'npsphere'),
    ], true);

    if (empty($dados[$chave])) {
        $sx->linha([__('Sem dados no período', 'npsphere')]);
    }

    foreach ($dados[$chave] as $g) {
        $tem_nps = $g['resumo']['nps'] !== null;
        $sx->linha([
            $g['rotulo'],
            $num($g['resumo']['total']),
            $num($g['resumo']['nps']),
            $media($g['resumo']['media']),
            $tem_nps ? SheetExport::pct($g['resumo']['promotores_pct']) : '',
            $tem_nps ? SheetExport::pct($g['resumo']['detratores_pct']) : '',
        ]);
    }
}

$sx->enviar($formato, 'NPSphere - ' . __('Painel', 'npsphere') . ' - ' . date('Y-m-d'));
