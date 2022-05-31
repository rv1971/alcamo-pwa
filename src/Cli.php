<?php

namespace alcamo\pwa;

class Cli extends AbstractCli
{
    public const COMMANDS = [
        'setup-database' => [
            'setupDatabase',
            [],
            [],
            'Setup the database'
        ]
    ];

    public function setupDatabase(): int
    {
        $this->accountMgr_->createTables();

        return 0;
    }
}
