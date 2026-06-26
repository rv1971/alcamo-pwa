<?php

namespace alcamo\pwa;

use alcamo\dao\{Statement, RelationAccessor as BaseRelationAccessor};

abstract class AbstractTableAccessor extends BaseRelationAccessor
{
    public const SELECT_STMT = 'SELECT * FROM %s ORDER BY 1, 2, 3 LIMIT 1000';

    private $getStmt_;        ///< Statement
    private $addStmt_;        ///< Statement
    private $modifyStmt_;     ///< Statement
    private $removeStmt_;     ///< Statement

    protected function getGetStmt(): Statement
    {
        if (!isset($this->getStmt_)) {
            $this->getStmt_ = $this->prepare(
                sprintf(static::GET_STMT, $this->relationName_)
            );
        }

        return $this->getStmt_;
    }

    protected function getAddStmt(): Statement
    {
        if (!isset($this->addStmt_)) {
            $this->addStmt_ = $this->prepare(
                sprintf(static::ADD_STMT, $this->relationName_)
            );
        }

        return $this->addStmt_;
    }

    protected function getModifyStmt(): Statement
    {
        if (!isset($this->modifyStmt_)) {
            $this->modifyStmt_ = $this->prepare(
                sprintf(static::MODIFY_STMT, $this->relationName_)
            );
        }

        return $this->modifyStmt_;
    }

    protected function getRemoveStmt(): Statement
    {
        if (!isset($this->removeStmt_)) {
            $this->removeStmt_ = $this->prepare(
                sprintf(static::REMOVE_STMT, $this->relationName_)
            );
        }

        return $this->removeStmt_;
    }
}
