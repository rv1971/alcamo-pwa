<?php

namespace alcamo\pwa;

use alcamo\time\Duration;

class OpenInstAccessor extends AbstractTableAccessor
{
    public const RECORD_CLASS = OpenInstRecord::class;

    public const TABLE_NAME = 'open_inst';

    public const GET_STMT = "SELECT * FROM %s WHERE username = ?";

    public const ADD_STMT =
        "INSERT INTO %s(passwd_hash, username, created)\n"
        . "  VALUES(?, ?, CURRENT_TIMESTAMP)";

    public const REMOVE_STMT = "DELETE FROM %s WHERE passwd_hash = ?";

    private $passwdTransformer_; ///< PasswdTransformer
    private $maxAge_;            ///< Duration

    public static function newFromParams(iterable $params)
    {
        return new static(
            $params['connection'],
            $params['tablePrefix'],
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
    public function get(string $obfuscated, string $username): ?OpenInstRecord
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

                break;
            }
        }

        return $record;
    }

    /// @return obfuscated password
    public function add($username): string
    {
        $passwd = $this->passwdTransformer_->createPasswd();

        $this->getAddStmt()->execute(
            [ $this->passwdTransformer_->createHash($passwd), $username ]
        );

        return $this->passwdTransformer_->obfuscatePasswd($passwd);
    }

    public function remove($passwdHash): void
    {
        $this->getRemoveStmt()->execute([ $passwdHash ]);
    }
}
