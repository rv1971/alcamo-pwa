<?php

namespace alcamo\pwa;

class InstRecord
{
    use CreatedModifiedTrait;

    private $inst_id;
    private $username;
    private $passwd_hash;
    private $user_agent;
    private $app_version;
    private $update_count;

    public function __construct()
    {
        $this->initCreatedModified();
    }

    public function getInstId(): string
    {
        return $this->inst_id;
    }

    public function getShortInstId(): string
    {
        return substr($this->inst_id, 0, 6);
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPasswdHash(): string
    {
        return $this->passwd_hash;
    }

    public function getUserAgent(): string
    {
        return $this->user_agent;
    }

    public function getAppVersion(): string
    {
        return $this->app_version;
    }

    public function getUpdateCount(): int
    {
        return $this->update_count;
    }
}
