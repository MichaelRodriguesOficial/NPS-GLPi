<?php

namespace GlpiPlugin\Npsphere\QuestionType;

/**
 * Base comum para os tipos "Múltipla escolha" (uma opção) e
 * "Múltiplas opções" (várias opções), que compartilham a mesma
 * estrutura de configuração: uma lista de opções de texto livre.
 */
abstract class AbstractOptionListType extends AbstractQuestionType
{
    public static function getDefaultConfiguration(): array
    {
        return ['options' => [__('Opção 1', 'npsphere'), __('Opção 2', 'npsphere')]];
    }

    public static function validateConfiguration(array $input): array
    {
        $raw = $input['choice_options'] ?? [];
        $options = [];
        foreach ($raw as $option) {
            $option = trim((string) $option);
            if ($option !== '') {
                $options[] = $option;
            }
        }
        if (empty($options)) {
            $options = self::getDefaultConfiguration()['options'];
        }
        return ['options' => array_values($options)];
    }

    public static function renderConfigForm(array $configuration): string
    {
        $options = $configuration['options'] ?? self::getDefaultConfiguration()['options'];

        $html  = '<div class="form-field row"><label class="col-form-label col-xxl-4">';
        $html .= __('Opções', 'npsphere');
        $html .= '</label><div class="col-xxl-8">';
        $html .= '<div class="npsphere-choice-options-editor">';
        foreach ($options as $option) {
            $html .= self::renderOptionRow($option);
        }
        $html .= '</div>';
        $html .= '<button type="button" class="btn btn-sm btn-outline-primary npsphere-add-option">';
        $html .= '<i class="ti ti-plus"></i> ' . __('Adicionar opção', 'npsphere');
        $html .= '</button>';
        $html .= '<div class="form-text">' . __('Linhas em branco são removidas ao salvar.', 'npsphere') . '</div>';
        $html .= '</div></div>';
        return $html;
    }

    /**
     * Uma linha de opção editável (usada tanto na renderização inicial
     * do PHP quanto, com o mesmo HTML, pelo JS ao clicar em "+ Adicionar
     * opção" -- ver public/js/survey-builder.js).
     */
    public static function renderOptionRow(string $value = ''): string
    {
        return '<div class="input-group mb-1 npsphere-choice-option-row">'
            . '<input type="text" class="form-control" name="choice_options[]" value="' . self::e($value) . '" placeholder="' . __('Nova opção…', 'npsphere') . '">'
            . '<button type="button" class="btn btn-outline-danger npsphere-remove-option" title="' . __('Remover', 'npsphere') . '">'
            . '<i class="ti ti-x"></i>'
            . '</button>'
            . '</div>';
    }

    /**
     * "radio" para o SingleChoice (uma resposta), "checkbox" para o
     * MultipleChoice (várias respostas).
     */
    abstract protected static function getInputType(): string;

    public static function renderAnswerField(string $field_name, array $configuration, $current_value = null): string
    {
        $options = $configuration['options'] ?? self::getDefaultConfiguration()['options'];
        $input_type = static::getInputType();
        $html_name = $input_type === 'checkbox' ? $field_name . '[]' : $field_name;

        $current_values = is_array($current_value) ? $current_value : (array) $current_value;

        $html = '<div class="npsphere-choice-options">';
        foreach ($options as $i => $option) {
            $id = $field_name . '_' . $i;
            $checked = in_array($option, $current_values, true) ? ' checked' : '';
            $html .= '<label class="npsphere-choice-option form-check">';
            $html .= '<input type="' . $input_type . '" class="form-check-input" id="' . $id . '" name="' . self::e($html_name) . '" value="' . self::e($option) . '"' . $checked . '>';
            $html .= '<span class="form-check-label">' . self::e($option) . '</span>';
            $html .= '</label>';
        }
        $html .= '</div>';
        return $html;
    }

    public static function validateAnswerValue($raw_value, array $configuration): array
    {
        $options = $configuration['options'] ?? self::getDefaultConfiguration()['options'];

        if (static::getInputType() === 'checkbox') {
            // Múltiplas opções: aceita array vazio se a pergunta não for obrigatória
            // (a obrigatoriedade em si é validada em Question/AnswerService, não aqui).
            $values = is_array($raw_value) ? $raw_value : ($raw_value === null ? [] : [$raw_value]);
            foreach ($values as $v) {
                if (!in_array($v, $options, true)) {
                    return self::invalid(__('Uma das opções selecionadas é inválida.', 'npsphere'));
                }
            }
            return self::valid(['text_value' => implode(' | ', $values)]);
        }

        // Múltipla escolha (uma opção)
        if ($raw_value === null || $raw_value === '') {
            return self::invalid(__('Selecione uma opção.', 'npsphere'));
        }
        if (!in_array($raw_value, $options, true)) {
            return self::invalid(__('Opção inválida.', 'npsphere'));
        }
        return self::valid(['option_value' => $raw_value]);
    }

    public static function getConditionChoices(array $configuration): ?array
    {
        $options = $configuration['options'] ?? self::getDefaultConfiguration()['options'];
        $out = [];
        foreach ($options as $option) {
            $out[(string) $option] = (string) $option;
        }
        return $out;
    }
}
