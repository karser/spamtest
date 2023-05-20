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
        $this->addOption('accounts-path', null,InputOption::VALUE_REQUIRED, 'Path to a json file with accounts');
        $this->addOption('subject', null,InputOption::VALUE_REQUIRED, 'Email subject');
        $this->addOption('body-path', null,InputOption::VALUE_REQUIRED, 'Path to email body in html format');
        $this->addOption('min-interval', null,InputOption::VALUE_REQUIRED, 'Minimum testing interval within an account. Works as foolproof in case if command runs multiple times in a row', '-10 days');
        $this->addOption('validate-only', '', InputOption::VALUE_NONE, 'Validate DSN instead of creating the Glockapps test');
        $this->addOption('email-only', '', InputOption::VALUE_NONE, 'Send a test mail to the recipient-email instead of creating the Glockapps test');
        $this->addOption('recipient-email', null,InputOption::VALUE_REQUIRED, 'Required if email-only option is specified');
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
        $minInterval = $input->getOption('min-interval') ?? getenv('MIN_INTERVAL');
        Assert::string($minInterval, '--min-interval or MIN_INTERVAL is required');
        $validateOnly = $input->getOption('validate-only') === true;
        $emailOnly = $input->getOption('email-only') === true;

        $es = new EmailSender($subject, $bodyHtml);

        if ($emailOnly) {
            $recipientEmail = $input->getOption('recipient-email') ?? getenv('RECIPIENT_EMAIL');
            Assert::string($recipientEmail, '--recipient-email or RECIPIENT_EMAIL is required');
            $recipientEmail = explode(',', $recipientEmail);

            foreach ($accounts as $account) {
                $output->write('- '.$account['dsn'].'...');
                try {
                    $es->sendEmails($account['dsn'], $recipientEmail, $account['fromEmail'], $account['fromName'], '', $account['note']);
                    $output->writeln(' OK');
                } catch (\Exception $e) {
                    $output->writeln(' Error: '.$e->getMessage());
                }
            }
            return self::SUCCESS;
        }
        if ($validateOnly) {
            foreach ($accounts as $account) {
                $output->write('- Validating '.$account['dsn'].'...');
                try {
                    $es->validateDsn($account['dsn']);
                    $output->writeln(' OK');
                } catch (\Exception $e) {
                    $output->writeln(' Error: '.$e->getMessage());
                }
            }
            return self::SUCCESS;
        }

        $key = $input->getOption('glockapps-key') ?? getenv('GLOCKAPPS_KEY');
        Assert::string($key);
        $gc = new GlockappsClient($key);

        $accounts = $this->filterAccounts($accounts, $gc, $minInterval);
        if ($accounts === []) {
            $output->writeln('No matching accounts');
            return self::SUCCESS;
        }

        $providers = $gc->getProviders();
        $selectAllGroups = array_sum(array_column($providers['Groups'], 'GroupID'));
        foreach ($accounts as $account) {
            $output->write('- Validating '.$account['dsn'].'...');
            try {
                $es->validateDsn($account['dsn']);
                $output->writeln(' OK');
            } catch (\Exception $e) {
                $output->writeln(' Error: '.$e->getMessage());
                continue;
            }
            $note = $account['note'];
            $data = $gc->createTest($note, $selectAllGroups);
            $testId = $data['TestID'];
            $recipients = $data['SeedList'];
            $output->writeln(sprintf("%s (%d) https://shared-report.glockapps.com/tests/%s", $note, count($recipients), $testId));
            $es->sendEmails($account['dsn'], $recipients, $account['fromEmail'], $account['fromName'], $testId, null);
        }
        return self::SUCCESS;
    }

    private function filterAccounts(array $accounts, GlockappsClient $gc, string $minInterval): array
    {
        $tests = $gc->getTestList($minInterval)['Items'];
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
