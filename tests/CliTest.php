<?php

namespace alcamo\pwa;

use alcamo\dao\DbAccessor;
use PHPUnit\Framework\TestCase;

class CliTest extends TestCase
{
    public const DSN = 'sqlite::memory:';

    private $cli_;

    public function setUp(): void
    {
        $this->cli_ = new Cli(
            (object)[
                'db' => (object)[
                    'dsn' => static::DSN
                ],
                'passwdKey' => random_bytes(8),
                'maxOpenInstAge' => 'PT4S',
                'maxPrevInstAge' => 'PT5S',
                'url' => 'https://localhost/myapp',
                'smtp' => (object)[
                    'host' => 'smtp.example.info',
                    'port' => 587,
                    'encryption' => 'tls',
                    'username' => 'bob',
                    'passwd' => 'passw$1234',
                    'from' => 'bob@example.info'
                ]
            ]
        );
    }

    public function testOptionJsonConfigFile(): void
    {
        $this->cli_->run('');

        $this->assertSame(
            'smtp.example.info',
            $this->cli_->getConf()->smtp->host
        );

        $this->assertSame(
            'bob@example.info',
            $this->cli_->getConf()->smtp->from
        );

        $cli2 = new Cli($this->cli_->getConf());

        $cli2->run(
            '-j ' . dirname(__DIR__) . DIRECTORY_SEPARATOR . 'etc'
            . DIRECTORY_SEPARATOR . 'config_example.json'
            . ' test-database'
        );

        $this->assertSame(
            'smtp.example.com',
            $cli2->getConf()->smtp->host
        );

        $this->assertSame(
            'alice@example.com',
            $cli2->getConf()->smtp->from
        );
    }

    public function testAddInst(): void
    {
        $this->cli_->run('setup-database');

        $this->cli_->run('add alice');

        foreach (
            $this->cli_->getAccountMgr()->getOpenInstAccessor() as $record
        ) {
            $this->assertSame(
                'alice',
                $record->username
            );
        }
    }

    public function testSetupDatabase(): void
    {
        $this->cli_->run('setup-database');

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
