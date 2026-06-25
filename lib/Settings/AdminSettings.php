<?php

declare(strict_types=1);

namespace OCA\NcAclManager\Settings;

use OCA\NcAclManager\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\Settings\ISettings;

class AdminSettings implements ISettings
{
    public function __construct(
        private readonly IConfig $config,
        private readonly IL10N   $l,
    ) {}

    public function getForm(): TemplateResponse
    {
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
