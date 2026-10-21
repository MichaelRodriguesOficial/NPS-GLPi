<?php

namespace GlpiPlugin\Npsphere\QuestionType;

class SingleChoice extends AbstractOptionListType
{
    public static function getKey(): string
    {
        return 'single_choice';
    }

    public static function getLabel(): string
    {
        return __('Múltipla escolha (uma opção)', 'npsphere');
    }

    public static function getIcon(): string
    {
        return 'ti ti-circle-dot';
    }

    protected static function getInputType(): string
    {
        return 'radio';
    }
}
