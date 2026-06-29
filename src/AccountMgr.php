<?php

namespace alcamo\pwa;

use alcamo\dao\DbAccessor;
use alcamo\exception\DataNotFound;
use alcamo\time\Duration;

class AccountMgr
{
    private $accountAccessor_;  ///< AccountAccessor;
    private $openInstAccessor_; ///< OpenInstAccessor;
    private $instAccessor_;     ///< InstAccessor;

    private $maxPrevInstAge_;   /// Duration

    /**
     * @param $props array|object Properties containing
     * - `db`
     *   - `dsn`
     *   - `?string namePrefix`
     * - `string passwdKey`
     * - `string maxOpenInstAge`
     * - `string maxPrevInstAge`
     * - `?string minReplaceableInstAge`
     */
    public static function newFromConf($conf): self
    {
        $conf = (object)$conf;

        $dbAccessor = DbAccessor::newFromProps($conf->db);

        $passwdTransformer = new PasswdTransformer($conf->passwdKey);

        return new static(
            new AccountAccessor($dbAccessor),
            new OpenInstAccessor(
                $dbAccessor,
                $passwdTransformer,
                $conf->maxOpenInstAge
            ),
            new InstAccessor(
                $dbAccessor,
                $passwdTransformer,
                $conf->minReplaceableInstAge ?? null
            ),
            $conf->maxPrevInstAge
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
         * return true iff the strings are equal. */
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
                $openInst->passwd_hash,
                $userAgent,
                $appVersion,
                $launcher
            );

            $this->openInstAccessor_->remove($openInst->passwd_hash);

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
                    $inst->passwd_hash
                )
            ) {
                if (
                    static::isSimilarUserAgent(
                        $userAgent,
                        $inst->user_agent
                    )
                ) {
                    $instCandidates[] = $inst;
                }
            }
        }

        if (
            count($instCandidates) == 1
            && $instCandidates[0]->modified->add($this->maxPrevInstAge_)
                ->getTimestamp()
            > (new \DateTimeImmutable())->getTimestamp()
        ) {
            $instAccessor->add(
                $instId,
                $username,
                $inst->passwd_hash,
                $inst->user_agent,
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
        $username = $this->instAccessor_->get($instId)->username;

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
}
