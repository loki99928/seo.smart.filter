<?php

namespace Atmosfera\SeoFilter\Service;

final class Sanitizer
{
    public static function plainText(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $charset = defined('LANG_CHARSET') ? LANG_CHARSET : 'UTF-8';
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, $charset);
        $value = strip_tags($value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value) ?? '';
        $value = trim(preg_replace('/[[:space:]]+/', ' ', $value) ?? '');

        return function_exists('htmlspecialcharsbx')
            ? htmlspecialcharsbx($value)
            : htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, $charset);
    }

    public static function html(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (!class_exists('\CBXSanitizer')) {
            $path = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/classes/general/sanitizer.php';
            if (is_file($path)) {
                require_once $path;
            }
        }

        if (class_exists('\CBXSanitizer')) {
            $sanitizer = new \CBXSanitizer();
            $sanitizer->SetLevel(\CBXSanitizer::SECURE_LEVEL_LOW);

            return $sanitizer->SanitizeHtml($value);
        }

        return self::seoHtml($value);
    }

    /**
     * Softer HTML cleanup for SEO texts — CBXSanitizer often wipes DETAIL_TEXT from the editor.
     */
    public static function seoHtml(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $charset = defined('LANG_CHARSET') ? LANG_CHARSET : 'UTF-8';
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, $charset);
        $value = preg_replace(
            [
                '#<\s*(script|iframe|object|embed|applet|meta|base|link|form)[^>]*>.*?<\s*/\s*\1\s*>#is',
                '#<\s*(script|iframe|object|embed|applet|meta|base|link|form)[^>]*\/?\s*>#is',
                '#\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i',
                '#\s+(href|src)\s*=\s*([\'"])\s*(javascript:|data:)#i',
            ],
            '',
            $value
        ) ?? '';

        $value = trim($value);
        if ($value === '' || self::isVisuallyEmpty($value)) {
            return '';
        }

        return $value;
    }

    /** True when HTML has no readable text (only &nbsp;, <br>, empty tags). */
    public static function isVisuallyEmpty(string $html): bool
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xC2\xA0", ' ', $text); // nbsp
        $text = preg_replace('/\s+/u', '', $text) ?? '';

        return $text === '';
    }

    public static function propertyValue(mixed $raw): string
    {
        if ($raw === null || $raw === '' || $raw === false) {
            return '';
        }

        if (is_array($raw)) {
            if (isset($raw['TEXT']) && $raw['TEXT'] !== '' && $raw['TEXT'] !== null) {
                $value = (string)$raw['TEXT'];
            } elseif (isset($raw['HTML']) && $raw['HTML'] !== '' && $raw['HTML'] !== null) {
                $value = (string)$raw['HTML'];
            } else {
                return '';
            }

            if (($raw['TYPE'] ?? '') === 'HTML') {
                $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, defined('LANG_CHARSET') ? LANG_CHARSET : 'UTF-8');
            }

            return trim($value);
        }

        return (string)$raw;
    }
}
