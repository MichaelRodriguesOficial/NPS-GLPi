<?php

namespace GlpiPlugin\Npsphere;

/**
 * Assinatura dos links de resposta sem login (e-mail do convite).
 *
 * O link leva uma CHAVE: o HMAC-SHA256 de uma mensagem que identifica o
 * que ele autoriza ("responder a pesquisa X como a pessoa Y", "avaliar o
 * chamado Z"), feito com um segredo que só o servidor conhece. Quem
 * troca qualquer número da URL não consegue a chave certa.
 *
 * Assinatura, e não uma tabela de códigos: não há o que gravar nem
 * limpar, e convites antigos continuam valendo enquanto a pesquisa
 * puder ser respondida.
 */
class LinkSigner
{
    /** Segredo, criado na primeira vez e guardado na configuração do plugin. */
    private static function segredo(): string
    {
        $atual   = \Config::getConfigurationValues('plugin:npsphere', ['chave_assinatura']);
        $segredo = (string) ($atual['chave_assinatura'] ?? '');

        if (strlen($segredo) < 64) {
            $segredo = bin2hex(random_bytes(32));
            \Config::setConfigurationValues('plugin:npsphere', ['chave_assinatura' => $segredo]);
        }

        return $segredo;
    }

    public static function assinar(string $mensagem): string
    {
        return hash_hmac('sha256', $mensagem, self::segredo());
    }

    /** A chave confere com a mensagem? Comparação em tempo constante. */
    public static function confere($chave, string $mensagem): bool
    {
        $ok = is_string($chave)
            && preg_match('/^[a-f0-9]{64}$/', $chave) === 1
            && hash_equals(self::assinar($mensagem), $chave);

        // v0.65.0: chave errada conta para o bloqueio por IP (RateLimit).
        if (!$ok) {
            RateLimit::registrarFalha();
        }

        return $ok;
    }
}
