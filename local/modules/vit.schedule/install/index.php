<?php

use Bitrix\Main\Application;
use Bitrix\Main\ModuleManager;

class vit_schedule extends CModule
{
    public $MODULE_ID = 'vit.schedule';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;

    public function __construct()
    {
        $this->MODULE_VERSION = '1.0.0';
        $this->MODULE_VERSION_DATE = '2026-04-01';
        $this->MODULE_NAME = 'Расписание врача';
        $this->MODULE_DESCRIPTION = 'Управление рабочим расписанием врача';
    }

    public function DoInstall(): void
    {
        ModuleManager::registerModule($this->MODULE_ID);
        $this->InstallDB();
        $this->InstallFiles();
    }

    public function DoUninstall(): void
    {
        $this->UnInstallDB();
        $this->UnInstallFiles();
        ModuleManager::unRegisterModule($this->MODULE_ID);
    }

    public function InstallDB(): void
    {
        $connection = Application::getConnection();
        $sqlFile = __DIR__ . '/db/install.sql';
        $sql = file_get_contents($sqlFile);

        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            fn(string $s) => $s !== ''
        );

        foreach ($statements as $statement) {
            $connection->queryExecute($statement);
        }
    }

    public function UnInstallDB(): void
    {
        $connection = Application::getConnection();
        $sqlFile = __DIR__ . '/db/uninstall.sql';
        $sql = file_get_contents($sqlFile);
        $connection->queryExecute(trim($sql, "; \n\r\t"));
    }

    public function InstallFiles(): void
    {
        CopyDirFiles(
            __DIR__ . '/../admin',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin',
            true,
            true
        );
    }

    public function UnInstallFiles(): void
    {
        DeleteDirFiles(
            __DIR__ . '/../admin',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin'
        );
    }
}
