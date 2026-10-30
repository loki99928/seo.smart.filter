<?php

namespace Atmosfera\SeoFilter\Service;

use Bitrix\Main\Config\Option;

/**
 * Configured site languages for SEO descriptions (name + lang code).
 *
 * @phpstan-type LangRow array{name: string, code: string}
 */
final class LanguageConfig
{
    public const MODULE_ID = 'seo.smart.filter';
    public const OPTION_KEY = 'languages';

    /**
     * @return list<LangRow>
     */
    public static function all(): array
    {
        $raw = Option::get(self::MODULE_ID, self::OPTION_KEY, '');
        if ($raw === '') {
            return self::defaults();
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return self::defaults();
        }

        $out = self::normalizeList($decoded);

        return $out !== [] ? $out : self::defaults();
    }

    /**
     * @param list<array{name?:string,code?:string}|mixed> $rows
     * @return list<LangRow>
     */
    public static function save(array $rows): array
    {
        $normalized = self::normalizeList($rows);
        Option::set(self::MODULE_ID, self::OPTION_KEY, json_encode($normalized, JSON_UNESCAPED_UNICODE));

        return $normalized;
    }

    /**
     * @return list<LangRow>
     */
    public static function defaults(): array
    {
        return [
            ['name' => 'Русский', 'code' => 'ru'],
        ];
    }

    public static function defaultCode(): string
    {
        $all = self::all();

        return $all[0]['code'] ?? 'ru';
    }

    /**
     * Detect public storefront language the same way as site /kz/ switcher.
     * Does not require the code to be in module settings (properties still use SEO_*_{CODE}).
     */
    public static function detectPublicLang(): string
    {
        $getLang = mb_strtolower(trim((string)($_GET['lang'] ?? $_REQUEST['lang'] ?? '')));
        $atmLang = mb_strtolower(trim((string)($_GET['atm_lang'] ?? '')));
        if ($getLang === 'ru' || $atmLang === 'ru') {
            return 'ru';
        }

        if (
            $getLang === 'kz'
            || $getLang === 'kk'
            || (string)($GLOBALS['ATM_PUBLIC_LANG'] ?? '') === 'kz'
            || self::uriLooksKz()
        ) {
            return 'kz';
        }

        $globalLang = mb_strtolower(trim((string)($GLOBALS['lang'] ?? '')));
        if ($globalLang === 'kz' || $globalLang === 'kk') {
            return 'kz';
        }

        // Loc::setCurrentLang('kz') from atmApplyKzLang — do not trust site LANGUAGE_ID alone
        if (class_exists('\Bitrix\Main\Localization\Loc')) {
            try {
                $locLang = mb_strtolower((string)\Bitrix\Main\Localization\Loc::getCurrentLang());
                if ($locLang === 'kz' || $locLang === 'kk') {
                    return 'kz';
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // Session/cookie: full page after history.replaceState may lose /kz/ in URI
        $saved = mb_strtolower(trim((string)($_SESSION['CUSTOM_LANG'] ?? '')));
        if ($saved === '') {
            $saved = mb_strtolower(trim((string)($_COOKIE['CUSTOM_LANG'] ?? '')));
        }
        if ($saved === 'kz' || $saved === 'kk') {
            return 'kz';
        }

        if ($globalLang !== '') {
            return self::normalizeCode($globalLang) ?: 'ru';
        }

        $bitrixLang = defined('LANGUAGE_ID') ? mb_strtolower((string)LANGUAGE_ID) : '';
        if ($bitrixLang === 'kk' || $bitrixLang === 'kz') {
            return 'kz';
        }
        if ($bitrixLang !== '') {
            return self::normalizeCode($bitrixLang) ?: 'ru';
        }

        return 'ru';
    }

    /**
     * Property-code variants for a language (html kk ↔ module kz).
     *
     * @return list<string>
     */
    public static function codesToTry(string $code): array
    {
        $code = self::normalizeCode($code);
        if ($code === '') {
            return [];
        }
        if ($code === 'kz' || $code === 'kk') {
            return ['kz', 'kk'];
        }

        return [$code];
    }

    private static function uriLooksKz(): bool
    {
        foreach (['ATM_LANG_URI', 'REDIRECT_URL', 'REQUEST_URI', 'SCRIPT_URL'] as $key) {
            $uri = (string)($_SERVER[$key] ?? '');
            if ($uri !== '' && (str_starts_with($uri, '/kz/') || $uri === '/kz' || str_starts_with($uri, '/kz?'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Language code for SEO overlay on the public site.
     * Prefers configured spelling when present; otherwise uses detected code as-is.
     */
    public static function currentCode(): string
    {
        $detected = self::detectPublicLang();
        $detectedKey = mb_strtolower($detected);

        $configured = [];
        foreach (self::all() as $row) {
            $configured[mb_strtolower($row['code'])] = $row['code'];
        }

        if ($detectedKey === 'kk' && isset($configured['kz'])) {
            return $configured['kz'];
        }
        if (isset($configured[$detectedKey])) {
            return $configured[$detectedKey];
        }

        $normalized = self::normalizeCode($detected);

        return $normalized !== '' ? $normalized : self::defaultCode();
    }

    public static function normalizeCode(string $code): string
    {
        $code = mb_strtolower(trim($code));
        $code = preg_replace('/[^a-z0-9_]/', '', $code) ?? '';

        return mb_substr($code, 0, 10);
    }

    public static function topPropertyCode(string $langCode): string
    {
        return self::seoFieldCodes($langCode)['top'];
    }

    public static function bottomPropertyCode(string $langCode): string
    {
        return self::seoFieldCodes($langCode)['bottom'];
    }

    /**
     * Per-language SEO property codes (H1, title, meta, top/bottom texts).
     *
     * @return array{h1:string,title:string,keywords:string,description:string,top:string,bottom:string}
     */
    public static function seoFieldCodes(string $langCode): array
    {
        $suffix = mb_strtoupper(self::normalizeCode($langCode));

        return [
            'h1' => 'SEO_H1_' . $suffix,
            'title' => 'SEO_TITLE_' . $suffix,
            'keywords' => 'SEO_KEYWORDS_' . $suffix,
            'description' => 'SEO_DESCRIPTION_' . $suffix,
            'top' => 'SEO_DESC_TOP_' . $suffix,
            'bottom' => 'SEO_DESC_BOTTOM_' . $suffix,
        ];
    }

    /**
     * @return array<string, string> propertyCode => langCode
     */
    public static function allLocalizedPropertyCodes(): array
    {
        $map = [];
        foreach (self::all() as $lang) {
            foreach (self::seoFieldCodes($lang['code']) as $propCode) {
                $map[$propCode] = $lang['code'];
            }
        }

        return $map;
    }

    /**
     * @param list<mixed> $rows
     * @return list<LangRow>
     */
    private static function normalizeList(array $rows): array
    {
        $out = [];
        $seen = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = trim((string)($row['name'] ?? ''));
            $code = self::normalizeCode((string)($row['code'] ?? ''));
            if ($name === '' || $code === '') {
                continue;
            }
            if (isset($seen[$code])) {
                continue;
            }
            $seen[$code] = true;
            $out[] = ['name' => $name, 'code' => $code];
        }

        return $out;
    }
}
