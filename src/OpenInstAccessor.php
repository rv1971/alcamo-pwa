<?php

namespace alcamo\pwa;

use alcamo\dao\{DbAccessor, RelationAccessor};
use alcamo\exception\DataNotFound;
use alcamo\time\Duration;

class OpenInstAccessor extends RelationAccessor
{
    public const RELATION_NAME = 'open_inst';

    public const FETCH_CLASS = OpenInstRecord::class;

    public const STMT_MAP = [
        'add' => [
            'INSERT INTO /*_*/%s(passwd_hash, username, created) '
                . 'VALUES(?, ?, CURRENT_TIMESTAMP)'
        ],
        'get' => [
            'SELECT * FROM /*_*/%s WHERE username = ?'
        ],
        'get-user-insts' => [
            'SELECT * FROM /*_*/%s WHERE username = ? order by created'
        ],
        'remove' => [
            'DELETE FROM /*_*/%s WHERE passwd_hash = ?'
        ],
        'select' => [
            'SELECT * FROM /*_*/%s ORDER BY username, created LIMIT 1000'
        ]
    ]
    + parent::STMT_MAP;

    private $passwdTransformer_; ///< PasswdTransformer
    private $maxAge_;            ///< Duration

    public function __construct(
        DbAccessor $dbAccessor,
        PasswdTransformer $passwdTransformer,
        Duration $maxAge
    ) {
        parent::__construct($dbAccessor);

        $this->passwdTransformer_ = $passwdTransformer;
        $this->maxAge_ = $maxAge;
    }

    public function getPasswdTransformer(): PasswdTransformer
    {
        return $this->passwdTransformer_;
    }

    /**
     * @brief Get record, if present and not expired
     *
     * Remove any expired records.
     */
    public function get(string $username, string $obfuscated): ?OpenInstRecord
    {
        foreach (
            $this->getStmt('get')
                ->executeAndReturnSelf([ $username ]) as $record
        ) {
            if (
                $this->passwdTransformer_->verifyObfuscatedPasswd(
                    $obfuscated,
                    $record->getPasswdHash()
                )
            ) {
                if (
                    $record->getCreated()->add($this->maxAge_)->getTimestamp()
                    < (new \DateTimeImmutable())->getTimestamp()
                ) {
                    $this->remove($record->getPasswdHash());
                    $record = null;
                }

                return $record;
            }
        }

        return null;
    }

    public function getUserInsts(string $username): \Traversable
    {
        return $this->getStmt('get-user-insts')->executeAndReturnSelf(
            [ $username ]
        );
    }

    /// @return obfuscated password
    public function add(string $username): string
    {
        $passwd = $this->passwdTransformer_->createPasswd();

        $this->getStmt('add')->execute(
            [ $this->passwdTransformer_->createHash($passwd), $username ]
        );

        return $this->passwdTransformer_->obfuscate($passwd);
    }

    public function remove($passwdHash): void
    {
        $stmt = $this->getStmt('remove')->executeAndReturnSelf([ $passwdHash ]);

        if (!$stmt->rowCount()) {
            /** @throw alcamo::exception::DataNotFound if $passwdHash does not
             *  exist */
            throw (new DataNotFound())->setMessageContext(
                [
                    'inTable' => $this->relationName_,
                    'forKey' => $passwdHash
                ]
            );
        }
    }
}
