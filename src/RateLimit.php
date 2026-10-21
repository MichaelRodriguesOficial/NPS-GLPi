<?php

namespace GlpiPlugin\Npsphere;

use Glpi\Application\View\TemplateRenderer;
use Symfony\Component\Cache\Adapter\Psr16Adapter;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

/**
 * Limite de requisições por tempo nas páginas que abrem SEM login
 * (link público, links do e-mail da pesquisa interna e do chamado).
 *
 * Usa o mesmo mecanismo do GLPI 11 no código de verificação em duas
 * etapas (TOTPManager): Symfony RateLimiter com janela deslizante,
 * guardado no cache do GLPI. Vale entre sessões -- apagar o cookie não
 * zera a contagem, como acontecia com o bloqueio antigo, só por sessão.
 *
 * Três contadores, por endereço IP:
 *  - "pagina": abrir a pesquisa e pedir token do formulário;
 *  - "envio" : gravar resposta (enviar o formulário ou clicar no e-mail);
 *  - "falha" : link, chave ou token inválido. Estourou, o IP fica
 *              bloqueado em TODAS as rotas sem login até a janela passar
 *              (dificulta varrer tokens por força bruta).
 *
 * Os limites são folgados para a situação comum de uma empresa inteira
 * atrás do mesmo IP respondendo logo depois de um envio em massa. Podem
 * ser trocados em config/local_define.php, por exemplo:
 *
 *     define('NPSPHERE_LIMITE_PAGINA', 600);  // por minuto
 *     define('NPSPHERE_LIMITE_ENVIO', 300);   // por minuto
 *     define('NPSPHERE_LIMITE_FALHA', 50);    // a cada 10 minutos
 *
 * Valor 0 desliga aquele contador.
 */
final class RateLimit
{
    public const PAGINA = 'pagina';
    public const ENVIO  = 'envio';
    public const FALHA  = 'falha';

    /** @var array<string, array{0:int, 1:string, 2:string}> limite padrão, janela, constante */
    private const POLITICAS = [
        self::PAGINA => [240, '1 minute', 'NPSPHERE_LIMITE_PAGINA'],
        self::ENVIO  => [120, '1 minute', 'NPSPHERE_LIMITE_ENVIO'],
        self::FALHA  => [20, '10 minutes', 'NPSPHERE_LIMITE_FALHA'],
    ];

    /**
     * Chamada no topo de cada rota sem login: barra o IP bloqueado por
     * falhas e conta mais uma requisição no contador indicado.
     *
     * @param bool $json responde 429 sem página (rotas AJAX)
     */
    public static function proteger(string $contador, bool $json = false): void
    {
        $espera = self::bloqueadoPorFalhas();
        if ($espera === null) {
            $espera = self::consumir($contador);
        }

        if ($espera !== null) {
            self::negar($espera, $json);
        }
    }

    /** Registra um link/chave/token inválido vindo deste IP. */
    public static function registrarFalha(): void
    {
        self::consumir(self::FALHA);
    }

    /**
     * Segundos até liberar, se o IP está bloqueado por falhas; null se
     * está liberado.
     */
    public static function bloqueadoPorFalhas(): ?int
    {
        $limitador = self::limitador(self::FALHA);
        if ($limitador === null) {
            return null;
        }

        // consume(0) só consulta: quantas ainda restam na janela.
        $estado = $limitador->consume(0);
        if ($estado->getRemainingTokens() > 0) {
            return null;
        }

        return max(1, $estado->getRetryAfter()->getTimestamp() - time());
    }

    /** Consome uma unidade; devolve os segundos de espera se estourou. */
    private static function consumir(string $contador): ?int
    {
        $limitador = self::limitador($contador);
        if ($limitador === null) {
            return null;
        }

        $estado = $limitador->consume(1);
        if ($estado->isAccepted()) {
            return null;
        }

        return max(1, $estado->getRetryAfter()->getTimestamp() - time());
    }

    private static function limitador(string $contador): ?LimiterInterface
    {
        global $GLPI_CACHE;

        if (!isset(self::POLITICAS[$contador]) || !isset($GLPI_CACHE)) {
            return null;
        }

        [$limite, $janela, $constante] = self::POLITICAS[$contador];
        if (defined($constante)) {
            $limite = (int) constant($constante);
        }
        if ($limite <= 0) {
            return null;
        }

        try {
            $fabrica = new RateLimiterFactory(
                [
                    'id'       => 'npsphere_' . $contador,
                    'policy'   => 'sliding_window',
                    'limit'    => $limite,
                    'interval' => $janela,
                ],
                new CacheStorage(new Psr16Adapter($GLPI_CACHE))
            );

            return $fabrica->create(self::ip());
        } catch (\Throwable $e) {
            // Falha no cache não pode derrubar a pesquisa: sem limite.
            return null;
        }
    }

    /**
     * IP do visitante. Só REMOTE_ADDR: cabeçalhos como X-Forwarded-For
     * podem ser forjados por quem quer escapar do limite.
     */
    private static function ip(): string
    {
        return 'ip_' . hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'desconhecido'));
    }

    /** Responde 429 e encerra. */
    private static function negar(int $segundos, bool $json): void
    {
        http_response_code(429);
        header('Retry-After: ' . $segundos);
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        $minutos = max(1, (int) ceil($segundos / 60));
        $mensagem = sprintf(
            _n(
                'Muitas tentativas em pouco tempo. Tente novamente em %d minuto.',
                'Muitas tentativas em pouco tempo. Tente novamente em %d minutos.',
                $minutos,
                'npsphere'
            ),
            $minutos
        );

        if ($json) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => $mensagem, 'retry_after' => $segundos]);
            exit;
        }

        \Html::nullHeader(__('Pesquisa de satisfação', 'npsphere'));
        TemplateRenderer::getInstance()->display('@npsphere/public/message.html.twig', [
            'title'   => __('Aguarde um pouco', 'npsphere'),
            'message' => $mensagem,
            'icon'    => 'ti ti-clock-pause',
        ]);
        \Html::nullFooter();
        exit;
    }
}
