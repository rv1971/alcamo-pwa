<?php

namespace alcamo\pwa;

class AccountAccessor extends AbstractTableAccessor
{
    public const RECORD_CLASS = AccountRecord::class;

    public const TABLE_NAME = 'account';

    public const ADD_STMT =
        "INSERT INTO %s(username, created, modified)\n"
        . "  VALUES(?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";

    public function add($username): void
    {
        $this->getAddStmt()->execute([ $username ]);
    }
}
