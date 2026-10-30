<?php

namespace CustomAI\SeoFilter\Service;

use CustomAI\SeoFilter\Repository\RuleRepository;

final class FilterUrlMapper
{
    public function map(array $arParams, array &$arResult): void
    {
        $clearPath = '/filter/clear/apply/';
        foreach (['FILTER_URL', 'FILTER_AJAX_URL', 'SEF_SET_FILTER_URL', 'SEF_DEL_FILTER_URL', 'FORM_ACTION'] as $key) {
            if (!empty($arResult[$key]) && is_string($arResult[$key])) {
                $arResult[$key] = str_replace($clearPath, '/', $arResult[$key]);
            }
        }

        if (!empty($arResult['JS_FILTER_PARAMS']) && is_array($arResult['JS_FILTER_PARAMS'])) {
            foreach (['SEF_SET_FILTER_URL', 'SEF_DEL_FILTER_URL'] as $key) {
                if (!empty($arResult['JS_FILTER_PARAMS'][$key])) {
                    $arResult['JS_FILTER_PARAMS'][$key] = str_replace(
                        $clearPath,
                        '/',
                        $arResult['JS_FILTER_PARAMS'][$key]
                    );
                }
            }
            $arResult['JS_FILTER_PARAMS']['FOLDER'] = $arParams['FOLDER'] ?? '';
            $arResult['JS_FILTER_PARAMS']['FILTER_NAME'] = $arParams['FILTER_NAME'] ?? '';
        }

        // AJAX must stay on technical filter URL: RequestGuard skips NEW→OLD rewrite on XHR,
        // so posting to pretty NEW_URL loses SMART_FILTER_PATH and breaks the filter.
        $technicalAction = (string)($arResult['FORM_ACTION'] ?? '');
        if ($technicalAction !== '' && empty($arResult['FILTER_AJAX_URL'])) {
            $arResult['FILTER_AJAX_URL'] = $technicalAction;
        }

        $urlSlots = [];
        $collect = static function (string $url, array $path) use (&$urlSlots): void {
            if ($url === '') {
                return;
            }
            $urlSlots[$url][] = $path;
        };

        // Do not map FORM_ACTION / FILTER_AJAX_URL — JCSmartFilter uses them for XHR.
        $collect((string)($arResult['JS_FILTER_PARAMS']['SEF_SET_FILTER_URL'] ?? ''), ['JS_FILTER_PARAMS', 'SEF_SET_FILTER_URL']);
        $collect((string)($arResult['JS_FILTER_PARAMS']['SEF_DEL_FILTER_URL'] ?? ''), ['JS_FILTER_PARAMS', 'SEF_DEL_FILTER_URL']);
        $collect((string)($arResult['FILTER_URL'] ?? ''), ['FILTER_URL']);
        $collect((string)($arResult['SEF_SET_FILTER_URL'] ?? ''), ['SEF_SET_FILTER_URL']);
        $collect((string)($arResult['SEF_DEL_FILTER_URL'] ?? ''), ['SEF_DEL_FILTER_URL']);

        if (!empty($arResult['SELECTED']) && is_array($arResult['SELECTED'])) {
            foreach ($arResult['SELECTED'] as $key => $value) {
                if (!empty($value['URL'])) {
                    $collect((string)$value['URL'], ['SELECTED', $key, 'URL']);
                }
            }
        }

        if ($urlSlots === []) {
            return;
        }

        $slotUrls = array_keys($urlSlots);
        $rules = (new RuleRepository())->findByOldUrls($slotUrls);
        foreach ($slotUrls as $slotUrl) {
            $rule = $rules[$slotUrl] ?? null;
            if ($rule === null) {
                foreach (UrlNormalizer::variants($slotUrl) as $variant) {
                    if (isset($rules[$variant])) {
                        $rule = $rules[$variant];
                        break;
                    }
                }
            }
            if ($rule === null || $rule->newUrl === '' || empty($urlSlots[$slotUrl])) {
                continue;
            }
            if (!UrlNormalizer::isSafeLocalRedirect($rule->newUrl)) {
                continue;
            }
            foreach ($urlSlots[$slotUrl] as $path) {
                $arResult = $this->setByPath($arResult, $path, $rule->newUrl);
            }
        }
    }

    private function setByPath(array $arr, array $keys, string $value): array
    {
        $ref = &$arr;
        while ($keys !== []) {
            $key = array_shift($keys);
            if (!is_array($ref)) {
                $ref = [];
            }
            if (!array_key_exists($key, $ref)) {
                $ref[$key] = [];
            }
            $ref = &$ref[$key];
        }
        $ref = $value;

        return $arr;
    }
}
