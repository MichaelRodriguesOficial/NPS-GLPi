<?php

namespace GlpiPlugin\Npsphere\QuestionType;

class Text extends AbstractQuestionType
{
    public static function getKey(): string
    {
        return 'text';
    }

    public static function getLabel(): string
    {
        return __('Texto (resposta aberta)', 'npsphere');
    }

    public static function getIcon(): string
    {
        return 'ti ti-align-left';
    }

    public static function getDefaultConfiguration(): array
    {
        return ['long' => true, 'max_length' => 1000];
    }

    public static function validateConfiguration(array $input): array
    {
        $max_length = (int) ($input['text_max_length'] ?? 1000);
        if ($max_length < 1) {
            $max_length = 1000;
        }
        if ($max_length > 10000) {
            $max_length = 10000;
        }

        return [
            'long'       => !empty($input['text_long']),
            'max_length' => $max_length,
        ];
    }

    public static function renderConfigForm(array $configuration): string
    {
        $long       = !empty($configuration['long']);
        $max_length = (int) ($configuration['max_length'] ?? 1000);

        $html  = '<div class="form-field row"><label class="col-form-label col-xxl-4">';
        $html .= __('Tipo de campo', 'npsphere') . '</label><div class="col-xxl-8">';
        $html .= '<label class="form-check"><input type="radio" class="form-check-input" name="text_long" value="0"' . (!$long ? ' checked' : '') . '> ' . __('Texto curto', 'npsphere') . '</label> ';
        $html .= '<label class="form-check"><input type="radio" class="form-check-input" name="text_long" value="1"' . ($long ? ' checked' : '') . '> ' . __('Texto longo', 'npsphere') . '</label>';
        $html .= '</div></div>';

        $html .= '<div class="form-field row"><label class="col-form-label col-xxl-4">';
        $html .= __('Quantidade máxima de caracteres', 'npsphere') . '</label><div class="col-xxl-8">';
        $html .= '<input type="number" class="form-control" name="text_max_length" min="1" max="10000" value="' . $max_length . '">';
        $html .= '</div></div>';

        return $html;
    }

    public static function renderAnswerField(string $field_name, array $configuration, $current_value = null): string
    {
        $long       = !empty($configuration['long']);
        $max_length = (int) ($configuration['max_length'] ?? 1000);
        $value      = self::e((string) ($current_value ?? ''));

        if ($long) {
            return '<textarea class="form-control" name="' . self::e($field_name) . '" '
                . 'maxlength="' . $max_length . '" rows="4">' . $value . '</textarea>'
                . '<div class="form-text npsphere-char-count" data-max="' . $max_length . '"></div>';
        }

        return '<input type="text" class="form-control" name="' . self::e($field_name) . '" '
            . 'maxlength="' . $max_length . '" value="' . $value . '">';
    }

    public static function validateAnswerValue($raw_value, array $configuration): array
    {
        $max_length = (int) ($configuration['max_length'] ?? 1000);
        $text = trim((string) ($raw_value ?? ''));

        if (mb_strlen($text) > $max_length) {
            return self::invalid(sprintf(__('O texto ultrapassa o limite de %d caracteres.', 'npsphere'), $max_length));
        }

        // Texto vazio é válido aqui; a obrigatoriedade é responsabilidade
        // do serviço que orquestra o envio de todas as respostas (verifica
        // is_required por pergunta antes de chamar esta validação).
        return self::valid(['text_value' => $text]);
    }
}
