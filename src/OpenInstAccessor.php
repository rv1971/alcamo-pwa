<?php

namespace alcamo\pwa;

use alcamo\exception\DataNotFound;
use alcamo\time\Duration;

class OpenInstAccessor extends AbstractTableAccessor
{
    public const RECORD_CLASS = OpenInstRecord::class;

    public const TABLE_NAME = 'open_inst';

    public const SELECT_STMT =
        'SELECT * FROM %s ORDER BY username, created LIMIT 100';

    public const GET_STMT = "SELECT * FROM %s WHERE username = ?";

    public const GET_USER_INSTS_STMT =
        "SELECT * FROM %s WHERE username = ? order by created";

    public const ADD_STMT =
        "INSERT INTO %s(passwd_hash, username, created)\n"
        . "  VALUES(?, ?, CURRENT_TIMESTAMP)";

    public const REMOVE_STMT = "DELETE FROM %s WHERE passwd_hash = ?";

    private $passwdTransformer_; ///< PasswdTransformer
    private $maxAge_;            ///< Duration

    /**
     * @param $params array or ArrayAccess object containing
     * - `db`
     *   - `connection`
     *   - `?string tablePrefix`
     * - `string passwdKey`
     * - `string maxOpenInstAge`
     */
    public static function newFromParams(
        iterable $params
    ): AbstractTableAccessor {
        return new static(
            $params['db']['connection'],
            $params['db']['tablePrefix'] ?? null,
            new PasswdTransformer($params['passwdKey']),
            new Duration($params['maxOpenInstAge'])
        );
    }

    public function __construct(
        $connection,
        ?string $tablePrefix,
        PasswdTransformer $passwdTransformer,
        Duration $maxAge
    ) {
        parent::__construct($connection, $tablePrefix);

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
            sprintf(static::GET_USER_INSTS_STMT, $this->tableName_),
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
                    'inTable' => $this->tableName_,
                    'forKey' => $passwdHash
                ]
            );
        }
    }
}
