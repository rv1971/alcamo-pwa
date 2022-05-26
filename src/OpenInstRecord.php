<?php

namespace alcamo\pwa;

class OpenInstRecord
{
    use CreatedTrait;

    private $passwd_hash;
    private $username;

    public function __construct()
    {
        $this->initCreated();
    }

    public function getPasswdHash(): string
    {
        return $this->passwd_hash;
    }

    public function getUsername(): string
    {
        return $this->username;
    }
}
