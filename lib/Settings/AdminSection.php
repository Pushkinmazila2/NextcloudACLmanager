<?php

declare(strict_types=1);

namespace OCA\NcAclManager\Settings;

use OCA\NcAclManager\AppInfo\Application;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class AdminSection implements IIconSection
{
    public function __construct(
        private readonly IL10N         $l,
        private readonly IURLGenerator $urlGenerator,
    ) {}

    public function getID(): string
    {
        return Application::APP_ID;
    }

    public function getName(): string
    {
        return $this->l->t('ACL Manager');
    }

    public function getPriority(): int
    {
        return 75;
    }

    public function getIcon(): string
    {
        return $this->urlGenerator->imagePath(Application::APP_ID, 'lock.svg');
    }
}
