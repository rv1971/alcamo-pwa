<?php

namespace alcamo\pwa;

use alcamo\dao\RelationAccessor;
use alcamo\exception\DataNotFound;

/**
 * @brief Accessor for the account table
 *
 * @date last reviewed 2026-06-26
 */
class AccountAccessor extends RelationAccessor
{
    public const RELATION_NAME = 'account';

    public const FETCH_CLASS = AccountRecord::class;

    public const STMT_MAP = [
        'add'    => [
            'INSERT INTO /*_*/%s(username, created, modified) '
                . 'VALUES(?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
        ],
        'get'    => [ 'SELECT * FROM /*_*/%s WHERE username = ?' ],
        'remove' => [ 'DELETE FROM /*_*/%s WHERE username = ?' ]
    ]
    + parent::STMT_MAP;

    /// Get one account record
    public function get($username): ?AccountRecord
    {
        foreach (
            $this->getStmt('get')
                ->executeAndReturnSelf([ $username ]) as $record
        ) {
            return $record;
        }

        return null;
    }

    public function add($username): void
    {
        $this->getStmt('add')->execute([ $username ]);
    }

    public function remove($username): void
    {
        $stmt = $this->getStmt('remove')->executeAndReturnSelf([ $username ]);

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
