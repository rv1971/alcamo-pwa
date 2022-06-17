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
                'db' => [ 'connection' => static::DSN ],
                'passwdKey' => random_bytes(8),
                'maxOpenInstAge' => 'PT4S',
                'smtp' => [
                    'host' => 'smtp.example.info',
                    'port' => 587,
                    'username' => 'bob',
                    'passwd' => 'passw$1234',
                    'from' => 'bob@example.info'
                ]
            ]
        );
    }

    public function testOptionJsonConfigFile(): void
    {
        $this->cli_->process();

        $this->assertSame(
            'smtp.example.info',
            $this->cli_->getParams()['smtp']['host']
        );

        $this->assertSame(
            'bob@example.info',
            $this->cli_->getParams()['smtp']['from']
        );

        $cli2 = new Cli($this->cli_->getParams());

        $cli2->process(
            '-j ' . dirname(__DIR__) . DIRECTORY_SEPARATOR . 'etc'
            . DIRECTORY_SEPARATOR . 'config_example.json'
        );

        $this->assertSame(
            'smtp.example.com',
            $cli2->getParams()['smtp']['host']
        );

        $this->assertSame(
            'alice@example.com',
            $cli2->getParams()['smtp']['from']
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
