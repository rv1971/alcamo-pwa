<?php

namespace alcamo\pwa;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

class Mailer extends PHPMailer
{
    public const CHARSET = self::CHARSET_UTF8;

    public const TESTMAIL_SUBJECT = 'Testmail from ' . __CLASS__;

    public const TESTMAIL_BODY = <<<EOD
<!DOCTYPE html>

<html>
  <head>
    <meta charset="UTF-8"/>
  </head>

  <body>
    <p><a href="https://loremipsum.de/">Lorem ipsum</a> dolor sit amet,
      consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt
      ut labore et dolore magna aliquyam erat, sed diam voluptua.</p>
  </body>
</html>

EOD;

    public const TESTMAIL_CONTENT_TYPE = self::CONTENT_TYPE_TEXT_HTML;

     /**
     * @param $conf array or ArrayAccess object containing
     * - `host`
     * - `port`
     * - `username`
     * - `passwd`
     * - `from`
     * - `?bool debug`
     */
    public function newFromConf(iterable $conf): self
    {
        return new self(
            $conf['host'],
            $conf['port'],
            $conf['username'],
            $conf['passwd'],
            $conf['from'],
            $conf['debug'] ?? null
        );
    }

    public function __construct(
        string $host,
        string $port,
        string $username,
        string $passwd,
        string $from,
        ?bool $debug = null
    ) {
        parent::__construct(true);

        if ($debug) {
            $this->SMTPDebug = SMTP::DEBUG_SERVER;
        }

        $this->isSMTP();
        $this->Host = $host;
        $this->Port = $port;
        $this->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->SMTPAuth = true;
        $this->Username = $username;
        $this->Password = $passwd;

        $this->setFrom($from);
        $this->CharSet = static::CHARSET;
    }

    public function sendMail(
        string $to,
        string $subject,
        string $body,
        string $contentType,
        ?string $cc = null
    ): bool {
        $this->clearAllRecipients();
        $this->clearAttachments();
        $this->clearCustomHeaders();

        $this->addAddress($to);
        $this->Subject = $subject;
        $this->Body = $body;
        $this->ContentType = $contentType;

        if (isset($cc)) {
            $this->addCC($cc);
        }

        return $this->send();
    }

    public function sendTestMail(string $to): bool
    {
        return $this->sendMail(
            $to,
            static::TESTMAIL_SUBJECT,
            static::TESTMAIL_BODY,
            static::TESTMAIL_CONTENT_TYPE
        );
    }
}
