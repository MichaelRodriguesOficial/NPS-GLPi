<?php

namespace GlpiPlugin\Npsphere;

/**
 * Avalia as regras condicionais de exibição de perguntas (Fase 5).
 *
 * Uma pergunta pode ser condicionada à resposta de OUTRA pergunta da
 * mesma pesquisa: "Descreva o motivo" só aparece se "Gostou?" for
 * "Não", ou se a nota do NPS for <= 6.
 *
 * A MESMA lógica roda nos dois lados:
 *  - no navegador (JS), para mostrar/esconder a pergunta na hora;
 *  - aqui no servidor, ao processar o envio -- porque nunca se deve
 *    confiar somente na validação JavaScript (seção 20 do escopo).
 *    Sem isso, alguém poderia enviar resposta a uma pergunta que nem
 *    deveria estar visível, ou escapar de uma obrigatória.
 */
class ConditionEvaluator
{
    public const OPERATORS = [
        'eq'  => '=',
        'ne'  => '≠',
        'lt'  => '<',
        'lte' => '≤',
        'gt'  => '>',
        'gte' => '≥',
    ];

    /**
     * Rótulos traduzidos dos operadores, para o formulário administrativo.
     */
    public static function getOperatorsForDropdown(): array
    {
        return [
            'eq'  => __('é igual a', 'npsphere'),
            'ne'  => __('é diferente de', 'npsphere'),
            'lt'  => __('é menor que', 'npsphere'),
            'lte' => __('é menor ou igual a', 'npsphere'),
            'gt'  => __('é maior que', 'npsphere'),
            'gte' => __('é maior ou igual a', 'npsphere'),
        ];
    }

    public static function hasCondition(array $question): bool
    {
        return !empty($question['condition_questions_id'])
            && !empty($question['condition_operator']);
    }

    /**
     * A pergunta deve ser exibida/considerada, dadas as respostas atuais?
     *
     * @param array $question  Linha da pergunta (com os campos condition_*)
     * @param array $answers   questions_id => valor bruto respondido
     */
    public static function isVisible(array $question, array $answers): bool
    {
        if (!self::hasCondition($question)) {
            return true; // sem condição: sempre visível
        }

        $trigger_id = (int) $question['condition_questions_id'];
        if (!array_key_exists($trigger_id, $answers)) {
            return false; // a pergunta-gatilho não foi respondida
        }

        return self::compare(
            $answers[$trigger_id],
            $question['condition_operator'],
            $question['condition_value']
        );
    }

    /**
     * Compara o valor respondido com o valor esperado pela condição.
     *
     * Comparações numéricas (menor/maior) só fazem sentido quando ambos
     * os lados são numéricos; caso contrário retornam false em vez de
     * cair no comportamento imprevisível de comparar strings.
     */
    public static function compare($answer, string $operator, $expected): bool
    {
        // Múltiplas opções: a condição é satisfeita se QUALQUER uma das
        // opções marcadas atender ao critério.
        if (is_array($answer)) {
            foreach ($answer as $single) {
                if (self::compare($single, $operator, $expected)) {
                    return true;
                }
            }
            return false;
        }

        $answer_str = trim((string) $answer);
        $expected_str = trim((string) $expected);

        if ($operator === 'eq') {
            return $answer_str === $expected_str;
        }
        if ($operator === 'ne') {
            return $answer_str !== $expected_str;
        }

        if (!is_numeric($answer_str) || !is_numeric($expected_str)) {
            return false;
        }
        $a = (float) $answer_str;
        $b = (float) $expected_str;

        switch ($operator) {
            case 'lt':
                return $a < $b;
            case 'lte':
                return $a <= $b;
            case 'gt':
                return $a > $b;
            case 'gte':
                return $a >= $b;
        }

        return false;
    }
}
