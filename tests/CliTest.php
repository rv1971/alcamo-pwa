<?php

namespace alcamo\pwa;

use alcamo\dao\DbAccessor;
use alcamo\xml_conf\Loader;
use PHPUnit\Framework\TestCase;

/* This also tests ConfDocument. */
class CliTest extends TestCase
{
    public const DSN = 'sqlite::memory:';

    private $cli_;

    public function setUp(): void
    {
        $configHome = __DIR__;

        putenv("XDG_CONFIG_HOME=$configHome");

        $this->cli_ = new Cli();
    }

    public function testOptionConfigFile(): void
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

        $cli2 = new Cli();

        $cli2->run(
            '-c ' . dirname(__DIR__) . DIRECTORY_SEPARATOR . 'etc'
            . DIRECTORY_SEPARATOR . 'example-conf.xml'
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
