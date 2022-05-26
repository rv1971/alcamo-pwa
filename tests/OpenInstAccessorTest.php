<?php

namespace alcamo\pwa;

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

        $alice1 = $accessor->get('alice');

        $this->assertTrue(
            $accessor->getPasswdTransformer()->verifyObfuscatedPasswd(
                $alice1Obfuscated,
                $alice1->getPasswdHash()
            )
        );

        sleep(1);

        $alice2Obfuscated = $accessor->add('alice');

        // still gets the first record
        $alice1a = $accessor->get('alice');

        $this->assertEquals($alice1, $alice1a);

        $this->assertFalse(
            $accessor->getPasswdTransformer()->verifyObfuscatedPasswd(
                $alice2Obfuscated,
                $alice1a->getPasswdHash()
            )
        );

        sleep(3);

        // now the first alice is expired, and get() returns the second one
        $alice2 = $accessor->get('alice');

        $this->assertTrue(
            $accessor->getPasswdTransformer()->verifyObfuscatedPasswd(
                $alice2Obfuscated,
                $alice2->getPasswdHash()
            )
        );
    }
}
