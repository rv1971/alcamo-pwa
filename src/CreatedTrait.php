<?php

namespace alcamo\pwa;

/// Database record having column `created`
trait CreatedTrait
{
    public $created;

    private function initCreated(): void
    {
        $this->created = new \DateTimeImmutable($this->created);
    }
}
