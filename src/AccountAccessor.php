<?php

namespace alcamo\pwa;

use alcamo\exception\DataNotFound;

class AccountAccessor extends AbstractTableAccessor
{
    public const RECORD_CLASS = AccountRecord::class;

    public const TABLE_NAME = 'account';

    public const GET_STMT = "SELECT * FROM %s WHERE username = ?";

    public const ADD_STMT =
        "INSERT INTO %s(username, created, modified)\n"
        . "  VALUES(?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";

    public const REMOVE_STMT = "DELETE FROM %s WHERE username = ?";

    public function get($username): ?AccountRecord
    {
        foreach (
            $this->getGetStmt()->executeAndReturnSelf([ $username ]) as $record
        ) {
            return $record;
        }

        return null;
    }

    public function add($username): void
    {
        $this->getAddStmt()->execute([ $username ]);
    }

    public function remove($username): void
    {
        $stmt = $this->getRemoveStmt();

        $stmt->execute([ $username ]);

        if (!$stmt->rowCount()) {
            /** @throw alcamo::exception::DataNotFound if $username does not
             *  exist */
            throw (new DataNotFound())->setMessageContext(
                [
                    'inTable' => $this->tableName_,
                    'forKey' => $username
                ]
            );
        }
    }
}
