<?php

namespace GlpiPlugin\Npsphere;

/**
 * Monta e envia o convite da pesquisa vinculada a chamado (Fase 9).
 *
 * O corpo vem da PRÓPRIA pesquisa (campo `mail_body`), com marcadores
 * substituídos. Cada pesquisa tem o seu texto.
 */
class SurveyMailer
{
    /**
     * Corpo padrão, usado quando a pesquisa não tem texto próprio.
     *
     * Existe para o convite funcionar desde a primeira pesquisa, sem
     * exigir que alguém escreva um e-mail antes de a notificação passar
     * a funcionar.
     */
    public static function getCorpoPadrao(string $tipo = 'ticket', ?Survey $survey = null): string
    {
        // O bloco de opções clicáveis é a variável da PRIMEIRA pergunta
        // com nota (ou da primeira pergunta, se nenhuma tiver nota). Sem
        // pesquisa ou sem perguntas, o modelo sai só com o botão.
        $pergunta = $survey !== null ? self::getMarcadorPrincipal($survey) : '';

        // Uma linha por bloco: no modo código o HTML fica legível, e a
        // quebra não muda nada no e-mail.
        if ($tipo !== Survey::TYPE_TICKET) {
            $blocos = [
                '<p>' . __('Olá {{destinatario}},', 'npsphere') . '</p>',
                '<p>' . __('Você foi convidado a responder a pesquisa <strong>{{pesquisa}}</strong>.', 'npsphere') . '</p>',
                '<p>' . __('Leva menos de um minuto.', 'npsphere') . '</p>',
            ];
        } else {
            $blocos = [
                '<p>' . __('Olá {{requerente}},', 'npsphere') . '</p>',
                '<p>' . __('Seu chamado <strong>#{{chamado_id}} — {{chamado_titulo}}</strong> foi encerrado.', 'npsphere') . '</p>',
                '<p>' . __('Sua avaliação leva menos de um minuto e ajuda a melhorar o atendimento.', 'npsphere') . '</p>',
            ];
        }

        if ($pergunta !== '') {
            $blocos[] = $pergunta;
        }
        $blocos[] = '{{botao}}';

        return implode("\n", $blocos);
    }

    public static function getAssuntoPadrao(string $tipo = 'ticket'): string
    {
        if ($tipo !== Survey::TYPE_TICKET) {
            return __('Sua opinião sobre: {{pesquisa}}', 'npsphere');
        }

        return __('Como foi o atendimento do chamado #{{chamado_id}}?', 'npsphere');
    }

    /**
     * Corpo padrão do LEMBRETE (v0.58.0): o mesmo formato do convite,
     * com o texto de quem ainda não respondeu.
     */
    public static function getCorpoPadraoLembrete(string $tipo = 'ticket', ?Survey $survey = null): string
    {
        $pergunta = $survey !== null ? self::getMarcadorPrincipal($survey) : '';

        if ($tipo !== Survey::TYPE_TICKET) {
            $blocos = [
                '<p>' . __('Olá {{destinatario}},', 'npsphere') . '</p>',
                '<p>' . __('Há alguns dias convidamos você a responder a pesquisa <strong>{{pesquisa}}</strong> e ainda não recebemos a sua resposta.', 'npsphere') . '</p>',
                '<p>' . __('Leva menos de um minuto — a sua opinião faz diferença.', 'npsphere') . '</p>',
            ];
        } else {
            $blocos = [
                '<p>' . __('Olá {{requerente}},', 'npsphere') . '</p>',
                '<p>' . __('Ainda não recebemos a sua avaliação do chamado <strong>#{{chamado_id}} — {{chamado_titulo}}</strong>.', 'npsphere') . '</p>',
                '<p>' . __('Leva menos de um minuto e ajuda a melhorar o atendimento.', 'npsphere') . '</p>',
            ];
        }

        if ($pergunta !== '') {
            $blocos[] = $pergunta;
        }
        $blocos[] = '{{botao}}';

        return implode("\n", $blocos);
    }

    public static function getAssuntoPadraoLembrete(string $tipo = 'ticket'): string
    {
        if ($tipo !== Survey::TYPE_TICKET) {
            return __('Lembrete: sua opinião sobre {{pesquisa}}', 'npsphere');
        }

        return __('Lembrete: como foi o atendimento do chamado #{{chamado_id}}?', 'npsphere');
    }

    /**
     * Assunto e corpo a usar: os gravados na pesquisa ou, em branco, os
     * padrões -- do convite ou do lembrete.
     *
     * @return array{0: string, 1: string}
     */
    private static function textos(Survey $survey, string $tipo, bool $lembrete): array
    {
        $assunto = trim((string) ($survey->fields[$lembrete ? 'reminder_subject' : 'mail_subject'] ?? ''));
        $corpo   = trim((string) ($survey->fields[$lembrete ? 'reminder_body' : 'mail_body'] ?? ''));

        if ($assunto === '') {
            $assunto = $lembrete ? self::getAssuntoPadraoLembrete($tipo) : self::getAssuntoPadrao($tipo);
        }
        if ($corpo === '') {
            $corpo = $lembrete ? self::getCorpoPadraoLembrete($tipo, $survey) : self::getCorpoPadrao($tipo, $survey);
        }

        return [$assunto, $corpo];
    }

    /**
     * Marcadores disponíveis, para exibir na tela de edição.
     *
     * @return array<string, string> marcador => descrição
     */
    /**
     * Marcadores por TIPO de pesquisa.
     *
     * Os de chamado não existem numa pesquisa interna -- oferecer
     * {{chamado_id}} onde ele sempre sairia vazio é convidar ao erro.
     */
    public static function getMarcadores(string $tipo = 'ticket', ?Survey $survey = null): array
    {
        $comuns = [
            '{{pesquisa}}' => __('Nome da pesquisa', 'npsphere'),
            '{{link}}'     => __('Endereço da pesquisa (texto)', 'npsphere'),
            '{{botao}}'    => __('Botão "Responder a pesquisa"', 'npsphere'),
        ];

        // Uma variável por pergunta, criada a partir do texto dela.
        // ({{opcoes}} continua funcionando nas mensagens antigas, mas não
        // é mais oferecido: a variável com o nome da pergunta diz o que
        // vai aparecer.)
        $perguntas = [];
        if ($survey !== null) {
            foreach (self::getPerguntasComMarcador($survey) as $marcador => $q) {
                $perguntas[$marcador] = sprintf(
                    $q['type'] === 'text'
                        ? __('Pergunta "%s", com um link para responder', 'npsphere')
                        : __('Pergunta "%s", com as opções para clicar', 'npsphere'),
                    (string) $q['name']
                );
            }
        }

        if ($tipo !== Survey::TYPE_TICKET) {
            // Em pesquisa interna, quem recebe é o destinatário; na
            // externa, o nome pode não existir (lista de e-mails).
            return ['{{destinatario}}' => __('Nome de quem recebe', 'npsphere')] + $comuns + $perguntas;
        }

        return self::getMarcadoresDeChamado() + $comuns + $perguntas;
    }

    /**
     * Uma variável por pergunta ativa, na ordem da pesquisa.
     *
     * O nome da variável vem do texto da pergunta ("Como avalia o
     * atendimento?" vira {{como_avalia_o_atendimento}}), para quem lê a
     * mensagem saber qual pergunta vai aparecer ali. Nomes repetidos, ou
     * iguais aos das outras variáveis, ganham _2, _3...
     *
     * @return array<string, array<string, mixed>> marcador => linha da pergunta
     */
    public static function getPerguntasComMarcador(Survey $survey): array
    {
        $usados = array_fill_keys([
            'destinatario', 'pesquisa', 'link', 'botao', 'opcoes',
            'chamado_id', 'chamado_titulo', 'requerente', 'tecnico',
        ], true);

        $saida   = [];
        $posicao = 0;

        foreach (Question::getActiveQuestions($survey->getID()) as $q) {
            $posicao++;

            $base = self::paraNomeDeVariavel((string) $q['name']);
            if ($base === '') {
                $base = 'pergunta_' . $posicao;
            }

            $nome = $base;
            for ($n = 2; isset($usados[$nome]); $n++) {
                $nome = $base . '_' . $n;
            }
            $usados[$nome] = true;

            $saida['{{' . $nome . '}}'] = $q;
        }

        return $saida;
    }

    /**
     * Variável usada no modelo padrão: a primeira pergunta com nota, que
     * é a que mais se beneficia do clique direto no e-mail. Sem nenhuma
     * com nota, a primeira pergunta.
     */
    private static function getMarcadorPrincipal(Survey $survey): string
    {
        $perguntas = self::getPerguntasComMarcador($survey);

        foreach ($perguntas as $marcador => $q) {
            if (in_array($q['type'], self::TIPOS_COM_NOTA, true)) {
                return $marcador;
            }
        }

        return (string) (array_key_first($perguntas) ?? '');
    }

    /** Tipos cuja resposta é uma nota (e vão para o NPS/satisfação). */
    private const TIPOS_COM_NOTA = ['nps', 'stars', 'emoticon'];

    /**
     * "Como você avalia o atendimento?" -> "como_voce_avalia_o_atendimento".
     *
     * Sem acentos e sem espaços: a variável é digitada à mão na mensagem,
     * e um acento a mais ou a menos faria ela não ser trocada. Limitada a
     * 40 caracteres, cortando numa palavra inteira.
     */
    private static function paraNomeDeVariavel(string $texto): string
    {
        $texto = trim(strip_tags(html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $texto = mb_strtolower($texto, 'UTF-8');

        if (class_exists('\\Normalizer')) {
            $decomposto = \Normalizer::normalize($texto, \Normalizer::FORM_D);
            if (is_string($decomposto)) {
                $texto = (string) preg_replace('/\p{Mn}+/u', '', $decomposto);
            }
        }
        $texto = strtr($texto, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ]);

        $texto = trim((string) preg_replace('/[^a-z0-9]+/', '_', $texto), '_');

        if (strlen($texto) > 40) {
            $cortado = substr($texto, 0, 40);
            $ultimo  = strrpos($cortado, '_');
            $texto   = rtrim($ultimo !== false && $ultimo >= 15 ? substr($cortado, 0, $ultimo) : $cortado, '_');
        }

        return $texto;
    }

    /**
     * Valor de cada variável de pergunta (o bloco HTML com as opções),
     * mais {{opcoes}} para as mensagens gravadas antes desta versão.
     *
     * @return array<string, string>
     */
    private static function getValoresDasPerguntas(Survey $survey, string $url, ?array $perguntas = null): array
    {
        $valores = [];
        $legado  = '';

        foreach ($perguntas ?? self::getPerguntasComMarcador($survey) as $marcador => $q) {
            $valores[$marcador] = self::montarPergunta($q, $url);

            if ($legado === '' && in_array($q['type'], self::TIPOS_COM_NOTA, true)) {
                $legado = $valores[$marcador];
            }
        }

        $valores['{{opcoes}}'] = $legado;

        return $valores;
    }

    private static function getMarcadoresDeChamado(): array
    {
        return [
            '{{chamado_id}}'      => __('Número do chamado', 'npsphere'),
            '{{chamado_titulo}}'  => __('Título do chamado', 'npsphere'),
            '{{requerente}}'      => __('Nome de quem abriu o chamado', 'npsphere'),
            '{{tecnico}}'         => __('Nome do técnico atribuído', 'npsphere'),
        ];
    }

    /**
     * Envia o convite de um chamado -- ou, com $lembrete, o lembrete
     * dele (ação automática, ver Delivery). O link é o mesmo nos dois.
     */
    public static function enviar(Survey $survey, int $tickets_id, bool $lembrete = false): bool
    {
        $ticket = new \Ticket();
        if (!$ticket->getFromDB($tickets_id)) {
            return self::registrar('chamado não encontrado', $tickets_id);
        }

        $requerente = self::getAtor($tickets_id, \CommonITILActor::REQUESTER);
        if ($requerente === null || empty($requerente['email'])) {
            return self::registrar('requerente sem e-mail cadastrado', $tickets_id);
        }

        // Link de resposta SEM login: a chave assinada do chamado
        // (TicketLink::chave) autoriza o requerente a avaliar direto do
        // e-mail. Dentro do GLPI, o chamado continua usando ticket.php.
        $url = self::getBaseAbsoluta((int) ($ticket->fields['entities_id'] ?? 0))
            . '/front/ticket.answer.php?tickets_id=' . $tickets_id
            . '&surveys_id=' . $survey->getID()
            . '&chave=' . TicketLink::chave($tickets_id, $survey->getID());

        $tecnico = self::getAtor($tickets_id, \CommonITILActor::ASSIGN);

        $valores = [
            '{{chamado_id}}'     => (string) $tickets_id,
            '{{chamado_titulo}}' => (string) $ticket->fields['name'],
            '{{requerente}}'     => $requerente['nome'],
            '{{tecnico}}'        => $tecnico['nome'] ?? __('equipe de atendimento', 'npsphere'),
            '{{pesquisa}}'       => (string) $survey->fields['name'],
            '{{link}}'           => $url,
            '{{botao}}'          => self::montarBotao($url),
        ] + self::getValoresDasPerguntas($survey, $url);

        [$assunto, $corpo] = self::textos($survey, Survey::TYPE_TICKET, $lembrete);

        $ok = self::despachar(
            $requerente['email'],
            (string) $requerente['nome'],
            strtr($assunto, $valores),
            self::envelopar(strtr($corpo, $valores), (string) $survey->fields['name']),
            $tickets_id,
            $survey,
            // Evento próprio para o lembrete: na fila do GLPI dá para
            // separar o convite do lembrete.
            $lembrete ? NotificationTargetSurvey::EVENTO_LEMBRETE : NotificationTargetSurvey::EVENTO_CONVITE
        );

        // Anota o convite para o lembrete saber a quem cobrar.
        if ($ok && !$lembrete) {
            Delivery::registrarConvite($survey->getID(), Delivery::chaveDoChamado($tickets_id), [
                'tickets_id' => $tickets_id,
                'users_id'   => TicketLink::getRequerenteId($tickets_id),
                'email'      => $requerente['email'],
                'name'       => $requerente['nome'],
            ]);
        }

        return $ok;
    }

    /**
     * Envia o convite de uma pesquisa que NÃO é de chamado.
     *
     * O GLPI não notifica esse tipo de pesquisa -- não há evento nativo
     * para "pesquisa interna criada". Aqui o plugin é o único que pode
     * avisar, e o envio é disparado manualmente, pela tela da pesquisa.
     *
     * Destinatários:
     *  - INTERNA: o público-alvo configurado, pulando quem já respondeu
     *    (quando a pesquisa não permite múltiplas respostas);
     *  - EXTERNA: a lista de e-mails da própria pesquisa.
     *
     * @return array{enviados: int, ignorados: int, erros: int}
     */
    public static function enviarConvites(Survey $survey): array
    {
        $resultado = ['enviados' => 0, 'ignorados' => 0, 'erros' => 0];

        $tipo = (string) ($survey->fields['type'] ?? '');
        if ($tipo === Survey::TYPE_TICKET) {
            return $resultado;
        }

        $destinatarios = $tipo === Survey::TYPE_EXTERNAL
            ? self::getListaExterna($survey)
            : Target::getUsuariosAlvo($survey);

        $url = self::getUrlDaPesquisa($survey);
        $permite_repetir = !empty($survey->fields['allow_multiple_answers']);

        // Iguais para todos os destinatários: monta uma vez só.
        $perguntas = self::getPerguntasComMarcador($survey);
        $fixos = [
            '{{pesquisa}}' => (string) $survey->fields['name'],
            '{{link}}'     => $url,
            '{{botao}}'    => self::montarBotao($url),
        ] + self::getValoresDasPerguntas($survey, $url, $perguntas);

        foreach ($destinatarios as $pessoa) {
            // Quem já respondeu não recebe de novo -- a menos que a
            // pesquisa aceite múltiplas respostas.
            if (
                !$permite_repetir
                && $tipo === Survey::TYPE_INTERNAL
                && Target::hasAnswered($survey, (int) $pessoa['id'])
            ) {
                $resultado['ignorados']++;
                continue;
            }

            $valores = self::valoresDaPessoa($survey, $pessoa, $url, $perguntas, $fixos);
            [$assunto, $corpo] = self::textos($survey, $tipo, false);

            $ok = self::despachar(
                $pessoa['email'],
                self::nomeParaCabecalho($pessoa),
                strtr($assunto, $valores),
                self::envelopar(strtr($corpo, $valores), (string) $survey->fields['name']),
                0,
                $survey
            );

            if ($ok) {
                $resultado['enviados']++;
                self::anotarConvite($survey, $pessoa);
            } else {
                $resultado['erros']++;
            }
        }

        return $resultado;
    }

    /**
     * Valores das variáveis para UMA pessoa (convite e lembrete).
     *
     *  - INTERNA: link pessoal com chave assinada (pessoa + pesquisa).
     *    Responde direto do e-mail, sem entrar no GLPI, e a resposta é
     *    gravada em nome dela (Target::resolverPorChave);
     *  - EXTERNA identificada: o link leva o código do convite da pessoa.
     *    É ele que liga a resposta a quem recebeu e faz um novo clique
     *    alterar a resposta em vez de duplicá-la;
     *  - EXTERNA anônima: o link comum. Guardar qual convite gerou qual
     *    resposta permitiria descobrir quem respondeu o quê -- contra a
     *    promessa feita a quem responde.
     *
     * @param array{id?: int, nome: string, email: string} $pessoa
     * @param array<string, array<string, mixed>> $perguntas
     * @param array<string, string> $fixos valores iguais para todos
     * @return array<string, string>
     */
    private static function valoresDaPessoa(Survey $survey, array $pessoa, string $url, array $perguntas, array $fixos): array
    {
        $tipo = (string) ($survey->fields['type'] ?? '');
        $url_pessoa = null;

        if ($tipo === Survey::TYPE_INTERNAL && !empty($pessoa['id'])) {
            $url_pessoa = self::getBaseAbsoluta((int) ($survey->fields['entities_id'] ?? 0))
                . '/front/internal.answer.php?surveys_id=' . $survey->getID()
                . '&usuario=' . (int) $pessoa['id']
                . '&chave=' . Target::chave((int) $pessoa['id'], $survey->getID());
        } elseif ($tipo === Survey::TYPE_EXTERNAL && empty($survey->fields['is_anonymous'])) {
            $url_pessoa = $url . '&convite=' . Invite::obterToken(
                $survey->getID(),
                (string) $pessoa['email'],
                self::nomeParaCabecalho($pessoa)
            );
        }

        if ($url_pessoa === null) {
            return ['{{destinatario}}' => $pessoa['nome']] + $fixos;
        }

        return [
            '{{destinatario}}' => $pessoa['nome'],
            '{{pesquisa}}'     => (string) $survey->fields['name'],
            '{{link}}'         => $url_pessoa,
            '{{botao}}'        => self::montarBotao($url_pessoa),
        ] + self::getValoresDasPerguntas($survey, $url_pessoa, $perguntas);
    }

    /**
     * Anota o convite enviado, para o lembrete saber a quem cobrar.
     * A externa anônima não é anotada (ver Delivery).
     *
     * @param array{id?: int, nome: string, email: string} $pessoa
     */
    private static function anotarConvite(Survey $survey, array $pessoa): void
    {
        if (!Delivery::aceitaLembrete($survey)) {
            return;
        }

        $tipo = (string) ($survey->fields['type'] ?? '');

        if ($tipo === Survey::TYPE_INTERNAL && !empty($pessoa['id'])) {
            Delivery::registrarConvite($survey->getID(), Delivery::chaveDoUsuario((int) $pessoa['id']), [
                'users_id' => (int) $pessoa['id'],
                'email'    => (string) $pessoa['email'],
                'name'     => (string) $pessoa['nome'],
            ]);
        } elseif ($tipo === Survey::TYPE_EXTERNAL) {
            Delivery::registrarConvite($survey->getID(), Delivery::chaveDoEmail((string) $pessoa['email']), [
                'email' => (string) $pessoa['email'],
                'name'  => self::nomeParaCabecalho($pessoa),
            ]);
        }
    }

    /**
     * Lembrete de UMA pessoa da pesquisa interna ou externa (chamado:
     * enviar() com $lembrete). Chamado pela ação automática (Delivery).
     *
     * Usa o mesmo link pessoal do convite: o clique no lembrete grava a
     * resposta do mesmo jeito.
     *
     * @param array<string, mixed> $linha linha de Delivery
     */
    public static function enviarLembrete(Survey $survey, array $linha): bool
    {
        $tipo = (string) ($survey->fields['type'] ?? '');

        $pessoa = [
            'id'    => (int) ($linha['users_id'] ?? 0),
            'nome'  => trim((string) ($linha['name'] ?? '')),
            'email' => trim((string) ($linha['email'] ?? '')),
        ];

        // Interna: nome e e-mail ATUAIS do cadastro do GLPI (o e-mail
        // pode ter mudado desde o convite).
        if ($tipo === Survey::TYPE_INTERNAL) {
            $user = new \User();
            if (!$user->getFromDB($pessoa['id'])) {
                return self::registrar('usuário do lembrete não encontrado', 0);
            }
            $email = method_exists($user, 'getDefaultEmail') ? (string) $user->getDefaultEmail() : '';
            if ($email !== '') {
                $pessoa['email'] = $email;
            }
            $completo = trim(($user->fields['firstname'] ?? '') . ' ' . ($user->fields['realname'] ?? ''));
            $pessoa['nome'] = $completo !== '' ? $completo : (string) $user->fields['name'];
        }

        if ($pessoa['email'] === '' || !filter_var($pessoa['email'], FILTER_VALIDATE_EMAIL)) {
            return self::registrar('lembrete sem e-mail válido', 0);
        }
        if ($pessoa['nome'] === '') {
            // Mesma regra da lista externa: sem nome, a parte antes do @.
            $pessoa['nome'] = strstr($pessoa['email'], '@', true) ?: $pessoa['email'];
        }

        $url = self::getUrlDaPesquisa($survey);
        $perguntas = self::getPerguntasComMarcador($survey);
        $fixos = [
            '{{pesquisa}}' => (string) $survey->fields['name'],
            '{{link}}'     => $url,
            '{{botao}}'    => self::montarBotao($url),
        ] + self::getValoresDasPerguntas($survey, $url, $perguntas);

        $valores = self::valoresDaPessoa($survey, $pessoa, $url, $perguntas, $fixos);
        [$assunto, $corpo] = self::textos($survey, $tipo, true);

        return self::despachar(
            $pessoa['email'],
            self::nomeParaCabecalho($pessoa),
            strtr($assunto, $valores),
            self::envelopar(strtr($corpo, $valores), (string) $survey->fields['name']),
            0,
            $survey,
            NotificationTargetSurvey::EVENTO_LEMBRETE
        );
    }

    /**
     * Endereço para responder, conforme o tipo.
     */
    private static function getUrlDaPesquisa(Survey $survey): string
    {
        $base = self::getBaseAbsoluta((int) ($survey->fields['entities_id'] ?? 0));

        if (($survey->fields['type'] ?? '') === Survey::TYPE_EXTERNAL) {
            // Link público, com o token da pesquisa: quem recebe não
            // precisa ter conta no GLPI.
            return $base . '/front/public.php?token=' . ($survey->fields['public_token'] ?? '');
        }

        return $base . '/front/answer.form.php?surveys_id=' . $survey->getID();
    }

    /**
     * Enfileira um e-mail já montado (HTML completo do corpo, sem o
     * cartão do convite) -- usado pelo relatório diário (DailyReport).
     * Mesmas regras de remetente, cabeçalhos e fila do convite.
     */
    public static function enfileirar(
        string $para,
        string $para_nome,
        string $assunto,
        string $html,
        Survey $survey,
        string $evento
    ): bool {
        return self::despachar($para, $para_nome, $assunto, $html, 0, $survey, $evento);
    }

    /**
     * Endereço completo do GLPI (sem o caminho do plugin), para links a
     * telas do próprio GLPI no e-mail -- o chamado, por exemplo.
     */
    public static function getUrlGlpi(int $entities_id): string
    {
        global $CFG_GLPI;

        $url_base = '';
        if (class_exists('\\Entity')) {
            $url_base = trim((string) \Entity::getUsedConfig('url_base', $entities_id, '', ''));
        }
        if ($url_base === '') {
            $url_base = (string) ($CFG_GLPI['url_base'] ?? '');
        }

        return rtrim($url_base, '/');
    }

    /**
     * Endereço COMPLETO do plugin (https://servidor/.../marketplace/npsphere),
     * para os links que saem no e-mail.
     *
     * Plugin::getWebDir('npsphere', true) devolve só o caminho
     * ("/marketplace/npsphere"): serve dentro do GLPI, onde o navegador
     * completa com o servidor atual, mas no e-mail o link ficava sem
     * servidor e o Gmail o transformava em "http:///marketplace/...".
     *
     * A base é a "URL da aplicação" do GLPI -- a da entidade, se
     * configurada, senão a geral (Configurar › Geral) --, a mesma regra
     * de NotificationTarget::getUrlBase() nas notificações nativas.
     */
    public static function getBaseAbsoluta(int $entities_id): string
    {
        return self::getUrlGlpi($entities_id) . '/' . ltrim((string) \Plugin::getWebDir('npsphere', false), '/');
    }

    /**
     * Lista de e-mails da pesquisa externa.
     *
     * Duas formas convivem no mesmo campo, porque as listas chegam das
     * duas maneiras na prática:
     *
     *  - `email;Nome` por linha -- o formato do CSV importado, que dá
     *    nome a {{destinatario}};
     *  - vários endereços na mesma linha, separados por vírgula, espaço
     *    ou ponto e vírgula -- o que sai de um "copiar" do Outlook ou de
     *    uma planilha.
     *
     * A desambiguação é o segundo campo: se ele também é um e-mail
     * válido, a linha é uma LISTA de endereços, não um registro com
     * nome. Sem isso, importar passaria a quebrar as listas que já
     * estavam gravadas antes do CSV existir.
     *
     * Endereços inválidos são descartados em silêncio, e não
     * enfileirados para falhar depois.
     *
     * @return array<int, array{id: int, nome: string, email: string}>
     */
    public static function getListaExterna(Survey $survey): array
    {
        return self::parseLista((string) ($survey->fields['mail_recipients'] ?? ''));
    }

    /**
     * @return array<int, array{id: int, nome: string, email: string}>
     */
    public static function parseLista(string $bruto): array
    {
        if (trim($bruto) === '') {
            return [];
        }

        $saida = [];

        foreach (preg_split('/\r\n|\r|\n/', $bruto) ?: [] as $linha) {
            $linha = trim($linha);
            if ($linha === '') {
                continue;
            }

            $campos = array_map('trim', explode(';', $linha));

            // Registro `email;Nome`: só quando o segundo campo NÃO é
            // outro endereço (ver o comentário do método acima).
            $eh_registro = count($campos) > 1
                && filter_var($campos[0], FILTER_VALIDATE_EMAIL)
                && !filter_var($campos[1], FILTER_VALIDATE_EMAIL);

            if ($eh_registro) {
                self::acumular($saida, $campos[0], $campos[1]);
                continue;
            }

            foreach (preg_split('/[\s,;]+/', $linha, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $email) {
                self::acumular($saida, $email, '');
            }
        }

        return array_values($saida);
    }

    /**
     * Acrescenta um endereço à lista, sem repetir.
     *
     * A chave em minúsculas evita enviar duas vezes ao mesmo endereço
     * quando a lista vem com repetições -- e mantém o nome da PRIMEIRA
     * ocorrência, que é a que a pessoa digitou.
     *
     * @param array<string, array{id: int, nome: string, email: string}> $saida
     */
    private static function acumular(array &$saida, string $email, string $nome): void
    {
        $email = trim($email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $chave = strtolower($email);
        if (isset($saida[$chave])) {
            return;
        }

        $nome = trim($nome);

        $saida[$chave] = [
            'id'    => 0,
            'nome'  => $nome !== '' ? $nome : (strstr($email, '@', true) ?: $email),
            'email' => $email,
        ];
    }

    /**
     * Lista no formato da tela de destinatários: [{email, nome}], com o
     * nome VAZIO quando ele é só o que parseLista deduziu do e-mail (a
     * parte antes do @). Na tela, um nome que a pessoa não digitou
     * pareceria dado real.
     *
     * @param array<int, array{id: int, nome: string, email: string}> $lista
     * @return array<int, array{email: string, nome: string}>
     */
    public static function paraTela(array $lista): array
    {
        $saida = [];
        foreach ($lista as $pessoa) {
            $email = (string) ($pessoa['email'] ?? '');
            if ($email === '') {
                continue;
            }
            $nome = trim((string) ($pessoa['nome'] ?? ''));
            $saida[] = [
                'email' => $email,
                'nome'  => $nome === (strstr($email, '@', true) ?: $email) ? '' : $nome,
            ];
        }

        return $saida;
    }

    /**
     * Volta a lista para o texto gravado no campo `mail_recipients`.
     *
     * O caminho de ida (parseLista) e o de volta ficam lado a lado de
     * propósito: a importação do CSV grava por aqui, e qualquer mudança
     * no formato tem de ser feita nos dois ao mesmo tempo.
     *
     * Quando o nome é só a parte antes do @ -- o que parseLista inventa
     * para quem veio sem nome -- ele é omitido. Gravá-lo encheria o
     * campo de "fulano" repetido ao lado de "fulano@empresa.com".
     *
     * @param array<int, array{id: int, nome: string, email: string}> $lista
     */
    public static function formatarLista(array $lista): string
    {
        $linhas = [];

        foreach ($lista as $pessoa) {
            $email = (string) ($pessoa['email'] ?? '');
            if ($email === '') {
                continue;
            }

            $nome    = trim((string) ($pessoa['nome'] ?? ''));
            $deduzido = strstr($email, '@', true) ?: $email;

            $linhas[] = ($nome === '' || $nome === $deduzido)
                ? $email
                : $email . ';' . $nome;
        }

        return implode("\n", $linhas);
    }

    /**
     * Nome que vai no cabeçalho "Para:" (Nome <email>).
     *
     * parseLista() completa quem veio sem nome com a parte antes do @,
     * para o {{destinatario}} do texto não sair vazio. No cabeçalho isso
     * ficaria "fulano <fulano@empresa.com>" -- sem nome de verdade, vai
     * só o endereço.
     *
     * @param array{nome?: string, email?: string} $pessoa
     */
    private static function nomeParaCabecalho(array $pessoa): string
    {
        $nome  = trim((string) ($pessoa['nome'] ?? ''));
        $email = (string) ($pessoa['email'] ?? '');

        return $nome === (strstr($email, '@', true) ?: $email) ? '' : $nome;
    }

    /**
     * Quem aparece como {{destinatario}} na prévia e na sub-aba Variáveis.
     *
     * É a PRIMEIRA pessoa da lista de destinatários (externa) ou do
     * público-alvo (interna) -- um nome real, que vai mesmo receber o
     * e-mail. Sem ninguém cadastrado ainda, um rótulo deixa claro que ali
     * entra o nome, em vez de um nome inventado que parece dado real.
     *
     * $lista_na_tela: o texto do campo Destinatários como está na tela,
     * para a prévia acompanhar a lista antes de salvar.
     */
    public static function getDestinatarioExemplo(Survey $survey, ?string $lista_na_tela = null): string
    {
        $tipo = (string) ($survey->fields['type'] ?? Survey::TYPE_INTERNAL);

        if ($tipo === Survey::TYPE_EXTERNAL) {
            $lista = $lista_na_tela !== null
                ? self::parseLista($lista_na_tela)
                : self::getListaExterna($survey);
        } else {
            // Interna: a primeira pessoa que VAI receber (pula quem já
            // respondeu), a mesma ordem da lista na aba Template.
            $lista = array_values(array_filter(
                Target::getResumoDoPublico($survey)['pessoas'],
                static fn(array $p): bool => $p['situacao'] === 'recebe'
            ));
        }

        $primeiro = $lista[0]['nome'] ?? '';

        return $primeiro !== '' ? (string) $primeiro : __('[nome do destinatário]', 'npsphere');
    }

    /**
     * Valores de EXEMPLO dos marcadores, para a prévia e para a
     * sub-aba de variáveis.
     *
     * Usa os dados reais da pesquisa -- o link e as opções são os que
     * vão sair no e-mail. O destinatário é o primeiro da lista (ver
     * getDestinatarioExemplo).
     *
     * @return array<string, string>
     */
    public static function getValoresExemplo(Survey $survey, ?string $lista_na_tela = null): array
    {
        $tipo = (string) ($survey->fields['type'] ?? Survey::TYPE_INTERNAL);
        $url  = self::getUrlDaPesquisa($survey);

        $valores = [
            '{{pesquisa}}' => (string) $survey->fields['name'],
            '{{link}}'     => $url,
            '{{botao}}'    => self::montarBotao($url),
        ] + self::getValoresDasPerguntas($survey, $url);

        if ($tipo !== Survey::TYPE_TICKET) {
            $valores['{{destinatario}}'] = self::getDestinatarioExemplo($survey, $lista_na_tela);

            return $valores;
        }

        // Pesquisa de chamado: o exemplo é o último chamado ENCERRADO de
        // verdade da entidade da pesquisa -- não um chamado inventado.
        // Os links da prévia abrem a aba Visualizar: clicar neles não pode
        // registrar uma avaliação de verdade nesse chamado.
        // Endereço COMPLETO (https://servidor/...), como os links do
        // e-mail de verdade -- Survey::getFormURLWithID() devolvia só o
        // caminho (/marketplace/...), e {{link}} saía sem o servidor.
        $previa = self::getBaseAbsoluta((int) ($survey->fields['entities_id'] ?? 0))
            . '/front/survey.form.php?id=' . $survey->getID()
            . '&forcetab=' . rawurlencode(SurveyPreview::class . '$1');

        $valores = [
            '{{pesquisa}}' => (string) $survey->fields['name'],
            '{{link}}'     => $previa,
            '{{botao}}'    => self::montarBotao($previa),
        ] + self::getValoresDasPerguntas($survey, $previa);

        $chamado = self::getChamadoExemplo($survey);

        return $valores + [
            '{{chamado_id}}'     => $chamado !== null ? (string) $chamado['id'] : __('[número do chamado]', 'npsphere'),
            '{{chamado_titulo}}' => $chamado['titulo'] ?? __('[título do chamado]', 'npsphere'),
            '{{requerente}}'     => $chamado['requerente'] ?? __('[nome do requerente]', 'npsphere'),
            '{{tecnico}}'        => $chamado['tecnico'] ?? __('equipe de atendimento', 'npsphere'),
        ];
    }

    /**
     * Último chamado encerrado no alcance da pesquisa de chamado (a
     * entidade dela e, se recursiva, as filhas), para a prévia.
     *
     * @return array{id: int, titulo: string, requerente: ?string, tecnico: ?string, encerrado: string}|null
     */
    public static function getChamadoExemplo(Survey $survey): ?array
    {
        global $DB;

        $entidade  = (int) ($survey->fields['entities_id'] ?? 0);
        $entidades = [$entidade];
        if (!empty($survey->fields['is_recursive']) && function_exists('getSonsOf')) {
            $entidades = array_map('intval', getSonsOf('glpi_entities', $entidade));
        }

        $row = $DB->request([
            'SELECT' => ['id', 'name', 'closedate'],
            'FROM'   => 'glpi_tickets',
            'WHERE'  => [
                'entities_id' => $entidades,
                'status'      => \CommonITILObject::CLOSED,
                'is_deleted'  => 0,
            ],
            'ORDER'  => 'closedate DESC',
            'LIMIT'  => 1,
        ])->current();

        if (!$row) {
            return null;
        }

        $id = (int) $row['id'];

        return [
            'id'         => $id,
            'titulo'     => (string) $row['name'],
            'requerente' => self::getAtor($id, \CommonITILActor::REQUESTER)['nome'] ?? null,
            'tecnico'    => self::getAtor($id, \CommonITILActor::ASSIGN)['nome'] ?? null,
            'encerrado'  => (string) ($row['closedate'] ?? ''),
        ];
    }

    /**
     * Prévia do e-mail, com os marcadores já substituídos.
     *
     * Passa pelo MESMO strtr + envelopar do envio real: se a prévia
     * tivesse montagem própria, mostraria uma coisa e enviaria outra.
     */
    public static function montarPrevia(Survey $survey, string $corpo, ?string $lista_na_tela = null, bool $lembrete = false): string
    {
        $corpo = trim($corpo);
        if ($corpo === '') {
            $tipo  = (string) ($survey->fields['type'] ?? Survey::TYPE_INTERNAL);
            $corpo = $lembrete ? self::getCorpoPadraoLembrete($tipo, $survey) : self::getCorpoPadrao($tipo, $survey);
        }

        return self::envelopar(
            strtr($corpo, self::getValoresExemplo($survey, $lista_na_tela)),
            (string) $survey->fields['name']
        );
    }

    /**
     * Bloco de UMA pergunta para o e-mail: o texto dela e as opções como
     * links.
     *
     * Cada link leva o id da pergunta e o valor (&pergunta=ID&valor=X):
     * a página da pesquisa abre com aquela opção JÁ MARCADA, sem gravar
     * nada -- quem grava é a pessoa, ao confirmar. Pergunta de texto não
     * tem opção para clicar, então vai só um link para responder.
     *
     * Tabela e estilos em linha: é o que funciona em cliente de e-mail.
     * Nada de flexbox, classes ou folhas externas.
     *
     * @param array<string, mixed> $pergunta linha da tabela de perguntas
     */
    private static function montarPergunta(array $pergunta, string $url): string
    {
        $tipo = (string) $pergunta['type'];

        $cfg = json_decode($pergunta['configuration'] ?? '{}', true);
        $cfg = is_array($cfg) ? $cfg : [];
        $classe = QuestionTypeManager::get($tipo);
        if ($classe !== null) {
            $cfg = array_merge($classe::getDefaultConfiguration(), $cfg);
        }

        $base = $url . '&pergunta=' . (int) $pergunta['id'];

        // [valor, rótulo, cor]
        $opcoes   = [];
        $vertical = false;

        switch ($tipo) {
            case 'nps':
                $max = (int) ($cfg['scale'] ?? 10);
                for ($i = 0; $i <= $max; $i++) {
                    $opcoes[] = [(string) $i, (string) $i, self::cor($i, $max)];
                }
                break;

            case 'stars':
                $max = (int) ($cfg['max'] ?? 5);
                for ($i = 1; $i <= $max; $i++) {
                    // Estrelas menores e juntas: 5 delas cabem na fração da linha no celular.
                    $opcoes[] = [(string) $i, '<span style="font-size:12px;letter-spacing:-1px;">' . str_repeat('★', $i) . '</span>', '#f59f00'];
                }
                break;

            case 'emoticon':
                $niveis = array_values((array) ($cfg['levels'] ?? []));
                foreach ($niveis as $indice => $nivel) {
                    $valor = (int) ($nivel['value'] ?? $indice + 1);
                    $opcoes[] = [
                        (string) $valor,
                        htmlspecialchars((string) ($nivel['emoji'] ?? $nivel['label'] ?? $valor)),
                        self::cor($indice + 1, max(1, count($niveis))),
                    ];
                }
                break;

            case 'yesno':
                $opcoes[] = ['yes', htmlspecialchars(__('Yes')), '#2fb344'];
                $opcoes[] = ['no', htmlspecialchars(__('No')), '#d63939'];
                if (!empty($cfg['allow_dont_know'])) {
                    $opcoes[] = ['dont_know', htmlspecialchars(__('Não sei', 'npsphere')), '#868e96'];
                }
                break;

            case 'single_choice':
            case 'multiple_choice':
                // Texto das opções pode ser longo: uma por linha.
                $vertical = true;
                foreach ((array) ($cfg['options'] ?? []) as $opcao) {
                    $opcoes[] = [(string) $opcao, htmlspecialchars((string) $opcao), '#206bc4'];
                }
                break;
        }

        $html = '<p style="margin:18px 0 6px;font-weight:bold;">'
            . htmlspecialchars((string) $pergunta['name']) . '</p>';

        if (empty($opcoes)) {
            // Texto livre (ou tipo sem opções): link para responder.
            return $html . '<p style="margin:0 0 6px;">'
                . '<a href="' . htmlspecialchars($base) . '" style="color:#206bc4;">'
                . __('Responder esta pergunta', 'npsphere') . '</a></p>';
        }

        if ($vertical) {
            $html .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0">';
            foreach ($opcoes as [$valor, $rotulo, $cor]) {
                $html .= '<tr><td style="padding:0 0 6px 0;">'
                    . self::botaoBlindado($base . '&valor=' . rawurlencode($valor), $rotulo, $cor, 0, '9px 10px', 4, true)
                    . '</td></tr>';
            }
            return $html . '</table>';
        }

        // v0.64.3: linha FLUIDA -- cada opção é uma fração igual da largura
        // (table-layout fixo + largura em %). Antes cada botão tinha 34px
        // fixos e a linha de 0 a 10 passava da tela do celular (~520px),
        // obrigando a rolar de lado. Poucas opções (Sim/Não, estrelas,
        // emoji) têm largura máxima, para não virarem faixas enormes.
        $total   = count($opcoes);
        $maximo  = $total >= 6 ? '100%' : ($total * ($total <= 3 ? 110 : 84)) . 'px';
        $largura = floor(10000 / $total) / 100;

        $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"'
            . ' style="width:100%;max-width:' . $maximo . ';table-layout:fixed;"><tr>';
        foreach ($opcoes as $i => [$valor, $rotulo, $cor]) {
            $html .= '<td width="' . $largura . '%" class="nps-opcao" style="width:' . $largura . '%;padding:0 '
                . ($i === $total - 1 ? '0' : '3px') . ' 6px 0;">'
                . self::botaoFluido($base . '&valor=' . rawurlencode($valor), $rotulo, $cor)
                . '</td>';
        }

        return $html . '</tr></table>';
    }

    private static function montarBotao(string $url): string
    {
        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:18px 0;"><tr><td>'
            . self::botaoBlindado($url, htmlspecialchars(__('Responder a pesquisa', 'npsphere')), '#206bc4', 0, '11px 20px', 6, false)
            . '</td></tr></table>';
    }

    /**
     * Botão que fica igual no Outlook para Windows e nos demais.
     *
     * O Outlook para Windows desenha o e-mail com o motor do Word, que
     * IGNORA padding, largura mínima e display:inline-block em links: os
     * botões viravam só o número, espremidos. A cor, o tamanho e o
     * espaçamento ficam na CÉLULA da tabela em volta do link (bgcolor,
     * width, mso-padding-alt), que o Word respeita. Os outros clientes
     * (Outlook web, Gmail, celular) usam o padding do próprio link, como
     * antes -- para eles a aparência não muda.
     *
     * @param string $rotulo já escapado para HTML
     */
    private static function botaoBlindado(
        string $url,
        string $rotulo,
        string $cor,
        int $largura,
        string $padding,
        int $raio,
        bool $negrito
    ): string {
        $fonte = 'font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:18px;'
            . ($negrito ? 'font-weight:bold;' : '');

        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>'
            . '<td align="center" bgcolor="' . $cor . '"'
            . ($largura > 0 ? ' width="' . $largura . '"' : '')
            . ' style="background:' . $cor . ';border-radius:' . $raio . 'px;mso-padding-alt:' . $padding . ';'
            . ($largura > 0 ? 'min-width:' . $largura . 'px;' : '') . '">'
            . '<a href="' . htmlspecialchars($url) . '" target="_blank"'
            . ' style="display:inline-block;padding:' . $padding . ';' . $fonte
            . 'color:#ffffff;text-decoration:none;border-radius:' . $raio . 'px;'
            . ($largura > 0 ? 'min-width:' . ($largura - 20) . 'px;text-align:center;' : '') . '">'
            . $rotulo . '</a>'
            . '</td></tr></table>';
    }

    /**
     * Botão de uma opção na linha fluida: ocupa a célula inteira. Mesma
     * blindagem para o Outlook do botaoBlindado() (cor e espaçamento na
     * célula), sem largura fixa.
     *
     * @param string $rotulo já escapado para HTML
     */
    private static function botaoFluido(string $url, string $rotulo, string $cor): string
    {
        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;"><tr>'
            . '<td align="center" bgcolor="' . $cor . '" style="background:' . $cor . ';border-radius:4px;mso-padding-alt:9px 0;">'
            . '<a href="' . htmlspecialchars($url) . '" target="_blank"'
            . ' style="display:block;padding:9px 0;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:18px;'
            . 'font-weight:bold;color:#ffffff;text-decoration:none;text-align:center;border-radius:4px;'
            . 'white-space:nowrap;overflow:hidden;">'
            . $rotulo . '</a>'
            . '</td></tr></table>';
    }

    /**
     * Cor da opção conforme a posição na escala.
     *
     * Fixas de propósito: o e-mail é lido fora do GLPI, onde as
     * variáveis do tema não existem.
     */
    private static function cor(int $valor, int $maximo): string
    {
        $proporcao = $maximo > 0 ? $valor / $maximo : 0;

        if ($proporcao >= 0.8) {
            return '#2fb344';
        }
        if ($proporcao >= 0.6) {
            return '#f59f00';
        }

        return '#d63939';
    }

    /**
     * Envolve o corpo num cartão simples, com o nome da pesquisa.
     */
    private static function envelopar(string $corpo, string $titulo): string
    {
        // Espaçamento SEMPRE nas células (<td>), nunca na <table>: Outlook
        // e a tela da fila do GLPI ignoram padding de tabela, e o cartão
        // ficava encostado na borda. A centralização vai pelos dois
        // caminhos -- align="center" (clientes antigos) e margin:0 auto
        // (quem descarta o atributo, como a fila do GLPI).
        $fonte = 'font-family:Arial,Helvetica,sans-serif;';

        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"'
            . ' style="width:100%;background:#f5f7fb;">'
            . '<tr><td align="center" class="nps-fora" style="padding:32px 16px;text-align:center;">'
            . '<table role="presentation" width="560" cellpadding="0" cellspacing="0" border="0"'
            . ' style="width:100%;max-width:560px;margin:0 auto;background:#ffffff;'
            . 'border:1px solid #e6e7e9;border-radius:8px;text-align:left;">'
            . '<tr><td class="nps-cartao" style="padding:28px 32px 6px 32px;' . $fonte
            . 'font-size:18px;font-weight:bold;color:#206bc4;">'
            . htmlspecialchars($titulo) . '</td></tr>'
            . '<tr><td class="nps-cartao" style="padding:6px 32px 28px 32px;' . $fonte
            . 'font-size:14px;line-height:1.5;color:#333333;">' . $corpo . '</td></tr>'
            . '</table></td></tr></table>';
    }

    /**
     * @return array{nome: string, email: string}|null
     */
    private static function getAtor(int $tickets_id, int $tipo): ?array
    {
        global $DB;

        foreach ($DB->request([
            'SELECT'     => ['glpi_users.id', 'glpi_users.name', 'glpi_users.realname', 'glpi_users.firstname'],
            'FROM'       => 'glpi_tickets_users',
            'INNER JOIN' => [
                'glpi_users' => ['ON' => ['glpi_tickets_users' => 'users_id', 'glpi_users' => 'id']],
            ],
            'WHERE' => ['glpi_tickets_users.tickets_id' => $tickets_id, 'glpi_tickets_users.type' => $tipo],
            'ORDER' => 'glpi_tickets_users.id ASC',
            'LIMIT' => 1,
        ]) as $row) {
            $user = new \User();
            $email = $user->getFromDB((int) $row['id']) && method_exists($user, 'getDefaultEmail')
                ? (string) $user->getDefaultEmail()
                : '';

            $completo = trim(($row['firstname'] ?? '') . ' ' . ($row['realname'] ?? ''));

            return [
                'nome'  => $completo !== '' ? $completo : (string) $row['name'],
                'email' => $email,
            ];
        }

        return null;
    }

    /**
     * Entrega pela FILA de e-mails do GLPI.
     *
     * Assim a mensagem herda remetente, tentativas e registro de erro
     * que o GLPI já mantém, e aparece em Configurar > Notificações como
     * qualquer outra -- um envio direto seria menos código agora e um
     * problema depois, quando alguém perguntar por que um e-mail não
     * chegou.
     */
    private static function despachar(
        string $para,
        string $para_nome,
        string $assunto,
        string $html,
        int $tickets_id,
        ?Survey $survey = null,
        string $evento = NotificationTargetSurvey::EVENTO_CONVITE
    ): bool {

        if (!class_exists('\\QueuedNotification')) {
            return self::registrar('fila de notificações indisponível', $tickets_id);
        }

        // Sem chamado, a linha da fila aponta para a PESQUISA. Apontar
        // para o chamado 0 fazia a tela "Configurar > Notificações"
        // mostrar o convite como se pertencesse a um chamado que não
        // existe, e não havia como saber de qual pesquisa ele saiu.
        $itemtype = $tickets_id > 0 ? 'Ticket' : Survey::class;
        $items_id = $tickets_id > 0 ? $tickets_id : ($survey?->getID() ?? 0);

        $entities_id = $tickets_id > 0
            ? 0
            : (int) ($survey->fields['entities_id'] ?? 0);

        // Remetente e "responder para" pelas MESMAS regras das
        // notificações do GLPI: configuração da entidade, depois a global
        // (from_email, e na falta dele admin_email). Ler admin_email direto
        // ignorava a entidade e podia deixar o remetente vazio.
        $remetente = \Config::getEmailSender($entities_id);

        if (empty($remetente['email'])) {
            return self::registrar('nenhum e-mail de remetente configurado no GLPI', $tickets_id);
        }

        // "Responder para": o configurado no GLPI; sem ele, o "não
        // responder" (é o que o GLPI usa em notificação que não espera
        // resposta); sem nenhum dos dois, o próprio remetente. Assim o
        // campo nunca sai vazio e a resposta não se perde.
        $responder = \Config::getReplyToEmailSender($entities_id);
        if (empty($responder['email'])) {
            $responder = \Config::getNoReplyEmailSender($entities_id);
        }
        if (empty($responder['email'])) {
            $responder = $remetente;
        }


        // Modelo do evento ("Convite de pesquisa" ou "Lembrete de
        // pesquisa", criados na instalação), para a fila mostrar o "Modelo
        // de notificação" como nas notificações nativas.
        static $modelos = [];
        $modelos[$evento] ??= NotificationTargetSurvey::getModeloId($evento);
        $modelo_id = $modelos[$evento];

        // Message-ID no formato do GLPI (UUID da instância + item + evento
        // + aleatório), o mesmo que as notificações nativas usam.
        $message_id = \NotificationTarget::getMessageIdForEvent($itemtype, $items_id, $evento);

        // Todos os campos de nome vão como TEXTO, mesmo vazios. O GLPI 11
        // monta os endereços com Symfony\Component\Mime\Address, que exige
        // string no nome: um NULL em recipientname derrubava o envio com
        // "Argument #2 ($name) must be of type string, null given", e o
        // e-mail ficava sem destinatário ("must have a To header").
        $fila = new \QueuedNotification();

        $ok = $fila->add([
            'itemtype'         => $itemtype,
            'items_id'         => $items_id,
            'itemtype_trigger' => $itemtype,
            'items_id_trigger' => $items_id,
            'entities_id'      => $entities_id,
            'notificationtemplates_id' => $modelo_id,
            'event'            => $evento,
            'sendertype'       => 0,
            'sender'           => (string) $remetente['email'],
            'sendername'       => (string) ($remetente['name'] ?? ''),
            'replyto'          => (string) ($responder['email'] ?? ''),
            'replytoname'      => (string) ($responder['name'] ?? ''),
            'recipient'        => $para,
            'recipientname'    => $para_nome,
            'name'             => $assunto,
            'messageid'        => $message_id,
            // Os mesmos cabeçalhos das notificações do GLPI: avisam ao
            // servidor de quem recebe que é mensagem automática, para não
            // voltar resposta de férias ou de "fora do escritório".
            'headers'          => [
                'Auto-Submitted'           => 'auto-generated',
                'X-Auto-Response-Suppress' => 'OOF, DR, NDR, RN, NRN',
            ],
            // Documento HTML completo. A tela da fila do GLPI mostra só o
            // que está entre <body> e </body> (QueuedNotification::cleanHtml,
            // que procura as tags em linhas próprias); sem o documento, a
            // aba "HTML" da fila ficava em branco.
            'body_html'        => self::documentoHtml($html, $assunto),
            'body_text'        => self::paraTexto($html),
            'mode'             => 'mailing',
            'attach_documents' => 0,
        ]);

        return $ok ? true : self::registrar('falha ao enfileirar', $tickets_id);
    }

    /**
     * Fragmento do e-mail -> documento HTML5 completo, com <body> e
     * </body> em linhas próprias (ver despachar()).
     */
    private static function documentoHtml(string $fragmento, string $titulo): string
    {
        return "<!DOCTYPE html>\n"
            . "<html lang=\"" . JsI18n::langHtml() . "\">\n"
            . "<head>\n"
            . "<meta charset=\"UTF-8\">\n"
            . "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n"
            . '<title>' . htmlspecialchars($titulo) . "</title>\n"
            // v0.64.3: no celular, menos margem em volta do cartão e botões
            // de opção mais compactos. Quem ignora <style> fica com o
            // layout fluido, que já cabe na tela.
            . "<style>\n"
            . "@media only screen and (max-width:480px){"
            . ".nps-fora{padding:12px 6px !important;}"
            . ".nps-cartao{padding-left:16px !important;padding-right:16px !important;}"
            . ".nps-opcao a{font-size:13px !important;padding:10px 0 !important;}"
            . "}\n"
            . "</style>\n"
            . "</head>\n"
            . "<body>\n"
            . $fragmento . "\n"
            . "</body>\n"
            . '</html>';
    }

    /**
     * Versão em texto puro, para quem lê e-mail sem HTML.
     *
     * Usa a conversão do próprio GLPI (a mesma das notificações nativas),
     * que mantém os parágrafos e mostra o endereço de cada link. Sem ela,
     * uma conversão simples.
     */
    private static function paraTexto(string $html): string
    {
        if (class_exists('\\Glpi\\RichText\\RichText')) {
            return trim(\Glpi\RichText\RichText::getTextFromHtml($html, true, false));
        }

        $texto = preg_replace('~<(br\s*/?|/p|/tr|/h[1-6]|/div)>~i', "\n", $html);
        $texto = html_entity_decode(strip_tags((string) $texto), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace("~\n{3,}~", "\n\n", $texto));
    }

    private static function registrar(string $motivo, int $tickets_id): bool
    {
        if (class_exists('\\Toolbox') && method_exists('\\Toolbox', 'logInFile')) {
            \Toolbox::logInFile(
                'npsphere',
                sprintf("[FALHA] Convite de pesquisa (chamado %d): %s\n", $tickets_id, $motivo)
            );
        }

        return false;
    }
}
