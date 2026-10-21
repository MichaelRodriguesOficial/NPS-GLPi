<?php

namespace GlpiPlugin\Npsphere;

use Glpi\Search\Output\SpreadsheetValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Ods;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Exportação em planilha: CSV, Excel (.xlsx) e LibreOffice (.ods).
 *
 * As telas montam o conteúdo uma vez só, em abas e linhas, e esta classe
 * grava no formato pedido. Assim o CSV e as planilhas nunca divergem.
 *
 * Usa o PhpSpreadsheet que já vem com o GLPI 11 (o mesmo das exportações
 * das listas do próprio GLPI) -- nada a instalar.
 *
 * Tipos de célula:
 *  - int/float  → número;
 *  - string     → sempre texto (uma resposta que comece com "=" não vira
 *                 fórmula na planilha);
 *  - self::pct() e self::data() → percentual e data/hora formatados.
 */
final class SheetExport
{
    public const FORMATOS = ['csv', 'xlsx', 'ods'];

    /** @var array<int, array{titulo:string, linhas:array, cabecalhos:array, congelar:?int, titulo_no_csv:bool}> */
    private array $abas = [];

    private int $atual = -1;

    /** Normaliza o parâmetro "format" da URL. */
    public static function formato($valor): string
    {
        return in_array($valor, self::FORMATOS, true) ? $valor : 'csv';
    }

    /** Percentual (0 a 100). */
    public static function pct($valor): array
    {
        return ['__tipo' => 'pct', 'v' => $valor === null || $valor === '' ? null : (float) $valor];
    }

    /** Data/hora no formato do banco (Y-m-d H:i:s). */
    public static function data($valor): array
    {
        return ['__tipo' => 'data', 'v' => $valor];
    }

    /** Número vindo do banco como texto: devolve int/float ou ''. */
    public static function num($valor)
    {
        if ($valor === null || $valor === '' || !is_numeric($valor)) {
            return $valor ?? '';
        }
        return str_contains((string) $valor, '.') ? (float) $valor : (int) $valor;
    }

    /**
     * Abre uma aba nova. No CSV as abas saem uma embaixo da outra; com
     * $titulo_no_csv o nome da aba vira uma linha de título.
     */
    public function aba(string $titulo, bool $titulo_no_csv = false): self
    {
        $this->abas[] = [
            'titulo'        => $titulo,
            'linhas'        => [],
            'cabecalhos'    => [],
            'congelar'      => null,
            'titulo_no_csv' => $titulo_no_csv,
        ];
        $this->atual = count($this->abas) - 1;
        return $this;
    }

    /** Linha comum. */
    public function linha(array $celulas): self
    {
        $this->abas[$this->atual]['linhas'][] = array_values($celulas);
        return $this;
    }

    /**
     * Linha de cabeçalho (negrito e fundo cinza na planilha). Com
     * $congelar, a planilha mantém essa linha fixa ao rolar.
     */
    public function cabecalho(array $celulas, bool $congelar = false): self
    {
        $aba = &$this->abas[$this->atual];
        $aba['linhas'][] = array_values($celulas);
        $indice = count($aba['linhas']) - 1;
        $aba['cabecalhos'][] = $indice;
        if ($congelar && $aba['congelar'] === null) {
            $aba['congelar'] = $indice;
        }
        return $this;
    }

    /** Linha em branco. */
    public function vazia(): self
    {
        $this->abas[$this->atual]['linhas'][] = [];
        return $this;
    }

    /**
     * Envia o arquivo ao navegador e encerra.
     *
     * @param string $nome nome do arquivo, sem extensão
     */
    public function enviar(string $formato, string $nome): void
    {
        $formato = self::formato($formato);
        $arquivo = $nome . '.' . $formato;

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Content-Disposition: attachment; filename="' . $arquivo . '"');

        if ($formato === 'csv') {
            header('Content-Type: text/csv; charset=UTF-8');
            $this->gravarCsv();
            exit;
        }

        $planilha = $this->montarPlanilha();
        if ($formato === 'xlsx') {
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            (new Xlsx($planilha))->save('php://output');
        } else {
            header('Content-Type: application/vnd.oasis.opendocument.spreadsheet');
            (new Ods($planilha))->save('php://output');
        }
        exit;
    }

    // -----------------------------------------------------------------
    // CSV
    // -----------------------------------------------------------------

    private function gravarCsv(): void
    {
        // Mesmo separador configurado pelo usuário no GLPI, para o arquivo
        // abrir certo no Excel da máquina dele. Com ";" (padrão no Brasil)
        // o Excel espera vírgula como separador decimal.
        $sep     = $_SESSION['glpicsv_delimiter'] ?? ';';
        $decimal = $sep === ';' ? ',' : '.';

        $out = fopen('php://output', 'w');
        // BOM UTF-8: sem isso o Excel no Windows exibe acentuação quebrada.
        fwrite($out, "\xEF\xBB\xBF");

        foreach ($this->abas as $i => $aba) {
            if ($i > 0) {
                fputcsv($out, [], $sep);
            }
            if ($aba['titulo_no_csv']) {
                fputcsv($out, [$aba['titulo']], $sep);
            }
            foreach ($aba['linhas'] as $linha) {
                $texto = [];
                foreach ($linha as $celula) {
                    $texto[] = $this->celulaCsv($celula, $decimal);
                }
                fputcsv($out, $texto, $sep);
            }
        }

        fclose($out);
    }

    private function celulaCsv($celula, string $decimal): string
    {
        if (is_array($celula)) {
            if ($celula['__tipo'] === 'pct') {
                return $celula['v'] === null ? '' : $this->numeroCsv($celula['v'], $decimal) . '%';
            }
            return (string) ($celula['v'] ?? '');
        }
        if (is_float($celula)) {
            return $this->numeroCsv($celula, $decimal);
        }
        return (string) ($celula ?? '');
    }

    private function numeroCsv(float $valor, string $decimal): string
    {
        $texto = (string) round($valor, 2);
        return $decimal === ',' ? str_replace('.', ',', $texto) : $texto;
    }

    // -----------------------------------------------------------------
    // XLSX / ODS
    // -----------------------------------------------------------------

    private function montarPlanilha(): Spreadsheet
    {
        $planilha = new Spreadsheet();
        // Mesmo cuidado do GLPI: texto que parece fórmula fica texto.
        $planilha->setValueBinder(new SpreadsheetValueBinder());
        $planilha->getProperties()
            ->setCreator('NPSphere ' . (defined('PLUGIN_NPSPHERE_VERSION') ? PLUGIN_NPSPHERE_VERSION : ''))
            ->setTitle($this->abas[0]['titulo'] ?? 'NPSphere');
        $planilha->getDefaultStyle()->getFont()->setSize(10);
        $planilha->removeSheetByIndex(0);

        $usados = [];
        foreach ($this->abas as $aba) {
            $folha = $planilha->createSheet();
            $folha->setTitle($this->tituloDaAba($aba['titulo'], $usados));

            $larguras = [];
            foreach ($aba['linhas'] as $l => $linha) {
                $r = $l + 1;
                foreach ($linha as $c => $celula) {
                    $col = $c + 1;
                    $larguras[$col] = max($larguras[$col] ?? 0, $this->escreverCelula($folha, $col, $r, $celula));
                }
            }

            $ultimaColuna = max(1, count($larguras) ? max(array_keys($larguras)) : 1);
            $letraFinal   = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ultimaColuna);

            foreach ($aba['cabecalhos'] as $l) {
                $r = $l + 1;
                $estilo = $folha->getStyle("A{$r}:{$letraFinal}{$r}");
                $estilo->getFont()->setBold(true);
                $estilo->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE9ECEF');
            }

            // Larguras pelo conteúdo, com teto: textos longos (comentários)
            // quebram a linha em vez de criar uma coluna enorme.
            foreach ($larguras as $col => $tamanho) {
                $letra = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                $folha->getColumnDimension($letra)->setWidth(min(60, max(10, $tamanho + 2)));
                if ($tamanho > 60) {
                    $folha->getStyle("{$letra}:{$letra}")->getAlignment()->setWrapText(true);
                }
            }
            $folha->getStyle("A1:{$letraFinal}" . max(1, count($aba['linhas'])))
                ->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

            if ($aba['congelar'] !== null) {
                $folha->freezePane('A' . ($aba['congelar'] + 2));
            }
        }

        $planilha->setActiveSheetIndex(0);
        return $planilha;
    }

    /** Grava a célula e devolve a largura aproximada do conteúdo. */
    private function escreverCelula($folha, int $col, int $r, $celula): int
    {
        if (is_array($celula)) {
            $v = $celula['v'];
            if ($v === null || $v === '') {
                return 0;
            }
            if ($celula['__tipo'] === 'pct') {
                $folha->setCellValue([$col, $r], $v / 100);
                $folha->getStyle([$col, $r])->getNumberFormat()->setFormatCode('0.0%');
                return 7;
            }
            try {
                $quando = new \DateTime((string) $v);
            } catch (\Throwable $e) {
                $folha->setCellValueExplicit([$col, $r], (string) $v, DataType::TYPE_STRING);
                return mb_strlen((string) $v);
            }
            // DateTime preserva a hora como está no GLPI (sem converter fuso).
            $folha->setCellValue([$col, $r], ExcelDate::PHPToExcel($quando));
            $folha->getStyle([$col, $r])->getNumberFormat()->setFormatCode('dd/mm/yyyy hh:mm');
            return 16;
        }

        if (is_int($celula) || is_float($celula)) {
            $folha->setCellValue([$col, $r], $celula);
            if (is_float($celula)) {
                $folha->getStyle([$col, $r])->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
            }
            return mb_strlen((string) $celula);
        }

        $texto = (string) ($celula ?? '');
        if ($texto === '') {
            return 0;
        }
        $folha->setCellValueExplicit([$col, $r], $texto, DataType::TYPE_STRING);

        $maior = 0;
        foreach (explode("\n", $texto) as $parte) {
            $maior = max($maior, mb_strlen($parte));
        }
        return $maior;
    }

    /** Nome de aba válido: até 31 caracteres, sem []:*?/\ e sem repetir. */
    private function tituloDaAba(string $titulo, array &$usados): string
    {
        $base = trim(str_replace(['[', ']', ':', '*', '?', '/', '\\'], ' ', $titulo));
        $base = mb_substr($base !== '' ? $base : __('Planilha', 'npsphere'), 0, 31);
        $nome = $base;
        $n = 2;
        while (in_array(mb_strtolower($nome), $usados, true)) {
            $sufixo = ' (' . $n++ . ')';
            $nome = mb_substr($base, 0, 31 - mb_strlen($sufixo)) . $sufixo;
        }
        $usados[] = mb_strtolower($nome);
        return $nome;
    }
}
