<?php

namespace alcamo\pwa;

use alcamo\exception\DataNotFound;
use PHPUnit\Framework\TestCase;

class OpenInstAccessorTest extends TestCase
{
    public const DSN = 'sqlite::memory:';

    private $accessor_;

    public function setUp(): void
    {
        $this->accessor_ = OpenInstAccessor::newFromParams(
            [
                'connection' => static::DSN,
                'tablePrefix' => 'foo_',
                'passwdKey' => random_bytes(8),
                'maxOpenInstAge' => 'PT4S'
            ]
        );

        $this->accessor_->createTable();
    }

    public function testAdd()
    {
        $alice1Obfuscated = $this->accessor_->add('alice');

        sleep(2);

        $alice2Obfuscated = $this->accessor_->add('alice');

        $alice2 = $this->accessor_->get('alice', $alice2Obfuscated);

        $alice1 = $this->accessor_->get('alice', $alice1Obfuscated);

        $this->assertTrue(
            $this->accessor_->getPasswdTransformer()->verifyObfuscatedPasswd(
                $alice1Obfuscated,
                $alice1->getPasswdHash()
            )
        );

        $this->assertTrue(
            $this->accessor_->getPasswdTransformer()->verifyObfuscatedPasswd(
                $alice2Obfuscated,
                $alice2->getPasswdHash()
            )
        );

        $this->assertFalse(
            $this->accessor_->getPasswdTransformer()->verifyObfuscatedPasswd(
                $alice2Obfuscated,
                $alice1->getPasswdHash()
            )
        );

        $this->assertFalse(
            $this->accessor_->getPasswdTransformer()->verifyObfuscatedPasswd(
                $alice1Obfuscated,
                $alice2->getPasswdHash()
            )
        );

        sleep(3);

        // alice 1 is expired now
        $this->assertNull($this->accessor_->get('alice', $alice1Obfuscated));

        // unlike the previous invocation, this tests the case that no
        // record is found in the table
        $this->assertNull($this->accessor_->get('alice', $alice1Obfuscated));

        // alice 2 is still there
        $this->assertInstanceOf(
            OpenInstRecord::class,
            $this->accessor_->get('alice', $alice2Obfuscated)
        );

        sleep(2);

        // now alice 2 is expired as well
        $this->assertNull($this->accessor_->get('alice', $alice2Obfuscated));
    }

    public function testRemoveException()
    {
        $this->expectException(DataNotFound::class);

        $this->expectExceptionMessage(
            'Data not found in table "foo_open_inst" for key "bar"'
        );

        $this->accessor_->remove('bar');
    }
}
