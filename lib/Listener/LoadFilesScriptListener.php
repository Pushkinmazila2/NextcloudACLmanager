<?php

declare(strict_types=1);

namespace OCA\NcAclManager\Listener;

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\NcAclManager\AppInfo\Application;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

class LoadFilesScriptListener implements IEventListener
{
    public function handle(Event $event): void
    {
        if (!$event instanceof LoadAdditionalScriptsEvent) {
            return;
        }

        Util::addScript(Application::APP_ID, 'ncaclmanager-files');
        Util::addStyle(Application::APP_ID, 'ncaclmanager');
    }
}
