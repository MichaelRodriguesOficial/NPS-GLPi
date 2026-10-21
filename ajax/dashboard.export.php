<?php

/**
 * Exportação do painel consolidado em CSV.
 *
 * Usa Dashboard::montar() com os MESMOS parâmetros da tela: escopo por
 * criador, período e filtros. Uma consulta própria aqui inevitavelmente
 * divergiria da tela com o tempo.
 */

use GlpiPlugin\Npsphere\Dashboard;
use GlpiPlugin\Npsphere\DashboardMenu;

if (!DashboardMenu::temDireito()) {
    http_response_code(403);
    exit;
}

$dados = Dashboard::montar($_GET);

// Mesmo separador configurado pelo usuário no GLPI (vírgula ou ponto e
// vírgula), para o arquivo abrir certo no Excel da máquina dele.
$sep = $_SESSION['glpicsv_delimiter'] ?? ';';

$nome = 'NPSphere - Painel - ' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nome . '"');
header('Cache-Control: no-store');

$out = fopen('php://output', 'w');

// BOM: sem ele o Excel abre acentos quebrados.
fwrite($out, "\xEF\xBB\xBF");

$linha = static function (array $campos) use ($out, $sep): void {
    fputcsv($out, $campos, $sep);
};

$num = static fn($v): string => $v === null ? '' : str_replace('.', ',', (string) $v);

// --- Cabeçalho: o recorte exportado ---
$linha([__('Painel de satisfação', 'npsphere')]);
$linha([__('Período', 'npsphere'), $dados['date_from'] . ' a ' . $dados['date_to']]);
$linha([__('Gerado em', 'npsphere'), date('Y-m-d H:i')]);
if (!$dados['ver_todas']) {
    $linha([__('Escopo', 'npsphere'), __('Apenas as pesquisas criadas por você', 'npsphere')]);
}
$linha([]);

// --- Indicadores ---
$r = $dados['resumo'];
$linha([__('Indicadores', 'npsphere')]);
$c = $dados['comparacao'];

// Colunas: valor atual | período anterior | variação
$linha(['', __('Atual', 'npsphere'), __('Período anterior', 'npsphere'), __('Variação', 'npsphere')]);
$linha([__('NPS', 'npsphere'), $r['nps'] ?? '', $c['nps_antes'] ?? '', $c['nps'] ?? '']);
$linha([__('Nota média', 'npsphere'), $num($r['media']), $num($c['media_antes']), $num($c['media'])]);
$linha([__('Respostas', 'npsphere'), $r['total'], $c['total_antes'], $c['tem_base'] ? $c['total'] : '']);
$linha([__('Período anterior', 'npsphere'), $c['de'] . ' a ' . $c['ate']]);
$linha([]);
$linha([__('Promotores', 'npsphere'), $num($r['promotores_pct']) . '%', $r['promotores']]);
$linha([__('Neutros', 'npsphere'), $num($r['neutros_pct']) . '%', $r['neutros']]);
$linha([__('Detratores', 'npsphere'), $num($r['detratores_pct']) . '%', $r['detratores']]);
$linha([]);

// --- Recortes ---
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
    $linha([$titulo]);
    $linha([
        $rotulo,
        __('Respostas', 'npsphere'),
        __('NPS', 'npsphere'),
        __('Nota média', 'npsphere'),
        __('Promotores %', 'npsphere'),
        __('Detratores %', 'npsphere'),
    ]);

    if (empty($dados[$chave])) {
        $linha([__('Sem dados no período', 'npsphere')]);
    }

    foreach ($dados[$chave] as $g) {
        $linha([
            $g['rotulo'],
            $g['resumo']['total'],
            $g['resumo']['nps'] ?? '',
            $num($g['resumo']['media']),
            $g['resumo']['nps'] !== null ? $num($g['resumo']['promotores_pct']) : '',
            $g['resumo']['nps'] !== null ? $num($g['resumo']['detratores_pct']) : '',
        ]);
    }

    $linha([]);
}

fclose($out);
