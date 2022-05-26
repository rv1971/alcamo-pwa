<?php

namespace alcamo\pwa;

use alcamo\dao\TableAccessor as TableAccessorBase;

abstract class AbstractTableAccessor extends TableAccessorBase
{
    private $tablePrefix_; ///< ?string

    public static function newFromDbParams(iterable $params)
    {
        return new self(
            $params['connection'],
            $params['table-prefix']
        );
    }

    public function __construct($connection, ?string $tablePrefix = null)
    {
        $this->tablePrefix_ = $tablePrefix;

        parent::__construct($connection, $tablePrefix . static::TABLE_NAME);
    }

    public function createTable(): void
    {
        $this
            ->prepare(
                str_replace(
                    '/*_*/',
                    $this->tablePrefix_,
                    file_get_contents(
                        dirname(__DIR__) . DIRECTORY_SEPARATOR
                        . 'sql' . DIRECTORY_SEPARATOR
                        . static::TABLE_NAME . '.sql'
                    )
                )
            )
            ->execute();
    }
}
