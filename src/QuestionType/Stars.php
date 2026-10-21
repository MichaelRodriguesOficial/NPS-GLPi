<?php

namespace GlpiPlugin\Npsphere\QuestionType;

class Stars extends AbstractQuestionType
{
    public static function getKey(): string
    {
        return 'stars';
    }

    public static function getLabel(): string
    {
        return __('Estrelas', 'npsphere');
    }

    public static function getIcon(): string
    {
        return 'ti ti-star';
    }

    public static function getDefaultConfiguration(): array
    {
        return ['max' => 5];
    }

    public static function validateConfiguration(array $input): array
    {
        $max = (int) ($input['stars_max'] ?? 5);
        if (!in_array($max, [3, 5, 10], true)) {
            $max = 5;
        }
        return ['max' => $max];
    }

    public static function renderConfigForm(array $configuration): string
    {
        $max = (int) ($configuration['max'] ?? 5);
        $html  = '<div class="form-field row"><label class="col-form-label col-xxl-4">';
        $html .= __('Quantidade de estrelas', 'npsphere');
        $html .= '</label><div class="col-xxl-8"><select name="stars_max" class="form-select" style="max-width:10rem">';
        foreach ([3, 5, 10] as $option) {
            $html .= '<option value="' . $option . '"' . ($max === $option ? ' selected' : '') . '>' . $option . '</option>';
        }
        $html .= '</select></div></div>';
        return $html;
    }

    public static function renderAnswerField(string $field_name, array $configuration, $current_value = null): string
    {
        $max = (int) ($configuration['max'] ?? 5);

        // Truque clássico de "rating com CSS puro": o HTML lista as
        // estrelas de trás para frente (max, max-1, ..., 1), como pares
        // <input><label> IRMÃOS (não aninhados um dentro do outro) --
        // isso é o que permite o seletor CSS "~" acender todas as
        // estrelas da selecionada para trás. O CSS então usa
        // flex-direction: row-reverse para exibir na ordem natural de
        // leitura (1 à esquerda, max à direita).
        $html = '<div class="npsphere-stars-scale" role="radiogroup">';
        for ($i = $max; $i >= 1; $i--) {
            $checked = ((string) $current_value === (string) $i) ? ' checked' : '';
            $id = $field_name . '_' . $i;
            $html .= '<input type="radio" id="' . $id . '" name="' . self::e($field_name) . '" value="' . $i . '"' . $checked . '>';
            $html .= '<label class="npsphere-star-option" for="' . $id . '" title="' . $i . '"><i class="ti ti-star"></i></label>';
        }
        $html .= '</div>';
        return $html;
    }

    public static function validateAnswerValue($raw_value, array $configuration): array
    {
        $max = (int) ($configuration['max'] ?? 5);
        if ($raw_value === null || $raw_value === '') {
            return self::invalid(__('Selecione uma quantidade de estrelas.', 'npsphere'));
        }
        if (!is_numeric($raw_value)) {
            return self::invalid(__('Valor inválido.', 'npsphere'));
        }
        $value = (int) $raw_value;
        if ($value < 1 || $value > $max) {
            return self::invalid(sprintf(__('A avaliação deve estar entre 1 e %d estrelas.', 'npsphere'), $max));
        }
        return self::valid(['numeric_value' => $value]);
    }

    public static function getConditionChoices(array $configuration): ?array
    {
        $max = (int) ($configuration['max'] ?? 5);
        $out = [];
        for ($i = 1; $i <= $max; $i++) {
            $out[(string) $i] = sprintf(_n('%d estrela', '%d estrelas', $i, 'npsphere'), $i);
        }
        return $out;
    }
}
