<?php
declare(strict_types=1);

namespace App\Domain\Service;

/**
 * Normaliza códigos de idioma para o padrão BCP-47.
 *
 * Aceita códigos ISO 639-2 (3 letras — usado pela Open Library: "por", "eng", ...)
 * e ISO 639-1 (2 letras — usado pelo Google Books: "pt", "en", ...).
 *
 * - Para "por" usamos "pt-BR" porque o catálogo Hyb é brasileiro e essa é a
 *   variante que faz sentido como default.
 * - Para entradas já normalizadas (ex.: "pt-BR", "en-US") ou desconhecidas,
 *   devolve a entrada original sem alteração (apenas trim).
 * - case-insensitive na chave (mas devolve a forma canônica esperada).
 */
final class IdiomaNormalizer
{
    /** @var array<string,string> chave em lowercase → valor canônico */
    private const TABELA = [
        // ISO 639-2 (Open Library)
        'por' => 'pt-BR',
        'eng' => 'en',
        'spa' => 'es',
        'ita' => 'it',
        'fre' => 'fr',
        'fra' => 'fr',  // alias
        'ger' => 'de',
        'deu' => 'de',  // alias
        'jpn' => 'ja',
        'chi' => 'zh',
        'zho' => 'zh',  // alias
        'rus' => 'ru',
        'ara' => 'ar',
        // ISO 639-1 (Google Books) — normalizamos para minúsculas/canônico
        'pt'  => 'pt-BR',
        'en'  => 'en',
        'es'  => 'es',
        'it'  => 'it',
        'fr'  => 'fr',
        'de'  => 'de',
        'ja'  => 'ja',
        'zh'  => 'zh',
        'ru'  => 'ru',
        'ar'  => 'ar',
    ];

    /**
     * @param string|null $input código bruto retornado pelo provider
     * @return string|null código BCP-47 canônico, ou o próprio input quando
     *                     já vem em forma normalizada/desconhecida.
     */
    public function normalizar(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }
        $trim = trim($input);
        if ($trim === '') {
            return null;
        }
        $chave = strtolower($trim);
        return self::TABELA[$chave] ?? $trim;
    }
}
