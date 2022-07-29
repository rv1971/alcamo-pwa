<?php

namespace alcamo\pwa;

use alcamo\exception\DataNotFound;
use alcamo\time\Duration;

class AccountMgr
{
    private $accountAccessor_;  ///< AccountAccessor;
    private $openInstAccessor_; ///< OpenInstAccessor;
    private $instAccessor_;     ///< InstAccessor;

    private $maxPrevInstAge_;   /// Duration

    /**
     * @param $conf array or ArrayAccess object containing
     * - `db`
     *   - `connection`
     *   - `?string tablePrefix`
     * - `string passwdKey`
     * - `string maxOpenInstAge`
     * - `string maxPrevInstAge`
     */
    public static function newFromConf(iterable $conf): self
    {
        return new static(
            AccountAccessor::newFromConf($conf),
            OpenInstAccessor::newFromConf($conf),
            InstAccessor::newFromConf($conf),
            new Duration($conf['maxPrevInstAge'])
        );
    }

    public function __construct(
        AccountAccessor $accountAccessor,
        OpenInstAccessor $openInstAccessor,
        InstAccessor $instAccessor,
        Duration $maxPrevInstAge
    ) {
        $this->accountAccessor_ = $accountAccessor;
        $this->openInstAccessor_ = $openInstAccessor;
        $this->instAccessor_ = $instAccessor;

        $this->maxPrevInstAge_ = $maxPrevInstAge;
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
        $instAccessor = $this->instAccessor_;

        $inst = $instAccessor->get($instId, $username, $obfuscated);

        /** If the specified instance exists and the username/password
         *  matches, use it. */

        if (isset($inst)) {
            $instAccessor->modify($instId, $userAgent, $appVersion);
            return;
        }

        $openInst = $this->openInstAccessor_->get($username, $obfuscated);

        /** Otherwise, if there is an open instance and the username/password
         *  matches, transform it to an instance. */

        if (isset($openInst)) {
            $instAccessor->add(
                $instId,
                $username,
                $openInst->getPasswdHash(),
                $userAgent,
                $appVersion
            );

            $this->openInstAccessor_->remove($openInst->getPasswdHash());

            return;
        }

        /** Otherwise, if there is exactly one instance with a matching
         *  username/password which has exactly the same user agent
         *  information and has been modified at most $maxPrevInstAge_ ago,
         *  create a new instance.
         *
         * This is needed because some iPhones create a new instance when
         * adding a link to the home screen.
         */

        $instCandidates = [];

        foreach (
            $instAccessor->getUserUserAgentInsts($username, $userAgent) as $inst
        ) {
            if (
                $instAccessor->getPasswdTransformer()->verifyObfuscatedPasswd(
                    $obfuscated,
                    $inst->getPasswdHash()
                )
            ) {
                $instCandidates[] = $inst;
            }
        }

        if (
            count($instCandidates) == 1
            && $instCandidates[0]->getModified()->add($this->maxPrevInstAge_)
                ->getTimestamp()
            > (new \DateTimeImmutable())->getTimestamp()
        ) {
            $instAccessor->add(
                $instId,
                $username,
                $inst->getPasswdHash(),
                $userAgent,
                $appVersion
            );

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

        foreach ($this->openInstAccessor_->getUserInsts($username) as $inst) {
            return;
        }

        // remove user if no (open) installations left
        $this->accountAccessor_->remove($username);
    }

    public function createTables(): void
    {
        $this->accountAccessor_->createTable();
        $this->openInstAccessor_->createTable();
        $this->instAccessor_->createTable();
    }
}
