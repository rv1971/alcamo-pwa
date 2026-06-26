<?php

namespace alcamo\pwa;

use alcamo\dao\AbstractFileBasedInstaller;

class Installer extends AbstractFileBasedInstaller
{
    public const SCRIPT_DIR = __DIR__ . DIRECTORY_SEPARATOR . '..'
        . DIRECTORY_SEPARATOR . 'sql';

    public const SCRIPT_FILE_LISTS = [
        '*' => [ 'account.sql', 'open_inst.sql', 'inst.sql' ]
    ];
}
