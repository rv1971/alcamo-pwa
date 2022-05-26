<?php

namespace alcamo\pwa;

class PasswdTransformer
{
    public const PASSWD_LENGTH = 16;

    private $passwdKey_; ///< Key to obfuscate password

    public function __construct(string $passwdKey)
    {
        $this->passwdKey_ = $passwdKey;
    }

    public function createPasswd(): string
    {
        return random_bytes(static::PASSWD_LENGTH);
    }

    public function obfuscatePasswd(string $passwd): string
    {
        $passwdLen = strlen($passwd);
        $passwdKeyLen = strlen($this->passwdKey_);

        $result = '';

        for ($i = 0; $i < $passwdLen; $i++) {
            $result[$i] = $passwd[$i] ^ $this->passwdKey_[$i % $passwdKeyLen];
        }

        return $result;
    }

    public function unobfuscatePasswd(string $passwd): string
    {
        return $this->obfuscatePasswd($passwd);
    }

    public function getHash(string $obfuscated): string
    {
        return password_hash($obfuscated, PASSWORD_DEFAULT);
    }

    public function verifyObfuscatedPasswd(
        string $obfuscated,
        string $passwdHash
    ): bool {
        return password_verify(
            $this->unobfuscatePasswd($obfuscated),
            $passwdHash
        );
    }
}
