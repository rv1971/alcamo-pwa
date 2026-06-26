<?php

namespace alcamo\pwa;

use alcamo\dao\{DbAccessor, RelationAccessor};
use alcamo\exception\DataNotFound;

class AccountAccessor extends AbstractTableAccessor
{
    public const RELATION_NAME = 'account';

    public const FETCH_CLASS = AccountRecord::class;

    public const GET_STMT = "SELECT * FROM /*_*/%s WHERE username = ?";

    public const ADD_STMT =
        "INSERT INTO /*_*/%s(username, created, modified)\n"
        . "  VALUES(?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";

    public const REMOVE_STMT = "DELETE FROM /*_*/%s WHERE username = ?";

    /**
     * @param $props array|object Properties containing
     * - `db`
     *   - `dsn`
     *   - `?string namePrefix`
     */
    public static function newFromDbAccessorAndConf(
        DbAccessor $dbAccessor,
        $conf
    ): RelationAccessor {
        return new static($dbAccessor);
    }

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
                    'inTable' => $this->relationName_,
                    'forKey' => $username
                ]
            );
        }
    }
}
