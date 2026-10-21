<?php

namespace GlpiPlugin\Npsphere;

/**
 * Espelha a nota do plugin na pesquisa de satisfação NATIVA do GLPI.
 *
 * Por que: sem isso, a avaliação existe apenas dentro do plugin, e as
 * estatísticas nativas do GLPI (Estatísticas > Satisfação, relatórios,
 * dashboards) ficam vazias -- como se os chamados nunca tivessem sido
 * avaliados. Gravando também em `glpi_ticketsatisfactions`, o ciclo se
 * fecha nos dois lados.
 *
 * O que NÃO é feito: apagar esses registros. A nota gravada ali passa a
 * ser um dado do GLPI, e desinstalar o plugin não pode levá-la embora
 * -- ver plugin_npsphere_uninstall(), que não toca nessa tabela.
 */
class GlpiSatisfaction
{
    /**
     * Grava (ou atualiza) a satisfação nativa do chamado.
     *
     * A nota do plugin é normalizada em 0–10 com casas decimais; o GLPI
     * trabalha com um inteiro na escala configurada na entidade. Por
     * isso a conversão arredonda -- e o valor com decimais continua
     * disponível no plugin, que é onde a precisão importa.
     */
    public static function sync(int $tickets_id, ?array $score, string $comment = ''): bool
    {
        global $DB;

        if ($tickets_id <= 0 || $score === null || !isset($score['score'])) {
            return self::registrar('sem nota a sincronizar', $tickets_id);
        }

        if (!class_exists('\\TicketSatisfaction') || !class_exists('\\Ticket')) {
            return self::registrar('classes de satisfação do GLPI indisponíveis', $tickets_id);
        }

        $ticket = new \Ticket();
        if (!$ticket->getFromDB($tickets_id)) {
            return self::registrar('chamado não encontrado', $tickets_id);
        }

        // A nota é gravada SEMPRE na escala 0–10, que é a do plugin.
        //
        // Antes eu convertia para a escala configurada na entidade
        // (inquest_max_rate) -- e isso descaracterizava a avaliação: uma
        // nota 8 virava 4, indistinguível de quem deu 4 de verdade. A
        // escala de origem é 0–10 e é assim que ela deve ser registrada.
        //
        // O arredondamento é para BAIXO no meio exato (9,5 → 9), para a
        // nota nunca ficar melhor do que a pessoa deu.
        $nota = (int) round((float) $score['score'], 0, PHP_ROUND_HALF_DOWN);
        $nota = max(0, min(10, $nota));

        // satisfaction_scaled_to_5 segue a fórmula do próprio GLPI:
        //
        //     satisfaction × 5 / max_rate
        //
        // com max_rate = 10, que é a escala em que a nota foi gravada.
        // Gravado explicitamente porque o GLPI só o calcula no caminho
        // de atualização pela API dele, que não é o usado aqui.
        $scaled = round(($nota * 5) / 10, 4);

        $agora = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');

        $dados = [
            'satisfaction'             => $nota,
            'satisfaction_scaled_to_5' => $scaled,
            'date_answered'            => $agora,
        ];

        // O comentário só é enviado quando existe: um valor vazio
        // apagaria um comentário anterior sem motivo.
        if ($comment !== '') {
            $dados['comment'] = $comment;
        }

        // A gravação é feita pelo TICKETS_ID, e não pelo id da linha.
        //
        // Por quê: TicketSatisfaction usa `tickets_id` como chave de
        // índice, então getID() devolve o número do CHAMADO, e não a
        // chave primária da tabela. Tentar "WHERE id = <getID()>" não
        // casava com linha nenhuma -- e como o DB->update devolve
        // sucesso mesmo sem afetar linhas, a falha se disfarçava de
        // gravação bem-sucedida.
        //
        // Gravar pelo tickets_id elimina a ambiguidade: é a coluna que
        // identifica a avaliação daquele chamado, e é única.
        if (!self::existeRegistro($tickets_id)) {
            // Sem registro nativo (pesquisa criada apenas pelo plugin):
            // cria um pela API, que traz o comportamento completo.
            $satisfaction = new \TicketSatisfaction();

            $dados['tickets_id'] = $tickets_id;
            $dados['type']       = self::getTipoExterno();
            $dados['date_begin'] = $agora;

            if ($satisfaction->add($dados) && self::gravou($tickets_id, $nota)) {
                return true;
            }

            return self::registrar('falha ao criar a satisfação', $tickets_id);
        }

        $ok = false;

        try {
            $ok = (bool) $DB->update('glpi_ticketsatisfactions', $dados, ['tickets_id' => $tickets_id]);
        } catch (\Throwable $e) {
            return self::registrar('erro do banco ao gravar: ' . $e->getMessage(), $tickets_id);
        }

        if (!$ok && method_exists($DB, 'error')) {
            $erro = (string) $DB->error();
            if ($erro !== '') {
                return self::registrar('banco recusou a gravação: ' . $erro, $tickets_id);
            }
        }

        // Só reporta sucesso o que estiver DE FATO no banco: o retorno
        // da chamada não é prova de gravação.
        if (self::gravou($tickets_id, $nota)) {
            return true;
        }

        return self::registrar('a nota não persistiu após a gravação', $tickets_id);
    }

    /**
     * Já existe registro de satisfação para este chamado?
     */
    private static function existeRegistro(int $tickets_id): bool
    {
        global $DB;

        if (!$DB->tableExists('glpi_ticketsatisfactions')) {
            return false;
        }

        $linha = $DB->request([
            'COUNT' => 'cpt',
            'FROM'  => 'glpi_ticketsatisfactions',
            'WHERE' => ['tickets_id' => $tickets_id],
        ])->current();

        return is_array($linha) && (int) ($linha['cpt'] ?? 0) > 0;
    }


    /**
     * A nota está mesmo no banco?
     *
     * Existe porque "a chamada retornou sucesso" e "o dado foi gravado"
     * são coisas diferentes -- e nesta integração a diferença já
     * apareceu na prática.
     */
    /**
     * A nota está mesmo no banco?
     *
     * Consulta pelo TICKETS_ID, a mesma chave usada na gravação --
     * verificar por outra coluna reintroduziria a divergência que
     * causou o problema.
     *
     * Existe porque "a chamada retornou sucesso" e "o dado foi gravado"
     * são coisas diferentes, e nesta integração a diferença apareceu na
     * prática mais de uma vez.
     */
    private static function gravou(int $tickets_id, int $nota_esperada): bool
    {
        global $DB;

        if ($tickets_id <= 0 || !$DB->tableExists('glpi_ticketsatisfactions')) {
            return false;
        }

        foreach ($DB->request([
            'SELECT' => ['satisfaction'],
            'FROM'   => 'glpi_ticketsatisfactions',
            'WHERE'  => ['tickets_id' => $tickets_id],
        ]) as $row) {
            $armazenado = $row['satisfaction'];

            if ($armazenado !== null && (int) $armazenado === $nota_esperada) {
                return true;
            }

            // Registra o valor ENCONTRADO: "não persistiu" pode
            // significar campo nulo, valor de gravação anterior ou
            // conversão diferente -- e cada caso leva a uma
            // investigação distinta.
            self::registrar(
                sprintf(
                    'nota divergente: esperado %d, encontrado %s',
                    $nota_esperada,
                    $armazenado === null ? 'NULL' : (string) $armazenado
                ),
                $tickets_id
            );

            return false;
        }

        self::registrar('nenhum registro de satisfação para este chamado', $tickets_id);
        return false;
    }


    /**
     * Registra o motivo de a sincronização não acontecer.
     *
     * Antes tudo falhava em SILÊNCIO: a nota não aparecia no GLPI e não
     * havia nada em lugar nenhum indicando por quê. Agora o motivo vai
     * para o log do GLPI (arquivo npsphere.log), que é onde se procura.
     */
    private static function registrar(string $motivo, int $tickets_id): bool
    {
        self::logar('FALHA', $motivo, $tickets_id);
        return false;
    }

    private static function logar(string $nivel, string $mensagem, int $tickets_id): void
    {
        if (class_exists('\\Toolbox') && method_exists('\\Toolbox', 'logInFile')) {
            \Toolbox::logInFile(
                'npsphere',
                sprintf("[%s] Satisfação do GLPI (chamado %d): %s\n", $nivel, $tickets_id, $mensagem)
            );
        }
    }


    private static function getTipoExterno(): int
    {
        if (class_exists('\\CommonITILSatisfaction')
            && defined('\\CommonITILSatisfaction::TYPE_EXTERNAL')) {
            return (int) \CommonITILSatisfaction::TYPE_EXTERNAL;
        }

        return 2; // valor documentado para TYPE_EXTERNAL
    }
}
