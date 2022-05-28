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

    public function obfuscate(string $passwd): string
    {
        $passwdLen = strlen($passwd);
        $passwdKeyLen = strlen($this->passwdKey_);

        $result = '';

        for ($i = 0; $i < $passwdLen; $i++) {
            $result[$i] = $passwd[$i] ^ $this->passwdKey_[$i % $passwdKeyLen];
        }

        return $result;
    }

    public function unobfuscate(string $passwd): string
    {
        return $this->obfuscate($passwd);
    }

    public function createHash(string $passwd): string
    {
        return password_hash($passwd, PASSWORD_DEFAULT);
    }

    public function verifyObfuscatedPasswd(
        string $obfuscated,
        string $passwdHash
    ): bool {
        return password_verify($this->unobfuscate($obfuscated), $passwdHash);
    }
}
