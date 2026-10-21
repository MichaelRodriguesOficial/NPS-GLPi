<?php

namespace GlpiPlugin\Npsphere\QuestionType;

class Emoticon extends AbstractQuestionType
{
    public static function getKey(): string
    {
        return 'emoticon';
    }

    public static function getLabel(): string
    {
        return __('Emoticons', 'npsphere');
    }

    public static function getIcon(): string
    {
        return 'ti ti-mood-smile';
    }

    /**
     * Conjunto canônico de 5 emojis -- a única fonte de emojis "padrão"
     * usada tanto para a configuração de 5 níveis quanto para a versão
     * reduzida de 3 níveis (que usa os itens 1, 3 e 5 desta lista:
     * negativo / neutro / positivo). O admin ainda pode editar o texto
     * e o próprio emoji livremente depois.
     */
    private static function getCanonicalLevels(): array
    {
        return [
            ['emoji' => '😡', 'label' => __('Muito insatisfeito', 'npsphere')],
            ['emoji' => '🙁', 'label' => __('Insatisfeito', 'npsphere')],
            ['emoji' => '😐', 'label' => __('Neutro', 'npsphere')],
            ['emoji' => '🙂', 'label' => __('Satisfeito', 'npsphere')],
            ['emoji' => '😍', 'label' => __('Muito satisfeito', 'npsphere')],
        ];
    }

    public static function getDefaultConfiguration(): array
    {
        $canonical = self::getCanonicalLevels();
        return [
            'count'  => 5,
            'levels' => self::withValues($canonical),
        ];
    }

    private static function withValues(array $levels): array
    {
        $value = 1;
        foreach ($levels as &$level) {
            $level['value'] = $value++;
        }
        return $levels;
    }

    public static function validateConfiguration(array $input): array
    {
        $emojis = $input['emoticon_emoji'] ?? [];
        $labels = $input['emoticon_label'] ?? [];

        $levels = [];
        foreach ($emojis as $i => $emoji) {
            $emoji = trim((string) $emoji);
            $label = trim((string) ($labels[$i] ?? ''));
            if ($emoji === '' && $label === '') {
                continue;
            }
            $levels[] = ['emoji' => $emoji, 'label' => $label];
        }

        if (empty($levels)) {
            $levels = self::getCanonicalLevels();
        }

        // "count" é apenas informativo (para o formulário lembrar qual
        // opção do seletor mostrar); o que realmente vale é a
        // quantidade de linhas efetivamente enviadas.
        $count = count($levels) <= 3 ? 3 : 5;

        return ['count' => $count, 'levels' => self::withValues($levels)];
    }

    public static function renderConfigForm(array $configuration): string
    {
        $levels = $configuration['levels'] ?? self::getDefaultConfiguration()['levels'];
        $count  = $configuration['count'] ?? count($levels);
        $canonical_json = self::e(json_encode(self::getCanonicalLevels(), JSON_UNESCAPED_UNICODE));

        $html  = '<div class="form-field row"><label class="col-form-label col-xxl-4">';
        $html .= __('Quantidade de níveis', 'npsphere');
        $html .= '</label><div class="col-xxl-8">';
        $html .= '<select name="emoticon_count" class="form-select" style="max-width:10rem">';
        foreach ([3, 5] as $option) {
            $html .= '<option value="' . $option . '"' . ($count == $option ? ' selected' : '') . '>' . $option . '</option>';
        }
        $html .= '</select></div></div>';

        $html .= '<div class="form-field row"><label class="col-form-label col-xxl-4">';
        $html .= __('Emoji e descrição', 'npsphere');
        $html .= '</label><div class="col-xxl-8">';
        $html .= '<div class="npsphere-emoticon-levels-editor" data-canonical-levels="' . $canonical_json . '">';
        foreach ($levels as $level) {
            $html .= '<div class="input-group mb-1 npsphere-emoticon-level-row">';
            $html .= '<input type="text" class="form-control" style="max-width:5rem" name="emoticon_emoji[]" '
                . 'value="' . self::e($level['emoji'] ?? '') . '" maxlength="8">';
            $html .= '<input type="text" class="form-control" name="emoticon_label[]" '
                . 'value="' . self::e($level['label'] ?? '') . '" placeholder="' . __('Descrição textual (acessibilidade)', 'npsphere') . '">';
            $html .= '</div>';
        }
        $html .= '</div>';
        $html .= '<div class="form-text">' . __('Cada emoji deve ter uma descrição textual — obrigatório para acessibilidade.', 'npsphere') . '</div>';
        $html .= '</div></div>';
        return $html;
    }

    public static function renderAnswerField(string $field_name, array $configuration, $current_value = null): string
    {
        $levels = $configuration['levels'] ?? self::getDefaultConfiguration()['levels'];

        $html = '<div class="npsphere-emoticon-scale" role="radiogroup">';
        foreach ($levels as $level) {
            $value = (string) ($level['value'] ?? '');
            $id = $field_name . '_' . $value;
            $checked = ((string) $current_value === $value) ? ' checked' : '';
            $html .= '<label class="npsphere-emoticon-option" for="' . $id . '">';
            $html .= '<input type="radio" id="' . $id . '" name="' . self::e($field_name) . '" value="' . self::e($value) . '"' . $checked . '>';
            $html .= '<span class="npsphere-emoji" aria-hidden="true">' . self::e($level['emoji'] ?? '') . '</span>';
            $html .= '<span class="npsphere-emoji-label">' . self::e($level['label'] ?? '') . '</span>';
            $html .= '</label>';
        }
        $html .= '</div>';
        return $html;
    }

    public static function validateAnswerValue($raw_value, array $configuration): array
    {
        if ($raw_value === null || $raw_value === '') {
            return self::invalid(__('Selecione uma opção.', 'npsphere'));
        }
        $levels = $configuration['levels'] ?? self::getDefaultConfiguration()['levels'];
        $allowed_values = array_map(static fn ($l) => (string) ($l['value'] ?? ''), $levels);
        if (!in_array((string) $raw_value, $allowed_values, true)) {
            return self::invalid(__('Opção inválida.', 'npsphere'));
        }
        return self::valid(['numeric_value' => (int) $raw_value]);
    }

    public static function getConditionChoices(array $configuration): ?array
    {
        $levels = $configuration['levels'] ?? self::getDefaultConfiguration()['levels'];
        $out = [];
        foreach ($levels as $level) {
            $value = (string) ($level['value'] ?? '');
            $out[$value] = trim(($level['emoji'] ?? '') . ' ' . ($level['label'] ?? ''));
        }
        return $out;
    }
}
