<?php

namespace GlpiPlugin\Npsphere;

use Session;

/**
 * Orquestra a submissão de uma resposta completa a uma pesquisa:
 * elegibilidade (pode responder?), validação de cada pergunta
 * (delegada ao QuestionType correspondente) e persistência atômica
 * de Answer + AnswerValue.
 *
 * Centralizar essa lógica aqui (em vez de espalhá-la pelo front/*.php)
 * é o que permite reaproveitar as mesmas regras tanto no formulário
 * autenticado (Fase 3) quanto, futuramente, no formulário público
 * (sub-fase dedicada) sem duplicar validação.
 */
class ResponseService
{
    /**
     * Verifica se o usuário atual pode responder a esta pesquisa agora.
     *
     * @return array{can: bool, reason: string|null}
     */
    public static function canRespond(Survey $survey, bool $substituindo = false): array
    {
        if (!$survey->fields['is_active']) {
            return self::blocked(__('Esta pesquisa não está mais ativa.', 'npsphere'));
        }

        $today = date('Y-m-d');
        if (!empty($survey->fields['date_begin']) && $today < $survey->fields['date_begin']) {
            return self::blocked(__('Esta pesquisa ainda não está disponível.', 'npsphere'));
        }
        if (!empty($survey->fields['date_end']) && $today > $survey->fields['date_end']) {
            return self::blocked(__('O prazo para responder esta pesquisa terminou.', 'npsphere'));
        }

        // Pesquisa VINCULADA A CHAMADO: a unicidade é por CHAMADO, não
        // por pessoa.
        //
        // Uma única pesquisa atende todos os chamados da entidade. A
        // verificação por usuário -- correta nos demais tipos --
        // bloqueava a partir do segundo chamado: a pessoa preenchia
        // tudo e só no envio recebia "Você já respondeu esta pesquisa",
        // referindo-se a OUTRO chamado. Quem controla a duplicidade
        // aqui é TicketLink::jaRespondido(), por tickets_id.
        if (($survey->fields['type'] ?? '') !== Survey::TYPE_TICKET) {
            // Substituindo a própria resposta (novo clique no e-mail, ou
            // completando a gravada no clique): "já respondeu" é o
            // esperado, não um impedimento.
            if (!$substituindo && empty($survey->fields['allow_multiple_answers']) && self::hasAlreadyAnswered($survey)) {
                return self::blocked(__('Você já respondeu esta pesquisa.', 'npsphere'));
            }
        }

        return ['can' => true, 'reason' => null];
    }

    /**
     * Resposta mais recente deste usuário nesta pesquisa (0 se não há).
     * Usada para que um novo clique no e-mail troque a resposta em vez
     * de criar outra.
     */
    public static function getRespostaDoUsuario(int $surveys_id, int $users_id): int
    {
        global $DB;

        if ($surveys_id <= 0 || $users_id <= 0) {
            return 0;
        }

        $linha = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => Answer::getTable(),
            'WHERE'  => ['surveys_id' => $surveys_id, 'users_id' => $users_id],
            'ORDER'  => 'id DESC',
            'LIMIT'  => 1,
        ])->current();

        return (int) ($linha['id'] ?? 0);
    }

    /**
     * Resposta do chamado (0 se ainda não avaliado). Um chamado, uma
     * avaliação: um novo clique troca a nota desta mesma resposta.
     */
    public static function getRespostaDoChamado(int $tickets_id): int
    {
        global $DB;

        if ($tickets_id <= 0) {
            return 0;
        }

        $linha = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => Answer::getTable(),
            'WHERE'  => ['tickets_id' => $tickets_id],
            'ORDER'  => 'id DESC',
            'LIMIT'  => 1,
        ])->current();

        return (int) ($linha['id'] ?? 0);
    }

    private static function blocked(string $reason): array
    {
        return ['can' => false, 'reason' => $reason];
    }

    public static function hasAlreadyAnswered(Survey $survey): bool
    {
        global $DB;

        $users_id = Session::getLoginUserID();
        if (!$users_id) {
            return false;
        }

        // Também considera o registro de conclusão: numa pesquisa
        // anônima é a única evidência de que esta pessoa já respondeu.
        if (Completion::hasCompleted($survey->getID(), (int) $users_id)) {
            return true;
        }

        $iterator = $DB->request([
            'COUNT' => 'cpt',
            'FROM'  => Answer::getTable(),
            'WHERE' => [
                'surveys_id' => $survey->getID(),
                'users_id'   => $users_id,
            ],
        ]);

        // Consulta sem resultado não deve virar erro: na dúvida,
        // considera que NÃO respondeu -- bloquear alguém por causa de
        // uma consulta vazia seria pior do que deixar responder.
        $linha = $iterator->current();

        return is_array($linha) && (int) ($linha['cpt'] ?? 0) > 0;
    }

    /**
     * Texto das respostas abertas, para o campo "comment" do GLPI.
     *
     * A satisfação nativa tem um único campo de comentário, enquanto a
     * pesquisa pode ter várias perguntas de texto. Elas são reunidas em
     * linhas, na ordem das perguntas -- perder os comentários seria
     * jogar fora justamente a parte qualitativa da avaliação.
     */
    private static function getCommentForAnswer(int $answers_id): string
    {
        global $DB;

        if ($answers_id <= 0) {
            return '';
        }

        $values_table = AnswerValue::getTable();
        $questions_table = Question::getTable();

        $partes = [];

        foreach ($DB->request([
            'SELECT'     => ["$values_table.text_value", "$questions_table.name"],
            'FROM'       => $values_table,
            'INNER JOIN' => [
                $questions_table => [
                    'ON' => [
                        $values_table    => 'questions_id',
                        $questions_table => 'id',
                    ],
                ],
            ],
            'WHERE' => [
                "$values_table.answers_id" => $answers_id,
                ['NOT' => ["$values_table.text_value" => null]],
                ['NOT' => ["$values_table.text_value" => '']],
            ],
            'ORDER' => "$questions_table.position ASC",
        ]) as $row) {
            $texto = trim((string) $row['text_value']);
            if ($texto === '') {
                continue;
            }

            // Uma pergunta só: o comentário é o texto puro. Várias:
            // cada uma vem rotulada, senão o comentário viraria um
            // amontoado sem contexto.
            $partes[] = ['nome' => (string) $row['name'], 'texto' => $texto];
        }

        if (empty($partes)) {
            return '';
        }

        if (count($partes) === 1) {
            return $partes[0]['texto'];
        }

        return implode("\n", array_map(
            static fn(array $p): string => $p['nome'] . ': ' . $p['texto'],
            $partes
        ));
    }

    /**
     * Entidade a gravar na resposta.
     *
     * NÃO é a entidade da pesquisa. Uma pesquisa criada em BRA3 e
     * marcada como recursiva atende também BRA3 > Distribuição -- e
     * gravar sempre "BRA3" apagava a informação de onde a avaliação
     * realmente veio, justamente o que o filtro por entidades precisa
     * para funcionar.
     *
     * A ordem de preferência reflete a fonte mais específica disponível:
     *
     *  1. A entidade do CHAMADO avaliado, quando há chamado -- é o
     *     atendimento sendo avaliado, e ele pertence a uma entidade;
     *  2. A entidade ativa de quem responde, para pesquisa interna;
     *  3. A entidade da pesquisa, como último recurso -- é o caso do
     *     link público, em que quem responde não tem entidade.
     */
    private static function resolveEntity(Survey $survey, array $post): int
    {
        $tickets_id = (int) ($post['tickets_id'] ?? 0);
        if ($tickets_id > 0 && class_exists('\\Ticket')) {
            $ticket = new \Ticket();
            if ($ticket->getFromDB($tickets_id)) {
                return (int) $ticket->fields['entities_id'];
            }
        }

        if (Session::getLoginUserID()) {
            $ativa = Session::getActiveEntity();
            if ($ativa !== null && $ativa !== '') {
                return (int) $ativa;
            }
        }

        return (int) ($survey->fields['entities_id'] ?? 0);
    }

    /**
     * Dados de identificação a partir do cadastro do usuário no GLPI.
     *
     * @return array{respondent_name: string, respondent_email: string, respondent_phone: string}
     */
    private static function getIdentityFromUser(int $users_id): array
    {
        $empty = ['respondent_name' => '', 'respondent_email' => '', 'respondent_phone' => ''];

        if ($users_id <= 0) {
            return $empty;
        }

        // class_exists: melhor gravar a resposta sem identificação do
        // que perder a resposta inteira por um erro fatal, caso a
        // classe do GLPI não esteja disponível no contexto.
        if (!class_exists('\\User')) {
            return $empty;
        }

        $user = new \User();
        if (!$user->getFromDB($users_id)) {
            return $empty;
        }

        // getFriendlyName() respeita o formato de nome configurado na
        // instalação (nome + sobrenome, ou o login quando não há nome).
        $name = $user->getFriendlyName();

        // O e-mail principal fica em outra tabela (glpi_useremails);
        // getDefaultEmail() é o acessor do GLPI para ele.
        $email = method_exists($user, 'getDefaultEmail') ? (string) $user->getDefaultEmail() : '';

        $phone = (string) ($user->fields['phone'] ?? '');
        if ($phone === '') {
            $phone = (string) ($user->fields['mobile'] ?? '');
        }

        return [
            'respondent_name'  => $name !== '' ? $name : ($user->fields['name'] ?? ''),
            'respondent_email' => $email,
            'respondent_phone' => $phone,
        ];
    }

    /**
     * Valida e persiste uma submissão completa.
     *
     * @param Survey $survey    Pesquisa sendo respondida
     * @param array  $post      Dados brutos de $_POST
     * @param int    $started_at Timestamp Unix de quando o formulário foi carregado
     * @param array  $opcoes    'answers_id' => resposta existente a SUBSTITUIR
     *                          (convite pessoal: uma resposta por convite);
     *                          'parcial' => true para gravar sem exigir as
     *                          perguntas obrigatórias que ainda faltam
     *                          (clique no e-mail numa pesquisa com várias
     *                          perguntas -- o restante vem depois);
     *                          'users_id' => em nome de quem gravar, quando
     *                          quem responde não está logado (link do
     *                          e-mail do chamado).
     *
     * @return array{success: bool, errors: array<int, string>, answers_id: int|null}
     */
    public static function submit(Survey $survey, array $post, int $started_at, array $identity = [], array $opcoes = []): array
    {
        $parcial       = !empty($opcoes['parcial']);
        $substituir_id = (int) ($opcoes['answers_id'] ?? 0);

        // PRÉ-VISUALIZAÇÃO: a resposta é validada normalmente, mas
        // NÃO é gravada.
        //
        // A aba "Visualizar" existe para o administrador conferir como
        // a pesquisa ficou. Se essas respostas de teste fossem
        // gravadas, entrariam no NPS, na nota média e na lista de quem
        // respondeu -- corrompendo justamente os números que ele está
        // conferindo.
        //
        // A checagem fica AQUI, e não só nas rotas, porque qualquer
        // caminho novo que venha a chamar submit() herda a proteção,
        // em vez de depender de alguém lembrar de repetir a validação.
        $is_preview = !empty($post['preview']);

        // A elegibilidade (já respondeu? está no prazo? pesquisa ativa?)
        // NÃO se aplica à pré-visualização.
        //
        // Ela protege os DADOS: impede resposta duplicada ou fora do
        // prazo. Como a pré-visualização não grava nada, não há dado a
        // proteger -- e barrá-la produzia justamente o efeito errado:
        // o administrador testava uma vez e a aba passava a responder
        // "Você já respondeu esta pesquisa" para sempre.
        // Trava por CHAMADO no momento da gravação: dois envios
        // simultâneos do mesmo chamado não podem virar duas avaliações.
        $tickets_id_check = (int) ($post['tickets_id'] ?? 0);
        if (!$is_preview && $tickets_id_check > 0 && $substituir_id === 0 && TicketLink::jaRespondido($tickets_id_check)) {
            return [
                'success'    => false,
                'errors'     => [__('Este chamado já foi avaliado.', 'npsphere')],
                'answers_id' => null,
            ];
        }

        if (!$is_preview) {
            $eligibility = self::canRespond($survey, $substituir_id > 0);
            if (!$eligibility['can']) {
                return ['success' => false, 'errors' => [$eligibility['reason']], 'answers_id' => null];
            }
        }

        $questions = Question::getActiveQuestions($survey->getID());
        if (empty($questions)) {
            return ['success' => false, 'errors' => [__('Esta pesquisa ainda não possui perguntas.', 'npsphere')], 'answers_id' => null];
        }

        $errors = [];
        $validated = []; // questions_id => resultado de validateAnswerValue()

        // Índice das respostas brutas por pergunta, usado para avaliar as
        // regras condicionais (Fase 5) aqui no SERVIDOR. Nunca confiar
        // apenas no JavaScript que esconde/mostra as perguntas: sem esta
        // verificação, alguém poderia enviar resposta a uma pergunta que
        // nem deveria aparecer, ou escapar de uma pergunta obrigatória.
        $raw_by_question = [];
        foreach ($questions as $qid => $q) {
            $field = 'answer_' . $qid;
            if (array_key_exists($field, $post)) {
                $raw_by_question[$qid] = $post[$field];
            }
        }

        foreach ($questions as $questions_id => $question) {
            // Pergunta condicionada cuja condição não foi satisfeita:
            // é ignorada por completo -- não é obrigatória, e qualquer
            // valor enviado para ela é descartado (não vai para o banco).
            if (!ConditionEvaluator::isVisible($question, $raw_by_question)) {
                continue;
            }

            $type_class = QuestionTypeManager::get($question['type']);
            if ($type_class === null) {
                continue; // tipo desconhecido (não deveria acontecer); ignora com segurança
            }

            $configuration = json_decode($question['configuration'] ?? '{}', true);
            if (!is_array($configuration)) {
                $configuration = [];
            }
            $configuration = array_merge($type_class::getDefaultConfiguration(), $configuration);

            $field_name = 'answer_' . $questions_id;
            $is_checkbox_group = ($question['type'] === 'multiple_choice');
            $raw = $is_checkbox_group ? ($post[$field_name] ?? []) : ($post[$field_name] ?? null);

            $is_empty = $is_checkbox_group ? empty($raw) : ($raw === null || $raw === '');

            if ($is_empty) {
                if ($question['is_required'] && !$parcial) {
                    $errors[] = sprintf(__('"%s" é obrigatória.', 'npsphere'), $question['name']);
                }
                continue; // pergunta opcional não respondida: não grava linha
            }

            $result = $type_class::validateAnswerValue($raw, $configuration);
            if (!$result['valid']) {
                $errors[] = sprintf('%s: %s', $question['name'], $result['error']);
                continue;
            }

            $validated[$questions_id] = [
                'raw'    => $raw,
                'result' => $result,
            ];
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'answers_id' => null];
        }

        if ($parcial && empty($validated)) {
            return ['success' => false, 'errors' => [__('Nenhuma resposta para gravar.', 'npsphere')], 'answers_id' => null];
        }

        // Ponto de corte da pré-visualização: TODA a validação acima já
        // rodou -- obrigatoriedade, regras condicionais, formato de cada
        // tipo -- então o administrador vê exatamente o comportamento
        // que o respondente teria. Só a GRAVAÇÃO é dispensada.
        //
        // Devolve sucesso de propósito: a experiência da aba
        // "Visualizar" deve ser idêntica à real, incluindo a tela de
        // confirmação. O 'preview' no retorno permite que a interface
        // deixe claro que nada foi registrado.
        if ($is_preview) {
            return [
                'success'    => true,
                'errors'     => [],
                'answers_id' => null,
                'preview'    => true,
            ];
        }

        // -----------------------------------------------------------------
        // Persistência atômica
        // -----------------------------------------------------------------
        $duration = max(0, min(86400, time() - $started_at));
        $is_anonymous = !empty($survey->fields['is_anonymous']);

        $answer = new Answer();
        $answer_input = [
            'surveys_id'   => $survey->getID(),
            'entities_id'  => self::resolveEntity($survey, $post),
            // 'users_id' nas opções: resposta pelo link do e-mail, sem
            // login -- é gravada em nome do requerente do chamado.
            'users_id'     => $is_anonymous ? 0 : (int) ($opcoes['users_id'] ?? Session::getLoginUserID()),
            'is_anonymous' => $is_anonymous ? 1 : 0,
            'token'        => bin2hex(random_bytes(16)),
            'duration'     => $duration,
        ];

        // Vínculo com o chamado (Fase 8). A coluna existe desde a Fase 3,
        // mas nunca era preenchida -- é ela que permitirá cruzar
        // satisfação com técnico, categoria e tempo de atendimento.
        $tickets_id = (int) ($post['tickets_id'] ?? 0);
        if ($tickets_id > 0) {
            $answer_input['tickets_id'] = $tickets_id;
        }

        // Identificação informada no formulário público (nome/e-mail/
        // telefone). Nunca é gravada em pesquisa anônima, mesmo que
        // venha no POST -- seria contradizer a promessa feita ao
        // respondente.
        if (!$is_anonymous) {
            if (!empty($identity)) {
                // Veio do formulário público (visitante identificado)
                $answer_input['respondent_name']  = $identity['respondent_name'] ?? '';
                $answer_input['respondent_email'] = $identity['respondent_email'] ?? '';
                $answer_input['respondent_phone'] = $identity['respondent_phone'] ?? '';
            } else {
                // Usuário autenticado do GLPI: copia os dados do cadastro
                // dele. Assim a lista "Quem respondeu" e a exportação
                // funcionam igual nos dois casos -- antes, respostas
                // internas apareciam sem nome nenhum.
                $answer_input += self::getIdentityFromUser((int) $answer_input['users_id']);
            }
        }

        // Convite pessoal já respondido: a MESMA resposta é reescrita
        // (valores trocados, identificação e duração atualizadas) em vez
        // de nascer outra. Escrita direta na tabela -- quem responde pelo
        // link público não tem perfil no GLPI para um update() comum.
        $answers_id = 0;
        if ($substituir_id > 0) {
            global $DB;

            $existe = $DB->request([
                'COUNT' => 'cpt',
                'FROM'  => Answer::getTable(),
                'WHERE' => ['id' => $substituir_id, 'surveys_id' => $survey->getID()],
            ])->current();

            if ((int) ($existe['cpt'] ?? 0) > 0) {
                $atualizar = $answer_input;
                unset($atualizar['token'], $atualizar['surveys_id']);
                $atualizar['date_mod'] = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');

                $DB->update(Answer::getTable(), $atualizar, ['id' => $substituir_id]);
                $DB->delete(AnswerValue::getTable(), ['answers_id' => $substituir_id]);
                $answers_id = $substituir_id;
            }
        }

        if ($answers_id === 0) {
            $answers_id = $answer->add($answer_input);
        }

        if (!$answers_id) {
            return ['success' => false, 'errors' => [__('Não foi possível salvar sua resposta. Tente novamente.', 'npsphere')], 'answers_id' => null];
        }

        foreach ($validated as $questions_id => $data) {
            $result = $data['result'];
            $answer_value = new AnswerValue();
            $answer_value->add([
                'answers_id'    => $answers_id,
                'questions_id'  => $questions_id,
                'value'         => is_array($data['raw']) ? implode(' | ', $data['raw']) : (string) $data['raw'],
                'numeric_value' => $result['numeric_value'],
                'text_value'    => $result['text_value'],
                'option_value'  => $result['option_value'],
            ]);
        }

        // Marca que este usuário concluiu a pesquisa. Vale inclusive
        // para pesquisa anônima -- onde a resposta em si não guarda
        // users_id -- para o aviso obrigatório parar de aparecer sem
        // que isso revele o que a pessoa respondeu.
        // try/catch: registrar a conclusão importa, mas a RESPOSTA já
        // está gravada neste ponto -- uma falha aqui não pode
        // transformar um envio bem-sucedido em erro para quem respondeu.
        try {
            // Com 'users_id' (link do e-mail, sem login), a conclusão é de
            // quem o link identifica -- não há usuário na sessão.
            Completion::register($survey->getID(), isset($opcoes['users_id']) ? (int) $opcoes['users_id'] : null);
        } catch (\Throwable $e) {
            // Silencioso, pelo motivo acima.
        }

        // Espelha a nota na satisfação NATIVA do GLPI, fechando o ciclo
        // também do lado dele: estatísticas, relatórios e dashboards
        // passam a enxergar a avaliação.
        if ($tickets_id_check > 0) {
            try {
                $score_questions = MetricsCalculator::getScoreQuestions(
                    Question::getActiveQuestions($survey->getID())
                );

                GlpiSatisfaction::sync(
                    $tickets_id_check,
                    MetricsCalculator::getAnswerScore($answers_id, $score_questions),
                    self::getCommentForAnswer($answers_id)
                );
            } catch (\Throwable $e) {
                // Mesma proteção: a resposta do plugin já está gravada.
            }
        }

        return ['success' => true, 'errors' => [], 'answers_id' => $answers_id];
    }
}
