<?php

namespace alcamo\pwa;

class AccountAccessor extends AbstractTableAccessor
{
    public const RECORD_CLASS = AccountRecord::class;

    public const TABLE_NAME = 'account';

    public const ADD_STMT =
        "INSERT INTO %s(username, created, modified)\n"
        . "  VALUES(?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";

    private $addStmt_; ///< Statement

    public function add($username): void
    {
        if (!isset($this->addStmt_)) {
            $this->addStmt_ = $this->prepare(
                sprintf(static::ADD_STMT, $this->tableName_)
            );
        }

        $this->addStmt_->execute([ $username ]);
    }
}
