<?php declare(strict_types=1);

namespace App\Command;

use App\EmailSender;
use App\GlockappsClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\Assert\Assert;

class SpamTestCommand extends Command
{
    protected static $defaultName = 'app:spamtest';

    public function configure(): void
    {
        $this->addOption('glockapps-key', null,InputOption::VALUE_REQUIRED);
        $this->addOption('accounts-path', null,InputOption::VALUE_REQUIRED);
        $this->addOption('subject', null,InputOption::VALUE_REQUIRED);
        $this->addOption('body-path', null,InputOption::VALUE_REQUIRED);
        $this->addOption('test-period', null,InputOption::VALUE_REQUIRED, '', '-7 days');
        $this->addOption('verify', '', InputOption::VALUE_NONE, 'Verify account without creating a Glockapps test');
        $this->addOption('recipient-email', null,InputOption::VALUE_REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $accountsPath = $input->getOption('accounts-path') ?? getenv('ACCOUNTS_PATH');
        Assert::string($accountsPath, '--accounts-path or ACCOUNTS_PATH is required');
        Assert::fileExists($accountsPath);
        $accounts = json_decode(file_get_contents($accountsPath), true, 512, JSON_THROW_ON_ERROR);
        $subject = $input->getOption('subject') ?? getenv('SUBJECT');
        Assert::string($subject, '--subject or SUBJECT is required');
        $bodyPath = $input->getOption('body-path') ?? getenv('BODY_PATH');
        Assert::string($bodyPath, '--body-path or BODY_PATH is required');
        Assert::fileExists($bodyPath);
        $bodyHtml = file_get_contents($bodyPath);
        $testPeriod = $input->getOption('test-period') ?? getenv('TEST_PERIOD');
        Assert::string($testPeriod, '--test-period or TEST_PERIOD is required');
        $verify = $input->getOption('verify') === true;
        $recipientEmail = $input->getOption('recipient-email') ?? getenv('RECIPIENT_EMAIL');
        Assert::string($recipientEmail, '--recipient-email or RECIPIENT_EMAIL is required');
        $recipientEmail = explode(',', $recipientEmail);

        $es = new EmailSender($subject, $bodyHtml);

        if ($verify) {
            foreach ($accounts as $account) {
                $output->write($account['dsn'].'...');
                $es->sendEmails($account['dsn'], $recipientEmail, $account['fromEmail'], $account['fromName'], '', $account['note']);
                $output->writeln(' OK');
            }
            return self::SUCCESS;
        }

        $key = $input->getOption('glockapps-key') ?? getenv('GLOCKAPPS_KEY');
        Assert::string($key);
        $gc = new GlockappsClient($key);

        $accounts = $this->filterAccounts($accounts, $gc, $testPeriod);
        if ($accounts === []) {
            $output->writeln('No matching accounts');
            return self::SUCCESS;
        }

        $providers = $gc->getProviders();
        $selectAllGroups = array_sum(array_column($providers['Groups'], 'GroupID'));
        foreach ($accounts as $account) {
            $note = $account['note'];
            $data = $gc->createTest($note, $selectAllGroups);
            $testId = $data['TestID'];
            $recipients = $data['SeedList'];
            $output->writeln(sprintf("%s (%d) https://shared-report.glockapps.com/tests/%s", $note, count($recipients), $testId));
            $es->sendEmails($account['dsn'], $recipients, $account['fromEmail'], $account['fromName'], $testId, null);
        }
        return self::SUCCESS;
    }

    private function filterAccounts(array $accounts, GlockappsClient $gc, string $testPeriod): array
    {
        $tests = $gc->getTestList($testPeriod)['Items'];
        return array_values(array_filter($accounts, static function (array $account) use ($tests, $gc) {
            foreach ($tests as $test) {
                if ($gc->accountMatchesTest($account, $test)) {
                    return false;
                }
            }
            return true;
        }));
    }
}
