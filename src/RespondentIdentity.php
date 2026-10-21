<?php

namespace GlpiPlugin\Npsphere;

/**
 * Identificação do respondente em pesquisas públicas NÃO anônimas.
 *
 * Só se aplica quando a pesquisa é pública (link sem login) E não é
 * anônima: no fluxo autenticado o GLPI já sabe quem é o usuário, e numa
 * pesquisa anônima identificar seria contraditório com a proposta.
 */
class RespondentIdentity
{
    /**
     * Países mais comuns primeiro, depois ordem alfabética.
     * Formato: código ISO => [bandeira, código telefônico, nome, dígitos esperados]
     *
     * "digits" é a quantidade de dígitos do número APÓS o código do
     * país, usada para validação e para a máscara. Onde o país aceita
     * mais de um comprimento, todos são listados.
     */
    public const COUNTRIES = [
        'BR' => ['🇧🇷', '55',  'Brasil',          [10, 11]],
        'PT' => ['🇵🇹', '351', 'Portugal',        [9]],
        'US' => ['🇺🇸', '1',   'Estados Unidos',  [10]],
        'AR' => ['🇦🇷', '54',  'Argentina',       [10]],
        'CL' => ['🇨🇱', '56',  'Chile',           [9]],
        'CO' => ['🇨🇴', '57',  'Colômbia',        [10]],
        'MX' => ['🇲🇽', '52',  'México',          [10]],
        'PY' => ['🇵🇾', '595', 'Paraguai',        [9]],
        'UY' => ['🇺🇾', '598', 'Uruguai',         [8]],
        'AO' => ['🇦🇴', '244', 'Angola',          [9]],
        'CA' => ['🇨🇦', '1',   'Canadá',          [10]],
        'DE' => ['🇩🇪', '49',  'Alemanha',        [10, 11]],
        'ES' => ['🇪🇸', '34',  'Espanha',         [9]],
        'FR' => ['🇫🇷', '33',  'França',          [9]],
        'GB' => ['🇬🇧', '44',  'Reino Unido',     [10]],
        'IT' => ['🇮🇹', '39',  'Itália',          [9, 10]],
        'JP' => ['🇯🇵', '81',  'Japão',           [10]],
        'MZ' => ['🇲🇿', '258', 'Moçambique',      [9]],
    ];

    /**
     * Esta pesquisa deve pedir identificação do respondente?
     */
    public static function isRequiredFor(Survey $survey): bool
    {
        return !empty($survey->fields['require_identification'])
            && !empty($survey->fields['allow_public_access'])
            && empty($survey->fields['is_anonymous']);
    }

    /**
     * Valida os dados enviados.
     *
     * @return array{valid: bool, errors: string[], data: array}
     */
    public static function validate(array $post): array
    {
        $errors = [];

        $name = trim((string) ($post['respondent_name'] ?? ''));
        if ($name === '') {
            $errors[] = __('Informe seu nome.', 'npsphere');
        } elseif (mb_strlen($name) > 255) {
            $errors[] = __('O nome informado é longo demais.', 'npsphere');
        }

        $email = trim((string) ($post['respondent_email'] ?? ''));
        if ($email === '') {
            $errors[] = __('Informe seu e-mail.', 'npsphere');
        } elseif (!self::isValidEmail($email)) {
            $errors[] = __('O e-mail informado não é válido.', 'npsphere');
        }

        $country = strtoupper(trim((string) ($post['respondent_phone_country'] ?? 'BR')));
        if (!isset(self::COUNTRIES[$country])) {
            $country = 'BR';
        }
        // Guarda só os dígitos: o que o usuário digitou pode vir com
        // parênteses, espaços e traços da máscara visual.
        $digits = preg_replace('/\D+/', '', (string) ($post['respondent_phone'] ?? ''));

        if ($digits === '') {
            $errors[] = __('Informe seu telefone.', 'npsphere');
        } else {
            [$flag, $dial, $label, $lengths] = self::COUNTRIES[$country];
            $label = self::nomePais($country);
            if (!in_array(strlen($digits), $lengths, true)) {
                $errors[] = sprintf(
                    __('O telefone informado não tem a quantidade de dígitos esperada para %s (%s).', 'npsphere'),
                    $label,
                    implode(__(' ou ', 'npsphere'), $lengths)
                );
            }
        }

        $phone = '';
        if ($digits !== '') {
            $phone = '+' . self::COUNTRIES[$country][1] . $digits;
        }

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
            'data'   => [
                'respondent_name'  => $name,
                'respondent_email' => $email,
                'respondent_phone' => $phone,
            ],
        ];
    }

    /**
     * Valida um endereço de e-mail para uso em formulário público.
     *
     * O filter_var() do PHP sozinho NÃO basta: ele considera válido um
     * endereço sem ponto no domínio, como "fulano@q" ou "fulano@local".
     * Isso é correto pela especificação (esses endereços existem em
     * redes internas), mas para um formulário público é quase sempre
     * erro de digitação -- e um e-mail assim nunca receberia resposta.
     *
     * Por isso exigimos, além do filter_var:
     *  - pelo menos um ponto no domínio;
     *  - domínio de topo com 2 ou mais letras (.com, .br, .com.br...);
     *  - sem pontos consecutivos e sem ponto no início/fim do domínio.
     */
    public static function isValidEmail(string $email): bool
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $at = strrpos($email, '@');
        if ($at === false) {
            return false;
        }

        $domain = substr($email, $at + 1);

        if (strpos($domain, '.') === false) {
            return false; // "fulano@q" -- sem domínio de topo
        }
        if (strpos($domain, '..') !== false) {
            return false; // pontos consecutivos
        }
        if ($domain[0] === '.' || substr($domain, -1) === '.') {
            return false; // ponto no início ou no fim
        }

        // Domínio de topo: apenas letras, no mínimo duas
        $tld = substr($domain, strrpos($domain, '.') + 1);
        return (bool) preg_match('/^[a-zA-Z]{2,}$/', $tld);
    }

    /** Nome do país no idioma do usuário (v0.65.0). */
    public static function nomePais(string $iso): string
    {
        return match ($iso) {
            'BR' => __('Brasil', 'npsphere'),
            'PT' => __('Portugal', 'npsphere'),
            'US' => __('Estados Unidos', 'npsphere'),
            'AR' => __('Argentina', 'npsphere'),
            'CL' => __('Chile', 'npsphere'),
            'CO' => __('Colômbia', 'npsphere'),
            'MX' => __('México', 'npsphere'),
            'PY' => __('Paraguai', 'npsphere'),
            'UY' => __('Uruguai', 'npsphere'),
            'AO' => __('Angola', 'npsphere'),
            'CA' => __('Canadá', 'npsphere'),
            'DE' => __('Alemanha', 'npsphere'),
            'ES' => __('Espanha', 'npsphere'),
            'FR' => __('França', 'npsphere'),
            'GB' => __('Reino Unido', 'npsphere'),
            'IT' => __('Itália', 'npsphere'),
            'JP' => __('Japão', 'npsphere'),
            'MZ' => __('Moçambique', 'npsphere'),
            default => (string) (self::COUNTRIES[$iso][2] ?? $iso),
        };
    }

    /**
     * Lista de países no formato usado pelo formulário/JS.
     */
    public static function getCountriesForForm(): array
    {
        $out = [];
        foreach (self::COUNTRIES as $iso => [$flag, $dial, $label, $lengths]) {
            $out[] = [
                'iso'     => $iso,
                'flag'    => $flag,
                'dial'    => $dial,
                'label'   => self::nomePais($iso),
                'lengths' => $lengths,
            ];
        }
        return $out;
    }
}
