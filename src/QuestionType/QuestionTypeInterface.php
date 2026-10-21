<?php

namespace GlpiPlugin\Npsphere\QuestionType;

/**
 * Contrato que todo tipo de pergunta do NPSphere deve implementar.
 *
 * Cada tipo concentra sua própria lógica de:
 *  - Renderização (formulário de configuração no builder administrativo,
 *    e o próprio campo de resposta exibido ao respondente);
 *  - Validação (sanitização da configuração armazenada em JSON, e do
 *    valor de resposta recebido do respondente -- sempre no servidor);
 *  - Apresentação (rótulo, ícone).
 */
interface QuestionTypeInterface
{
    /**
     * Chave técnica única do tipo (armazenada na coluna `type`).
     */
    public static function getKey(): string;

    /**
     * Rótulo traduzido exibido ao administrador.
     */
    public static function getLabel(): string;

    /**
     * Ícone (tabler-icons) representando o tipo no builder.
     */
    public static function getIcon(): string;

    /**
     * Configuração padrão (estrutura mínima) quando uma pergunta deste
     * tipo é criada pela primeira vez.
     *
     * @return array
     */
    public static function getDefaultConfiguration(): array;

    /**
     * Sanitiza/valida a configuração recebida do formulário administrativo
     * antes de ser persistida (como JSON) na coluna `configuration`.
     *
     * @param array $input Dados brutos recebidos do POST
     *
     * @return array Configuração validada, pronta para json_encode()
     */
    public static function validateConfiguration(array $input): array;

    /**
     * HTML dos campos específicos de configuração exibidos no formulário
     * de edição da pergunta (builder administrativo).
     *
     * @param array $configuration Configuração atual (já decodificada)
     *
     * @return string HTML
     */
    public static function renderConfigForm(array $configuration): string;

    /**
     * HTML do campo de resposta exibido ao respondente.
     * Implementação plena prevista para a Fase 3 (formulário público de
     * resposta); disponibilizado aqui para manter a arquitetura extensível
     * descrita na seção 48 do escopo funcional.
     *
     * @param string $field_name    Nome do campo HTML (ex.: "answer_12")
     * @param array  $configuration Configuração da pergunta
     * @param mixed  $current_value Valor atualmente preenchido, se houver
     *
     * @return string HTML
     */
    public static function renderAnswerField(string $field_name, array $configuration, $current_value = null): string;

    /**
     * Valida e normaliza o valor bruto enviado pelo respondente (sempre
     * no servidor -- nunca confiar somente na validação JavaScript).
     *
     * @param mixed $raw_value     Valor recebido via $_POST para esta pergunta
     * @param array $configuration Configuração da pergunta
     *
     * @return array{
     *     valid: bool,
     *     error: string|null,
     *     numeric_value: float|null,
     *     text_value: string|null,
     *     option_value: string|null,
     * }
     */
    public static function validateAnswerValue($raw_value, array $configuration): array;
}
