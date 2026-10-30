<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\EventManager;
use Bitrix\Main\Loader;

Loc::loadMessages(__FILE__);

class seo_smart_filter extends CModule
{
    public const MODULE_ID = 'seo.smart.filter';

    public $MODULE_ID = self::MODULE_ID;
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $PARTNER_NAME;
    public $PARTNER_URI;
    public $MODULE_GROUP_RIGHTS = 'N';
    public $errors = [];

    public function __construct()
    {
        $arModuleVersion = [];
        include __DIR__ . '/version.php';

        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->MODULE_NAME = Loc::getMessage('ATMOSFERA_SEOFILTER_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('ATMOSFERA_SEOFILTER_MODULE_DESC');
        $this->PARTNER_NAME = Loc::getMessage('ATMOSFERA_SEOFILTER_PARTNER_NAME');
        $this->PARTNER_URI = Loc::getMessage('ATMOSFERA_SEOFILTER_PARTNER_URI');
    }

    public function DoInstall()
    {
        global $APPLICATION;

        ModuleManager::registerModule(self::MODULE_ID);
        $this->InstallEvents();
        $this->InstallDB();
        $this->InstallAgents();

        $APPLICATION->IncludeAdminFile(
            Loc::getMessage('ATMOSFERA_SEOFILTER_INSTALL_TITLE'),
            __DIR__ . '/step.php'
        );

        return true;
    }

    public function DoUninstall()
    {
        global $APPLICATION, $step;

        $step = (int)$step;
        if ($step < 2) {
            $APPLICATION->IncludeAdminFile(
                Loc::getMessage('ATMOSFERA_SEOFILTER_UNINSTALL_TITLE'),
                __DIR__ . '/unstep.php'
            );

            return true;
        }

        $this->UnInstallDB([
            'savedata' => isset($_REQUEST['savedata']) ? (string)$_REQUEST['savedata'] : 'N',
        ]);
        $this->UnInstallEvents();
        $this->UnInstallAgents();
        ModuleManager::unRegisterModule(self::MODULE_ID);

        $APPLICATION->IncludeAdminFile(
            Loc::getMessage('ATMOSFERA_SEOFILTER_UNINSTALL_TITLE'),
            __DIR__ . '/unstep.php'
        );

        return true;
    }

    public function InstallEvents()
    {
        $em = EventManager::getInstance();
        $class = '\\Atmosfera\\SeoFilter\\Events';

        $em->registerEventHandler('main', 'OnBeforeProlog', self::MODULE_ID, $class, 'onBeforeProlog', 50);
        $em->registerEventHandler('main', 'OnEpilog', self::MODULE_ID, $class, 'onEpilog', 50);
        $em->registerEventHandler('main', 'OnEndBufferContent', self::MODULE_ID, $class, 'onEndBufferContent', 50);
        $em->registerEventHandler('main', 'OnBuildGlobalMenu', self::MODULE_ID, $class, 'onBuildGlobalMenu', 1000);

        $em->registerEventHandler('iblock', 'OnAfterIBlockElementAdd', self::MODULE_ID, $class, 'onAfterElementSave', 50);
        $em->registerEventHandler('iblock', 'OnAfterIBlockElementUpdate', self::MODULE_ID, $class, 'onAfterElementSave', 50);

        $em->registerEventHandler('search', 'BeforeIndex', self::MODULE_ID, $class, 'onBeforeSearchIndex', 50);

        return true;
    }

    public function UnInstallEvents()
    {
        $em = EventManager::getInstance();
        $class = '\\Atmosfera\\SeoFilter\\Events';

        $em->unRegisterEventHandler('main', 'OnBeforeProlog', self::MODULE_ID, $class, 'onBeforeProlog');
        $em->unRegisterEventHandler('main', 'OnProlog', self::MODULE_ID, $class, 'onProlog');
        $em->unRegisterEventHandler('main', 'OnEpilog', self::MODULE_ID, $class, 'onEpilog');
        $em->unRegisterEventHandler('main', 'OnEndBufferContent', self::MODULE_ID, $class, 'onEndBufferContent');
        $em->unRegisterEventHandler('main', 'OnBuildGlobalMenu', self::MODULE_ID, $class, 'onBuildGlobalMenu');

        $em->unRegisterEventHandler('iblock', 'OnAfterIBlockElementAdd', self::MODULE_ID, $class, 'onAfterElementSave');
        $em->unRegisterEventHandler('iblock', 'OnAfterIBlockElementUpdate', self::MODULE_ID, $class, 'onAfterElementSave');

        $em->unRegisterEventHandler('search', 'BeforeIndex', self::MODULE_ID, $class, 'onBeforeSearchIndex');
        $em->unRegisterEventHandler('search', 'OnSearchGetURL', self::MODULE_ID, $class, 'onSearchGetUrl');

        return true;
    }

    public function InstallAgents()
    {
        \CAgent::AddAgent(
            '\\Atmosfera\\SeoFilter\\Service\\SitemapService::agentGenerate();',
            self::MODULE_ID,
            'N',
            86400,
            '',
            'Y',
            '',
            100
        );

        return true;
    }

    public function UnInstallAgents()
    {
        \CAgent::RemoveModuleAgents(self::MODULE_ID);

        return true;
    }

    public function InstallDB()
    {
        if (!Loader::includeModule(self::MODULE_ID) || !Loader::includeModule('iblock')) {
            return false;
        }

        $installer = new \Atmosfera\SeoFilter\Schema\IblockInstaller();
        $installer->ensureType();
        if (!$installer->hasIblocks()) {
            $installer->createIblock();
        }

        return true;
    }

    public function UnInstallDB(array $arParams = [])
    {
        if (($arParams['savedata'] ?? 'N') === 'Y') {
            return true;
        }

        if (!Loader::includeModule('iblock')) {
            return true;
        }

        Loader::includeModule(self::MODULE_ID);
        $typeId = 'atmosfera_seofilter';
        $res = \Bitrix\Iblock\IblockTable::getList([
            'filter' => ['=IBLOCK_TYPE_ID' => $typeId],
            'select' => ['ID'],
        ]);
        while ($row = $res->fetch()) {
            \CIBlock::Delete((int)$row['ID']);
        }
        \CIBlockType::Delete($typeId);

        return true;
    }
}
