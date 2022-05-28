<?php

namespace alcamo\pwa;

use alcamo\exception\DataNotFound;
use PHPUnit\Framework\TestCase;

class OpenInstAccessorTest extends TestCase
{
    public const DSN = 'sqlite::memory:';

    public function testBasics()
    {
        $accessor = OpenInstAccessor::newFromParams(
            [
                'connection' => static::DSN,
                'tablePrefix' => 'foo_',
                'passwdKey' => random_bytes(8),
                'maxOpenInstAge' => 'PT4S'
            ]
        );

        $accessor->createTable();

        $alice1Obfuscated = $accessor->add('alice');

        sleep(2);

        $alice2Obfuscated = $accessor->add('alice');

        $alice2 = $accessor->get($alice2Obfuscated, 'alice');

        $alice1 = $accessor->get($alice1Obfuscated, 'alice');

        $this->assertTrue(
            $accessor->getPasswdTransformer()->verifyObfuscatedPasswd(
                $alice1Obfuscated,
                $alice1->getPasswdHash()
            )
        );

        $this->assertTrue(
            $accessor->getPasswdTransformer()->verifyObfuscatedPasswd(
                $alice2Obfuscated,
                $alice2->getPasswdHash()
            )
        );

        $this->assertFalse(
            $accessor->getPasswdTransformer()->verifyObfuscatedPasswd(
                $alice2Obfuscated,
                $alice1->getPasswdHash()
            )
        );

        $this->assertFalse(
            $accessor->getPasswdTransformer()->verifyObfuscatedPasswd(
                $alice1Obfuscated,
                $alice2->getPasswdHash()
            )
        );

        sleep(3);

        // alice 1 is expired now
        $this->assertNull($accessor->get($alice1Obfuscated, 'alice'));

        // unlike the previous invocation, this tests the case that no
        // record is found in the table
        $this->assertNull($accessor->get($alice1Obfuscated, 'alice'));

        // alice 2 is still there
        $this->assertInstanceOf(
            OpenInstRecord::class,
            $accessor->get($alice2Obfuscated, 'alice')
        );

        sleep(2);

        // now alice 2 is expired as well
        $this->assertNull($accessor->get($alice2Obfuscated, 'alice'));

        $this->expectException(DataNotFound::class);

        $this->expectExceptionMessage(
            'Data not found in table "foo_open_inst" for key'
        );

        $accessor->remove($alice1->getPasswdHash());
    }
}
