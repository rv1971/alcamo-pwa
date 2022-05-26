<?php

namespace alcamo\pwa;

use alcamo\time\Duration;

class OpenInstAccessor extends AbstractTableAccessor
{
    public const RECORD_CLASS = OpenInstRecord::class;

    public const TABLE_NAME = 'open_inst';

    public const GET_STMT =
        "SELECT * FROM %s WHERE username = ? ORDER BY created, passwd_hash";

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
     * @brief Get oldest record for this user, if any
     *
     * Remove any expired records.
     */
    public function get(string $username): ?OpenInstRecord
    {
        for (;;) {
            $record = $this->getGetStmt()
                ->executeAndReturnSelf([ $username ])
                ->fetch();

            if (!$record) {
                return null;
            }

            if (
                $record->getCreated()
                    ->add($this->maxAge_)
                    ->getTimestamp()
                > (new \DateTimeImmutable())->getTimestamp()
            ) {
                return $record;
            }

            $this->remove($record->getPasswdHash());
        }
    }

    /// @return obfuscated password
    public function add($username): string
    {
        if (!isset($this->addStmt_)) {
            $this->addStmt_ = $this->prepare(
                sprintf(static::ADD_STMT, $this->tableName_)
            );
        }

        $passwd = $this->passwdTransformer_->createPasswd();

        $this->addStmt_->execute(
            [ $this->passwdTransformer_->getHash($passwd), $username ]
        );

        return $this->passwdTransformer_->obfuscatePasswd($passwd);
    }

    public function remove($passwdHash): void
    {
        $this->getRemoveStmt()->execute([ $passwdHash ]);
    }
}
