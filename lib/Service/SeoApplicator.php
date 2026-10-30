<?php

namespace Atmosfera\SeoFilter\Service;

use Atmosfera\SeoFilter\Dto\SeoRule;
use Atmosfera\SeoFilter\Repository\RuleRepository;

final class SeoApplicator
{
    private static ?SeoRule $current = null;
    private static bool $chainApplied = false;
    private static bool $viewApplied = false;

    public static function getCurrent(): ?SeoRule
    {
        return self::$current;
    }

    public function resolveRule(): ?SeoRule
    {
        if (self::$current !== null) {
            return self::$current;
        }

        if (RequestGuard::isAdmin()) {
            return null;
        }

        $rule = UrlRewriter::getActiveRule();
        if ($rule === null) {
            $rule = (new RuleRepository())->findByAnyUrl(UrlNormalizer::currentPath());
        }
        if ($rule === null) {
            return null;
        }

        self::$current = $rule;

        return self::$current;
    }

    /** Early: texts for templates (before catalog body). */
    public function applyViewContent(): void
    {
        if (RequestGuard::isAdmin() || self::$viewApplied) {
            return;
        }

        // Do NOT restorePublicUrl here — catalog SEF must keep internal
        // /catalog/ + SMART_FILTER_PATH until the component renders the listing.
        // Public NEW_URL is restored on Epilog / FilterUrlMapper.
        $rule = $this->resolveRule();
        if ($rule === null) {
            return;
        }

        self::$viewApplied = true;
        global $APPLICATION;

        [$top, $bottom] = $this->buildTextBlocks($rule);

        $APPLICATION->__view['SEO_DESCRIPTION_TOP'] = [];
        $APPLICATION->__view['top_desc'] = [];
        $APPLICATION->__view['SEO_DESCRIPTION_BOTTOM'] = [];
        $APPLICATION->__view['bottom_desc'] = [];

        $APPLICATION->AddViewContent('SEO_DESCRIPTION_TOP', $top);
        $APPLICATION->AddViewContent('top_desc', $top);
        $APPLICATION->AddViewContent('SEO_DESCRIPTION_BOTTOM', $bottom);
        $APPLICATION->AddViewContent('bottom_desc', $bottom);
    }

    /**
     * Late (OnEpilog): title/meta after catalog/index.php SetTitle('').
     */
    public function applyDocumentMeta(): void
    {
        if (RequestGuard::isAdmin()) {
            return;
        }

        UrlRewriter::restorePublicUrl();
        $rule = $this->resolveRule();
        if ($rule === null) {
            return;
        }

        global $APPLICATION;

        $title = Sanitizer::plainText($rule->seoTitle);
        $h1 = Sanitizer::plainText($rule->seoH1);

        if ($title !== '') {
            $APPLICATION->SetPageProperty('title', $title);
            // <title> in header uses ShowTitle() → SetTitle, not only page property
            if ($h1 === '') {
                $APPLICATION->SetTitle($title, false);
            }
        }

        if ($h1 !== '') {
            $APPLICATION->SetTitle($h1, false);
            if ($title === '') {
                $APPLICATION->SetPageProperty('title', $h1);
            }
        }

        $keywords = Sanitizer::plainText($rule->seoKeywords);
        if ($keywords !== '') {
            $APPLICATION->SetPageProperty('keywords', $keywords);
        }

        $description = Sanitizer::plainText($rule->seoDescription);
        if ($description !== '') {
            $APPLICATION->SetPageProperty('description', $description);
        }

        if ($rule->robotsXmlId !== '') {
            $APPLICATION->SetPageProperty('robots', str_replace('_', ', ', $rule->robotsXmlId));
        }

        if (!self::$chainApplied) {
            self::$chainApplied = true;
            $chain = [];
            if ($rule->parentChainId > 0) {
                $chain = (new RuleRepository())->getChain($rule->parentChainId, $rule->iblockId);
            }
            if ($h1 !== '' && $rule->addToChain) {
                $chain[] = [
                    'NAME' => $h1,
                    'URL' => $rule->newUrl,
                ];
            }
            foreach ($chain as $item) {
                $APPLICATION->AddChainItem($item['NAME'], $item['URL']);
            }
        }
    }

    /** @deprecated use applyViewContent + applyDocumentMeta */
    public function apply(bool $force = false): void
    {
        $this->applyViewContent();
        $this->applyDocumentMeta();
    }

    /**
     * @return array{h1:string, top:string, bottom:string}
     */
    public static function getBlocks(): array
    {
        $self = new self();
        $rule = $self->resolveRule();
        if ($rule === null) {
            return ['h1' => '', 'top' => '', 'bottom' => ''];
        }

        [$top, $bottom] = $self->buildTextBlocks($rule);

        return [
            'h1' => Sanitizer::plainText($rule->seoH1),
            'top' => $top,
            'bottom' => $bottom,
        ];
    }

    /** @return array{0:string,1:string} */
    private function buildTextBlocks(SeoRule $rule): array
    {
        $isFirstPage = true;
        if (isset($_SERVER['OLD_GET']['PAGEN_1']) && (int)$_SERVER['OLD_GET']['PAGEN_1'] > 1) {
            $isFirstPage = false;
        } elseif (isset($_GET['PAGEN_1']) && (int)$_GET['PAGEN_1'] > 1) {
            $isFirstPage = false;
        }

        $top = '';
        $bottom = '';
        if ($isFirstPage) {
            if ($rule->previewText !== '' && !Sanitizer::isVisuallyEmpty($rule->previewText)) {
                $top = Sanitizer::seoHtml($rule->previewText);
                if ($top === '') {
                    $plain = Sanitizer::plainText($rule->previewText);
                    if ($plain !== '') {
                        $top = '<p>' . $plain . '</p>';
                    }
                }
            }
            if ($rule->detailText !== '' && !Sanitizer::isVisuallyEmpty($rule->detailText)) {
                $bottom = Sanitizer::seoHtml($rule->detailText);
                if ($bottom === '') {
                    $plain = Sanitizer::plainText($rule->detailText);
                    if ($plain !== '') {
                        $bottom = '<p>' . $plain . '</p>';
                    }
                }
            }
        }

        return [$top, $bottom];
    }

    public function replacePlaceholders(string $content): string
    {
        $rule = self::$current;
        if ($rule === null) {
            return $content;
        }

        $replace = [];
        $search = [];

        foreach ($rule->fields as $code => $value) {
            if ($code === 'PROPERTIES' || is_array($value)) {
                continue;
            }
            $replace['#ATMOSFERA_SEOFILTER_' . $code . '#'] = Sanitizer::plainText((string)$value);
            $replace['#~ATMOSFERA_SEOFILTER_' . $code . '#'] = Sanitizer::html((string)$value);
            $search[] = '#ATMOSFERA_SEOFILTER_' . $code . '#';
            $search[] = '#~ATMOSFERA_SEOFILTER_' . $code . '#';
        }

        foreach ($rule->clearedProperties as $code => $value) {
            if (mb_strpos((string)$code, '~') === 0) {
                continue;
            }
            $plain = is_array($value)
                ? Sanitizer::propertyValue($value)
                : (string)$value;
            $replace['#ATMOSFERA_SEOFILTER_PROPERTY_' . $code . '#'] = Sanitizer::plainText($plain);
            $replace['#~ATMOSFERA_SEOFILTER_PROPERTY_' . $code . '#'] = Sanitizer::html($plain);
            $search[] = '#ATMOSFERA_SEOFILTER_PROPERTY_' . $code . '#';
            $search[] = '#~ATMOSFERA_SEOFILTER_PROPERTY_' . $code . '#';
        }

        if ($search !== []) {
            $content = str_replace($search, $replace, $content);
        }

        return preg_replace('/#ATMOSFERA_SEOFILTER_[^#]+#/', '', $content) ?? $content;
    }
}
