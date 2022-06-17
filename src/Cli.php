<?php

namespace alcamo\pwa;

use alcamo\cli\AbstractCli;
use GetOpt\{GetOpt, Operand};

class Cli extends AbstractCli
{
    public const OPTIONS =
        [
            'json-config-file' => [
                'j',
                GetOpt::REQUIRED_ARGUMENT,
                'Read conf from this JSON file'
            ]
        ]
        + parent::OPTIONS;

    public const COMMANDS = [
        'setup-database' => [
            'setupDatabase',
            [],
            [],
            'Setup the database'
        ],
        'test-database' => [
            'testDatabase',
            [],
            [],
            'Test whether database is accessible'
        ],
        'test-smtp' => [
            'testSmtp',
            [],
            [ 'to' => Operand::REQUIRED ],
            'Test the SMTP server'
        ]
    ];

    protected $params_;
    protected $accountMgr_;
    protected $mailer_;

    /**
     * @param $params array or ArrayAccess object containing
     * - `db`
     * - `smtp`
     */
    public function __construct(iterable $params)
    {
        parent::__construct();

        $this->params_ = $params;
    }

    public function getParams(): array
    {
        return $this->params_;
    }

    public function getAccountMgr(): AccountMgr
    {
        return $this->accountMgr_;
    }

    public function getMailer(): Mailer
    {
        return $this->mailer_;
    }

    public function process($arguments = null): int
    {
        parent::process($arguments);

        if ($this->getOption('json-config-file')) {
            $this->params_ = json_decode(
                file_get_contents($this->getOption('json-config-file')),
                true
            );
        }

        $this->accountMgr_ = AccountMgr::newFromParams($this->params_);

        if ($this->getOption('verbose') > 0) {
            $this->params_['smtp']['debug'] = true;
        }

        $this->mailer_ = Mailer::newFromParams($this->params_['smtp']);

        if (!$this->getCommand()) {
            $this->showHelp();
            return 0;
        }

        return $this->{$this->getCommand()->getHandler()}();
    }

    public function setupDatabase(): int
    {
        $this->getAccountMgr()->createTables();

        return 0;
    }

    public function testDatabase(): int
    {
        /* Nothing extra to do since the constructor of AccountMgr called in
         * process() fails if the database is not accessible. */
        return 0;
    }

    public function testSmtp(): int
    {
        $this->getMailer()->sendTestMail($this->getOperand('to'));

        return 0;
    }
}
