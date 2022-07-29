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
            [
                'db' => [ 'connection' => static::DSN ],
                'passwdKey' => random_bytes(8),
                'maxOpenInstAge' => 'PT4S',
                'maxPrevInstAge' => 'PT5S',
                'url' => 'https://localhost/myapp',
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
            $this->cli_->getConf()['smtp']['host']
        );

        $this->assertSame(
            'bob@example.info',
            $this->cli_->getConf()['smtp']['from']
        );

        $cli2 = new Cli($this->cli_->getConf());

        $cli2->process(
            '-j ' . dirname(__DIR__) . DIRECTORY_SEPARATOR . 'etc'
            . DIRECTORY_SEPARATOR . 'config_example.json'
            . ' test-database'
        );

        $this->assertSame(
            'smtp.example.com',
            $cli2->getConf()['smtp']['host']
        );

        $this->assertSame(
            'alice@example.com',
            $cli2->getConf()['smtp']['from']
        );
    }

    public function testAddInst(): void
    {
        $dbAccessor = new DbAccessor(self::DSN);

        $conf = $this->cli_->getConf();
        $conf['db']['connection'] = $dbAccessor;

        (new Cli($conf))->process('setup-database');

        $cli3 = new Cli($conf);

        $cli3->process('add alice');

        foreach (
            $cli3->getAccountMgr()->getOpenInstAccessor() as $record
        ) {
            $this->assertSame(
                'alice',
                $record->getUsername()
            );
        }
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
