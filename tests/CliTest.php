<?php

namespace alcamo\pwa;

use PHPUnit\Framework\TestCase;

class CliTest extends TestCase
{
    public const DSN = 'sqlite::memory:';

    private $cli_;

    public function setUp(): void
    {
        $this->cli_ = new Cli(
            [
                'db' => [
                    'connection' => static::DSN,
                    'passwdKey' => random_bytes(8),
                    'maxOpenInstAge' => 'PT4S'
                ]
            ]
        );
    }

    public function testSetupDatabase(): void
    {
        $this->cli_->process('setup-database');

        $this->assertSame(
            0,
            count($this->cli_->getAccountMgr()->getAccountAccessor())
        );

        $this->assertSame(
            0,
            count($this->cli_->getAccountMgr()->getOpenInstAccessor())
        );

        $this->assertSame(
            0,
            count($this->cli_->getAccountMgr()->getInstAccessor())
        );
    }
}
