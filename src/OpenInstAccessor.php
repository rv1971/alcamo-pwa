<?php

namespace alcamo\pwa;

use alcamo\dao\{DbAccessor, RelationAccessor};
use alcamo\exception\DataNotFound;
use alcamo\time\Duration;

class OpenInstAccessor extends AbstractTableAccessor
{
    public const RELATION_NAME = 'open_inst';

    public const FETCH_CLASS = OpenInstRecord::class;

    public const SELECT_STMT =
        'SELECT * FROM /*_*/%s ORDER BY username, created LIMIT 1000';

    public const GET_STMT = "SELECT * FROM /*_*/%s WHERE username = ?";

    public const GET_USER_INSTS_STMT =
        "SELECT * FROM /*_*/%s WHERE username = ? order by created";

    public const ADD_STMT =
        "INSERT INTO /*_*/%s(passwd_hash, username, created)\n"
        . "  VALUES(?, ?, CURRENT_TIMESTAMP)";

    public const REMOVE_STMT = "DELETE FROM /*_*/%s WHERE passwd_hash = ?";

    private $passwdTransformer_; ///< PasswdTransformer
    private $maxAge_;            ///< Duration

    /**
     * @param $props array|object Properties containing
     * - `db`
     *   - `dsn`
     *   - `?string namePrefix`
     * - `string passwdKey`
     * - `string maxOpenInstAge`
     */
    public static function newFromDbAccessorAndConf(
        DbAccessor $dbAccessor,
        $conf
    ): RelationAccessor {
        $conf = (object)$conf;

        return new static(
            $dbAccessor,
            new PasswdTransformer($conf->passwdKey),
            new Duration($conf->maxOpenInstAge)
        );
    }

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
            $this->getGetStmt()->executeAndReturnSelf([ $username ]) as $record
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
        return $this->query(
            sprintf(static::GET_USER_INSTS_STMT, $this->relationName_),
            [ $username ]
        );
    }

    /// @return obfuscated password
    public function add(string $username): string
    {
        $passwd = $this->passwdTransformer_->createPasswd();

        $this->getAddStmt()->execute(
            [ $this->passwdTransformer_->createHash($passwd), $username ]
        );

        return $this->passwdTransformer_->obfuscate($passwd);
    }

    public function remove($passwdHash): void
    {
        $stmt = $this->getRemoveStmt();

        $stmt->execute([ $passwdHash ]);

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
