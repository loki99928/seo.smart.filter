<?php

namespace CustomAI\SeoFilter;

use CustomAI\SeoFilter\Schema\IblockInstaller;
use CustomAI\SeoFilter\Service\FilterUrlMapper;
use CustomAI\SeoFilter\Service\LanguageConfig;
use CustomAI\SeoFilter\Service\MigrationService;
use CustomAI\SeoFilter\Service\SeoApplicator;
use CustomAI\SeoFilter\Service\SitemapService;
use CustomAI\SeoFilter\Service\UrlRewriter;

/**
 * Public facade for templates / result_modifier.
 */
final class Helper
{
    public static function restorePublicUrl(): void
    {
        UrlRewriter::restorePublicUrl();
    }

    /** @deprecated Use restorePublicUrl() */
    public static function returnUrl(): void
    {
        self::restorePublicUrl();
    }

    public static function mapSmartFilterUrls(array $arParams, array &$arResult): void
    {
        (new FilterUrlMapper())->map($arParams, $arResult);
        self::restorePublicUrl();
    }

    /** @deprecated Use mapSmartFilterUrls() */
    public static function returnNewUrl(array $arParams, array &$arResult): void
    {
        self::mapSmartFilterUrls($arParams, $arResult);
    }

    public static function createIblock(bool $withTimestamp = false): int|false
    {
        return (new IblockInstaller())->createIblock($withTimestamp);
    }

    public static function getCurrentRule(): ?Dto\SeoRule
    {
        return SeoApplicator::getCurrent();
    }

    /**
     * @return array{h1:string, top:string, bottom:string}
     */
    public static function getSeoBlocks(): array
    {
        return SeoApplicator::getBlocks();
    }

    /** Current storefront language code used for SEO texts (e.g. ru / kz). */
    public static function getCurrentLang(): string
    {
        return LanguageConfig::currentCode();
    }

    /** @return array{ok:bool, created:int, skipped:int, errors:list<string>, targetIblockId:int} */
    public static function migrateFromDwstroy(?int $targetIblockId = null): array
    {
        return (new MigrationService())->run($targetIblockId);
    }

    /** @return array{ok:bool, file:string, count:int, error?:string} */
    public static function generateSitemap(?string $domain = null, ?string $filename = null): array
    {
        return (new SitemapService())->generate($domain, $filename);
    }

    /** @return list<string> */
    public static function getSitemapPaths(?string $siteId = null): array
    {
        return (new SitemapService())->getActivePaths($siteId);
    }
}
