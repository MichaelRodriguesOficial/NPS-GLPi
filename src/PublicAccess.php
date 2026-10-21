<?php

namespace GlpiPlugin\Npsphere;

/**
 * Acesso público a uma pesquisa por token (seção 33 do escopo).
 *
 * A página que usa esta classe roda SEM autenticação (liberada no
 * setup.php via Firewall::STRATEGY_NO_CHECK), então toda a autorização
 * acontece aqui. Regras aplicadas:
 *
 *  - o token é comparado em tempo constante (hash_equals), evitando
 *    ataques de temporização que poderiam revelá-lo caractere a caractere;
 *  - só abre pesquisas que estão ativas, dentro do prazo E com o acesso
 *    público explicitamente habilitado -- desabilitar a opção fecha o
 *    link imediatamente, sem precisar trocar o token;
 *  - há limite de tentativas por sessão, para dificultar varredura de
 *    tokens por força bruta.
 */
class PublicAccess
{
    /** Tentativas com token inválido toleradas antes de bloquear */
    private const MAX_ATTEMPTS = 10;

    /** Janela do bloqueio, em segundos */
    private const LOCKOUT_SECONDS = 300;

    /**
     * Localiza a pesquisa correspondente ao token, se o acesso for
     * permitido agora.
     *
     * @return array{survey: ?Survey, error: ?string}
     */
    public static function resolve(?string $token): array
    {
        global $DB;

        if (self::isLockedOut()) {
            return self::fail(__('Muitas tentativas inválidas. Tente novamente mais tarde.', 'npsphere'));
        }

        $token = trim((string) $token);
        // Os tokens são gerados com bin2hex(random_bytes(32)) => 64
        // caracteres hexadecimais. Descartar de imediato tudo que não
        // tem esse formato evita consultas desnecessárias ao banco.
        if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            self::registerFailedAttempt();
            return self::fail(__('Link inválido.', 'npsphere'));
        }

        $found = null;
        $iterator = $DB->request([
            'FROM'  => Survey::getTable(),
            'WHERE' => [
                'allow_public_access' => 1,
                'is_active'           => 1,
                'is_deleted'          => 0,
            ],
        ]);

        foreach ($iterator as $row) {
            // hash_equals: comparação em tempo constante.
            if (!empty($row['public_token']) && hash_equals((string) $row['public_token'], $token)) {
                $found = $row;
                break;
            }
        }

        if ($found === null) {
            self::registerFailedAttempt();
            return self::fail(__('Link inválido ou expirado.', 'npsphere'));
        }

        $survey = new Survey();
        $survey->fields = $found;

        // Reaproveita exatamente as mesmas regras de elegibilidade do
        // fluxo autenticado (pesquisa ativa, dentro do prazo...) -- ter
        // duas listas de regras separadas seria garantia de divergência.
        $eligibility = ResponseService::canRespond($survey);
        if (!$eligibility['can']) {
            return self::fail($eligibility['reason']);
        }

        return ['survey' => $survey, 'error' => null];
    }

    private static function fail(string $message): array
    {
        return ['survey' => null, 'error' => $message];
    }

    private static function isLockedOut(): bool
    {
        $until = $_SESSION['npsphere_public_lockout'] ?? 0;
        return $until > time();
    }

    private static function registerFailedAttempt(): void
    {
        $attempts = (int) ($_SESSION['npsphere_public_attempts'] ?? 0) + 1;
        $_SESSION['npsphere_public_attempts'] = $attempts;

        if ($attempts >= self::MAX_ATTEMPTS) {
            $_SESSION['npsphere_public_lockout'] = time() + self::LOCKOUT_SECONDS;
            $_SESSION['npsphere_public_attempts'] = 0;
        }
    }

    /**
     * Alguém com esta identidade já respondeu a pesquisa?
     *
     * O controle por sessão (markAnswered) só protege contra reenvio no
     * MESMO navegador -- abrir uma aba anônima ou trocar de navegador
     * driblava a restrição. Quando a pesquisa exige identificação,
     * passamos a checar também no BANCO, por e-mail ou telefone.
     *
     * O e-mail é comparado sem diferenciar maiúsculas/minúsculas, e o
     * telefone apenas pelos dígitos -- "(11) 98765-4321" e
     * "+5511987654321" são a mesma pessoa.
     */
    public static function hasAnsweredByIdentity(int $surveys_id, string $email, string $phone): bool
    {
        global $DB;

        $email = trim(mb_strtolower($email));
        $phone_digits = preg_replace('/\D+/', '', $phone);

        if ($email === '' && $phone_digits === '') {
            return false;
        }

        $iterator = $DB->request([
            'SELECT' => ['respondent_email', 'respondent_phone'],
            'FROM'   => Answer::getTable(),
            'WHERE'  => ['surveys_id' => $surveys_id],
        ]);

        foreach ($iterator as $row) {
            $row_email = trim(mb_strtolower((string) ($row['respondent_email'] ?? '')));
            if ($email !== '' && $row_email !== '' && $row_email === $email) {
                return true;
            }

            $row_phone = preg_replace('/\D+/', '', (string) ($row['respondent_phone'] ?? ''));
            if (self::samePhone($phone_digits, $row_phone)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Dois telefones são a mesma pessoa?
     *
     * Compara pelo FINAL do número, porque o valor salvo inclui o
     * código do país ("5511987654321") e o que a pessoa digita
     * normalmente não ("11987654321"). Exigimos pelo menos 8 dígitos em
     * comum para evitar falso positivo entre números curtos diferentes.
     */
    private static function samePhone(string $a, string $b): bool
    {
        $min = 8;
        if (strlen($a) < $min || strlen($b) < $min) {
            return false;
        }

        $len = min(strlen($a), strlen($b));
        return substr($a, -$len) === substr($b, -$len);
    }

    /**
     * Marca que esta sessão já respondeu a pesquisa.
     *
     * Num acesso anônimo não há usuário para checar no banco, então o
     * controle de "responder só uma vez" é por sessão. É uma proteção
     * contra reenvio acidental (recarregar a página), não uma garantia
     * absoluta -- e isso está documentado no README, para não passar
     * uma falsa sensação de segurança.
     */
    public static function markAnswered(int $surveys_id): void
    {
        $_SESSION['npsphere_public_answered'][$surveys_id] = true;
    }

    public static function hasAnsweredInSession(int $surveys_id): bool
    {
        return !empty($_SESSION['npsphere_public_answered'][$surveys_id]);
    }
}
