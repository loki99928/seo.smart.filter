<?php

namespace Atmosfera\SeoFilter\Dto;

final class SeoRule
{
    public int $id = 0;
    public int $iblockId = 0;
    public string $name = '';
    public string $oldUrl = '';
    public string $newUrl = '';
    /** @var list<string> */
    public array $otherUrls = [];
    public int $redirectCode = 0;
    public string $robotsXmlId = '';
    public string $seoH1 = '';
    public string $seoTitle = '';
    public string $seoKeywords = '';
    public string $seoDescription = '';
    public string $previewText = '';
    public string $detailText = '';
    public bool $textsEnsured = false;
    public bool $addToChain = false;
    public bool $addToSitemap = false;
    public bool $searchIndex = false;
    public int $parentChainId = 0;
    /** @var array<string, mixed> */
    public array $fields = [];
    /** @var array<string, mixed> */
    public array $clearedProperties = [];
}
