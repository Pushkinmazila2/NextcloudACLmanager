<?php

declare(strict_types=1);

namespace OCA\NcAclManager\Settings;

use OCA\NcAclManager\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IL10N;
use OCP\Settings\ISettings;
use OCP\Util;

class AdminSettings implements ISettings
{
    public function __construct(
        private readonly IL10N $l,
    ) {}

    public function getForm(): TemplateResponse
    {
        Util::addScript(Application::APP_ID, 'ncaclmanager-settings');
        Util::addStyle(Application::APP_ID, 'ncaclmanager');

        return new TemplateResponse(Application::APP_ID, 'admin', []);
    }

    public function getSection(): string
    {
        return Application::APP_ID;
    }

    public function getPriority(): int
    {
        return 10;
    }
}
