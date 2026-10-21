<?php

namespace GlpiPlugin\Npsphere;

use CommonDBChild;
use CommonGLPI;
use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Npsphere\QuestionType\QuestionTypeInterface;
use Session;

/**
 * Uma pergunta pertencente a uma Pesquisa (Survey).
 *
 * Implementada como CommonDBChild: cada pergunta tem exatamente uma
 * pesquisa "dona" (surveys_id) e reaproveita o mesmo direito de
 * permissão da pesquisa (plugin_npsphere_survey) — não existe uma
 * tela de administração de perguntas isolada da pesquisa.
 */
class Question extends CommonDBChild
{
    public static $itemtype = Survey::class;
    public static $items_id = 'surveys_id';

    public static $rightname = 'plugin_npsphere_survey';

    public $dohistory = true;

    /**
     * URLs pelo caminho REAL de instalação do plugin.
     *
     * O gerador genérico do GLPI produz "/plugins/npsphere/front/...",
     * enquanto os demais menus do plugin usavam Plugin::getWebDir(), que
     * devolve o caminho onde o plugin realmente está ("/marketplace/..."
     * quando instalado pelo Marketplace). O resultado eram dois padrões
     * de URL no mesmo plugin.
     *
     * getWebDir() foi o escolhido por acompanhar a instalação: continua
     * correto se o plugin for instalado em "plugins/" em vez de
     * "marketplace/".
     */
    public static function getFormURL($full = true)
    {
        return \Plugin::getWebDir('npsphere', (bool) $full) . '/front/question.form.php';
    }

    public static function getTypeName($nb = 0)
    {
        return _n('Pergunta', 'Perguntas', $nb, 'npsphere');
    }

    public static function getIcon()
    {
        return 'ti ti-help-hexagon';
    }

    public static function getDefaultValues(): array
    {
        return [
            'type'          => 'text',
            'is_active'     => 1,
            'is_required'   => 0,
            'configuration' => '{}',
            // Sem condição: a pergunta é sempre exibida.
            'condition_questions_id' => 0,
            'condition_operator'     => '',
            'condition_value'        => '',
        ];
    }

    /**
     * Hook oficial do GLPI (chamado ao final de CommonDBTM::getEmpty()),
     * usado para popular os valores padrão de uma pergunta nova. Sem
     * isso, uma pergunta nova nascia com is_active vazio -- exibido como
     * "Não" mesmo o padrão pretendido sendo "Sim".
     */
    public function post_getEmpty()
    {
        foreach (self::getDefaultValues() as $field => $value) {
            $this->fields[$field] = $value;
        }
    }

    /**
     * @return class-string<QuestionTypeInterface>|null
     */
    public function getQuestionTypeClass(): ?string
    {
        return QuestionTypeManager::get($this->fields['type'] ?? '');
    }

    /**
     * Configuração já decodificada (com fallback para o padrão do tipo).
     */
    public function getConfiguration(): array
    {
        $decoded = json_decode($this->fields['configuration'] ?? '{}', true);
        if (!is_array($decoded)) {
            $decoded = [];
        }

        $type_class = $this->getQuestionTypeClass();
        if ($type_class !== null) {
            $decoded = array_merge($type_class::getDefaultConfiguration(), $decoded);
        }

        return $decoded;
    }

    public function prepareInputForAdd($input)
    {
        $input = $this->prepareCommonInput($input, true);
        if ($input === false) {
            return false;
        }

        if (!isset($input['position'])) {
            $input['position'] = $this->getNextPosition((int) $input['surveys_id']);
        }

        return $input;
    }

    public function prepareInputForUpdate($input)
    {
        return $this->prepareCommonInput($input, false);
    }

    /**
     * Validações e sanitização compartilhadas por add() e update().
     *
     * @return array|false
     */
    private function prepareCommonInput(array $input, bool $is_new)
    {
        $type = $input['type'] ?? ($this->fields['type'] ?? 'text');

        if (!QuestionTypeManager::has($type)) {
            Session::addMessageAfterRedirect(
                __('Tipo de pergunta desconhecido.', 'npsphere'),
                false,
                ERROR
            );
            return false;
        }

        // Todas as perguntas do tipo "nps" contam automaticamente para o
        // cálculo do NPS da pesquisa (ver Survey::hasNpsQuestion()) --
        // não existe mais uma marcação manual de "principal" para isso.

        // Só revalida a configuração/obrigatoriedade/status quando o
        // envio for realmente uma submissão completa do formulário da
        // pergunta (identificada pela presença do campo "type") ou uma
        // criação nova. Uma atualização PARCIAL -- como a reordenação
        // por arrastar-e-soltar, que só envia "id" e "position" -- não
        // deve resetar esses campos para os valores padrão. Esse era
        // exatamente o bug que fazia reordenar apagar a configuração,
        // a obrigatoriedade e o status ativo de uma pergunta.
        if ($is_new || isset($input['type'])) {
            $type_class = QuestionTypeManager::get($type);
            $configuration = $type_class::validateConfiguration($input);
            $input['configuration'] = json_encode($configuration, JSON_UNESCAPED_UNICODE);

            $input['is_required'] = !empty($input['is_required']) ? 1 : 0;
            $input['is_active']   = isset($input['is_active']) ? (!empty($input['is_active']) ? 1 : 0) : 1;

            // Regra condicional (Fase 5): só é gravada se estiver
            // completa (pergunta-gatilho + operador válido). Qualquer
            // preenchimento parcial limpa a condição, para nunca deixar
            // uma pergunta presa numa regra pela metade.
            $trigger  = (int) ($input['condition_questions_id'] ?? 0);
            $operator = $input['condition_operator'] ?? '';
            $value    = trim((string) ($input['condition_value'] ?? ''));

            $valid_operator = array_key_exists($operator, ConditionEvaluator::OPERATORS);
            // Uma pergunta nunca pode depender de si mesma (laço infinito).
            $self_reference = !$is_new && $trigger === (int) ($this->fields['id'] ?? 0);

            // O valor precisa existir de fato entre as respostas
            // possíveis da pergunta-gatilho. Sem esta checagem, um valor
            // inválido (erro de digitação, ou envio forjado) criaria uma
            // regra que nunca seria satisfeita -- a pergunta simplesmente
            // nunca apareceria, sem nenhum aviso ao administrador.
            $valid_value = true;
            if ($trigger > 0) {
                $trigger_question = new static();
                if ($trigger_question->getFromDB($trigger)) {
                    $trigger_type = QuestionTypeManager::get($trigger_question->fields['type'] ?? '');
                    if ($trigger_type !== null) {
                        $choices = $trigger_type::getConditionChoices($trigger_question->getConfiguration());
                        // choices null = respostas livres (texto): aceita qualquer valor.
                        if (is_array($choices) && !array_key_exists($value, $choices)) {
                            Session::addMessageAfterRedirect(
                                __('O valor da condição não é uma resposta possível da pergunta escolhida.', 'npsphere'),
                                false,
                                ERROR
                            );
                            $valid_value = false;
                        }
                    }
                }
            }

            if ($trigger > 0 && $valid_operator && $value !== '' && !$self_reference && $valid_value) {
                $input['condition_questions_id'] = $trigger;
                $input['condition_operator']     = $operator;
                $input['condition_value']        = $value;
            } else {
                $input['condition_questions_id'] = 0;
                $input['condition_operator']     = '';
                $input['condition_value']        = '';
            }
        }

        return $input;
    }

    private function getNextPosition(int $surveys_id): int
    {
        global $DB;

        $iterator = $DB->request([
            'SELECT' => ['MAX' => 'position AS max_position'],
            'FROM'   => self::getTable(),
            'WHERE'  => ['surveys_id' => $surveys_id],
        ]);
        $row = $iterator->current();

        return $row && $row['max_position'] !== null ? ((int) $row['max_position'] + 1) : 1;
    }

    /**
     * Perguntas que podem servir de gatilho para uma condição: as da
     * mesma pesquisa que vêm ANTES desta na ordem de exibição (uma
     * pergunta não pode depender de outra que ainda não foi respondida)
     * e que não sejam a própria pergunta.
     *
     * @return array<int, string> questions_id => nome
     */
    public static function getTriggerCandidates(int $surveys_id, ?int $exclude_id = null, ?int $before_position = null): array
    {
        global $DB;

        $where = ['surveys_id' => $surveys_id, 'is_active' => 1];
        if ($exclude_id !== null) {
            $where[] = ['NOT' => ['id' => $exclude_id]];
        }
        if ($before_position !== null) {
            $where[] = ['position' => ['<', $before_position]];
        }

        $out = [];
        $iterator = $DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => $where,
            'ORDER' => 'position ASC',
        ]);
        foreach ($iterator as $row) {
            $out[$row['id']] = $row['position'] . '. ' . $row['name'];
        }

        return $out;
    }

    /**
     * Para cada pergunta que pode servir de gatilho, quais valores de
     * resposta ela aceita -- para o formulário oferecer um SELETOR com
     * as opções reais daquela pergunta, em vez de um campo de texto
     * livre onde o administrador teria que adivinhar ("no", "6"...).
     *
     * Formato: questions_id => {type, choices: {valor: rótulo} | null}
     * (choices null = respostas livres, ex.: texto)
     */
    public static function getTriggerChoices(int $surveys_id): array
    {
        global $DB;

        $out = [];
        $iterator = $DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => ['surveys_id' => $surveys_id, 'is_active' => 1],
        ]);

        foreach ($iterator as $row) {
            $type_class = QuestionTypeManager::get($row['type']);
            if ($type_class === null) {
                continue;
            }

            $configuration = json_decode($row['configuration'] ?? '{}', true);
            if (!is_array($configuration)) {
                $configuration = [];
            }
            $configuration = array_merge($type_class::getDefaultConfiguration(), $configuration);

            $out[(string) $row['id']] = [
                'type'    => $row['type'],
                'choices' => $type_class::getConditionChoices($configuration),
            ];
        }

        return $out;
    }

    /**
     * Perguntas ativas de uma pesquisa, ordenadas para exibição.
     *
     * @return array<int, array<string, mixed>> linhas da tabela, indexadas por id
     */
    public static function getActiveQuestions(int $surveys_id): array
    {
        global $DB;

        $questions = [];
        $iterator = $DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => [
                'surveys_id' => $surveys_id,
                'is_active'  => 1,
            ],
            'ORDER' => 'position ASC',
        ]);
        foreach ($iterator as $row) {
            $questions[$row['id']] = $row;
        }

        return $questions;
    }

    /**
     * Texto de uma resposta para a confirmação ("Sua resposta: 9",
     * "Sim", "🙂 Satisfeito", "★★★★").
     *
     * @param array<string, mixed> $question linha da pergunta
     * @param mixed                $valor    valor como vem do formulário
     */
    public static function rotuloDaResposta(array $question, $valor): string
    {
        $texto = is_array($valor) ? implode(', ', $valor) : (string) $valor;

        switch ((string) ($question['type'] ?? '')) {
            case 'yesno':
                return [
                    'yes'       => __('Sim'),
                    'no'        => __('Não'),
                    'dont_know' => __('Não sei', 'npsphere'),
                ][$texto] ?? $texto;

            case 'stars':
                return str_repeat('★', max(0, (int) $texto));

            case 'emoticon':
                $cfg = json_decode($question['configuration'] ?? '{}', true);
                foreach ((array) ($cfg['levels'] ?? []) as $i => $nivel) {
                    if ((int) ($nivel['value'] ?? $i + 1) === (int) $texto) {
                        return trim(($nivel['emoji'] ?? '') . ' ' . ($nivel['label'] ?? ''));
                    }
                }
                return $texto;
        }

        return $texto;
    }

    /**
     * Resposta a pré-marcar quando a pessoa chega pelo link de uma opção
     * do e-mail (&pergunta=ID&valor=X).
     *
     * Só PRÉ-MARCA, não grava: filtros de segurança de e-mail abrem os
     * links das mensagens para verificá-los, e gravar no clique encheria a
     * base de respostas que ninguém deu.
     *
     * Links sem &pergunta (convites enviados antes da variável por
     * pergunta) continuam valendo para a primeira pergunta com nota.
     *
     * @return array<string, mixed> no formato de renderAnswerFields($post)
     */
    public static function getPreenchimentoDoLink(int $surveys_id, array $params): array
    {
        $valor = $params['valor'] ?? null;
        if ($valor === null || $valor === '' || is_array($valor)) {
            return [];
        }
        $valor = (string) $valor;

        $perguntas = self::getActiveQuestions($surveys_id);
        $notas     = ['nps', 'stars', 'emoticon'];
        $alvo      = null;

        $id = (int) ($params['pergunta'] ?? 0);
        if ($id > 0) {
            $alvo = $perguntas[$id] ?? null;
        } else {
            foreach ($perguntas as $q) {
                if (in_array($q['type'], $notas, true)) {
                    $alvo = $q;
                    break;
                }
            }
        }

        if ($alvo === null || $alvo['type'] === 'text') {
            return [];
        }

        $campo = 'answer_' . $alvo['id'];

        if (in_array($alvo['type'], $notas, true)) {
            return is_numeric($valor) ? [$campo => (string) (int) $valor] : [];
        }

        if ($alvo['type'] === 'multiple_choice') {
            return [$campo => [$valor]];
        }

        // Escolha única e Sim/Não: o campo só marca o que bater com uma
        // das opções; valor inventado no link simplesmente não marca nada.
        return [$campo => $valor];
    }

    /**
     * Monta, para cada pergunta ativa da pesquisa, o HTML já renderizado
     * do seu campo de resposta (delegado à classe do tipo correspondente)
     * e o valor previamente enviado, se houver (para reexibir em caso de
     * erro de validação). Compartilhado entre o formulário de resposta
     * (front/answer.form.php) e a aba "Visualizar" da pesquisa.
     *
     * @return array<int, array{question: array, field_html: string}>
     */
    public static function renderAnswerFields(int $surveys_id, array $post = []): array
    {
        $rendered = [];
        foreach (self::getActiveQuestions($surveys_id) as $questions_id => $question) {
            $type_class = QuestionTypeManager::get($question['type']);
            if ($type_class === null) {
                continue;
            }

            $configuration = json_decode($question['configuration'] ?? '{}', true);
            if (!is_array($configuration)) {
                $configuration = [];
            }
            $configuration = array_merge($type_class::getDefaultConfiguration(), $configuration);

            $field_name = 'answer_' . $questions_id;
            $current_value = $question['type'] === 'multiple_choice'
                ? ($post[$field_name] ?? [])
                : ($post[$field_name] ?? null);

            $rendered[$questions_id] = [
                'question'   => $question,
                'field_html' => $type_class::renderAnswerField($field_name, $configuration, $current_value),
            ];
        }

        return $rendered;
    }

    /**
     * Aba "Perguntas" exibida no formulário da Pesquisa.
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof Survey && $item->getID()) {
            $nb = countElementsInTable(self::getTable(), ['surveys_id' => $item->getID()]);
            return self::createTabEntry(self::getTypeName($nb), $nb);
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof Survey && $item->getID()) {
            self::showForSurvey($item);
        }
        return true;
    }

    public static function showForSurvey(Survey $survey, bool $table_only = false): void
    {
        global $DB;

        $questions = [];
        $iterator = $DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => ['surveys_id' => $survey->getID()],
            'ORDER' => 'position ASC',
        ]);
        foreach ($iterator as $row) {
            $questions[] = $row;
        }

        $context = [
            'survey'             => $survey,
            'questions'          => $questions,
            'types'              => QuestionTypeManager::getTypesForDropdown(),
            'can_edit'           => Session::haveRight(self::$rightname, UPDATE),
            'can_create'         => Session::haveRight(self::$rightname, CREATE),
            'can_delete'         => Session::haveRight(self::$rightname, DELETE),
            'reorder_url'        => \Plugin::getWebDir('npsphere', true) . '/ajax/question.reorder.php',
            'question_form_url'  => \Plugin::getWebDir('npsphere', true) . '/front/question.form.php',
            'list_url'           => \Plugin::getWebDir('npsphere', true) . '/ajax/question.list.php?surveys_id=' . $survey->getID(),
        ];

        // "table_only" é usado pelo recarregamento via AJAX (depois de
        // salvar/editar uma pergunta): retorna só o miolo da listagem,
        // sem o wrapper que controla a alternância lista/formulário
        // (esse wrapper só deve existir uma vez na página).
        $template = $table_only ? '@npsphere/question/list_table.html.twig' : '@npsphere/question/list.html.twig';
        TemplateRenderer::getInstance()->display($template, $context);
    }
}
