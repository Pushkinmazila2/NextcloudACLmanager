<?php

declare(strict_types=1);

namespace OCA\NcAclManager\AppInfo;

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\NcAclManager\Listener\LoadFilesScriptListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\EventDispatcher\IEventDispatcher;

class Application extends App implements IBootstrap
{
    public const APP_ID = 'ncaclmanager';

    public function __construct()
    {
        parent::__construct(self::APP_ID);
    }

    public function register(IRegistrationContext $context): void
    {
        $context->registerEventListener(
            LoadAdditionalScriptsEvent::class,
            LoadFilesScriptListener::class
        );
    }

    public function boot(IBootContext $context): void
    {
        // ничего дополнительного при старте не нужно
    }
}
