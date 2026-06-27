<?php

namespace alcamo\pwa;

class OpenInstRecord
{
    use CreatedTrait;

    public $passwd_hash;
    public $username;

    public function __construct()
    {
        $this->initCreated();
    }
}
