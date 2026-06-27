<?php

namespace alcamo\pwa;

use alcamo\dao\{DbAccessor, RelationAccessor};
use alcamo\exception\DataNotFound;
use alcamo\time\Duration;

/**
 * @brief Accessor for the open_inst table
 *
 * @date last reviewed 2026-06-26
 */
class InstAccessor extends RelationAccessor
{
    public const RELATION_NAME = 'inst';

    public const FETCH_CLASS = InstRecord::class;

    public const STMT_MAP = [
        'add' => [ <<<EOD
INSERT INTO /*_*/%s(
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
EOD
        ],
        'get' => [
            'SELECT * FROM /*_*/%s WHERE inst_id = ?'
        ],
        'get-by-user' => [
            'SELECT * FROM /*_*/%s WHERE username = ? ORDER BY modified'
        ],
        'get-by-user-user-agent' => [
            'SELECT * FROM /*_*/%s WHERE username = ? and user_agent = ? '
                . 'ORDER BY modified'
        ],
        'modify' => [ <<<EOD
UPDATE /*_*/%s SET
    user_agent = ?,
    launcher = ?,
    created = created,
    modified = CURRENT_TIMESTAMP
WHERE inst_id = ?
EOD
        ],
        'remove' => [
            'DELETE FROM /*_*/%s WHERE inst_id = ?'
        ],
        'replace-inst' => [ <<<EOD
UPDATE /*_*/%s SET
    inst_id = ?,
    created = created,
    modified = CURRENT_TIMESTAMP
WHERE inst_id = ?
EOD
        ],
        'select' => [
            'SELECT * FROM /*_*/%s ORDER BY username, modified DESC LIMIT 1000'
        ],
        'update-inst' => [ <<<EOD
UPDATE /*_*/%s SET
    user_agent = ?,
    app_version = ?,
    launcher = ?,
    update_count = update_count + 1,
    created = created,
    modified = CURRENT_TIMESTAMP
WHERE inst_id = ?
EOD
        ]
    ]
    + parent::STMT_MAP;

    private $passwdTransformer_;     ///< PasswdTransformer
    private $minReplaceableInstAge_; ///< ?Duration

    public function __construct(
        DbAccessor $dbAccessor,
        PasswdTransformer $passwdTransformer,
        ?Duration $minReplaceableInstAge
    ) {
        parent::__construct($dbAccessor);

        $this->passwdTransformer_ = $passwdTransformer;
        $this->minReplaceableInstAge_ = $minReplaceableInstAge;
    }

    public function getPasswdTransformer(): PasswdTransformer
    {
        return $this->passwdTransformer_;
    }

    public function getMinReplaceableInstAge(): ?Duration
    {
        return $this->minReplaceableInstAge_;
    }

    public function get(
        string $instId,
        ?string $username = null,
        ?string $obfuscated = null
    ): ?InstRecord {
        // loop finds at most one record
        foreach (
            $this->getStmt('get')->executeAndReturnSelf([ $instId ]) as $record
        ) {
            /** Verify user and password if username is given. */
            if (isset($username)) {
                if ($record->username != $username) {
                    /** @throw alcamo::exception::DataNotFound if $username is
                     *  given but does not match */
                    throw (new DataNotFound())->setMessageContext(
                        [
                            'inTable' => $this->relationName_,
                            'forKey' => [ $instId, $username ]
                        ]
                    );
                }

                if (
                    !$this->passwdTransformer_->verifyObfuscatedPasswd(
                        $obfuscated,
                        $record->passwd_hash
                    )
                ) {
                    /** @throw alcamo::exception::DataNotFound if $username is
                     *  given but password does not match */
                    throw (new DataNotFound())->setMessageContext(
                        [
                            'inTable' => $this->relationName_,
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
                        $record->created < $maxTimestamp
                        && $this->passwdTransformer_->verifyObfuscatedPasswd(
                            $obfuscated,
                            $record->passwd_hash
                        )
                    ) {
                        $this->getStmt('replace-inst')
                            ->execute([ $instId, $record->inst_id ]);

                        /* Do not check rowCount() since for some reason it
                         * does not seem to work reliably with all postgres
                         * drivers. */

                        /** Then return the record for the newly created
                         *  instance. */
                        foreach (
                            $this->getStmt('get')
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
        return $this->getStmt('get-by-user')->executeAndReturnSelf(
            [ $username ]
        );
    }

    public function getUserUserAgentInsts(
        string $username,
        string $userAgent
    ): \Traversable {
        return $this->getStmt('get-by-user-user-agent')->executeAndReturnSelf(
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
        $this->getStmt('add')->execute(
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
        $stmt = $this->getStmt('modify')->executeAndReturnSelf(
            [ $userAgent, $launcher, $instId ]
        );

        if (!$stmt->rowCount()) {
            throw (new DataNotFound())->setMessageContext(
                [
                    'inTable' => $this->relationName_,
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
        $stmt = $this->getStmt('update-inst')->executeAndReturnSelf(
            [ $userAgent, $appVersion, $launcher, $instId ]
        );

        if (!$stmt->rowCount()) {
            throw (new DataNotFound())->setMessageContext(
                [
                    'inTable' => $this->relationName_,
                    'forKey' => $instId
                ]
            );
        }
    }

    public function remove($instId): void
    {
        $stmt = $this->getStmt('remove')->executeAndReturnSelf([ $instId ]);

        if (!$stmt->rowCount()) {
            throw (new DataNotFound())->setMessageContext(
                [
                    'inTable' => $this->relationName_,
                    'forKey' => $instId
                ]
            );
        }
    }
}
