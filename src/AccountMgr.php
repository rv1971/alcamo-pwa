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

    /** Check whether two user agent strings are so similar that they may
     * plausibly belong to the same device.
     *
     * There are indeed mobile devices which send a different user agent
     * string depending whether a link is opened in a browser or from the home
     * screen. */
    public static function isSimilarUserAgent(
        string $userAgent1,
        string $userAgent2
    ): bool {
        $pos1 = strpos($userAgent1, ')');
        $pos2 = strpos($userAgent2, ')');

        /** - If one of the strings does not contain a closing parenthesis,
         * return true iff the strings are euqal. */
        if ($pos1 === false || $pos2 === false) {
            return $userAgent1 == $userAgent2;
        }

        /** - Otherwise, extract prefixes until the first closing
         * parenthesis. If the prefixes are equal, return true. */
        $prefix1 = substr($userAgent1, 0, $pos1);
        $prefix2 = substr($userAgent2, 0, $pos2);

        if ($prefix1 == $prefix2) {
            return true;
        }

        /** - Otherwise, if one of the prefixes does not contain two
         * semicolons, return false. */
        $pos1 = strpos($prefix1, ';');
        $pos2 = strpos($prefix2, ';');

        if ($pos1 === false || $pos2 === false) {
            return false;
        }

        $pos1 = strpos($prefix1, ';', $pos1 + 1);
        $pos2 = strpos($prefix2, ';', $pos2 + 1);

        if ($pos1 === false || $pos2 === false) {
            return false;
        }

        /** - Otherwise, extract prefixes until the second semicolon, and
         * return true iff they are equal. */
        $prefix1 = substr($userAgent1, 0, $pos1);
        $prefix2 = substr($userAgent2, 0, $pos2);

        return $prefix1 == $prefix2;
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
        string $appVersion,
        ?string $launcher = null
    ): void {
        $instAccessor = $this->instAccessor_;

        $inst = $instAccessor->get($instId, $username, $obfuscated);

        /** If the specified instance exists and the username/password
         *  matches, use it. */

        if (isset($inst)) {
            $instAccessor
                ->updateInst($instId, $userAgent, $appVersion, $launcher);
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
                $appVersion,
                $launcher
            );

            $this->openInstAccessor_->remove($openInst->getPasswdHash());

            return;
        }

        /** Otherwise, if there is exactly one instance with a matching
         *  username/password which has a similar user agent information and
         *  has been modified at most $maxPrevInstAge_ ago, create a new
         *  instance.
         *
         * This is needed because some iPhones create a new instance when
         * adding a link to the home screen.
         */

        $instCandidates = [];

        foreach (
            $instAccessor->getUserInsts($username) as $inst
        ) {
            if (
                $instAccessor->getPasswdTransformer()->verifyObfuscatedPasswd(
                    $obfuscated,
                    $inst->getPasswdHash()
                )
            ) {
                if (
                    static::isSimilarUserAgent(
                        $userAgent,
                        $inst->getUserAgent()
                    )
                ) {
                    $instCandidates[] = $inst;
                }
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
                $inst->getUserAgent(),
                $appVersion,
                $launcher
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
