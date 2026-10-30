<?php

namespace CustomAI\SeoFilter\Service;

use CustomAI\SeoFilter\Repository\RuleRepository;

final class RedirectService
{
    public function handle(): bool
    {
        if (RequestGuard::shouldSkipRewrite()) {
            return false;
        }

        // Internal NEW→OLD rewrite already happened this request — do not bounce back
        if (UrlRewriter::wasApplied()) {
            return false;
        }

        $path = UrlNormalizer::currentPath();
        $rule = (new RuleRepository())->findByAnyUrl($path);
        if ($rule === null || $rule->newUrl === '') {
            return false;
        }

        // Already on pretty URL — nothing to do
        if (UrlNormalizer::pathsEqual($rule->newUrl, $path)) {
            return false;
        }

        $isOld = UrlNormalizer::sameSmartFilterPath($rule->oldUrl, $path)
            || UrlNormalizer::pathsEqual($rule->oldUrl, $path);
        if (!$isOld) {
            foreach ($rule->otherUrls as $other) {
                if (UrlNormalizer::sameSmartFilterPath($other, $path)
                    || UrlNormalizer::pathsEqual($other, $path)
                ) {
                    $isOld = true;
                    break;
                }
            }
        }

        if (!$isOld) {
            return false;
        }

        if (!UrlNormalizer::isSafeLocalRedirect($rule->newUrl)) {
            return false;
        }

        $redirectCode = $rule->redirectCode > 0 ? $rule->redirectCode : 301;

        $target = UrlNormalizer::encodePath($rule->newUrl);
        $queryParams = $_GET ?? [];
        unset(
            $queryParams['clear_cache'],
            $queryParams['ajax'],
            $queryParams['bxajaxid'],
            $queryParams['SMART_FILTER_PATH'],
            $queryParams['set_filter'],
            $queryParams['del_filter']
        );

        foreach (array_keys($queryParams) as $key) {
            if (!is_string($key) || strpbrk($key, '/') !== false) {
                unset($queryParams[$key]);
            }
        }

        if ($query = http_build_query($queryParams)) {
            $target .= (mb_strpos($target, '?') === false ? '?' : '&') . $query;
        }

        LocalRedirect($target, true, (string)$redirectCode);

        return true;
    }
}
