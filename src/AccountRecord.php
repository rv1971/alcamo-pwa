<?php

namespace alcamo\pwa;

class AccountRecord
{
    use CreatedModifiedTrait;

    private $username;

    public function __construct()
    {
        $this->initCreatedModified();
    }

    public function getUsername(): string
    {
        return $this->username;
    }
}
