<?php

namespace alcamo\pwa;

use alcamo\exception\DataNotFound;

class AccountMgr
{
    private $accountAccessor_;  ///< AccountAccessor;
    private $openInstAccessor_; ///< OpenInstAccessor;
    private $instAccessor_;     ///< InstAccessor;

    /**
     * @param $params array or ArrayAccess object containing
     * - `db`
     *   - `connection`
     *   - `?string tablePrefix`
     * - `string passwdKey`
     * - `string maxOpenInstAge`
     */
    public static function newFromParams(iterable $params): self
    {
        return new static(
            AccountAccessor::newFromParams($params),
            OpenInstAccessor::newFromParams($params),
            InstAccessor::newFromParams($params)
        );
    }

    public function __construct(
        AccountAccessor $accountAccessor,
        OpenInstAccessor $openInstAccessor,
        InstAccessor $instAccessor
    ) {
        $this->accountAccessor_ = $accountAccessor;
        $this->openInstAccessor_ = $openInstAccessor;
        $this->instAccessor_ = $instAccessor;
    }

    public function getAccountAccessor(): AccountAccessor
    {
        return $this->accountAccessor_;
    }

    public function getOpenInstAccessor(): OpenInstAccessor
    {
        return $this->openInstAccessor_;
    }

    public function getInstAccessor(): InstAccessor
    {
        return $this->instAccessor_;
    }

    /// @return obfuscated password
    public function addOpenInst(string $username): string
    {
        if (!$this->accountAccessor_->get($username)) {
            $this->accountAccessor_->add($username);
        }

        return $this->openInstAccessor_->add($username);
    }

    /** @throw alcamo::exception::DataNotFound if not authenticated */
    public function addOrModifyInst(
        string $instId,
        string $username,
        string $obfuscated,
        string $userAgent,
        string $appVersion
    ): void {
        $inst = $this->instAccessor_->get($instId, $username, $obfuscated);

        if (isset($inst)) {
            $this->instAccessor_->modify($instId, $userAgent, $appVersion);
            return;
        }

        $openInst = $this->openInstAccessor_->get($username, $obfuscated);

        if (isset($openInst)) {
            $this->instAccessor_->add(
                $instId,
                $username,
                $openInst->getPasswdHash(),
                $userAgent,
                $appVersion
            );

            $this->openInstAccessor_->remove($openInst->getPasswdHash());

            return;
        }

        throw (new DataNotFound())->setMessageContext(
            [ 'forKey' => [ $instId, $username, $obfuscated ] ]
        );
    }

    public function removeInst(string $instId)
    {
        $username = $this->instAccessor_->get($instId)->getUsername();

        $this->instAccessor_->remove($instId);

        foreach ($this->instAccessor_->getUserInsts($username) as $inst) {
            return;
        }

        // remove user if no installations left
        $this->accountAccessor_->remove($username);
    }

    public function createTables(): void
    {
        $this->accountAccessor_->createTable();
        $this->openInstAccessor_->createTable();
        $this->instAccessor_->createTable();
    }
}
