<?php

namespace alcamo\pwa;

use alcamo\dao\{Statement, TableAccessor as TableAccessorBase};

abstract class AbstractTableAccessor extends TableAccessorBase
{
    private $tablePrefix_; ///< ?string

    private $getStmt_;    ///< Statement
    private $addStmt_;    ///< Statement
    private $removeStmt_; ///< Statement

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

    protected function getGetStmt(): Statement
    {
        if (!isset($this->getStmt_)) {
            $this->getStmt_ = $this->prepare(
                sprintf(static::GET_STMT, $this->tableName_)
            );
        }

        return $this->getStmt_;
    }

    protected function getAddStmt(): Statement
    {
        if (!isset($this->addStmt_)) {
            $this->addStmt_ = $this->prepare(
                sprintf(static::ADD_STMT, $this->tableName_)
            );
        }

        return $this->addStmt_;
    }

    protected function getRemoveStmt(): Statement
    {
        if (!isset($this->removeStmt_)) {
            $this->removeStmt_ = $this->prepare(
                sprintf(static::REMOVE_STMT, $this->tableName_)
            );
        }

        return $this->removeStmt_;
    }
}
