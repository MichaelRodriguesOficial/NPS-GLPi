<?php

/**
 * Modelo de CSV para a lista de destinatários da pesquisa externa.
 *
 * Existe para a importação não depender de adivinhação: a pessoa baixa o
 * arquivo, preenche na planilha dela e sobe de volta. Sem um modelo, cada
 * um montaria as colunas de um jeito e a importação passaria a vida
 * recusando arquivos.
 *
 * O separador é FIXO em ponto e vírgula, diferente da exportação de
 * resultados -- que segue a preferência do usuário no GLPI. Aqui o
 * arquivo vai ser lido de volta por este mesmo plugin, e um separador
 * combinado dos dois lados vale mais do que um configurável.
 *
 * Vive em ajax/ junto de results.export.php: é o lugar onde os downloads
 * gerados pelo plugin já estavam.
 */

use GlpiPlugin\Npsphere\Survey;

Session::checkRight(Survey::$rightname, UPDATE);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="npsphere-destinatarios-modelo.csv"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$out = fopen('php://output', 'w');

// BOM UTF-8: sem isso o Excel no Windows exibe acentuação quebrada.
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, ['email', 'nome'], ';');
fputcsv($out, ['maria.silva@empresa.com', 'Maria Silva'], ';');
fputcsv($out, ['joao.souza@empresa.com', 'João Souza'], ';');
fputcsv($out, ['contato@cliente.com.br', ''], ';');

fclose($out);
