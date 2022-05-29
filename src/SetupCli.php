<?php

namespace alcamo\pwa;

class SetupCli extends AbstractCli
{
    public const COMMANDS = [
        'database' => [
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
