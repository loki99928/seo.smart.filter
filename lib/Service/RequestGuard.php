<?php

namespace Atmosfera\SeoFilter\Service;

use Bitrix\Main\Application;
use Bitrix\Main\Context;

final class RequestGuard
{
    public static function isAdmin(): bool
    {
        if (defined('ADMIN_SECTION') && ADMIN_SECTION === true) {
            return true;
        }

        global $APPLICATION;
        $page = (string)$APPLICATION->GetCurPage(false);

        return mb_strpos($page, '/bitrix/') !== false;
    }

    public static function isAjax(): bool
    {
        if (!empty($_GET['bxajaxid'])) {
            return true;
        }

        $xhr = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        if (is_string($xhr) && mb_strtolower($xhr) === 'xmlhttprequest') {
            return true;
        }

        $request = Application::getInstance()->getContext()->getRequest();
        if ($request->getHeader('Bx-ajax') || $request->getHeader('BX-Ajax')) {
            return true;
        }

        return false;
    }

    public static function isPost(): bool
    {
        return Context::getCurrent()->getRequest()->isPost();
    }

    public static function shouldSkipRewrite(): bool
    {
        if (self::isAdmin() || self::isAjax() || self::isPost()) {
            return true;
        }

        if (isset($_GET['ajax']) && (string)$_GET['ajax'] === 'y') {
            return true;
        }

        return false;
    }
}
