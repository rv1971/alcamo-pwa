<?php

namespace alcamo\pwa;

use PHPUnit\Framework\TestCase;

class PasswdTransformerTest extends TestCase
{
    /**
     * @dataProvider basicsProvider
     */
    public function testBasics($passwdKey)
    {
        $transformer = new PasswdTransformer($passwdKey);

        $passwd = $transformer->createPasswd();

        $this->assertSame(
            PasswdTransformer::PASSWD_LENGTH,
            strlen($passwd)
        );

        $passwd2 = $transformer->createPasswd();

        $this->assertTrue($passwd2 != $passwd);

        $obfuscated = $transformer->obfuscatePasswd($passwd);

        $this->assertSame(
            PasswdTransformer::PASSWD_LENGTH,
            strlen($obfuscated)
        );

        $this->assertSame(
            $passwd,
            $transformer->unobfuscatePasswd($obfuscated)
        );

        $this->assertTrue($obfuscated != $passwd);

        $this->assertSame(
            $passwd,
            $transformer->unobfuscatePasswd($obfuscated)
        );

        $hash = $transformer->createHash($passwd);

        $hash2 = $transformer->createHash($passwd2);

        $this->assertTrue(
            $transformer->verifyObfuscatedPasswd($obfuscated, $hash)
        );

        $this->assertFalse(
            $transformer->verifyObfuscatedPasswd($passwd, $hash)
        );

        $this->assertFalse(
            $transformer->verifyObfuscatedPasswd($obfuscated, $hash2)
        );

        $this->assertTrue(
            $transformer->verifyObfuscatedPasswd(
                $transformer->obfuscatePasswd($passwd2),
                $hash2
            )
        );
    }

    public function basicsProvider()
    {
        return [
            [ random_bytes(4) ],
            [ random_bytes(64) ],
        ];
    }
}
