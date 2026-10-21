<?php

namespace GlpiPlugin\Npsphere;

use GlpiPlugin\Npsphere\QuestionType\Nps;

/**
 * Cálculo de métricas de uma pesquisa (Fase 4).
 *
 * O cálculo é sempre feito sob demanda a partir das respostas gravadas
 * (seção 26 do escopo: "não depender exclusivamente de um valor
 * armazenado"), então nunca fica defasado após edições ou exclusões.
 *
 * Perguntas condicionais (Fase 5): uma pergunta que não foi exibida ao
 * respondente simplesmente não gera linha em answer_values. Por isso o
 * denominador de cada pergunta é o número de pessoas que REALMENTE a
 * responderam -- e não o total de respostas da pesquisa. Sem esse
 * cuidado, uma pergunta que só aparece para quem deu nota baixa
 * mostraria percentuais diluídos por gente que nunca a viu.
 */
class MetricsCalculator
{
    /**
     * Total de respostas (submissões completas) da pesquisa.
     */
    /**
     * Total de respostas, opcionalmente restrito a entidades.
     *
     * @param int[] $entities Lista EXATA de entidades. Vazia = todas.
     *                        Selecionar uma entidade-pai NÃO inclui as
     *                        filhas: quem analisa o atendimento de uma
     *                        unidade quer os números dela, não os da
     *                        estrutura inteira.
     */
    public static function getTotalAnswers(int $surveys_id, array $entities = []): int
    {
        global $DB;

        $where = ['surveys_id' => $surveys_id];
        if (!empty($entities)) {
            $where['entities_id'] = $entities;
        }

        $row = $DB->request([
            'COUNT' => 'cpt',
            'FROM'  => Answer::getTable(),
            'WHERE' => $where,
        ])->current();

        return (int) ($row['cpt'] ?? 0);
    }

    /**
     * Valores respondidos de uma pergunta específica.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getValuesForQuestion(int $surveys_id, int $questions_id, array $entities = []): array
    {
        global $DB;

        $answers_table = Answer::getTable();
        $values_table  = AnswerValue::getTable();

        $where = [
            "$values_table.questions_id" => $questions_id,
            "$answers_table.surveys_id"  => $surveys_id,
        ];
        if (!empty($entities)) {
            $where["$answers_table.entities_id"] = $entities;
        }

        $out = [];
        $iterator = $DB->request([
            'SELECT'     => ["$values_table.numeric_value", "$values_table.text_value", "$values_table.option_value", "$values_table.value"],
            'FROM'       => $values_table,
            'INNER JOIN' => [
                $answers_table => [
                    'ON' => [
                        $values_table  => 'answers_id',
                        $answers_table => 'id',
                    ],
                ],
            ],
            'WHERE' => $where,
        ]);
        foreach ($iterator as $row) {
            $out[] = $row;
        }

        return $out;
    }

    /**
     * Métricas de UMA pergunta, no formato adequado ao seu tipo.
     *
     * @return array{
     *     kind: string,
     *     answered: int,
     *     ...
     * }
     */
    public static function getQuestionMetrics(array $question, array $values): array
    {
        $answered = count($values);
        $base = ['kind' => 'none', 'answered' => $answered];

        if ($answered === 0) {
            return $base;
        }

        $configuration = json_decode($question['configuration'] ?? '{}', true);
        if (!is_array($configuration)) {
            $configuration = [];
        }
        $type_class = QuestionTypeManager::get($question['type']);
        if ($type_class !== null) {
            $configuration = array_merge($type_class::getDefaultConfiguration(), $configuration);
        }

        switch ($question['type']) {
            case 'nps':
                return array_merge($base, self::npsMetrics($values, (int) ($configuration['scale'] ?? 10)));

            case 'stars':
                return array_merge($base, self::averageMetrics($values, (int) ($configuration['max'] ?? 5), 'stars'));

            case 'emoticon':
                $labels = [];
                foreach (($configuration['levels'] ?? []) as $level) {
                    $labels[(string) ($level['value'] ?? '')] = trim(($level['emoji'] ?? '') . ' ' . ($level['label'] ?? ''));
                }
                $max = count($labels) ?: 5;
                return array_merge($base, self::averageMetrics($values, $max, 'emoticon', $labels));

            case 'yesno':
                return array_merge($base, self::yesNoMetrics($values));

            case 'single_choice':
            case 'multiple_choice':
                return array_merge($base, self::choiceMetrics($values, $question['type'] === 'multiple_choice'));

            case 'text':
                return array_merge($base, self::textMetrics($values));
        }

        return $base;
    }

    /**
     * NPS = % Promotores − % Detratores (seção 26 do escopo).
     */
    /**
     * Pública para o painel consolidado usar EXATAMENTE este cálculo.
     *
     * Duas implementações do NPS -- uma aqui e outra no painel --
     * divergiram na prática (-6 nos Resultados, +28 no painel, com os
     * mesmos dados). A única forma de garantir que batam é haver um
     * cálculo só.
     */
    public static function npsMetrics(array $values, int $scale): array
    {
        $counts = ['promoter' => 0, 'passive' => 0, 'detractor' => 0];
        $sum = 0;
        $total = 0;

        foreach ($values as $row) {
            if ($row['numeric_value'] === null) {
                continue;
            }
            $note = (int) $row['numeric_value'];
            $counts[Nps::classify($note, $scale)]++;
            $sum += $note;
            $total++;
        }

        if ($total === 0) {
            return ['kind' => 'nps', 'total' => 0];
        }

        $pct = static fn(int $n): float => round($n * 100 / $total, 1);
        $score = (int) round($pct($counts['promoter']) - $pct($counts['detractor']));

        return [
            'kind'          => 'nps',
            'total'         => $total,
            'scale'         => $scale,
            'score'         => $score,
            'rating'        => self::rateNps($score),
            'average'       => round($sum / $total, 2),
            'promoters'     => $counts['promoter'],
            'passives'      => $counts['passive'],
            'detractors'    => $counts['detractor'],
            'promoters_pct'  => $pct($counts['promoter']),
            'passives_pct'   => $pct($counts['passive']),
            'detractors_pct' => $pct($counts['detractor']),
        ];
    }

    /**
     * Faixas de leitura do NPS amplamente usadas no mercado.
     */
    private static function rateNps(int $score): string
    {
        if ($score >= 75) {
            return 'excellent';
        }
        if ($score >= 50) {
            return 'great';
        }
        if ($score >= 0) {
            return 'ok';
        }
        return 'bad';
    }

    /**
     * Média + distribuição, para escalas numéricas (estrelas, emoticons).
     */
    private static function averageMetrics(array $values, int $max, string $kind, array $labels = []): array
    {
        $distribution = [];
        $sum = 0;
        $total = 0;

        foreach ($values as $row) {
            if ($row['numeric_value'] === null) {
                continue;
            }
            $v = (int) $row['numeric_value'];
            $distribution[$v] = ($distribution[$v] ?? 0) + 1;
            $sum += $v;
            $total++;
        }

        if ($total === 0) {
            return ['kind' => $kind, 'total' => 0];
        }

        $rows = [];
        for ($i = ($kind === 'stars' ? 1 : 1); $i <= $max; $i++) {
            $count = $distribution[$i] ?? 0;
            $rows[] = [
                'label' => $labels[(string) $i] ?? (string) $i,
                'count' => $count,
                'pct'   => round($count * 100 / $total, 1),
            ];
        }

        return [
            'kind'         => $kind,
            'total'        => $total,
            'max'          => $max,
            'average'      => round($sum / $total, 2),
            'distribution' => $rows,
        ];
    }

    /**
     * Sim/Não: a taxa de "Sim" é a métrica natural (ex.: "Seu problema
     * foi resolvido?" vira uma taxa de resolução).
     */
    private static function yesNoMetrics(array $values): array
    {
        $counts = [];
        $total = 0;

        foreach ($values as $row) {
            $v = $row['option_value'] ?? $row['value'];
            if ($v === null || $v === '') {
                continue;
            }
            $counts[$v] = ($counts[$v] ?? 0) + 1;
            $total++;
        }

        if ($total === 0) {
            return ['kind' => 'yesno', 'total' => 0];
        }

        $labels = ['yes' => __('Yes'), 'no' => __('No'), 'dont_know' => __('Não sei', 'npsphere')];
        $rows = [];
        foreach ($labels as $key => $label) {
            if (!isset($counts[$key])) {
                continue;
            }
            $rows[] = [
                'label' => $label,
                'count' => $counts[$key],
                'pct'   => round($counts[$key] * 100 / $total, 1),
            ];
        }

        return [
            'kind'         => 'yesno',
            'total'        => $total,
            'yes_pct'      => round(($counts['yes'] ?? 0) * 100 / $total, 1),
            'distribution' => $rows,
        ];
    }

    /**
     * Múltipla escolha / múltiplas opções: frequência por opção.
     *
     * Em "múltiplas opções" a soma dos percentuais ultrapassa 100% de
     * propósito -- cada pessoa pode marcar várias. O percentual é
     * sempre "quantos % das pessoas marcaram esta opção".
     */
    private static function choiceMetrics(array $values, bool $is_multiple): array
    {
        $counts = [];
        $respondents = 0;

        foreach ($values as $row) {
            $raw = $row['option_value'] ?? null;
            $selected = [];

            if ($raw !== null && $raw !== '') {
                $selected = [$raw];
            } elseif (!empty($row['text_value'])) {
                // Múltiplas opções são gravadas separadas por " | "
                $selected = array_filter(array_map('trim', explode('|', $row['text_value'])));
            }

            if (empty($selected)) {
                continue;
            }
            $respondents++;
            foreach ($selected as $option) {
                $counts[$option] = ($counts[$option] ?? 0) + 1;
            }
        }

        if ($respondents === 0) {
            return ['kind' => 'choice', 'total' => 0];
        }

        arsort($counts);
        $rows = [];
        foreach ($counts as $option => $count) {
            $rows[] = [
                'label' => $option,
                'count' => $count,
                'pct'   => round($count * 100 / $respondents, 1),
            ];
        }

        return [
            'kind'         => 'choice',
            'is_multiple'  => $is_multiple,
            'total'        => $respondents,
            'top_option'   => $rows[0]['label'] ?? null,
            'distribution' => $rows,
        ];
    }

    /**
     * Texto: não há média possível -- o valor está nos comentários em si.
     */
    private static function textMetrics(array $values): array
    {
        $comments = [];
        foreach ($values as $row) {
            $text = trim((string) ($row['text_value'] ?? ''));
            if ($text !== '') {
                $comments[] = $text;
            }
        }

        return [
            'kind'     => 'text',
            'total'    => count($comments),
            'comments' => $comments,
        ];
    }

    /**
     * Perguntas que produzem uma NOTA, com a escala de cada uma.
     *
     * Não é só o tipo NPS: estrelas e emoticons também são notas -- uma
     * pesquisa feita só com emoticons tem nota média tanto quanto uma
     * feita com NPS. Antes, "Nota média" e a coluna "Nota" ficavam
     * vazias nessas pesquisas, o que dava a impressão de que o plugin
     * não sabia medi-las.
     *
     * @return array<int, array{max: int, type: string}> questions_id => escala
     */
    public static function getScoreQuestions(array $questions): array
    {
        $out = [];

        foreach ($questions as $question) {
            $configuration = json_decode($question['configuration'] ?? '{}', true);
            if (!is_array($configuration)) {
                $configuration = [];
            }

            switch ($question['type']) {
                case 'nps':
                    $max = (int) ($configuration['scale'] ?? 10);
                    break;
                case 'stars':
                    $max = (int) ($configuration['max'] ?? 5);
                    break;
                case 'emoticon':
                    $levels = $configuration['levels'] ?? [];
                    $max = count($levels) ?: 5;
                    break;
                default:
                    continue 2; // tipos sem nota (texto, escolhas, sim/não)
            }

            if ($max > 0) {
                $out[(int) $question['id']] = ['max' => $max, 'type' => $question['type']];
            }
        }

        return $out;
    }

    /**
     * Nota de UMA resposta, normalizada para 0–10.
     *
     * Perguntas com escalas diferentes (5 estrelas, 5 emoticons, NPS de
     * 0 a 10) são convertidas para a mesma régua antes da média --
     * somar "4 estrelas" com "8 de NPS" sem converter produziria um
     * número sem significado.
     *
     * @return array{score: float, classification: string, scale: int}|null
     */
    public static function getAnswerScore(int $answers_id, array $score_questions): ?array
    {
        global $DB;

        if (empty($score_questions)) {
            return null;
        }

        $normalized = [];
        foreach ($DB->request([
            'SELECT' => ['questions_id', 'numeric_value'],
            'FROM'   => AnswerValue::getTable(),
            'WHERE'  => [
                'answers_id'   => $answers_id,
                'questions_id' => array_keys($score_questions),
            ],
        ]) as $row) {
            if ($row['numeric_value'] === null) {
                continue;
            }
            $qid = (int) $row['questions_id'];
            $max = $score_questions[$qid]['max'] ?? 0;
            if ($max <= 0) {
                continue;
            }
            $normalized[] = ((float) $row['numeric_value'] / $max) * 10;
        }

        if (empty($normalized)) {
            return null;
        }

        $score = array_sum($normalized) / count($normalized);

        return [
            'score'          => round($score, 1),
            'classification' => Nps::classify((int) round($score), 10),
            'scale'          => 10,
        ];
    }

    /**
     * Nota de NPS de UMA resposta específica (a nota que aquela pessoa
     * deu), com sua classificação.
     *
     * Quando a pesquisa tem mais de uma pergunta de NPS, usa a média
     * das notas daquela pessoa -- é a leitura mais fiel de "a nota que
     * ela deu", já que todas as perguntas de NPS contam igualmente.
     *
     * @return array{score: float, classification: string, scale: int}|null
     */
    public static function getAnswerNps(int $answers_id, array $nps_question_ids, int $scale = 10): ?array
    {
        global $DB;

        if (empty($nps_question_ids)) {
            return null;
        }

        $notes = [];
        foreach ($DB->request([
            'SELECT' => ['numeric_value'],
            'FROM'   => AnswerValue::getTable(),
            'WHERE'  => [
                'answers_id'   => $answers_id,
                'questions_id' => $nps_question_ids,
            ],
        ]) as $row) {
            if ($row['numeric_value'] !== null) {
                $notes[] = (float) $row['numeric_value'];
            }
        }

        if (empty($notes)) {
            return null;
        }

        $score = array_sum($notes) / count($notes);

        return [
            'score'          => round($score, 1),
            'classification' => Nps::classify((int) round($score), $scale),
            'scale'          => $scale,
        ];
    }

    /**
     * Resumo consolidado da pesquisa, para exibir em destaque.
     *
     * @return array{total_answers:int, nps:?array, nps_questions:int, average:?float, average_scale:?int}
     */
    public static function getSummary(int $surveys_id, array $questions, array $entities = []): array
    {
        $nps_questions = 0;
        foreach ($questions as $q) {
            if ($q['type'] === 'nps') {
                $nps_questions++;
            }
        }

        $nps = self::getSurveyNps($surveys_id, $questions, $entities);

        // Nota média geral: vale para QUALQUER pergunta com nota
        // (NPS, estrelas, emoticons), não só NPS. Quando há só NPS o
        // valor coincide com a média dele; numa pesquisa só de
        // emoticons, que antes não mostrava nota nenhuma, passa a
        // existir.
        $overall = self::getOverallScore($surveys_id, $questions, $entities);

        return [
            'total_answers' => self::getTotalAnswers($surveys_id, $entities),
            'nps'           => $nps,
            'nps_questions' => $nps_questions,
            'average'       => $overall['score'] ?? ($nps['average'] ?? null),
            'average_scale' => $overall['scale'] ?? ($nps['scale'] ?? null),
            'overall'       => $overall,
        ];
    }

    /**
     * Nota média da pesquisa inteira, normalizada para 0–10.
     */
    public static function getOverallScore(int $surveys_id, array $questions, array $entities = []): ?array
    {
        $score_questions = self::getScoreQuestions($questions);
        if (empty($score_questions)) {
            return null;
        }

        $normalized = [];
        foreach ($score_questions as $questions_id => $info) {
            foreach (self::getValuesForQuestion($surveys_id, $questions_id, $entities) as $row) {
                if ($row['numeric_value'] === null) {
                    continue;
                }
                $normalized[] = ((float) $row['numeric_value'] / $info['max']) * 10;
            }
        }

        if (empty($normalized)) {
            return null;
        }

        $score = array_sum($normalized) / count($normalized);

        return [
            'score'          => round($score, 1),
            'scale'          => 10,
            'classification' => Nps::classify((int) round($score), 10),
        ];
    }

    /**
     * NPS geral da pesquisa: consolida TODAS as perguntas do tipo "nps"
     * (não existe mais o conceito de "pergunta NPS principal").
     */
    public static function getSurveyNps(int $surveys_id, array $questions, array $entities = []): ?array
    {
        $all = [];
        $scale = 10;

        foreach ($questions as $question) {
            if ($question['type'] !== 'nps') {
                continue;
            }
            $configuration = json_decode($question['configuration'] ?? '{}', true);
            $scale = (int) ($configuration['scale'] ?? 10);
            foreach (self::getValuesForQuestion($surveys_id, (int) $question['id'], $entities) as $row) {
                $all[] = $row;
            }
        }

        if (empty($all)) {
            return null;
        }

        $metrics = self::npsMetrics($all, $scale);
        return $metrics['total'] > 0 ? $metrics : null;
    }
}
