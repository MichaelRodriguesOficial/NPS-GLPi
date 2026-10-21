<?php

namespace GlpiPlugin\Npsphere\QuestionType;

class MultipleChoice extends AbstractOptionListType
{
    public static function getKey(): string
    {
        return 'multiple_choice';
    }

    public static function getLabel(): string
    {
        return __('Múltiplas opções (várias respostas)', 'npsphere');
    }

    public static function getIcon(): string
    {
        return 'ti ti-checkbox';
    }

    protected static function getInputType(): string
    {
        return 'checkbox';
    }
}
