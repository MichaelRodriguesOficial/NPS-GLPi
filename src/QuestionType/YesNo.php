<?php

namespace GlpiPlugin\Npsphere\QuestionType;

class YesNo extends AbstractQuestionType
{
    public static function getKey(): string
    {
        return 'yesno';
    }

    public static function getLabel(): string
    {
        return __('Sim / Não', 'npsphere');
    }

    public static function getIcon(): string
    {
        return 'ti ti-thumb-up';
    }

    public static function getDefaultConfiguration(): array
    {
        // "allow_dont_know" reservado para uma futura opção "Não sei" (seção 15).
        return ['allow_dont_know' => false];
    }

    public static function validateConfiguration(array $input): array
    {
        return ['allow_dont_know' => !empty($input['yesno_allow_dont_know'])];
    }

    public static function renderConfigForm(array $configuration): string
    {
        $checked = !empty($configuration['allow_dont_know']) ? ' checked' : '';
        return '<div class="form-field row"><label class="col-form-label col-xxl-4">'
            . __('Opções adicionais', 'npsphere') . '</label><div class="col-xxl-8">'
            . '<label class="form-check"><input type="checkbox" class="form-check-input" name="yesno_allow_dont_know" value="1"' . $checked . '> '
            . __('Permitir também "Não sei"', 'npsphere') . '</label>'
            . '</div></div>';
    }

    public static function renderAnswerField(string $field_name, array $configuration, $current_value = null): string
    {
        $options = ['yes' => __('Yes'), 'no' => __('No')];
        if (!empty($configuration['allow_dont_know'])) {
            $options['dont_know'] = __('Não sei', 'npsphere');
        }

        $html = '<div class="npsphere-yesno-options" role="radiogroup">';
        foreach ($options as $value => $label) {
            $checked = ((string) $current_value === $value) ? ' checked' : '';
            $id = $field_name . '_' . $value;
            $html .= '<label class="npsphere-yesno-option" for="' . $id . '">';
            $html .= '<input type="radio" id="' . $id . '" name="' . self::e($field_name) . '" value="' . $value . '"' . $checked . '>';
            $html .= '<span>' . self::e($label) . '</span>';
            $html .= '</label>';
        }
        $html .= '</div>';
        return $html;
    }

    public static function validateAnswerValue($raw_value, array $configuration): array
    {
        $allowed = ['yes', 'no'];
        if (!empty($configuration['allow_dont_know'])) {
            $allowed[] = 'dont_know';
        }
        if ($raw_value === null || $raw_value === '') {
            return self::invalid(__('Selecione uma opção.', 'npsphere'));
        }
        if (!in_array($raw_value, $allowed, true)) {
            return self::invalid(__('Opção inválida.', 'npsphere'));
        }
        return self::valid(['option_value' => $raw_value]);
    }

    public static function getConditionChoices(array $configuration): ?array
    {
        $out = ['yes' => __('Yes'), 'no' => __('No')];
        if (!empty($configuration['allow_dont_know'])) {
            $out['dont_know'] = __('Não sei', 'npsphere');
        }
        return $out;
    }
}
