<?php

declare(strict_types=1);

namespace OCA\NcAclManager\AppInfo;

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\NcAclManager\Listener\LoadFilesScriptListener;
use OCA\NcAclManager\Settings\AdminSection;
use OCA\NcAclManager\Settings\AdminSettings;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Settings\IManager as ISettingsManager;

class Application extends App implements IBootstrap
{
    public const APP_ID = 'ncaclmanager';

    public function __construct()
    {
        parent::__construct(self::APP_ID);
    }

    public function register(IRegistrationContext $context): void
    {
        // Файловый менеджер — подключаем JS
        $context->registerEventListener(
            LoadAdditionalScriptsEvent::class,
            LoadFilesScriptListener::class
        );

        // Страница настроек администратора
        $context->registerSettings(AdminSettings::class);
        $context->registerSettingsSection(AdminSection::class);
    }

    public function boot(IBootContext $context): void {}
}
