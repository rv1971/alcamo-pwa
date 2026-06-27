<?php

namespace alcamo\pwa;

class InstRecord
{
    use CreatedModifiedTrait;

    public $inst_id;
    public $username;
    public $passwd_hash;
    public $user_agent;
    public $app_version;
    public $launcher;
    public $update_count;

    public function __construct()
    {
        $this->initCreatedModified();
    }

    public function getShortInstId(): string
    {
        return substr($this->inst_id, 0, 6);
    }
}
