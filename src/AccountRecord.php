<?php

namespace alcamo\pwa;

class AccountRecord
{
    use CreatedModifiedTrait;

    public $username;

    public function __construct()
    {
        $this->initCreatedModified();
    }
}
