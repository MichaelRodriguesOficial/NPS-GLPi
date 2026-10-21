<?php

namespace GlpiPlugin\Npsphere\QuestionType;

class Nps extends AbstractQuestionType
{
    public static function getKey(): string
    {
        return 'nps';
    }

    public static function getLabel(): string
    {
        return __('NPS', 'npsphere');
    }

    public static function getIcon(): string
    {
        return 'ti ti-gauge';
    }

    public static function getDefaultConfiguration(): array
    {
        return ['presentation' => 'numbers', 'scale' => 10];
    }

    public static function validateConfiguration(array $input): array
    {
        $presentation = $input['nps_presentation'] ?? 'numbers';
        if (!in_array($presentation, ['numbers', 'scale'], true)) {
            $presentation = 'numbers';
        }

        $scale = (int) ($input['nps_scale'] ?? 10);
        if (!in_array($scale, [5, 10], true)) {
            $scale = 10;
        }

        return ['presentation' => $presentation, 'scale' => $scale];
    }

    public static function renderConfigForm(array $configuration): string
    {
        $presentation = $configuration['presentation'] ?? 'numbers';
        $scale = (int) ($configuration['scale'] ?? 10);

        $html  = '<div class="form-field row">';
        $html .= '<label class="col-form-label col-xxl-4">' . __('Escala', 'npsphere') . '</label>';
        $html .= '<div class="col-xxl-8">';
        $html .= '<select name="nps_scale" class="form-select" style="max-width:10rem">';
        $html .= '<option value="10"' . ($scale === 10 ? ' selected' : '') . '>' . __('0 a 10 (padrão)', 'npsphere') . '</option>';
        $html .= '<option value="5"' . ($scale === 5 ? ' selected' : '') . '>' . __('0 a 5', 'npsphere') . '</option>';
        $html .= '</select>';
        $html .= '<div class="form-text">'
            . __('O NPS "oficial" é sempre calculado em 0–10. Escolher 0–5 é uma variação simplificada: a classificação Detrator/Neutro/Promotor é reproporcionada automaticamente para essa escala.', 'npsphere')
            . '</div>';
        $html .= '</div></div>';

        $html .= '<div class="form-field row">';
        $html .= '<label class="col-form-label col-xxl-4">' . __('Apresentação', 'npsphere') . '</label>';
        $html .= '<div class="col-xxl-8">';
        $html .= '<select name="nps_presentation" class="form-select">';
        $html .= '<option value="numbers"' . ($presentation === 'numbers' ? ' selected' : '') . '>' . __('Números (caixas individuais)', 'npsphere') . '</option>';
        $html .= '<option value="scale"' . ($presentation === 'scale' ? ' selected' : '') . '>' . __('Escala visual (barra colorida)', 'npsphere') . '</option>';
        $html .= '</select>';
        $html .= '</div></div>';
        return $html;
    }

    public static function renderAnswerField(string $field_name, array $configuration, $current_value = null): string
    {
        $scale = (int) ($configuration['scale'] ?? 10);
        $presentation = $configuration['presentation'] ?? 'numbers';

        $html = $presentation === 'scale'
            ? self::renderVisualScale($field_name, $scale, $current_value)
            : self::renderNumberBoxes($field_name, $scale, $current_value);

        $html .= '<div class="npsphere-nps-labels d-flex justify-content-between">';
        $html .= '<small>' . __('Nada provável', 'npsphere') . '</small>';
        $html .= '<small>' . __('Extremamente provável', 'npsphere') . '</small>';
        $html .= '</div>';
        return $html;
    }

    /**
     * Apresentação "Números": uma caixa individual e clicável para cada nota.
     */
    private static function renderNumberBoxes(string $field_name, int $scale, $current_value): string
    {
        $html = '<div class="npsphere-nps-scale" role="radiogroup">';
        for ($i = 0; $i <= $scale; $i++) {
            $checked = ((string) $current_value === (string) $i) ? ' checked' : '';
            $id = $field_name . '_' . $i;
            $html .= '<label class="npsphere-nps-option" for="' . $id . '">';
            $html .= '<input type="radio" id="' . $id . '" name="' . self::e($field_name) . '" value="' . $i . '"' . $checked . '>';
            $html .= '<span>' . $i . '</span>';
            $html .= '</label>';
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Apresentação "Escala visual": uma barra contínua com um segmento
     * colorido por nota (vermelho=Detrator, laranja=Neutro,
     * verde=Promotor) -- visualmente bem distinta da versão em números.
     */
    private static function renderVisualScale(string $field_name, int $scale, $current_value): string
    {
        $html = '<div class="npsphere-nps-visual-scale" role="radiogroup">';
        for ($i = 0; $i <= $scale; $i++) {
            $checked = ((string) $current_value === (string) $i) ? ' checked' : '';
            $id = $field_name . '_' . $i;
            $zone = self::classify($i, $scale);
            $html .= '<input type="radio" id="' . $id . '" name="' . self::e($field_name) . '" value="' . $i . '"' . $checked . '>';
            $html .= '<label class="npsphere-nps-visual-segment npsphere-nps-zone-' . $zone . '" for="' . $id . '" title="' . $i . '">' . $i . '</label>';
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Classificação Detrator/Neutro/Promotor, reproporcionada para a
     * escala configurada (0–10 é a proporção "oficial": 0–6/7–8/9–10).
     * Pública porque o motor de métricas (Fase 4) reutiliza exatamente
     * esta mesma regra -- classificar em dois lugares diferentes seria
     * garantia de divergência futura.
     */
    public static function classify(int $value, int $scale): string
    {
        if ($scale <= 0) {
            return 'detractor';
        }
        $ratio = $value / $scale;
        if ($ratio >= 0.9) {
            return 'promoter';
        }
        if ($ratio >= 0.7) {
            return 'passive';
        }
        return 'detractor';
    }

    public static function validateAnswerValue($raw_value, array $configuration): array
    {
        $scale = (int) ($configuration['scale'] ?? 10);

        if ($raw_value === null || $raw_value === '') {
            return self::invalid(sprintf(__('Selecione uma nota de 0 a %d.', 'npsphere'), $scale));
        }
        if (!is_numeric($raw_value)) {
            return self::invalid(__('Nota inválida.', 'npsphere'));
        }
        $value = (int) $raw_value;
        if ($value < 0 || $value > $scale) {
            return self::invalid(sprintf(__('A nota deve estar entre 0 e %d.', 'npsphere'), $scale));
        }
        return self::valid(['numeric_value' => $value]);
    }

    public static function getConditionChoices(array $configuration): ?array
    {
        $scale = (int) ($configuration['scale'] ?? 10);
        $out = [];
        for ($i = 0; $i <= $scale; $i++) {
            $out[(string) $i] = (string) $i;
        }
        return $out;
    }
}
