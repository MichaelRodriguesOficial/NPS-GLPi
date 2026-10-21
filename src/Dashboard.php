<?php

namespace GlpiPlugin\Npsphere;

/**
 * Agregação consolidada para o painel (Fase 10).
 *
 * Diferente de MetricsCalculator, que responde sobre UMA pesquisa, aqui
 * o recorte é o conjunto: várias pesquisas, um período, e cruzamentos
 * por técnico, categoria e entidade.
 *
 * Os cruzamentos por técnico e categoria não exigiram coluna nova: o
 * `answers.tickets_id`, gravado na Fase 8, permite chegar ao chamado e,
 * dele, a quem atendeu e em que categoria.
 */
class Dashboard
{
    /**
     * Registros de nota: UM POR VALOR respondido, e não por resposta.
     *
     * É a mesma granularidade da aba Resultados. Uma versão anterior
     * pré-agregava por resposta (média das perguntas de cada pessoa) e
     * calculava o NPS sobre essa média -- uma definição diferente da
     * usada nos Resultados, que classifica cada nota de pergunta NPS na
     * própria escala. Com os mesmos dados, os Resultados mostravam -6 e
     * o painel +28.
     */
    public static function getScores(array $filtros = []): array
    {
        global $DB;

        $answers   = Answer::getTable();
        $values    = AnswerValue::getTable();
        $questions = Question::getTable();

        $where = [
            "$questions.type" => ['nps', 'stars', 'emoticon'],
            ['NOT' => ["$values.numeric_value" => null]],
        ];

        if (!empty($filtros['surveys'])) {
            $where["$answers.surveys_id"] = $filtros['surveys'];
        }
        if (!empty($filtros['entities'])) {
            $where["$answers.entities_id"] = $filtros['entities'];
        }
        if (!empty($filtros['date_from'])) {
            $where[] = ["$answers.date_creation" => ['>=', $filtros['date_from'] . ' 00:00:00']];
        }
        if (!empty($filtros['date_to'])) {
            $where[] = ["$answers.date_creation" => ['<=', $filtros['date_to'] . ' 23:59:59']];
        }

        $saida = [];

        foreach ($DB->request([
            'SELECT' => [
                "$answers.id AS answers_id",
                "$answers.entities_id AS entities_id",
                "$answers.tickets_id AS tickets_id",
                "$answers.date_creation AS date_creation",
                "$answers.surveys_id AS surveys_id",
                "$values.numeric_value AS numeric_value",
                "$questions.type AS qtype",
                "$questions.configuration AS qconfig",
            ],
            'FROM'       => $values,
            'INNER JOIN' => [
                $answers   => ['ON' => [$values => 'answers_id', $answers => 'id']],
                $questions => ['ON' => [$values => 'questions_id', $questions => 'id']],
            ],
            'WHERE' => $where,
        ]) as $row) {
            $max = self::getEscala((string) $row['qtype'], (string) ($row['qconfig'] ?? '{}'));
            if ($max <= 0) {
                continue;
            }

            $saida[] = [
                'answers_id'  => (int) $row['answers_id'],
                'surveys_id'  => (int) $row['surveys_id'],
                'entities_id' => (int) $row['entities_id'],
                'tickets_id'  => (int) $row['tickets_id'],
                'date'        => (string) $row['date_creation'],
                'type'        => (string) $row['qtype'],
                'value'       => (float) $row['numeric_value'],
                'max'         => $max,
            ];
        }

        return $saida;
    }

    /**
     * Escala máxima de uma pergunta, pela sua configuração.
     */
    private static function getEscala(string $tipo, string $configuracao): int
    {
        $cfg = json_decode($configuracao, true);
        if (!is_array($cfg)) {
            $cfg = [];
        }

        switch ($tipo) {
            case 'nps':
                return (int) ($cfg['scale'] ?? 10);
            case 'stars':
                return (int) ($cfg['max'] ?? 5);
            case 'emoticon':
                return count($cfg['levels'] ?? []) ?: 5;
        }

        return 0;
    }

    /**
     * Indicadores de um conjunto de registros, com as MESMAS regras da
     * aba Resultados -- para os números baterem nas duas telas:
     *
     *  - NPS: só as notas de perguntas do tipo NPS, cada uma
     *    classificada na PRÓPRIA escala, pelo mesmo
     *    MetricsCalculator::npsMetrics() que os Resultados usam;
     *  - Nota média: média de TODOS os valores com nota, normalizados
     *    para 0–10 (como em MetricsCalculator::getOverallScore());
     *  - Respostas: quantidade de respostas distintas.
     */
    public static function resumir(array $registros): array
    {
        $respostas = [];
        $normalizados = [];
        $nps_por_escala = [];

        foreach ($registros as $r) {
            $respostas[$r['answers_id']] = true;
            $normalizados[] = ($r['value'] / $r['max']) * 10;

            if ($r['type'] === 'nps') {
                $nps_por_escala[$r['max']][] = ['numeric_value' => $r['value']];
            }
        }

        $saida = [
            'total'          => count($respostas),
            'media'          => empty($normalizados)
                ? null
                : round(array_sum($normalizados) / count($normalizados), 1),
            'nps'            => null,
            'notas_nps'      => 0,
            'promotores'     => 0,
            'neutros'        => 0,
            'detratores'     => 0,
            'promotores_pct' => 0.0,
            'neutros_pct'    => 0.0,
            'detratores_pct' => 0.0,
        ];

        if (empty($nps_por_escala)) {
            return $saida;
        }

        // Normalmente há uma escala só; havendo mais de uma (pesquisas
        // com NPS 0–10 e 0–5 no mesmo recorte), cada nota é classificada
        // na sua, e as contagens são somadas.
        $prom = $neut = $detr = 0;
        foreach ($nps_por_escala as $escala => $valores) {
            $m = MetricsCalculator::npsMetrics($valores, (int) $escala);
            if (($m['total'] ?? 0) === 0) {
                continue;
            }
            $prom += $m['promoters'];
            $neut += $m['passives'];
            $detr += $m['detractors'];
        }

        $total_nps = $prom + $neut + $detr;
        if ($total_nps === 0) {
            return $saida;
        }

        // Mesma fórmula e mesmos arredondamentos dos Resultados.
        $pct = static fn(int $n): float => round($n * 100 / $total_nps, 1);

        $saida['notas_nps']      = $total_nps;
        $saida['promotores']     = $prom;
        $saida['neutros']        = $neut;
        $saida['detratores']     = $detr;
        $saida['promotores_pct'] = $pct($prom);
        $saida['neutros_pct']    = $pct($neut);
        $saida['detratores_pct'] = $pct($detr);
        $saida['nps']            = (int) round($pct($prom) - $pct($detr));

        return $saida;
    }








    /**
     * Evolução mês a mês, dos últimos 12 meses do período.
     *
     * Meses SEM resposta também aparecem, vazios. Antes eles eram
     * omitidos, e o gráfico mostrava julho e setembro lado a lado como se
     * fossem consecutivos -- uma queda ou alta entre dois meses distantes
     * parecia uma mudança de um mês para o outro.
     */
    public static function evolucao(array $scores): array
    {
        if (empty($scores)) {
            return [];
        }

        $meses = [];
        foreach ($scores as $s) {
            $meses[substr((string) $s['date'], 0, 7)][] = $s;
        }

        ksort($meses);

        // Preenche os meses intermediários sem resposta
        $primeiro = array_key_first($meses);
        $ultimo   = array_key_last($meses);

        $saida  = [];
        $cursor = \DateTime::createFromFormat('Y-m-d', $primeiro . '-01');
        $fim    = \DateTime::createFromFormat('Y-m-d', $ultimo . '-01');

        while ($cursor !== false && $cursor <= $fim) {
            $chave = $cursor->format('Y-m');
            $saida[] = [
                'periodo' => $chave,
                'resumo'  => self::resumir($meses[$chave] ?? []),
                'vazio'   => !isset($meses[$chave]),
            ];
            $cursor->modify('+1 month');
        }

        // Um ano basta para ver tendência sem encher a tela.
        return array_slice($saida, -12);
    }

    // =================================================================
    // Montagem do painel -- ponto ÚNICO usado pela tela e pela
    // exportação, para o arquivo exportado nunca divergir do que está
    // na tela.
    // =================================================================

    /**
     * Monta tudo o que o painel exibe, a partir dos parâmetros recebidos.
     *
     * Ordem das etapas:
     *  1. escopo: pesquisas que o usuário pode ver (as que criou, ou
     *     todas para o administrador geral);
     *  2. banco: registros de nota do período, dessas pesquisas;
     *  3. contexto: técnico, categoria e requerente de cada chamado;
     *  4. opções dos filtros, calculadas ANTES de aplicá-los -- senão,
     *     ao marcar um técnico, os demais sumiriam do seletor e não
     *     haveria como marcar um segundo;
     *  5. filtros por entidade, técnico, categoria e requerente;
     *  6. indicadores e recortes.
     */
    public static function montar(array $params): array
    {
        global $DB;

        $ids = static function ($valor): array {
            $saida = [];
            foreach ((array) $valor as $v) {
                if (is_numeric($v)) {
                    $saida[] = (int) $v;
                }
            }
            return array_values(array_unique($saida));
        };

        $sel = [
            'surveys'     => $ids($params['surveys'] ?? []),
            'entities'    => $ids($params['entities'] ?? []),
            'tecnicos'    => $ids($params['tecnicos'] ?? []),
            'categorias'  => $ids($params['categorias'] ?? []),
            'requerentes' => $ids($params['requerentes'] ?? []),
            'grupos'      => $ids($params['grupos'] ?? []),
        ];

        // Período padrão: últimos 12 meses. Sem recorte, um ano ruim e
        // um bom se misturariam e a média esconderia a tendência.
        $date_from = self::dataValida($params['date_from'] ?? null) ?? date('Y-m-d', strtotime('-12 months'));
        $date_to   = self::dataValida($params['date_to'] ?? null) ?? date('Y-m-d');

        // "De" depois de "Até" é quase sempre engano de digitação. Em vez
        // de devolver um painel vazio sem explicação, inverte as datas --
        // o intervalo pretendido fica evidente.
        if ($date_from > $date_to) {
            [$date_from, $date_to] = [$date_to, $date_from];
        }

        // 1) Escopo
        $ver_todas = Survey::isAdministradorGeral();

        $where = ['is_deleted' => 0];
        if (!$ver_todas) {
            $where['creator_id'] = (int) \Session::getLoginUserID();
        }

        $pesquisas = [];
        foreach ($DB->request([
            'SELECT' => ['id', 'name'],
            'FROM'   => Survey::getTable(),
            'WHERE'  => $where,
            'ORDER'  => 'name ASC',
        ]) as $row) {
            $pesquisas[(int) $row['id']] = (string) $row['name'];
        }

        // O filtro de pesquisas nunca sai do escopo: uma pesquisa alheia
        // pedida pela URL é descartada. "Nenhuma" significa todas AS
        // PERMITIDAS -- e, sem nenhuma permitida, um filtro que não casa
        // com nada, em vez de cair no "sem filtro".
        $sel['surveys'] = array_values(array_intersect($sel['surveys'], array_keys($pesquisas)));
        $surveys_sql = !empty($sel['surveys']) ? $sel['surveys'] : array_keys($pesquisas);
        if (empty($surveys_sql)) {
            $surveys_sql = [0];
        }

        // 2) Registros do banco
        $registros = self::getScores([
            'surveys'   => $surveys_sql,
            'date_from' => $date_from,
            'date_to'   => $date_to,
        ]);

        // 3) Contexto dos chamados
        $ctx = self::getContextos($registros);

        // 4) Opções dos filtros (antes de filtrar)
        $opcoes = [
            'entities'    => self::opcoesDe($registros, fn($r) => self::chaveEntidade($r)),
            'requerentes' => self::opcoesDe($registros, fn($r) => $ctx['requerente'][$r['tickets_id']] ?? null),
            'tecnicos'    => self::opcoesDe($registros, fn($r) => $ctx['tecnico'][$r['tickets_id']] ?? null),
            'grupos'      => self::opcoesDe($registros, fn($r) => $ctx['grupo'][$r['tickets_id']] ?? null),
            'categorias'  => self::opcoesDe($registros, fn($r) => $ctx['categoria'][$r['tickets_id']] ?? null),
        ];

        // 5) Filtros por dimensão
        $filtrados = self::filtrar($registros, $ctx, $sel);

        // 6) Período ANTERIOR, com a mesma duração e os MESMOS filtros.
        //
        // Os mesmos filtros são essenciais: comparar "NPS do técnico X
        // este mês" com "NPS de todo mundo no mês passado" produziria uma
        // variação que não significa nada.
        [$ant_from, $ant_to] = self::periodoAnterior($date_from, $date_to);

        $registros_ant = self::getScores([
            'surveys'   => $surveys_sql,
            'date_from' => $ant_from,
            'date_to'   => $ant_to,
        ]);
        $resumo_ant = self::resumir(self::filtrar($registros_ant, self::getContextos($registros_ant), $sel));

        $resumo = self::resumir($filtrados);

        // 7) Indicadores e recortes
        return [
            'resumo'         => $resumo,
            'comparacao'     => self::comparar($resumo, $resumo_ant, $ant_from, $ant_to),
            'evolucao'       => self::evolucao($filtrados),
            'por_pesquisa'   => self::agrupar($filtrados, static fn($r) => [
                'id'   => $r['surveys_id'],
                'nome' => $pesquisas[$r['surveys_id']] ?? ('#' . $r['surveys_id']),
            ]),
            'por_entidade'   => self::agrupar($filtrados, fn($r) => self::chaveEntidade($r)),
            'por_requerente' => self::agrupar($filtrados, fn($r) => $ctx['requerente'][$r['tickets_id']] ?? null),
            'por_tecnico'    => self::agrupar($filtrados, fn($r) => $ctx['tecnico'][$r['tickets_id']] ?? null),
            'por_grupo'      => self::agrupar($filtrados, fn($r) => $ctx['grupo'][$r['tickets_id']] ?? null),
            'por_categoria'  => self::agrupar($filtrados, fn($r) => $ctx['categoria'][$r['tickets_id']] ?? null),
            'pesquisas'      => $pesquisas,
            'opcoes'         => $opcoes,
            'sel'            => $sel,
            'date_from'      => $date_from,
            'date_to'        => $date_to,
            'ver_todas'      => $ver_todas,
            'amostra_minima' => self::AMOSTRA_MINIMA,
        ];
    }

    /**
     * Abaixo disto, um grupo é marcado como amostra pequena.
     *
     * Com poucas respostas, uma única avaliação move o NPS dezenas de
     * pontos: +100 em duas respostas diz muito pouco. O grupo continua
     * na tabela -- esconder seria pior, porque sumiria gente do
     * relatório --, mas sinalizado.
     */
    public const AMOSTRA_MINIMA = 5;

    /**
     * Aplica os filtros de dimensão. Extraído para valer IGUAL no
     * período atual e no anterior.
     */
    private static function filtrar(array $registros, array $ctx, array $sel): array
    {
        return array_values(array_filter($registros, static function (array $r) use ($sel, $ctx): bool {
            if (!empty($sel['entities']) && !in_array($r['entities_id'], $sel['entities'], true)) {
                return false;
            }

            // Estas dimensões só existem para respostas vinculadas a
            // chamado. Com um desses filtros ativo, uma resposta sem
            // chamado não tem como pertencer ao recorte.
            $dimensoes = [
                'requerentes' => 'requerente',
                'tecnicos'    => 'tecnico',
                'grupos'      => 'grupo',
                'categorias'  => 'categoria',
            ];

            foreach ($dimensoes as $campo => $dim) {
                if (empty($sel[$campo])) {
                    continue;
                }
                $item = $ctx[$dim][$r['tickets_id']] ?? null;
                if ($item === null || !in_array($item['id'], $sel[$campo], true)) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * Período imediatamente anterior, com a MESMA duração.
     *
     * 01/07 a 31/07 (31 dias) compara com 31/05 a 30/06 (31 dias).
     * Comparar períodos de tamanhos diferentes distorceria o volume de
     * respostas.
     *
     * @return array{0: string, 1: string}
     */
    private static function periodoAnterior(string $de, string $ate): array
    {
        $inicio = new \DateTimeImmutable($de);
        $fim    = new \DateTimeImmutable($ate);
        $dias   = (int) $inicio->diff($fim)->days;

        $ant_fim    = $inicio->modify('-1 day');
        $ant_inicio = $ant_fim->modify('-' . $dias . ' days');

        return [$ant_inicio->format('Y-m-d'), $ant_fim->format('Y-m-d')];
    }

    /**
     * Variação entre o período atual e o anterior.
     *
     * Cada variação só existe quando os DOIS períodos têm o valor. Sem
     * respostas antes, não há "subiu 40 pontos" -- há ausência de base
     * de comparação, e a tela precisa dizer isso.
     */
    private static function comparar(array $atual, array $anterior, string $de, string $ate): array
    {
        $delta = static function ($a, $b, int $casas = 0) {
            if ($a === null || $b === null) {
                return null;
            }
            return round((float) $a - (float) $b, $casas);
        };

        return [
            'de'         => $de,
            'ate'        => $ate,
            'tem_base'   => $anterior['total'] > 0,
            'nps'        => $delta($atual['nps'], $anterior['nps']),
            'media'      => $delta($atual['media'], $anterior['media'], 1),
            'total'      => $atual['total'] - $anterior['total'],
            'nps_antes'  => $anterior['nps'],
            'media_antes' => $anterior['media'],
            'total_antes' => $anterior['total'],
        ];
    }

    private static function dataValida($valor): ?string
    {
        if (!is_string($valor) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return null;
        }
        return $valor;
    }

    /** @var array<int, string> nomes de entidade já resolvidos */
    private static array $nomesEntidade = [];

    /**
     * Entidade de um registro, com o nome em CACHE.
     *
     * Sem o cache, o nome era consultado no banco uma vez por NOTA, e em
     * dois pontos (opções do filtro e agrupamento): com mil notas, duas
     * mil consultas para obter os mesmos poucos nomes.
     */
    private static function chaveEntidade(array $r): array
    {
        $id = (int) $r['entities_id'];

        if (!isset(self::$nomesEntidade[$id])) {
            self::$nomesEntidade[$id] = \Dropdown::getDropdownName('glpi_entities', $id);
        }

        return ['id' => $id, 'nome' => self::$nomesEntidade[$id]];
    }

    /**
     * Opções de um filtro: os valores que EXISTEM nos dados, e não a
     * lista inteira do GLPI -- um técnico sem nenhuma avaliação só
     * produziria um filtro vazio.
     *
     * @return array<int, string> id => nome, em ordem alfabética
     */
    private static function opcoesDe(array $registros, callable $chave): array
    {
        $opcoes = [];
        foreach ($registros as $r) {
            $item = $chave($r);
            if ($item !== null) {
                $opcoes[(int) $item['id']] = (string) $item['nome'];
            }
        }

        asort($opcoes, SORT_NATURAL | SORT_FLAG_CASE);
        return $opcoes;
    }

    /**
     * Agrupa os registros por uma chave e resume cada grupo.
     *
     * Registros sem chave (resposta sem chamado, num recorte por
     * técnico) ficam de fora -- e não num grupo "sem informação", que
     * inventaria um recorte inexistente.
     *
     * Ordenado por VOLUME, não por NPS: um grupo com duas avaliações não
     * deve encabeçar a lista por ter NPS 100.
     */
    private static function agrupar(array $registros, callable $chave): array
    {
        $grupos = [];
        $nomes  = [];

        foreach ($registros as $r) {
            $item = $chave($r);
            if ($item === null) {
                continue;
            }
            $id = (int) $item['id'];
            $grupos[$id][] = $r;
            $nomes[$id] = (string) $item['nome'];
        }

        $saida = [];
        foreach ($grupos as $id => $itens) {
            $resumo = self::resumir($itens);
            $saida[] = [
                'id'              => $id,
                'rotulo'          => $nomes[$id],
                'resumo'          => $resumo,
                'amostra_pequena' => $resumo['total'] < self::AMOSTRA_MINIMA,
            ];
        }

        usort($saida, static fn(array $a, array $b): int => $b['resumo']['total'] <=> $a['resumo']['total']);

        return $saida;
    }

    /**
     * Técnico, categoria e requerente de cada chamado presente nos
     * registros, numa consulta por dimensão.
     *
     * @return array{tecnico: array, categoria: array, requerente: array}
     *         cada um no formato tickets_id => ['id' => int, 'nome' => string]
     */
    private static function getContextos(array $registros): array
    {
        $ctx = ['tecnico' => [], 'categoria' => [], 'requerente' => [], 'grupo' => []];

        $tickets = [];
        foreach ($registros as $r) {
            if ($r['tickets_id'] > 0) {
                $tickets[$r['tickets_id']] = true;
            }
        }
        $tickets = array_keys($tickets);

        if (empty($tickets)) {
            return $ctx;
        }

        global $DB;

        foreach ($DB->request([
            'SELECT'    => ['glpi_tickets.id AS tid', 'glpi_itilcategories.id AS cid',
                            'glpi_itilcategories.completename AS nome'],
            'FROM'      => 'glpi_tickets',
            'INNER JOIN' => [
                'glpi_itilcategories' => [
                    'ON' => ['glpi_tickets' => 'itilcategories_id', 'glpi_itilcategories' => 'id'],
                ],
            ],
            'WHERE' => ['glpi_tickets.id' => $tickets],
        ]) as $row) {
            $ctx['categoria'][(int) $row['tid']] = ['id' => (int) $row['cid'], 'nome' => (string) $row['nome']];
        }

        if (class_exists('\\CommonITILActor')) {
            $ctx['tecnico']    = self::getAtor($tickets, \CommonITILActor::ASSIGN);
            $ctx['requerente'] = self::getAtor($tickets, \CommonITILActor::REQUESTER);
            $ctx['grupo']      = self::getGrupoAtribuido($tickets);
        }

        return $ctx;
    }

    /**
     * Grupo técnico ATRIBUÍDO ao chamado (N1, N2...).
     *
     * Mesma regra do técnico: havendo mais de um grupo, conta o
     * primeiro atribuído -- para a soma dos recortes bater com o total.
     */
    private static function getGrupoAtribuido(array $tickets): array
    {
        global $DB;

        if (!$DB->tableExists('glpi_groups_tickets')) {
            return [];
        }

        $saida = [];

        foreach ($DB->request([
            'SELECT'     => ['glpi_groups_tickets.tickets_id AS tid', 'glpi_groups.id AS gid',
                             'glpi_groups.completename AS nome'],
            'FROM'       => 'glpi_groups_tickets',
            'INNER JOIN' => [
                'glpi_groups' => ['ON' => ['glpi_groups_tickets' => 'groups_id', 'glpi_groups' => 'id']],
            ],
            'WHERE' => [
                'glpi_groups_tickets.tickets_id' => $tickets,
                'glpi_groups_tickets.type'       => \CommonITILActor::ASSIGN,
            ],
            'ORDER' => 'glpi_groups_tickets.id ASC',
        ]) as $row) {
            $tid = (int) $row['tid'];
            if (isset($saida[$tid])) {
                continue;
            }
            $saida[$tid] = ['id' => (int) $row['gid'], 'nome' => (string) $row['nome']];
        }

        return $saida;
    }

    /**
     * Primeiro usuário de um papel (técnico ou requerente) em cada
     * chamado.
     *
     * O PRIMEIRO, quando há mais de um: dividir a nota entre vários faria
     * a soma dos recortes não bater com o total, e atribuí-la inteira a
     * cada um inflaria todos.
     */
    private static function getAtor(array $tickets, int $tipo): array
    {
        global $DB;

        $saida = [];

        foreach ($DB->request([
            'SELECT' => ['glpi_tickets_users.tickets_id AS tid', 'glpi_users.id AS uid',
                         'glpi_users.name AS login', 'glpi_users.realname AS sobrenome',
                         'glpi_users.firstname AS nome'],
            'FROM'       => 'glpi_tickets_users',
            'INNER JOIN' => [
                'glpi_users' => ['ON' => ['glpi_tickets_users' => 'users_id', 'glpi_users' => 'id']],
            ],
            'WHERE' => ['glpi_tickets_users.tickets_id' => $tickets, 'glpi_tickets_users.type' => $tipo],
            'ORDER' => 'glpi_tickets_users.id ASC',
        ]) as $row) {
            $tid = (int) $row['tid'];
            if (isset($saida[$tid])) {
                continue;
            }

            $completo = trim(($row['nome'] ?? '') . ' ' . ($row['sobrenome'] ?? ''));
            $saida[$tid] = [
                'id'   => (int) $row['uid'],
                'nome' => $completo !== '' ? $completo : (string) $row['login'],
            ];
        }

        return $saida;
    }
}
