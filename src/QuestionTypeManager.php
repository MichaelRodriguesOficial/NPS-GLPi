<?php

namespace GlpiPlugin\Npsphere;

use GlpiPlugin\Npsphere\QuestionType\Emoticon;
use GlpiPlugin\Npsphere\QuestionType\MultipleChoice;
use GlpiPlugin\Npsphere\QuestionType\Nps;
use GlpiPlugin\Npsphere\QuestionType\QuestionTypeInterface;
use GlpiPlugin\Npsphere\QuestionType\SingleChoice;
use GlpiPlugin\Npsphere\QuestionType\Stars;
use GlpiPlugin\Npsphere\QuestionType\Text;
use GlpiPlugin\Npsphere\QuestionType\YesNo;

/**
 * Registro central dos tipos de pergunta disponíveis.
 *
 * Ponto único de extensão previsto pela seção 48 do escopo funcional:
 * novos tipos (CES, CSAT, ranking, matriz, upload, data, número, etc.)
 * poderão ser adicionados futuramente apenas registrando uma nova classe
 * aqui — sem alterar nenhuma lógica existente do builder, do formulário
 * de resposta ou do motor de regras.
 */
class QuestionTypeManager
{
    /** @var array<string, class-string<QuestionTypeInterface>>|null */
    private static ?array $types = null;

    /**
     * @return array<string, class-string<QuestionTypeInterface>> chave => FQCN
     */
    public static function getTypes(): array
    {
        if (self::$types === null) {
            self::$types = [
                Nps::getKey()            => Nps::class,
                Emoticon::getKey()       => Emoticon::class,
                Stars::getKey()          => Stars::class,
                YesNo::getKey()          => YesNo::class,
                SingleChoice::getKey()   => SingleChoice::class,
                MultipleChoice::getKey() => MultipleChoice::class,
                Text::getKey()           => Text::class,
            ];
        }

        return self::$types;
    }

    /**
     * @return array<string, string> chave => rótulo traduzido (para dropdowns)
     */
    public static function getTypesForDropdown(): array
    {
        $out = [];
        foreach (self::getTypes() as $key => $class) {
            $out[$key] = $class::getLabel();
        }
        return $out;
    }

    public static function has(string $key): bool
    {
        return isset(self::getTypes()[$key]);
    }

    /**
     * @return class-string<QuestionTypeInterface>|null
     */
    public static function get(string $key): ?string
    {
        return self::getTypes()[$key] ?? null;
    }
}
