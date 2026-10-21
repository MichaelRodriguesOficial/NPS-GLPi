<?php

namespace GlpiPlugin\Npsphere;

/**
 * Relatório diário das respostas, para os gestores (v0.59.0).
 *
 * Uma vez por dia, no horário escolhido na sub-aba "Relatório" da aba
 * Template, sai um e-mail para os usuários e grupos do GLPI escolhidos
 * ali, com TODAS as respostas desde o relatório anterior, separadas por
 * pessoa, e no topo a lista das respostas com nota baixa. A nota de cada
 * resposta é a mesma da coluna "Nota" da aba Resultados (0 a 10), e a nota
 * de corte é configurável por pesquisa (padrão 6, os detratores).
 *
 * Dia sem resposta, nada é enviado.
 *
 * Quem envia é a ação automática "npsphereRelatorio" (Configurar › Ações
 * automáticas), de hora em hora: a primeira execução depois do horário
 * escolhido manda o relatório do dia.
 *
 * Pesquisa anônima: o relatório nunca mostra quem respondeu -- cada
 * resposta aparece como "Resposta anônima".
 */
class DailyReport
{
    /** Nome da ação automática (Configurar › Ações automáticas). */
    public const CRON = 'npsphereRelatorio';

    /** Ação automática do AVISO IMEDIATO de nota baixa (v0.60.0). */
    public const CRON_AVISO = 'npsphereAviso';

    /** Opções de espera do aviso, em minutos. */
    public const ESPERAS = [5, 10, 15, 30, 60];

    // ------------------------------------------------------------------
    // Configuração da pesquisa
    // ------------------------------------------------------------------

    /**
     * @return array{users: int[], groups: int[]}
     */
    public static function getEscolhidos(Survey $survey, string $prefixo = 'report'): array
    {
        // 'report' = relatório diário; 'alert' = aviso imediato (v0.62.0:
        // cada um com a sua lista).
        $dados = json_decode((string) ($survey->fields[$prefixo . '_recipients'] ?? ''), true);
        $dados = is_array($dados) ? $dados : [];

        $ids = static fn($v): array => array_values(array_filter(
            array_map('intval', is_array($v) ? $v : []),
            static fn(int $id): bool => $id > 0
        ));

        return ['users' => $ids($dados['users'] ?? []), 'groups' => $ids($dados['groups'] ?? [])];
    }

    /**
     * Nota de corte: a resposta com NOTA (0 a 10) até este valor é nota
     * baixa. É a MESMA nota da coluna "Nota" da aba Resultados -- a média
     * das perguntas com nota da resposta, convertidas para 0 a 10
     * (MetricsCalculator::getAnswerScore). Padrão 6: os detratores.
     */
    public static function getCorte(Survey $survey, string $prefixo = 'report'): int
    {
        return max(0, min(9, (int) ($survey->fields[$prefixo . '_nps_cut'] ?? 6)));
    }

    /**
     * Minutos que o aviso espera depois da última alteração da resposta
     * -- tempo para a pessoa terminar o comentário (quem clica a nota no
     * e-mail completa o resto depois). Padrão 10.
     */
    public static function getEspera(Survey $survey): int
    {
        $min = (int) ($survey->fields['alert_delay'] ?? 10);

        return in_array($min, self::ESPERAS, true) ? $min : 10;
    }

    public static function getHora(Survey $survey): int
    {
        return max(0, min(23, (int) ($survey->fields['report_hour'] ?? 18)));
    }

    /**
     * Quem recebe: os usuários escolhidos e os membros dos grupos
     * escolhidos, ativos e com e-mail. Cada endereço uma vez só.
     *
     * @return array<string, array{id: int, nome: string, email: string, acesso: bool}> e-mail => pessoa
     */
    public static function getDestinatarios(Survey $survey, string $prefixo = 'report'): array
    {
        global $DB;

        $escolhidos = self::getEscolhidos($survey, $prefixo);
        $usuarios = $escolhidos['users'];

        if ($escolhidos['groups'] !== []) {
            foreach ($DB->request([
                'SELECT' => ['users_id'],
                'FROM'   => 'glpi_groups_users',
                'WHERE'  => ['groups_id' => $escolhidos['groups']],
            ]) as $linha) {
                $usuarios[] = (int) $linha['users_id'];
            }
        }

        $saida = [];
        foreach (array_unique($usuarios) as $users_id) {
            $user = new \User();
            if (
                !$user->getFromDB($users_id)
                || empty($user->fields['is_active'])
                || !empty($user->fields['is_deleted'])
            ) {
                continue;
            }

            $email = method_exists($user, 'getDefaultEmail') ? trim((string) $user->getDefaultEmail()) : '';
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $nome = trim(($user->fields['firstname'] ?? '') . ' ' . ($user->fields['realname'] ?? ''));
            $saida[mb_strtolower($email)] = [
                'id'     => (int) $users_id,
                'nome'   => $nome !== '' ? $nome : (string) $user->fields['name'],
                'email'  => $email,
                'acesso' => self::podeVerResultados($survey, (int) $users_id),
            ];
        }

        uasort($saida, static fn(array $a, array $b): int => strcasecmp($a['nome'], $b['nome']));

        return $saida;
    }

    /**
     * Este usuário consegue abrir a aba Resultados desta pesquisa no GLPI?
     *
     * A MESMA regra que o GLPI aplica ao abrir o formulário da pesquisa
     * (Survey::canViewItem), conferida sem a sessão dele -- o relatório
     * sai pela ação automática, sem ninguém logado:
     *
     *  - um perfil da interface padrão (a simplificada não tem o menu
     *    Administração) com leitura em "Pesquisas de satisfação";
     *  - nesse perfil, acesso à entidade da pesquisa (a própria, uma pai
     *    com "recursivo", ou uma filha se a pesquisa for recursiva);
     *  - e ser quem criou a pesquisa, ou administrador geral (escrita em
     *    Configuração geral) -- Survey::podeGerenciar().
     *
     * Quem não passa recebe o relatório sem os links para os resultados:
     * clicar daria "acesso negado".
     */
    public static function podeVerResultados(Survey $survey, int $users_id): bool
    {
        global $DB;

        if ($users_id <= 0) {
            return false;
        }

        $entidade_pesquisa = (int) ($survey->fields['entities_id'] ?? 0);
        $pesquisa_recursiva = !empty($survey->fields['is_recursive']);
        $criador = (int) ($survey->fields['creator_id'] ?? 0) === $users_id;

        foreach ($DB->request([
            'SELECT'     => ['glpi_profiles_users.profiles_id', 'glpi_profiles_users.entities_id', 'glpi_profiles_users.is_recursive'],
            'FROM'       => 'glpi_profiles_users',
            'INNER JOIN' => [
                'glpi_profiles' => ['ON' => ['glpi_profiles_users' => 'profiles_id', 'glpi_profiles' => 'id']],
            ],
            'WHERE'      => [
                'glpi_profiles_users.users_id' => $users_id,
                'glpi_profiles.interface'      => 'central',
            ],
        ]) as $pu) {
            $entidade = (int) $pu['entities_id'];

            $alcanca = $entidade === $entidade_pesquisa
                || (!empty($pu['is_recursive']) && in_array($entidade_pesquisa, array_map('intval', getSonsOf('glpi_entities', $entidade)), true))
                || ($pesquisa_recursiva && in_array($entidade, array_map('intval', getSonsOf('glpi_entities', $entidade_pesquisa)), true));
            if (!$alcanca) {
                continue;
            }

            $direitos = [];
            foreach ($DB->request([
                'SELECT' => ['name', 'rights'],
                'FROM'   => 'glpi_profilerights',
                'WHERE'  => ['profiles_id' => (int) $pu['profiles_id'], 'name' => [Survey::$rightname, 'config']],
            ]) as $linha) {
                $direitos[$linha['name']] = (int) $linha['rights'];
            }

            $le_pesquisas = (($direitos[Survey::$rightname] ?? 0) & READ) === READ;
            $admin_geral  = (($direitos['config'] ?? 0) & UPDATE) === UPDATE;

            if ($le_pesquisas && ($criador || $admin_geral)) {
                return true;
            }
        }

        return false;
    }

    // ------------------------------------------------------------------
    // Montagem
    // ------------------------------------------------------------------

    /**
     * Monta o relatório das respostas gravadas (ou alteradas) entre
     * $inicio e $fim.
     *
     * Usa a data de ALTERAÇÃO da resposta: quem clicou a nota no e-mail e
     * completou o comentário depois entra uma vez só, com tudo o que
     * respondeu.
     *
     * Cada resposta leva a MESMA nota da aba Resultados (coluna "Nota",
     * mesma cor), e "nota baixa" é dessa nota: o relatório e a tela do
     * GLPI nunca discordam sobre qual resposta foi baixa.
     *
     * @param int|null $corte null = o da pesquisa
     * @param string|null $corpo   modelo da mensagem; null = o da pesquisa (em branco, o padrão)
     * @param string|null $assunto modelo do assunto; idem
     * @return array{html: string, html_sem_links: string, assunto: string, respostas: int, pessoas: int, baixas: int}
     */
    public static function montar(Survey $survey, string $inicio, string $fim, ?int $corte = null, ?string $corpo = null, ?string $assunto = null): array
    {
        global $DB;

        $corte ??= self::getCorte($survey);
        $sid = $survey->getID();
        $anonima = !empty($survey->fields['is_anonymous']);

        $perguntas = [];
        foreach ($DB->request([
            'FROM'  => Question::getTable(),
            'WHERE' => ['surveys_id' => $sid],
            'ORDER' => ['position ASC', 'id ASC'],
        ]) as $q) {
            $perguntas[(int) $q['id']] = $q;
        }
        $com_nota = MetricsCalculator::getScoreQuestions($perguntas);

        $respostas = [];
        foreach ($DB->request([
            'FROM'  => Answer::getTable(),
            'WHERE' => [
                'surveys_id' => $sid,
                ['date_mod' => ['>', $inicio]],
                ['date_mod' => ['<=', $fim]],
            ],
            'ORDER' => 'date_mod ASC',
        ]) as $a) {
            $respostas[(int) $a['id']] = $a + ['valores' => []];
        }

        if ($respostas === []) {
            return ['html' => '', 'html_sem_links' => '', 'assunto' => '', 'respostas' => 0, 'pessoas' => 0, 'baixas' => 0];
        }

        foreach ($DB->request([
            'FROM'  => AnswerValue::getTable(),
            'WHERE' => ['answers_id' => array_keys($respostas)],
        ]) as $v) {
            $respostas[(int) $v['answers_id']]['valores'][(int) $v['questions_id']] = $v;
        }

        $pessoas = [];
        $baixas  = [];
        $notas   = [];

        foreach ($respostas as $aid => $r) {
            $item  = self::itemDaResposta($r, $perguntas, $com_nota, $anonima, $corte);
            $chave = $item['chave'];

            $pessoas[$chave] ??= [
                'nome'      => $item['pessoa'],
                'email'     => $item['email'],
                'respostas' => [],
            ];

            if ($item['nota'] !== null) {
                $notas[] = (float) $item['nota']['score'];
            }

            $pessoas[$chave]['respostas'][] = $item;
            if ($item['baixa']) {
                $baixas[] = $item;
            }
        }

        $total = count($respostas);
        $media = $notas !== [] ? round(array_sum($notas) / count($notas), 1) : null;

        $resumo = [
            'inicio'    => $inicio,
            'fim'       => $fim,
            'respostas' => $total,
            'baixas'    => count($baixas),
            'media'     => $media,
            'corte'     => $corte,
            'entidade'  => (int) ($survey->fields['entities_id'] ?? 0),
        ];

        [$modelo_assunto, $modelo_corpo] = self::textos(
            $survey,
            'report',
            $assunto,
            $corpo,
            self::getAssuntoPadraoRelatorio(),
            self::getCorpoPadraoRelatorio()
        );

        $texto = [
            '{{pesquisa}}'        => (string) $survey->fields['name'],
            '{{data}}'            => self::data($fim, 'dia'),
            '{{periodo}}'         => sprintf(__('%1$s a %2$s', 'npsphere'), self::data($inicio), self::data($fim)),
            '{{total_respostas}}' => (string) $total,
            '{{total_baixas}}'    => (string) count($baixas),
            '{{nota_media}}'      => $media !== null ? self::formatarNota((float) $media) : '—',
            '{{nota_corte}}'      => (string) $corte,
        ];

        // Duas versões: com os links para os resultados (quem tem acesso
        // a eles) e sem (quem não tem -- ver podeVerResultados).
        $versao = function (bool $links) use ($survey, $pessoas, $baixas, $resumo, $texto, $modelo_corpo): string {
            $valores = self::blocosRelatorio($survey, array_values($pessoas), $baixas, $resumo, $links)
                + self::escapar($texto);

            return self::envelope(strtr($modelo_corpo, $valores));
        };

        return [
            'html'           => $versao(true),
            'html_sem_links' => $versao(false),
            'assunto'        => strtr($modelo_assunto, $texto),
            'respostas'      => $total,
            'pessoas'        => count($pessoas),
            'baixas'         => count($baixas),
        ];
    }

    /**
     * Uma resposta pronta para o e-mail: nota (a da aba Resultados), se é
     * baixa, quem respondeu, chamado e as perguntas com as respostas.
     * Usado pelo relatório diário e pelo aviso imediato.
     *
     * @param array<string, mixed> $r linha de Answer com 'valores' (questions_id => AnswerValue)
     * @param array<int, array<string, mixed>> $perguntas
     * @param array<int, array<string, mixed>> $com_nota MetricsCalculator::getScoreQuestions()
     * @return array<string, mixed>
     */
    private static function itemDaResposta(array $r, array $perguntas, array $com_nota, bool $anonima, int $corte): array
    {
        $aid = (int) $r['id'];
        $identificada = !$anonima && empty($r['is_anonymous']);
        $users_id = (int) ($r['users_id'] ?? 0);
        $email    = trim((string) ($r['respondent_email'] ?? ''));

        if (!$identificada) {
            $chave = 'a:' . $aid;
        } elseif ($users_id > 0) {
            $chave = 'u:' . $users_id;
        } elseif ($email !== '') {
            $chave = 'e:' . mb_strtolower($email);
        } else {
            $chave = 'a:' . $aid;
        }

        $nota = MetricsCalculator::getAnswerScore($aid, $com_nota);

        $linhas = [];
        foreach ($perguntas as $qid => $q) {
            if (isset($r['valores'][$qid])) {
                $linhas[] = [
                    'pergunta' => (string) $q['name'],
                    'resposta' => self::textoDaResposta($q, $r['valores'][$qid]),
                ];
            }
        }

        return [
            'id'       => $aid,
            'chave'    => $chave,
            'data'     => (string) ($r['date_mod'] ?: $r['date_creation']),
            'chamado'  => self::chamado((int) ($r['tickets_id'] ?? 0)),
            'nota'     => $nota,
            'baixa'    => $nota !== null && (int) round($nota['score']) <= $corte,
            'linhas'   => $linhas,
            'pessoa'   => self::nomeDaPessoa($r, !$identificada),
            'email'    => $identificada ? $email : '',
            'telefone' => $identificada ? trim((string) ($r['respondent_phone'] ?? '')) : '',
        ];
    }

    /** @param array<string, mixed> $r */
    private static function nomeDaPessoa(array $r, bool $anonima): string
    {
        if ($anonima) {
            return __('Resposta anônima', 'npsphere');
        }

        $nome = trim((string) ($r['respondent_name'] ?? ''));
        if ($nome !== '') {
            return $nome;
        }

        $users_id = (int) ($r['users_id'] ?? 0);
        if ($users_id > 0) {
            $user = new \User();
            if ($user->getFromDB($users_id)) {
                $completo = trim(($user->fields['firstname'] ?? '') . ' ' . ($user->fields['realname'] ?? ''));
                return $completo !== '' ? $completo : (string) $user->fields['name'];
            }
        }

        $email = trim((string) ($r['respondent_email'] ?? ''));

        return $email !== '' ? $email : __('Sem identificação', 'npsphere');
    }

    /**
     * @param array<string, mixed> $pergunta
     * @param array<string, mixed> $valor linha de AnswerValue
     */
    private static function textoDaResposta(array $pergunta, array $valor): string
    {
        $bruto = (string) ($valor['value'] ?? '');
        $cfg = json_decode((string) ($pergunta['configuration'] ?? '{}'), true);
        $cfg = is_array($cfg) ? $cfg : [];

        switch ((string) $pergunta['type']) {
            case 'multiple_choice':
                return implode(', ', array_filter(array_map('trim', explode(' | ', $bruto)), 'strlen'));

            case 'nps':
                return $bruto . ' de ' . ((int) ($cfg['scale'] ?? 10) ?: 10);

            case 'stars':
                return $bruto . ' de ' . ((int) ($cfg['max'] ?? 5) ?: 5) . ' ' . __('estrelas', 'npsphere');

            case 'text':
                return trim((string) ($valor['text_value'] ?? '')) !== '' ? (string) $valor['text_value'] : $bruto;
        }

        return Question::rotuloDaResposta($pergunta, $bruto);
    }

    /**
     * @return array{id: int, titulo: string, url: string}|null
     */
    private static function chamado(int $tickets_id): ?array
    {
        if ($tickets_id <= 0) {
            return null;
        }

        $ticket = new \Ticket();
        if (!$ticket->getFromDB($tickets_id)) {
            return ['id' => $tickets_id, 'titulo' => '', 'url' => ''];
        }

        return [
            'id'     => $tickets_id,
            'titulo' => (string) $ticket->fields['name'],
            'url'    => SurveyMailer::getUrlGlpi((int) $ticket->fields['entities_id'])
                . '/front/ticket.form.php?id=' . $tickets_id,
        ];
    }

    /**
     * Data no formato das Preferências do GLPI (v0.65.0); 'dia' é só dia e
     * mês, na ordem desse mesmo formato.
     */
    private static function data(string $quando, string $formato = 'completa'): string
    {
        $ts = strtotime($quando);
        if (!$ts) {
            return $quando;
        }

        global $CFG_GLPI;

        // Como as notificações do GLPI: o formato do usuário e, na ação
        // automática (sem usuário), o da configuração geral.
        $formato_glpi = (int) ($_SESSION['glpidate_format'] ?? $CFG_GLPI['date_format'] ?? 0);

        if ($formato === 'dia') {
            return match ($formato_glpi) {
                1       => date('d/m', $ts),
                2       => date('m/d', $ts),
                default => date('Y-m-d', $ts),
            };
        }

        return (string) \Html::convDateTime(date('Y-m-d H:i:s', $ts), $formato_glpi);
    }

    /** Mesmas cores da coluna "Nota" da aba Resultados. */
    private static function corDaNota(float $nota): string
    {
        $n = (int) round($nota);

        return $n >= 9 ? '#2fb344' : ($n >= 7 ? '#f59f00' : '#d63939');
    }

    private static function formatarNota(float $nota): string
    {
        global $CFG_GLPI;

        // Separador decimal das Preferências (Formato dos números).
        $fmt = (int) ($_SESSION['glpinumber_format'] ?? $CFG_GLPI['number_format'] ?? 0);
        $sep = in_array($fmt, [2, 4], true) ? ',' : '.';

        return rtrim(rtrim(number_format($nota, 1, $sep, ''), '0'), $sep);
    }

    // ------------------------------------------------------------------
    // Modelos (v0.61.0): assunto e mensagem editáveis, como o convite
    // ------------------------------------------------------------------

    public static function getAssuntoPadraoRelatorio(): string
    {
        return __('Relatório diário — {{pesquisa}} — {{data}}: {{total_respostas}} resposta(s), {{total_baixas}} com nota baixa', 'npsphere');
    }

    /**
     * Mensagem padrão do relatório: o layout das versões anteriores,
     * agora montado com as variáveis. Uma linha por bloco, para o código
     * ficar legível no editor.
     */
    public static function getCorpoPadraoRelatorio(): string
    {
        return implode("\n", [
            '<p style="margin:0;font-size:12px;color:#667382;text-transform:uppercase;letter-spacing:.04em;">' . __('Relatório diário', 'npsphere') . '</p>',
            '<p style="margin:2px 0 0;font-size:20px;font-weight:bold;color:#206bc4;">{{pesquisa}}</p>',
            '<p style="margin:4px 0 16px;font-size:13px;color:#667382;">' . __('Respostas de {{periodo}}', 'npsphere') . '</p>',
            '{{numeros}}',
            '{{notas_baixas}}',
            '{{respostas_por_pessoa}}',
            '<p style="margin:20px 0 0;font-size:12px;color:#667382;">{{link_resultados}}<br>' . __('Você recebe este relatório porque foi escolhido na sub-aba "Relatório" desta pesquisa, no NPSphere.', 'npsphere') . '</p>',
        ]);
    }

    /** @return array<string, string> variável => descrição */
    public static function getMarcadoresRelatorio(): array
    {
        return [
            '{{pesquisa}}'             => __('Nome da pesquisa', 'npsphere'),
            '{{periodo}}'              => __('Período do relatório (de… a…)', 'npsphere'),
            '{{data}}'                 => __('Dia do relatório (dia e mês)', 'npsphere'),
            '{{total_respostas}}'      => __('Quantidade de respostas no período', 'npsphere'),
            '{{total_baixas}}'         => __('Quantidade de respostas com nota baixa', 'npsphere'),
            '{{nota_media}}'           => __('Nota média do período (0 a 10)', 'npsphere'),
            '{{nota_corte}}'           => __('Nota de corte (nota baixa: até…)', 'npsphere'),
            '{{numeros}}'              => __('Quadro com respostas, nota baixa e nota média', 'npsphere'),
            '{{notas_baixas}}'         => __('Lista das respostas com nota baixa (vazio se não houver)', 'npsphere'),
            '{{respostas_por_pessoa}}' => __('Todas as respostas, separadas por pessoa', 'npsphere'),
            '{{link_resultados}}'      => __('Link "Abrir os resultados" (ou o aviso de que a pessoa não tem acesso)', 'npsphere'),
        ];
    }

    /**
     * Variáveis do relatório que viram um BLOCO de HTML (só na mensagem;
     * no assunto não são trocadas).
     *
     * @return string[]
     */
    public static function getBlocosRelatorio(): array
    {
        return ['{{numeros}}', '{{notas_baixas}}', '{{respostas_por_pessoa}}', '{{link_resultados}}'];
    }

    /** @return string[] idem, do aviso */
    public static function getBlocosAviso(): array
    {
        return ['{{cartao}}', '{{respostas}}', '{{botao_resposta}}', '{{chamado}}'];
    }

    public static function getAssuntoPadraoAviso(): string
    {
        return __('Nota baixa ({{nota}}) — {{pesquisa}} — {{pessoa}}', 'npsphere');
    }

    public static function getCorpoPadraoAviso(): string
    {
        return implode("\n", [
            '<p style="margin:0;font-size:12px;color:#d63939;font-weight:bold;text-transform:uppercase;letter-spacing:.04em;">' . __('Nota baixa recebida', 'npsphere') . '</p>',
            '<p style="margin:2px 0 14px;font-size:14px;color:#667382;">{{pesquisa}}</p>',
            '{{cartao}}',
            '{{respostas}}',
            '{{botao_resposta}}',
            '<p style="margin:16px 0 0;font-size:12px;color:#667382;">' . __('Aviso enviado pelo menos {{espera}} minutos depois da resposta, para dar tempo de a pessoa terminar o comentário. Nota baixa: até {{nota_corte}}, a mesma nota da coluna "Nota" da aba Resultados. Esta resposta também entra no relatório diário.', 'npsphere') . '</p>',
        ]);
    }

    /** @return array<string, string> variável => descrição */
    public static function getMarcadoresAviso(): array
    {
        return [
            '{{pesquisa}}'       => __('Nome da pesquisa', 'npsphere'),
            '{{nota}}'           => __('Nota da resposta (0 a 10, a da aba Resultados)', 'npsphere'),
            '{{pessoa}}'         => __('Quem respondeu ("Resposta anônima" na pesquisa anônima)', 'npsphere'),
            '{{email}}'          => __('E-mail de quem respondeu', 'npsphere'),
            '{{telefone}}'       => __('Telefone de quem respondeu', 'npsphere'),
            '{{chamado_id}}'     => __('Número do chamado', 'npsphere'),
            '{{chamado_titulo}}' => __('Título do chamado', 'npsphere'),
            '{{chamado}}'        => __('Link do chamado (número e título)', 'npsphere'),
            '{{data}}'           => __('Data e hora da resposta', 'npsphere'),
            '{{espera}}'         => __('Minutos de espera configurados', 'npsphere'),
            '{{nota_corte}}'     => __('Nota de corte (nota baixa: até…)', 'npsphere'),
            '{{cartao}}'         => __('Quadro com a nota, a pessoa, o contato e o chamado', 'npsphere'),
            '{{respostas}}'      => __('Tabela com todas as perguntas e respostas', 'npsphere'),
            '{{botao_resposta}}' => __('Botão "Ver resposta no GLPI" (some para quem não tem acesso)', 'npsphere'),
        ];
    }

    /**
     * Assunto e mensagem a usar: os passados (prévia, com o que está na
     * tela), senão os gravados na pesquisa, senão os padrões.
     *
     * @return array{0: string, 1: string}
     */
    private static function textos(Survey $survey, string $prefixo, ?string $assunto, ?string $corpo, string $assunto_padrao, string $corpo_padrao): array
    {
        $assunto = trim((string) ($assunto ?? ($survey->fields[$prefixo . '_subject'] ?? '')));
        $corpo   = trim((string) ($corpo ?? ($survey->fields[$prefixo . '_body'] ?? '')));

        return [$assunto !== '' ? $assunto : $assunto_padrao, $corpo !== '' ? $corpo : $corpo_padrao];
    }

    /**
     * @param array<string, string> $valores
     * @return array<string, string>
     */
    private static function escapar(array $valores): array
    {
        return array_map(static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'), $valores);
    }

    /**
     * Fundo e cartão branco em volta da mensagem (o mesmo para todos os
     * modelos; o conteúdo é o do modelo).
     */
    private static function envelope(string $conteudo, int $largura = 680, string $topo = ''): string
    {
        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;background:#f5f7fb;">'
            . '<tr><td align="center" class="nps-fora" style="padding:24px 12px;">'
            . '<table role="presentation" width="' . $largura . '" cellpadding="0" cellspacing="0" border="0"'
            . ' style="width:100%;max-width:' . $largura . 'px;margin:0 auto;background:#ffffff;border:1px solid #e6e7e9;'
            . ($topo !== '' ? 'border-top:4px solid ' . $topo . ';' : '') . 'border-radius:8px;text-align:left;">'
            . '<tr><td class="nps-cartao" style="padding:24px 28px;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.45;color:#1d273b;">'
            . $conteudo
            . '</td></tr></table></td></tr></table>';
    }

    /**
     * Blocos prontos do relatório (variáveis {{numeros}},
     * {{notas_baixas}}, {{respostas_por_pessoa}} e {{link_resultados}}).
     *
     * Tabelas e estilos em linha, como o convite (é o que funciona em
     * cliente de e-mail, inclusive no Outlook).
     *
     * @param array<int, array<string, mixed>> $pessoas
     * @param array<int, array<string, mixed>> $baixas
     * @param array<string, mixed> $resumo
     * @return array<string, string>
     */
    private static function blocosRelatorio(Survey $survey, array $pessoas, array $baixas, array $resumo, bool $links): array
    {
        $e = static fn($t): string => htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');
        $f = 'font-family:Arial,Helvetica,sans-serif;';

        $resultados = SurveyMailer::getBaseAbsoluta((int) $resumo['entidade'])
            . '/front/survey.form.php?id=' . $survey->getID()
            . '&forcetab=' . rawurlencode(SurveyResults::class . '$1');
        $ver = static fn(int $id): string => $links
            ? '<a href="' . $e($resultados . '&npsphere_resposta=' . $id) . '" style="color:#206bc4;">' . $e(__('Ver resposta', 'npsphere')) . '</a>'
            : '';

        $pilula = static function (?array $nota) use ($e, $f): string {
            if ($nota === null) {
                return '<span style="' . $f . 'font-size:12px;color:#667382;">—</span>';
            }
            return '<span style="display:inline-block;min-width:26px;padding:3px 8px;border-radius:12px;text-align:center;'
                . $f . 'font-size:13px;font-weight:bold;color:#ffffff;background:' . self::corDaNota((float) $nota['score']) . ';">'
                . $e(self::formatarNota((float) $nota['score'])) . '</span>';
        };

        $titulo = static fn(string $texto, string $cor = '#1d273b'): string =>
            '<p style="margin:22px 0 8px;' . $f . 'font-size:15px;font-weight:bold;color:' . $cor . ';">' . $e($texto) . '</p>';

        $numero = static function (string $valor, string $rotulo, string $cor) use ($e, $f): string {
            return '<td align="center" width="33%" style="padding:12px 6px;' . $f . 'border:1px solid #e6e7e9;background:#ffffff;">'
                . '<div style="font-size:24px;font-weight:bold;line-height:1.2;color:' . $cor . ';">' . $e($valor) . '</div>'
                . '<div style="font-size:12px;color:#667382;">' . $e($rotulo) . '</div></td>';
        };

        // ------------------------------------------------- {{numeros}}
        $numeros = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;"><tr>'
            . $numero((string) $resumo['respostas'], __('respostas', 'npsphere'), '#206bc4')
            . $numero((string) $resumo['baixas'], __('com nota baixa', 'npsphere'), $resumo['baixas'] > 0 ? '#d63939' : '#2fb344')
            . $numero(
                $resumo['media'] !== null ? self::formatarNota((float) $resumo['media']) : '—',
                __('nota média', 'npsphere'),
                $resumo['media'] !== null ? self::corDaNota((float) $resumo['media']) : '#667382'
            )
            . '</tr></table>'
            . '<p style="margin:8px 0 0;' . $f . 'font-size:12px;color:#667382;">'
            . $e(sprintf(
                __('A nota de cada resposta é a mesma da coluna "Nota" da aba Resultados (de 0 a 10). Nota baixa: até %d.', 'npsphere'),
                $resumo['corte']
            ))
            . '</p>';

        // -------------------------------------------- {{notas_baixas}}
        $lista_baixas = '';
        if ($baixas !== []) {
            $lista_baixas = $titulo(__('Notas baixas', 'npsphere'), '#d63939')
                . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;border:1px solid #f1c0c0;">';
            foreach ($baixas as $b) {
                $lista_baixas .= '<tr>'
                    . '<td width="44" valign="middle" style="padding:10px 0 10px 12px;background:#fdf3f3;border-bottom:1px solid #f1c0c0;">' . $pilula($b['nota']) . '</td>'
                    . '<td valign="middle" style="padding:10px 12px;background:#fdf3f3;border-bottom:1px solid #f1c0c0;' . $f . 'font-size:13px;color:#1d273b;">'
                    . '<strong>' . $e($b['pessoa']) . '</strong>'
                    . ($b['chamado'] !== null ? '<br><span style="color:#667382;">' . $e(__('Chamado', 'npsphere')) . '</span> ' . self::linkDoChamado($b['chamado']) : '')
                    . '</td>'
                    . '<td align="right" valign="middle" style="padding:10px 12px;background:#fdf3f3;border-bottom:1px solid #f1c0c0;' . $f . 'font-size:12px;white-space:nowrap;">'
                    . $ver($b['id']) . '</td>'
                    . '</tr>';
            }
            $lista_baixas .= '</table>';
        }

        // ------------------------------------ {{respostas_por_pessoa}}
        $por_pessoa = $titulo(__('Todas as respostas, por pessoa', 'npsphere'));
        foreach ($pessoas as $p) {
            $qtd = count($p['respostas']);
            $por_pessoa .= '<div style="margin:14px 0 0;padding:0 0 6px 0;border-bottom:2px solid #206bc4;' . $f . '">'
                . '<span style="font-size:15px;font-weight:bold;color:#1d273b;">' . $e($p['nome']) . '</span>'
                . ($p['email'] !== '' ? ' <span style="font-size:13px;color:#667382;">' . $e($p['email']) . '</span>' : '')
                . ' <span style="font-size:12px;color:#667382;">· ' . $e(sprintf(_n('%d resposta', '%d respostas', $qtd, 'npsphere'), $qtd)) . '</span>'
                . '</div>';

            foreach ($p['respostas'] as $r) {
                $fundo = $r['baixa'] ? '#fdf3f3' : '#f8f9fb';
                $por_pessoa .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"'
                    . ' style="width:100%;margin-top:10px;border-collapse:collapse;border:1px solid #e6e7e9;">'
                    . '<tr><td colspan="2" style="padding:0;background:' . $fundo . ';">'
                    . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
                    . '<td width="44" valign="middle" style="padding:8px 0 8px 10px;">' . $pilula($r['nota']) . '</td>'
                    . '<td valign="middle" style="padding:8px 10px;' . $f . 'font-size:13px;color:#1d273b;">'
                    . ($r['chamado'] !== null
                        ? $e(__('Chamado', 'npsphere')) . ' ' . self::linkDoChamado($r['chamado'])
                        : $e(__('Resposta', 'npsphere')))
                    . ($r['baixa'] ? ' <span style="color:#d63939;font-weight:bold;">· ' . $e(__('nota baixa', 'npsphere')) . '</span>' : '')
                    . '<br><span style="font-size:12px;color:#667382;">' . $e(self::data($r['data'])) . '</span></td>'
                    . '<td align="right" valign="middle" style="padding:8px 10px;' . $f . 'font-size:12px;white-space:nowrap;">'
                    . $ver($r['id']) . '</td>'
                    . '</tr></table></td></tr>'
                    . self::linhasDaResposta($r['linhas'])
                    . '</table>';
            }
        }

        return [
            '{{numeros}}'              => $numeros,
            '{{notas_baixas}}'         => $lista_baixas,
            '{{respostas_por_pessoa}}' => $por_pessoa,
            '{{link_resultados}}'      => $links
                ? '<a href="' . $e($resultados) . '" style="color:#206bc4;">' . $e(__('Abrir os resultados da pesquisa', 'npsphere')) . '</a>'
                : $e(__('Os links para os resultados não aparecem porque o seu perfil no GLPI não abre esta pesquisa. Os links dos chamados continuam valendo.', 'npsphere')),
        ];
    }

    /** @param array{id: int, titulo: string, url: string} $c */
    private static function linkDoChamado(array $c): string
    {
        $e = static fn($t): string => htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');
        $texto = '#' . $c['id'] . ($c['titulo'] !== '' ? ' — ' . $c['titulo'] : '');

        return $c['url'] !== ''
            ? '<a href="' . $e($c['url']) . '" style="color:#206bc4;text-decoration:none;">' . $e($texto) . '</a>'
            : $e($texto);
    }

    /**
     * Linhas pergunta | resposta (duas colunas), para dentro de uma tabela.
     *
     * @param array<int, array{pergunta: string, resposta: string}> $linhas
     */
    private static function linhasDaResposta(array $linhas): string
    {
        $e = static fn($t): string => htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');
        $f = 'font-family:Arial,Helvetica,sans-serif;';
        $html = '';

        foreach ($linhas as $l) {
            $html .= '<tr>'
                . '<td valign="top" width="45%" style="padding:7px 10px;border-top:1px solid #eef0f3;' . $f . 'font-size:13px;color:#667382;">' . $e($l['pergunta']) . '</td>'
                . '<td valign="top" style="padding:7px 10px;border-top:1px solid #eef0f3;' . $f . 'font-size:13px;color:#1d273b;">'
                . nl2br($e($l['resposta'] !== '' ? $l['resposta'] : '—')) . '</td>'
                . '</tr>';
        }

        return $html;
    }

    // ------------------------------------------------------------------
    // Aviso imediato de nota baixa (v0.60.0)
    // ------------------------------------------------------------------

    /**
     * E-mail curto de UMA resposta com nota baixa, para o gestor agir no
     * mesmo dia, a partir do modelo do aviso (sub-aba "Relatório e
     * aviso"; em branco, o padrão).
     *
     * @param array<string, mixed> $item itemDaResposta()
     * @return array{assunto: string, html: string, html_sem_links: string}
     */
    private static function montarAviso(Survey $survey, array $item, int $espera, int $corte, ?string $corpo = null, ?string $assunto = null): array
    {
        [$modelo_assunto, $modelo_corpo] = self::textos(
            $survey,
            'alert',
            $assunto,
            $corpo,
            self::getAssuntoPadraoAviso(),
            self::getCorpoPadraoAviso()
        );

        $nota = $item['nota'] !== null ? self::formatarNota((float) $item['nota']['score']) : '—';
        $texto = [
            '{{pesquisa}}'       => (string) $survey->fields['name'],
            '{{nota}}'           => $nota,
            '{{pessoa}}'         => (string) $item['pessoa'],
            '{{email}}'          => (string) $item['email'],
            '{{telefone}}'       => (string) $item['telefone'],
            '{{chamado_id}}'     => $item['chamado'] !== null ? (string) $item['chamado']['id'] : '',
            '{{chamado_titulo}}' => $item['chamado'] !== null ? (string) $item['chamado']['titulo'] : '',
            '{{data}}'           => self::data($item['data']),
            '{{espera}}'         => (string) $espera,
            '{{nota_corte}}'     => (string) $corte,
        ];

        $versao = function (bool $links) use ($survey, $item, $texto, $modelo_corpo): string {
            $valores = self::blocosAviso($survey, $item, $links) + self::escapar($texto);

            return self::envelope(strtr($modelo_corpo, $valores), 600, '#d63939');
        };

        return [
            'assunto'        => strtr($modelo_assunto, $texto),
            'html'           => $versao(true),
            'html_sem_links' => $versao(false),
        ];
    }

    /**
     * Blocos prontos do aviso ({{cartao}}, {{respostas}},
     * {{botao_resposta}}, {{chamado}}).
     *
     * @param array<string, mixed> $item
     * @return array<string, string>
     */
    private static function blocosAviso(Survey $survey, array $item, bool $links): array
    {
        $e = static fn($t): string => htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');
        $f = 'font-family:Arial,Helvetica,sans-serif;';

        $resposta = SurveyMailer::getBaseAbsoluta((int) ($survey->fields['entities_id'] ?? 0))
            . '/front/survey.form.php?id=' . $survey->getID()
            . '&forcetab=' . rawurlencode(SurveyResults::class . '$1')
            . '&npsphere_resposta=' . $item['id'];

        $nota = $item['nota'] !== null ? self::formatarNota((float) $item['nota']['score']) : '—';
        $cor  = $item['nota'] !== null ? self::corDaNota((float) $item['nota']['score']) : '#667382';

        $contato = [];
        if ($item['email'] !== '') {
            $contato[] = '<a href="mailto:' . $e($item['email']) . '" style="color:#206bc4;">' . $e($item['email']) . '</a>';
        }
        if ($item['telefone'] !== '') {
            $contato[] = $e($item['telefone']);
        }

        $chamado = $item['chamado'] !== null ? self::linkDoChamado($item['chamado']) : '';

        $cartao = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:0 0 12px;"><tr>'
            . '<td width="70" valign="top" align="center" style="padding:4px 14px 4px 0;">'
            . '<div style="' . $f . 'font-size:30px;font-weight:bold;line-height:1;color:' . $cor . ';">' . $e($nota) . '</div>'
            . '<div style="' . $f . 'font-size:11px;color:#667382;">' . $e(__('de 10', 'npsphere')) . '</div></td>'
            . '<td valign="top" style="' . $f . 'font-size:14px;color:#1d273b;">'
            . '<div style="font-size:16px;font-weight:bold;">' . $e($item['pessoa']) . '</div>'
            . ($contato !== [] ? '<div style="font-size:13px;color:#667382;margin-top:2px;">' . implode(' · ', $contato) . '</div>' : '')
            . ($chamado !== '' ? '<div style="font-size:13px;margin-top:6px;">' . $e(__('Chamado', 'npsphere')) . ' ' . $chamado . '</div>' : '')
            . '<div style="font-size:12px;color:#667382;margin-top:4px;">' . $e(sprintf(__('Respondida em %s', 'npsphere'), self::data($item['data']))) . '</div>'
            . '</td></tr></table>';

        $respostas = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;border:1px solid #e6e7e9;">'
            . self::linhasDaResposta($item['linhas'])
            . '</table>';

        $botao = $links
            ? '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:14px 0 0;"><tr>'
                . '<td bgcolor="#206bc4" style="background:#206bc4;border-radius:6px;mso-padding-alt:9px 16px;">'
                . '<a href="' . $e($resposta) . '" target="_blank" style="display:inline-block;padding:9px 16px;' . $f . 'font-size:13px;font-weight:bold;color:#ffffff;text-decoration:none;">'
                . $e(__('Ver resposta no GLPI', 'npsphere')) . '</a></td></tr></table>'
            : '<p style="margin:14px 0 0;' . $f . 'font-size:12px;color:#667382;">'
                . $e(__('O link para a resposta não aparece porque o seu perfil no GLPI não abre esta pesquisa.', 'npsphere')) . '</p>';

        return [
            '{{cartao}}'         => $cartao,
            '{{respostas}}'      => $respostas,
            '{{botao_resposta}}' => $botao,
            '{{chamado}}'        => $chamado,
        ];
    }

    /**
     * Respostas com 'valores', já com as AnswerValue.
     *
     * @param array<string, mixed> $where
     * @return array<int, array<string, mixed>>
     */
    private static function buscarRespostas(array $where, int $limite = 0): array
    {
        global $DB;

        $consulta = ['FROM' => Answer::getTable(), 'WHERE' => $where, 'ORDER' => 'date_mod DESC'];
        if ($limite > 0) {
            $consulta['LIMIT'] = $limite;
        }

        $respostas = [];
        foreach ($DB->request($consulta) as $a) {
            $respostas[(int) $a['id']] = $a + ['valores' => []];
        }

        if ($respostas !== []) {
            foreach ($DB->request([
                'FROM'  => AnswerValue::getTable(),
                'WHERE' => ['answers_id' => array_keys($respostas)],
            ]) as $v) {
                $respostas[(int) $v['answers_id']]['valores'][(int) $v['questions_id']] = $v;
            }
        }

        return $respostas;
    }

    /** @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>} perguntas, com nota */
    private static function perguntas(Survey $survey): array
    {
        global $DB;

        $perguntas = [];
        foreach ($DB->request([
            'FROM'  => Question::getTable(),
            'WHERE' => ['surveys_id' => $survey->getID()],
            'ORDER' => ['position ASC', 'id ASC'],
        ]) as $q) {
            $perguntas[(int) $q['id']] = $q;
        }

        return [$perguntas, MetricsCalculator::getScoreQuestions($perguntas)];
    }

    /**
     * Prévia do aviso: a resposta com nota baixa mais recente dos últimos
     * 30 dias (com a nota de corte e a espera da tela).
     *
     * @return array{assunto: string, html: string}|null
     */
    public static function previaDoAviso(Survey $survey, int $corte, int $espera, ?string $corpo = null, ?string $assunto = null): ?array
    {
        $agora = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        [$perguntas, $com_nota] = self::perguntas($survey);
        $anonima = !empty($survey->fields['is_anonymous']);

        foreach (self::buscarRespostas([
            'surveys_id' => $survey->getID(),
            ['date_mod' => ['>', date('Y-m-d H:i:s', strtotime($agora . ' -30 days'))]],
        ], 200) as $r) {
            $item = self::itemDaResposta($r, $perguntas, $com_nota, $anonima, $corte);
            if ($item['baixa']) {
                $aviso = self::montarAviso($survey, $item, $espera, $corte, $corpo, $assunto);
                return ['assunto' => $aviso['assunto'], 'html' => $aviso['html']];
            }
        }

        return null;
    }

    /**
     * Manda o aviso das respostas com nota baixa que ficaram paradas pelo
     * tempo de espera e ainda não foram avisadas.
     *
     * Só entram respostas gravadas depois de o aviso ser ligado
     * (alert_since) e das últimas 24 horas: ligar o aviso não dispara
     * uma enxurrada de e-mails sobre respostas antigas.
     *
     * @return int 1 = enviou algo, 0 = nada a fazer
     */
    public static function cronNpsphereAviso($task = null): int
    {
        global $DB;

        $agora    = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $enviados = 0;
        $falhas   = 0;

        foreach ($DB->request([
            'FROM'  => Survey::getTable(),
            'WHERE' => ['alert_active' => 1, 'is_deleted' => 0],
        ]) as $dados) {
            $survey = new Survey();
            if (!$survey->getFromDB((int) $dados['id'])) {
                continue;
            }

            $espera = self::getEspera($survey);
            $corte  = self::getCorte($survey, 'alert');
            $ate    = date('Y-m-d H:i:s', strtotime($agora . ' -' . $espera . ' minutes'));
            $desde  = max(
                (string) ($survey->fields['alert_since'] ?? ''),
                date('Y-m-d H:i:s', strtotime($agora . ' -1 day'))
            );

            $respostas = self::buscarRespostas([
                'surveys_id'    => $survey->getID(),
                'date_alerted'  => null,
                ['date_mod' => ['>', $desde]],
                ['date_mod' => ['<=', $ate]],
            ]);
            if ($respostas === []) {
                continue;
            }

            [$perguntas, $com_nota] = self::perguntas($survey);
            $anonima = !empty($survey->fields['is_anonymous']);
            $destinatarios = null;

            foreach ($respostas as $r) {
                $item = self::itemDaResposta($r, $perguntas, $com_nota, $anonima, $corte);
                if (!$item['baixa']) {
                    // Não marca: se a pessoa mudar a nota para baixa depois,
                    // a resposta volta a ser conferida.
                    continue;
                }

                $destinatarios ??= self::getDestinatarios($survey, 'alert');
                $aviso = self::montarAviso($survey, $item, $espera, $corte);

                foreach ($destinatarios as $pessoa) {
                    $ok = SurveyMailer::enfileirar(
                        $pessoa['email'],
                        $pessoa['nome'],
                        $aviso['assunto'],
                        $pessoa['acesso'] ? $aviso['html'] : $aviso['html_sem_links'],
                        $survey,
                        NotificationTargetSurvey::EVENTO_AVISO
                    );
                    $ok ? $enviados++ : $falhas++;
                }

                // Um aviso por resposta, mesmo sem ninguém para receber.
                $DB->update(Answer::getTable(), ['date_alerted' => $agora], ['id' => $item['id']]);
                if ($destinatarios !== []) {
                    $DB->update(Survey::getTable(), ['date_last_alert' => $agora], ['id' => $survey->getID()]);
                }
            }
        }

        if ($task !== null) {
            $task->addVolume($enviados);
            if ($enviados > 0 || $falhas > 0) {
                $task->log(sprintf(__('%1$d aviso(s) de nota baixa enfileirado(s), %2$d falha(s).', 'npsphere'), $enviados, $falhas));
            }
        }

        return $enviados > 0 ? 1 : 0;
    }

    // ------------------------------------------------------------------
    // Ação automática
    // ------------------------------------------------------------------

    /**
     * @return array{description: string, parameter?: string}
     */
    public static function cronInfo($name)
    {
        if ($name === self::CRON) {
            return ['description' => __('NPSphere — relatório diário das respostas para os gestores', 'npsphere')];
        }
        if ($name === self::CRON_AVISO) {
            return ['description' => __('NPSphere — aviso imediato de nota baixa para os gestores', 'npsphere')];
        }

        return [];
    }

    /**
     * Manda o relatório das pesquisas cujo horário já chegou hoje e que
     * ainda não receberam o de hoje.
     *
     * @return int 1 = enviou algo, 0 = nada a fazer
     */
    public static function cronNpsphereRelatorio($task = null): int
    {
        global $DB;

        $agora    = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $agora_ts = strtotime($agora) ?: time();
        $enviados = 0;
        $falhas   = 0;

        foreach ($DB->request([
            'FROM'  => Survey::getTable(),
            'WHERE' => ['report_active' => 1, 'is_deleted' => 0],
        ]) as $dados) {
            $survey = new Survey();
            if (!$survey->getFromDB((int) $dados['id'])) {
                continue;
            }

            $hora = self::getHora($survey);
            if ((int) date('G', $agora_ts) < $hora) {
                continue; // ainda não chegou o horário de hoje
            }

            $marco_de_hoje = date('Y-m-d', $agora_ts) . sprintf(' %02d:00:00', $hora);
            $ultimo = (string) ($survey->fields['date_last_report'] ?? '');
            if ($ultimo !== '' && $ultimo >= $marco_de_hoje) {
                continue; // o de hoje já saiu
            }

            // Desde o relatório anterior; no primeiro, as últimas 24h.
            $inicio = $ultimo !== '' ? $ultimo : date('Y-m-d H:i:s', strtotime($marco_de_hoje . ' -1 day'));

            $relatorio = self::montar($survey, $inicio, $agora);

            $saiu = 0;
            if ($relatorio['respostas'] > 0) {
                foreach (self::getDestinatarios($survey) as $pessoa) {
                    $ok = SurveyMailer::enfileirar(
                        $pessoa['email'],
                        $pessoa['nome'],
                        $relatorio['assunto'],
                        $pessoa['acesso'] ? $relatorio['html'] : $relatorio['html_sem_links'],
                        $survey,
                        NotificationTargetSurvey::EVENTO_RELATORIO
                    );
                    if ($ok) {
                        $enviados++;
                        $saiu++;
                    } else {
                        $falhas++;
                    }
                }
            }

            // Marca o dia como PROCESSADO mesmo sem respostas (nada a
            // enviar) ou sem destinatários: o próximo relatório começa
            // daqui, em vez de acumular dias. O "último envio" da tela é
            // outro campo (date_report_sent), que só muda quando um e-mail
            // sai de verdade (v0.62.1).
            $marcar = ['date_last_report' => $agora];
            if ($saiu > 0) {
                $marcar['date_report_sent'] = $agora;
            }
            $DB->update(Survey::getTable(), $marcar, ['id' => $survey->getID()]);
        }

        if ($task !== null) {
            $task->addVolume($enviados);
            if ($enviados > 0 || $falhas > 0) {
                $task->log(sprintf(__('%1$d relatório(s) enfileirado(s), %2$d falha(s).', 'npsphere'), $enviados, $falhas));
            }
        }

        return $enviados > 0 ? 1 : 0;
    }

    /**
     * @return array{id: int, ativa: bool, url: string}|null
     */
    public static function getAcaoAutomatica(string $nome = self::CRON): ?array
    {
        $cron = new \CronTask();
        if (!$cron->getFromDBbyName(self::class, $nome)) {
            return null;
        }

        return [
            'id'    => (int) $cron->getID(),
            'ativa' => (int) $cron->fields['state'] !== \CronTask::STATE_DISABLE,
            'url'   => \CronTask::getFormURLWithID((int) $cron->getID()),
        ];
    }

    /** Cria a ação automática (instalação/atualização; pode repetir). */
    public static function registrarAcaoAutomatica(): void
    {
        \CronTask::register(self::class, self::CRON, HOUR_TIMESTAMP, [
            'state'   => \CronTask::STATE_WAITING,
            'hourmin' => 0,
            'hourmax' => 24,
            'comment' => __('Envia o relatório diário das respostas (sub-aba "Relatório" da aba Template) no horário escolhido em cada pesquisa.', 'npsphere'),
        ]);

        // Aviso imediato: a cada 5 minutos, o dia todo. O aviso chega
        // entre a espera escolhida e a espera + 5 minutos.
        \CronTask::register(self::class, self::CRON_AVISO, 5 * MINUTE_TIMESTAMP, [
            'state'   => \CronTask::STATE_WAITING,
            'hourmin' => 0,
            'hourmax' => 24,
            'comment' => __('Envia o aviso de nota baixa (sub-aba "Aviso" da aba Template) alguns minutos depois da resposta.', 'npsphere'),
        ]);
    }
}
