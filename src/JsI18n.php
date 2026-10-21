<?php

namespace GlpiPlugin\Npsphere;

/**
 * Tradução dos textos dos arquivos JS do plugin (v0.65.0).
 *
 * Os JS escrevem os textos em português dentro de t('...') (ver o início
 * de public/js/survey-builder.js). Aqui o servidor monta o dicionário
 * texto original -> texto traduzido no idioma do usuário e o entrega
 * numa <meta name="npsphere-i18n"> no <head> de todas as páginas.
 *
 * Por que não o i18n do próprio GLPI no navegador: ele baixa as
 * traduções depois que a página abre (e não existe nas páginas sem
 * login, como o link público). Os botões montados logo na abertura
 * ("Anterior", "Próxima") sairiam em português.
 *
 * Em português o dicionário fica vazio e a meta nem é gerada.
 */
final class JsI18n
{
    /** Arquivos com textos t('...'). */
    private const ARQUIVOS = ['public/js/survey-builder.js', 'public/js/pending-survey.js'];

    /** Registra a meta no cabeçalho (hook post_init: traduções já carregadas). */
    public static function registrarCabecalho(): void
    {
        global $PLUGIN_HOOKS;

        $meta = self::metaPropriedades();
        if ($meta === null) {
            return;
        }

        // Inclui o link público e as páginas do e-mail: elas usam o
        // cabeçalho comum (Html::nullHeader), não o de "página anônima".
        $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::ADD_HEADER_TAG]['npsphere'][] = ['tag' => 'meta', 'properties' => $meta];
    }

    /** A mesma meta, em HTML, para páginas montadas à mão (iframe do chamado). */
    public static function metaHtml(): string
    {
        $meta = self::metaPropriedades();
        if ($meta === null) {
            return '';
        }

        return '<meta name="npsphere-i18n" content="' . htmlspecialchars($meta['content'], ENT_QUOTES) . '">';
    }

    /** Idioma da página para o atributo lang do <html> (pt_BR -> pt-BR). */
    public static function langHtml(): string
    {
        global $CFG_GLPI;

        $lang = $_SESSION['glpilanguage'] ?? ($CFG_GLPI['language'] ?? 'pt_BR');

        return str_replace('_', '-', (string) $lang);
    }

    /** @return array{name: string, content: string}|null */
    private static function metaPropriedades(): ?array
    {
        $dicionario = self::dicionario();
        if ($dicionario === []) {
            return null;
        }

        return [
            'name'    => 'npsphere-i18n',
            'content' => json_encode($dicionario, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }

    /**
     * Texto original => traduzido, só dos que mudam no idioma atual.
     *
     * @return array<string, string>
     */
    public static function dicionario(): array
    {
        $dicionario = [];
        foreach (self::textos() as $texto) {
            $traduzido = __($texto, 'npsphere');
            if ($traduzido !== $texto) {
                $dicionario[$texto] = $traduzido;
            }
        }

        return $dicionario;
    }

    /**
     * Textos t('...') dos JS. Lidos dos próprios arquivos, para não ter
     * uma segunda lista para manter; guardados no cache do GLPI por
     * versão do plugin.
     *
     * @return string[]
     */
    private static function textos(): array
    {
        global $GLPI_CACHE;

        static $textos = null;
        if ($textos !== null) {
            return $textos;
        }

        $chave = 'npsphere_js_i18n_' . md5(PLUGIN_NPSPHERE_VERSION);
        try {
            $guardado = isset($GLPI_CACHE) ? $GLPI_CACHE->get($chave) : null;
            if (is_array($guardado)) {
                return $textos = $guardado;
            }
        } catch (\Throwable $e) {
            // sem cache: lê os arquivos
        }

        $raiz = dirname(__DIR__);
        $lista = [];
        foreach (self::ARQUIVOS as $arquivo) {
            $codigo = @file_get_contents($raiz . '/' . $arquivo);
            if ($codigo === false) {
                continue;
            }
            preg_match_all("/(?<![\\w$.])t\\(\\s*'((?:\\\\.|[^'\\\\])*)'/u", $codigo, $m);
            foreach ($m[1] as $texto) {
                $lista[] = stripcslashes($texto);
            }
        }
        $textos = array_values(array_unique($lista));

        try {
            if (isset($GLPI_CACHE)) {
                $GLPI_CACHE->set($chave, $textos);
            }
        } catch (\Throwable $e) {
            // ignora
        }

        return $textos;
    }
}
