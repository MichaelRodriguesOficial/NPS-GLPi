<?php

namespace GlpiPlugin\Npsphere\QuestionType;

use Html;

/**
 * Implementação base opcional com utilitários comuns aos tipos de pergunta.
 */
abstract class AbstractQuestionType implements QuestionTypeInterface
{
    public static function getDefaultConfiguration(): array
    {
        return [];
    }

    public static function validateConfiguration(array $input): array
    {
        return [];
    }

    public static function renderAnswerField(string $field_name, array $configuration, $current_value = null): string
    {
        return '<em>' . __('Tipo de pergunta sem campo de resposta implementado.', 'npsphere') . '</em>';
    }

    public static function validateAnswerValue($raw_value, array $configuration): array
    {
        return [
            'valid'         => false,
            'error'         => __('Tipo de pergunta sem validação implementada.', 'npsphere'),
            'numeric_value' => null,
            'text_value'    => null,
            'option_value'  => null,
        ];
    }

    /**
     * Valores que esta pergunta pode assumir como resposta, no formato
     * valor => rótulo -- usado para montar o seletor de valor das regras
     * condicionais (em vez de obrigar o administrador a adivinhar e
     * digitar "no" ou "6" à mão).
     *
     * Retorna null quando as respostas são livres (ex.: texto), caso em
     * que o campo continua sendo de digitação livre.
     */
    public static function getConditionChoices(array $configuration): ?array
    {
        return null;
    }

    protected static function e(string $value): string
    {
        return Html::entities_deep($value);
    }

    /**
     * Atalho para montar um retorno de validação bem-sucedido.
     */
    protected static function valid(array $values): array
    {
        return array_merge([
            'valid'         => true,
            'error'         => null,
            'numeric_value' => null,
            'text_value'    => null,
            'option_value'  => null,
        ], $values);
    }

    /**
     * Atalho para montar um retorno de validação com erro.
     */
    protected static function invalid(string $error): array
    {
        return [
            'valid'         => false,
            'error'         => $error,
            'numeric_value' => null,
            'text_value'    => null,
            'option_value'  => null,
        ];
    }
}
