<?php

namespace alcamo\pwa;

use alcamo\cli\AbstractCli as AbstractCliBase;

abstract class AbstractCli extends AbstractCliBase
{
    protected $accountMgr_;

    public function __construct(iterable $params)
    {
        parent::__construct();

        $this->accountMgr_ = AccountMgr::newFromParams($params);
    }

    public function getAccountMgr(): AccountMgr
    {
        return $this->accountMgr_;
    }

    public function process($arguments = null): int
    {
        parent::process($arguments);

        if (!$this->getCommand()) {
            $this->showHelp();
            return 0;
        }

        return $this->{$this->getCommand()->getHandler()}();
    }
}
