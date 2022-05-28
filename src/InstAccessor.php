<?php

namespace alcamo\pwa;

use alcamo\exception\DataNotFound;

class InstAccessor extends AbstractTableAccessor
{
    public const RECORD_CLASS = InstRecord::class;

    public const TABLE_NAME = 'inst';

    public const GET_STMT =
        "SELECT * FROM %s WHERE inst_id = ? AND username = ?";

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

    private $passwdTransformer_; ///< PasswdTransformer

    public static function newFromParams(iterable $params)
    {
        return new static(
            $params['connection'],
            $params['tablePrefix'],
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
        string $username,
        string $obfuscated
    ): ?InstRecord {
        foreach (
            $this->getGetStmt()
                ->executeAndReturnSelf([ $instId, $username ]) as $record
        ) {
            return
                $this->passwdTransformer_->verifyObfuscatedPasswd(
                    $obfuscated,
                    $record->getPasswdHash()
                )
                ? $record
                : null;
        };

        return null;
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
}
