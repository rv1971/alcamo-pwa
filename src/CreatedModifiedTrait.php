<?php

namespace alcamo\pwa;

/// Database record having columns `created` and `modified`
trait CreatedModifiedTrait
{
    use CreatedTrait;
    use ModifiedTrait;

    private function initCreatedModified(): void
    {
        $this->initCreated();
        $this->initModified();
    }
}
