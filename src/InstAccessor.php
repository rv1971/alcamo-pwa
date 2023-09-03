<?php

namespace alcamo\pwa;

use alcamo\dao\Statement;
use alcamo\exception\DataNotFound;
use alcamo\time\Duration;

class InstAccessor extends AbstractTableAccessor
{
    public const RECORD_CLASS = InstRecord::class;

    public const TABLE_NAME = 'inst';

    public const SELECT_STMT =
        'SELECT * FROM %s ORDER BY username, modified DESC LIMIT 1000';

    public const GET_STMT = 'SELECT * FROM %s WHERE inst_id = ?';

    public const GET_USER_INSTS_STMT =
        'SELECT * FROM %s WHERE username = ? ORDER BY modified';

    public const GET_USER_USER_AGENT_INSTS_STMT =
        'SELECT * FROM %s WHERE username = ? and user_agent = ? '
        . 'ORDER BY modified';

    public const ADD_STMT = <<<EOD
INSERT INTO %s(
    inst_id,
    username,
    passwd_hash,
    user_agent,
    app_version,
    launcher,
    update_count,
    created,
    modified
)
VALUES(?, ?, ?, ?, ?, ?, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
EOD;

    /** `created = created` to work around auto-updating columns in mysql. */
    public const MODIFY_STMT = <<<EOD
UPDATE %s SET
    user_agent = ?,
    launcher = ?,
    created = created,
    modified = CURRENT_TIMESTAMP
WHERE inst_id = ?
EOD;

    /** `created = created` to work around auto-updating columns in mysql. */
    public const REPLACE_INST_STMT = <<<EOD
UPDATE %s SET
    inst_id = ?,
    created = created,
    modified = CURRENT_TIMESTAMP
WHERE inst_id = ?
EOD;

    public const REMOVE_STMT = "DELETE FROM %s WHERE inst_id = ?";

    /** `created = created` to work around auto-updating columns in mysql. */
    public const UPDATE_INST_STMT = <<<EOD
UPDATE %s SET
    user_agent = ?,
    app_version = ?,
    launcher = ?,
    update_count = update_count + 1,
    created = created,
    modified = CURRENT_TIMESTAMP
WHERE inst_id = ?
EOD;

    private $passwdTransformer_;     ///< PasswdTransformer
    private $minReplaceableInstAge_; ///< ?Duration

    /**
     * @param $conf array or ArrayAccess object containing
     * - `db`
     *   - `connection`
     *   - `?string tablePrefix`
     * - `string passwdKey`
     * - optional `string minReplaceableInstAge`
     */
    public static function newFromConf(
        iterable $conf
    ): AbstractTableAccessor {
        return new static(
            $conf['db']['connection'],
            $conf['db']['tablePrefix'] ?? null,
            new PasswdTransformer($conf['passwdKey']),
            isset($conf['minReplaceableInstAge'])
            ? new Duration($conf['minReplaceableInstAge'])
            : null
        );
    }

    public function __construct(
        $connection,
        ?string $tablePrefix,
        PasswdTransformer $passwdTransformer,
        ?Duration $minReplaceableInstAge
    ) {
        parent::__construct($connection, $tablePrefix);

        $this->passwdTransformer_ = $passwdTransformer;

        $this->minReplaceableInstAge_ = $minReplaceableInstAge;
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

        /** If no record is found but username is given, and
         *  minReplaceableInstAge is configured, look for a matching record of
         *  a minimum age, and replace its instance ID. */
        if (isset($username)) {
            if (isset($this->minReplaceableInstAge_)) {
                $maxTimestamp =
                    (new \DateTime())->sub($this->minReplaceableInstAge_);

                foreach ($this->getUserInsts($username) as $record) {
                    if (
                        $record->getCreated() < $maxTimestamp
                        && $this->passwdTransformer_->verifyObfuscatedPasswd(
                            $obfuscated,
                            $record->getPasswdHash()
                        )
                    ) {
                        $stmt = $this->prepare(
                            sprintf(
                                static::REPLACE_INST_STMT,
                                $this->tableName_
                            )
                        );

                        $stmt->execute([ $instId, $record->getInstId() ]);

                        if (!$stmt->rowCount()) {
                            /** @throw alcamo::exception::DataNotFound if
                             *  update failed. This should not happen. */
                            throw (new DataNotFound())->setMessageContext(
                                [
                                    'inTable' => $this->tableName_,
                                    'forKey' => [ $instId ]
                                ]
                            );
                        }

                        /** Then return the record for the newly created
                         *  instance. */
                        foreach (
                            $this->getGetStmt()
                                ->executeAndReturnSelf([ $instId ]) as $record
                        ) {
                            return $record;
                        }
                    }
                }
            }
        }

        /** If no instance record is found nor created, return `null` without
         *  throwing. */
        return null;
    }

    public function getUserInsts(string $username): \Traversable
    {
        return $this->query(
            sprintf(static::GET_USER_INSTS_STMT, $this->tableName_),
            [ $username ]
        );
    }

    public function getUserUserAgentInsts(
        string $username,
        string $userAgent
    ): \Traversable {
        return $this->query(
            sprintf(static::GET_USER_USER_AGENT_INSTS_STMT, $this->tableName_),
            [ $username, $userAgent ]
        );
    }

    public function add(
        string $instId,
        string $username,
        string $passwdHash,
        string $userAgent,
        string $appVersion,
        ?string $launcher = null
    ): void {
        $this->getAddStmt()->execute(
            [
                $instId,
                $username,
                $passwdHash,
                $userAgent,
                $appVersion,
                $launcher
            ]
        );
    }

    public function modify(
        string $instId,
        string $userAgent,
        ?string $launcher = null
    ): void {
        $stmt = $this->getModifyStmt();

        $stmt->execute([ $userAgent, $launcher, $instId ]);

        if (!$stmt->rowCount()) {
            throw (new DataNotFound())->setMessageContext(
                [
                    'inTable' => $this->tableName_,
                    'forKey' => $instId
                ]
            );
        }
    }

    public function updateInst(
        string $instId,
        string $userAgent,
        string $appVersion,
        ?string $launcher = null
    ): void {
        $stmt = $this->prepare(
            sprintf(static::UPDATE_INST_STMT, $this->tableName_)
        );

        $stmt->execute([ $userAgent, $appVersion, $launcher, $instId ]);

        if (!$stmt->rowCount()) {
            throw (new DataNotFound())->setMessageContext(
                [
                    'inTable' => $this->tableName_,
                    'forKey' => $instId
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
