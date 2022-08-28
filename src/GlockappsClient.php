<?php declare(strict_types=1);

namespace App;

use GuzzleHttp\Client;

class GlockappsClient
{
    private $glockappsKey;

    public function __construct(string $glockappsKey)
    {
        $this->glockappsKey = $glockappsKey;
    }

    public function createTest(string $note, int $groups): array
    {
        $client = new Client();
        $response = $client->post('https://spamtest.glockapps.com/api/v1/CreateTest', ['query' => [
            'apikey' => $this->glockappsKey,
            'groups' => $groups,
            'v' => '2',
            'Note' => mb_substr($note, 0, 150),
        ]]);
        return json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
    }

    public function getProviders(): array
    {
        $client = new Client();
        $response = $client->get('https://spamtest.glockapps.com/api/v1/GetProviders', ['query' => [
            'apikey' => $this->glockappsKey,
            'v' => '2',
        ]]);
        return json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
    }


    public function getTestList(string $period): array
    {
        $dateFrom = (new \DateTime($period))->format('Y-m-d');
        $client = new Client();
        $response = $client->get('https://spamtest.glockapps.com/api/v1/GetTestList', ['query' => [
            'apikey' => $this->glockappsKey,
            'DateFrom' => $dateFrom,
            'OrderBy' => 'Created DESC',
            'limit' => 1000,
        ]]);
        return json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
    }

    public function accountMatchesTest(array $account, array $test): bool
    {
        $fromMatches = (false !== stripos($test['From'], $account['fromName'])) && (false !== stripos($test['From'], $account['fromEmail']));
        $serverMatches = false !== stripos($account['dsn'], $test['SenderHostName']);
        $noteMatches = $test['Note'] === $account['note'];
        return ($fromMatches && $serverMatches) || $noteMatches;
    }
}
