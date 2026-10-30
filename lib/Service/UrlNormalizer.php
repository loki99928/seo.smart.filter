<?php

namespace CustomAI\SeoFilter\Service;

final class UrlNormalizer
{
    public static function stripIndexPhp(string $url): string
    {
        if ($url === '') {
            return $url;
        }

        $parts = explode('?', $url, 2);
        $path = preg_replace('~/index\.php$~i', '/', $parts[0]) ?? $parts[0];

        return $path . (isset($parts[1]) ? '?' . $parts[1] : '');
    }

    /**
     * Canonical path for comparisons: decode, drop index.php, unify trailing slash.
     */
    public static function canonicalize(string $url): string
    {
        $url = trim(rawurldecode((string)$url));
        if ($url === '') {
            return '';
        }

        $parts = explode('?', $url, 2);
        $path = self::stripIndexPhp($parts[0]);
        $path = '/' . trim($path, '/');
        if ($path !== '/') {
            $path .= '/';
        }

        return $path . (isset($parts[1]) && $parts[1] !== '' ? '?' . $parts[1] : '');
    }

    public static function pathsEqual(string $a, string $b): bool
    {
        return self::canonicalize(self::stripSectionBeforeFilter($a))
            === self::canonicalize(self::stripSectionBeforeFilter($b));
    }

    /**
     * Bitrix SEF `#SECTION_ID#/filter/...` yields `/catalog/0/filter/...`;
     * rules and urlrewrite use `/catalog/filter/...`. Also strip section codes
     * like `/catalog/plitka/filter/...` for matching the filter token.
     */
    public static function stripSectionBeforeFilter(string $url): string
    {
        if ($url === '' || !str_contains($url, '/filter/')) {
            return $url;
        }

        $parts = explode('?', $url, 2);
        $path = preg_replace(
            '#(/(?:catalog|collection|brands))/(?:[^/]+/)+filter/#u',
            '$1/filter/',
            $parts[0]
        ) ?? $parts[0];

        return $path . (isset($parts[1]) ? '?' . $parts[1] : '');
    }

    /** Whether both URLs refer to the same smart-filter apply path. */
    public static function sameSmartFilterPath(string $a, string $b): bool
    {
        $fa = self::extractSmartFilterPath($a);
        $fb = self::extractSmartFilterPath($b);
        if ($fa !== null && $fb !== null) {
            return $fa === $fb;
        }

        return self::pathsEqual($a, $b);
    }

    /** @return list<string> */
    public static function variants(string $url): array
    {
        $url = (string)$url;
        if ($url === '') {
            return [];
        }

        $base = self::stripIndexPhp($url);
        $noQuery = explode('?', $base, 2)[0];
        $trimmed = rtrim($noQuery, '/');
        $withSlash = $trimmed === '' ? '/' : $trimmed . '/';
        $stripped = self::stripSectionBeforeFilter($withSlash);
        $strippedTrim = rtrim($stripped, '/');

        $candidates = [
            $url,
            $base,
            $noQuery,
            $trimmed,
            $withSlash,
            $stripped,
            $strippedTrim,
            $strippedTrim === '' ? '/' : $strippedTrim . '/',
            self::canonicalize($url),
            self::canonicalize($stripped),
            rawurldecode($url),
            rawurldecode($withSlash),
            rawurldecode($stripped),
            self::encodePath($withSlash),
            self::encodePath($trimmed),
            self::encodePath($stripped),
        ];

        // /catalog/filter/X/apply/ ↔ /catalog/0/filter/X/apply/
        if (preg_match('#^(/(?:catalog|collection|brands))/filter/#u', $stripped, $m)) {
            $withZero = preg_replace('#^(/(?:catalog|collection|brands))/filter/#u', '$1/0/filter/', $stripped);
            if (is_string($withZero) && $withZero !== '') {
                $candidates[] = $withZero;
                $candidates[] = rtrim($withZero, '/');
            }
        }

        $filterPath = self::extractSmartFilterPath($url);
        if ($filterPath === null && preg_match('#(?:^|[?&])SMART_FILTER_PATH=([^&]+)#', $url, $qm)) {
            $filterPath = rawurldecode($qm[1]);
        }
        if (is_string($filterPath) && $filterPath !== '' && $filterPath !== 'clear') {
            $folder = '/catalog/';
            if (preg_match('#^/(catalog|collection|brands)/#', $stripped, $fm)) {
                $folder = '/' . $fm[1] . '/';
            }
            $candidates[] = $folder . 'filter/' . $filterPath . '/apply/';
            $candidates[] = $folder . '0/filter/' . $filterPath . '/apply/';
            $candidates[] = $folder . '?SMART_FILTER_PATH=' . $filterPath;
            $candidates[] = $folder . 'index.php?SMART_FILTER_PATH=' . $filterPath;
        }

        $out = [];
        foreach ($candidates as $candidate) {
            $candidate = (string)$candidate;
            if ($candidate !== '' && !in_array($candidate, $out, true)) {
                $out[] = $candidate;
            }
        }

        return $out;
    }

    public static function encodePath(string $url): string
    {
        if ($url === '') {
            return $url;
        }

        $qPos = mb_strpos($url, '?');
        $path = $qPos !== false ? mb_substr($url, 0, $qPos) : $url;
        $query = $qPos !== false ? mb_substr($url, $qPos) : '';

        $parts = explode('/', $path);
        foreach ($parts as $i => $part) {
            $parts[$i] = rawurlencode(rawurldecode($part));
        }

        return implode('/', $parts) . $query;
    }

    public static function isSafeLocalRedirect(string $url): bool
    {
        if ($url === '' || preg_match('/[\x00-\x1F\x7F]/', $url)) {
            return false;
        }

        if (mb_substr($url, 0, 1) !== '/') {
            return false;
        }

        if (mb_substr($url, 0, 2) === '//' || mb_substr($url, 0, 2) === '/\\') {
            return false;
        }

        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $url)) {
            return false;
        }

        return true;
    }

    /**
     * Physical /catalog/{code}/index.php landings own the page — no NEW→OLD rewrite.
     * SEO texts/meta from rules still apply via getSeoBlocks.
     */
    public static function isPhysicalCatalogLanding(string $path): bool
    {
        $path = self::canonicalize($path);
        if (!preg_match('#^/catalog/([^/]+)/$#u', $path, $m)) {
            return false;
        }

        $code = (string)$m[1];
        if ($code === '' || strcasecmp($code, 'index.php') === 0) {
            return false;
        }

        $root = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
        if ($root === '') {
            return false;
        }

        return is_file($root . '/catalog/' . $code . '/index.php');
    }

    public static function extractSmartFilterPath(string $url): ?string
    {
        $decoded = rawurldecode($url);
        if (preg_match('#/filter/(.+?)/apply/?#', $decoded, $m)) {
            return $m[1];
        }
        if (preg_match('#(?:^|[?&])SMART_FILTER_PATH=([^&]+)#', $decoded, $m)) {
            $path = rawurldecode($m[1]);
            if ($path !== '' && $path !== 'clear') {
                return $path;
            }
        }

        return null;
    }

    /**
     * catalog/index.php → atmPrepareSefFolder() strips /filter/.../apply/ from REQUEST_URI
     * and leaves only SMART_FILTER_PATH. Rebuild the public filter URL for rule matching.
     */
    public static function rebuildFilterUrlFromRequest(): ?string
    {
        $smartFilter = $_REQUEST['SMART_FILTER_PATH'] ?? $_GET['SMART_FILTER_PATH'] ?? null;
        if (!is_string($smartFilter) || $smartFilter === '' || $smartFilter === 'clear') {
            return null;
        }

        $folder = self::detectSefFolder();
        return $folder . 'filter/' . trim($smartFilter, '/') . '/apply/';
    }

    public static function detectSefFolder(): string
    {
        $candidates = [
            (string)($GLOBALS['ATM_PUBLIC_PATH'] ?? ''),
            rawurldecode((string)strtok($_SERVER['REQUEST_URI'] ?? '', '?')),
        ];

        global $APPLICATION;
        if (is_object($APPLICATION) && method_exists($APPLICATION, 'GetCurPage')) {
            $candidates[] = (string)$APPLICATION->GetCurPage(false);
        }

        foreach ($candidates as $hint) {
            if ($hint !== '' && preg_match('#^/(catalog|collection|brands)(/|$)#', $hint, $m)) {
                return '/' . $m[1] . '/';
            }
        }

        return '/catalog/';
    }

    public static function currentPath(): string
    {
        global $APPLICATION;

        $rebuilt = self::rebuildFilterUrlFromRequest();
        if ($rebuilt !== null) {
            return $rebuilt;
        }

        $requestPath = rawurldecode((string)strtok($_SERVER['REQUEST_URI'] ?? '', '?'));
        $url = is_object($APPLICATION) ? (string)$APPLICATION->GetCurPage(false) : $requestPath;

        // Prefer browser path for catalog CHPU — GetCurPage often stays on /catalog/index.php
        if (
            $requestPath !== ''
            && (
                preg_match('#/filter/.+/apply/?$#', $requestPath)
                || (
                    preg_match('#^/catalog/.+#', $requestPath)
                    && ($url === '/catalog/index.php' || $url === '/catalog/' || $url === '/catalog')
                )
            )
        ) {
            $url = $requestPath;
        }

        if ($url === '' || $url === '/catalog/index.php' || $url === '/catalog/') {
            $public = (string)($GLOBALS['ATM_PUBLIC_PATH'] ?? '');
            if ($public !== '' && $public[0] === '/') {
                $url = $public;
            }
        }

        $segments = explode('/', $url);
        foreach ($segments as $i => $segment) {
            $segments[$i] = rawurldecode($segment);
        }

        return urldecode(implode('/', $segments));
    }
}
