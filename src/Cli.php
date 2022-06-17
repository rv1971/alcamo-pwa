<?php

namespace alcamo\pwa;

use alcamo\cli\AbstractCli;
use GetOpt\{GetOpt, Operand};

class Cli extends AbstractCli
{
    public const OPTIONS =
        [
            'smtp-host' => [
                null,
                GetOpt::REQUIRED_ARGUMENT,
                'Set SMTP host'
            ],
            'smtp-port' => [
                null,
                GetOpt::REQUIRED_ARGUMENT,
                'Set SMTP port'
            ],
            'smtp-username' => [
                null,
                GetOpt::REQUIRED_ARGUMENT,
                'Set SMTP username'
            ],
            'smtp-passwd' => [
                null,
                GetOpt::REQUIRED_ARGUMENT,
                'Set SMTP password'
            ],
            'smtp-from' => [
                null,
                GetOpt::REQUIRED_ARGUMENT,
                'Set SMTP from address'
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

        if (!$this->getCommand()) {
            $this->showHelp();
            return 0;
        }

        if ($this->getOption('smtp-host')) {
            $this->params_['smtp']['host'] =
                $this->getOption('smtp-host');
        }

        if ($this->getOption('smtp-port')) {
            $this->params_['smtp']['port'] =
                $this->getOption('smtp-port');
        }

        if ($this->getOption('smtp-username')) {
            $this->params_['smtp']['username'] =
                $this->getOption('smtp-username');
        }

        if ($this->getOption('smtp-passwd')) {
            $this->params_['smtp']['passwd'] =
                $this->getOption('smtp-passwd');
        }

        if ($this->getOption('smtp-from')) {
            $this->params_['smtp']['from'] =
                $this->getOption('smtp-from');
        }

        if ($this->getOption('verbose') > 0) {
            $this->params_['smtp']['debug'] = true;
        }

        if (isset($this->params_['db'])) {
            $this->accountMgr_ =
                AccountMgr::newFromParams($this->params_['db']);
        }

        if (isset($this->params_['smtp'])) {
            $this->mailer_ = Mailer::newFromParams($this->params_['smtp']);
        }

        return $this->{$this->getCommand()->getHandler()}();
    }

    public function setupDatabase(): int
    {
        $this->getAccountMgr()->createTables();

        return 0;
    }

    public function testSmtp(): int
    {
        $this->getMailer()->sendTestMail($this->getOperand('to'));

        return 0;
    }
}
