<?php

namespace alcamo\pwa;

use alcamo\exception\DataNotFound;

class InstAccessor extends AbstractTableAccessor
{
    public const RECORD_CLASS = InstRecord::class;

    public const TABLE_NAME = 'inst';

    public const GET_STMT = "SELECT * FROM %s WHERE inst_id = ?";

    public const GET_USER_INSTS_STMT =
        "SELECT * FROM %s WHERE username = ? order by inst_id";

    public const ADD_STMT = <<<EOD
INSERT INTO %s(
    inst_id,
    username,
    passwd_hash,
    user_agent,
    app_version,
    update_count,
    created,
    modified
)
VALUES(?, ?, ?, ?, ?, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
EOD;

    public const MODIFY_STMT = <<<EOD
UPDATE %s SET
    user_agent = ?,
    app_version = ?,
    update_count = update_count + 1,
    created = CURRENT_TIMESTAMP,
    modified = CURRENT_TIMESTAMP
WHERE inst_id = ?
EOD;

    public const REMOVE_STMT = "DELETE FROM %s WHERE inst_id = ?";

    private $passwdTransformer_; ///< PasswdTransformer

    /**
     * @param $params array or ArrayAccess object containing
     * - `db`
     *   - `connection`
     *   - `?string tablePrefix`
     * - `string passwdKey`
     */
    public static function newFromParams(
        iterable $params
    ): AbstractTableAccessor {
        return new static(
            $params['db']['connection'],
            $params['db']['tablePrefix'] ?? null,
            new PasswdTransformer($params['passwdKey'])
        );
    }

    public function __construct(
        $connection,
        ?string $tablePrefix,
        PasswdTransformer $passwdTransformer
    ) {
        parent::__construct($connection, $tablePrefix);

        $this->passwdTransformer_ = $passwdTransformer;
    }

    public function getPasswdTransformer(): PasswdTransformer
    {
        return $this->passwdTransformer_;
    }

    public function get(
        string $instId,
        ?string $username = null,
        ?string $obfuscated = null
    ): ?InstRecord {
        // loop finds at most one record
        foreach (
            $this->getGetStmt()
                ->executeAndReturnSelf([ $instId ]) as $record
        ) {
            /** Verify user and password if username is given. */
            if (isset($username)) {
                if ($record->getUsername() != $username) {
                    /** @throw alcamo::exception::DataNotFound if $username is
                     *  given but does not match */
                    throw (new DataNotFound())->setMessageContext(
                        [
                            'inTable' => $this->tableName_,
                            'forKey' => [ $instId, $username ]
                        ]
                    );
                }

                if (
                    !$this->passwdTransformer_->verifyObfuscatedPasswd(
                        $obfuscated,
                        $record->getPasswdHash()
                    )
                ) {
                    /** @throw alcamo::exception::DataNotFound if $username is
                     *  given but password does not match */
                    throw (new DataNotFound())->setMessageContext(
                        [
                            'inTable' => $this->tableName_,
                            'forKey' => [ $instId, $username, $obfuscated ]
                        ]
                    );
                }
            }

            return $record;
        };

        /** If no username is given and no record is found, return `null`
         *  without throwing. */
        return null;
    }

    public function getUserInsts(string $username): \Traversable
    {
        return $this->query(
            sprintf(static::GET_USER_INSTS_STMT, $this->tableName_),
            [ $username ]
        );
    }

    public function add(
        string $instId,
        string $username,
        string $passwdHash,
        string $userAgent,
        string $appVersion
    ): void {
        $this->getAddStmt()->execute(
            [
                $instId,
                $username,
                $passwdHash,
                $userAgent,
                $appVersion
            ]
        );
    }

    public function modify(
        string $instId,
        string $userAgent,
        string $appVersion
    ): void {
        $stmt = $this->getModifyStmt();

        $stmt->execute([ $userAgent, $appVersion, $instId ]);

        if (!$stmt->rowCount()) {
            throw (new DataNotFound())->setMessageContext(
                [
                    'inTable' => $this->tableName_,
                    'forKey' => $passwdHash
                ]
            );
        }
    }

    public function remove($instId): void
    {
        $stmt = $this->getRemoveStmt();

        $stmt->execute([ $instId ]);

        if (!$stmt->rowCount()) {
            throw (new DataNotFound())->setMessageContext(
                [
                    'inTable' => $this->tableName_,
                    'forKey' => $instId
                ]
            );
        }
    }
}
