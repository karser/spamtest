<?php declare(strict_types=1);

namespace App\Command;

use App\EmailSender;
use App\GlockappsClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Twig\Environment;
use Webmozart\Assert\Assert;

class ReportCommand extends Command
{
    protected static $defaultName = 'app:report';

    private $twig;

    public function __construct(Environment $twig)
    {
        parent::__construct();
        $this->twig = $twig;
    }

    public function configure(): void
    {
        $this->addOption('glockapps-key', null,InputOption::VALUE_REQUIRED);
        $this->addOption('accounts-path', null,InputOption::VALUE_REQUIRED);
        $this->addOption('report-dsn', null,InputOption::VALUE_REQUIRED);
        $this->addOption('report-from-email', null,InputOption::VALUE_REQUIRED);
        $this->addOption('report-from-name', null,InputOption::VALUE_REQUIRED);
        $this->addOption('report-period', null,InputOption::VALUE_REQUIRED, '', '-365 days');
        $this->addOption('recipient-email', null,InputOption::VALUE_REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $accountsPath = $input->getOption('accounts-path') ?? getenv('ACCOUNTS_PATH');
        Assert::string($accountsPath, '--accounts-path or ACCOUNTS_PATH is required');
        Assert::fileExists($accountsPath);
        $accounts = json_decode(file_get_contents($accountsPath), true, 512, JSON_THROW_ON_ERROR);
        $recipientEmail = $input->getOption('recipient-email') ?? getenv('RECIPIENT_EMAIL');
        Assert::string($recipientEmail, '--recipient-email or RECIPIENT_EMAIL is required');
        $recipientEmail = explode(',', $recipientEmail);

        $reportDsn = $input->getOption('report-dsn') ?? getenv('REPORT_DSN');
        Assert::string($reportDsn, '--report-dsn or REPORT_DSN is required');
        $reportFromEmail = $input->getOption('report-from-email') ?? getenv('REPORT_FROM_EMAIL');
        Assert::string($reportFromEmail, '--report-from-email or REPORT_FROM_EMAIL is required');
        $reportFromName = $input->getOption('report-from-name') ?? getenv('REPORT_FROM_NAME');
        Assert::string($reportFromName, '--report-from-name or REPORT_FROM_NAME is required');
        $reportPeriod = $input->getOption('report-period') ?? getenv('REPORT_PERIOD');
        Assert::string($reportPeriod, '--report-period or REPORT_PERIOD is required');

        $key = $input->getOption('glockapps-key') ?? getenv('GLOCKAPPS_KEY');
        Assert::string($key);
        $gc = new GlockappsClient($key);

        $tests = $gc->getTestList($reportPeriod)['Items'];
        foreach ($accounts as &$account) {
            foreach ($tests as $test) {
                if ($gc->accountMatchesTest($account, $test)) {
                    $account['tests'][] = $test;
                }
            }
        }
        $bodyHtml = $this->twig->render('email-report.html.twig', ['accounts' => $accounts]);
        $ess = new EmailSender('Spamtest Report', $bodyHtml);
        $ess->sendEmails($reportDsn, $recipientEmail, $reportFromEmail, $reportFromName);

        return self::SUCCESS;

    }
}
