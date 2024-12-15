<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Typesense\Client;

class UpdateTypesenseSynonyms extends Command
{
    // php artisan typesense:update-synonyms
    protected $signature = 'typesense:update-synonyms';
    protected $description = '1주일 이내 동의어를 업데이트 합니다.';

    public function __construct()
    {
        parent::__construct();
    }

    private function getSynonyms()
    {
        return config('synonyms');
    }

    public function handle()
    {
        $client = new Client([
            'api_key' => config('globalvar.env.TYPESENSE_API_KEY'),
            'nodes' => [
                [
                    'host' => config('globalvar.env.TYPESENSE_HOST'),
                    'port' => config('globalvar.env.TYPESENSE_PORT'),
                    'protocol' => config('globalvar.env.TYPESENSE_PROTOCOL'),
                ],
            ],
            'connection_timeout_seconds' => 2
        ]);

        $aSynonyms = $this->getSynonyms();
        $strNow = Carbon::now();
        $strAWeekAge = $strNow->copy()->subWeek();
        $strAWeekLater = $strNow->copy()->addWeek();

        $iNotUpload = 0;
        foreach ($aSynonyms as $division => $item) {
            foreach ($item as $key => $val) {
                try {
                    $strSync = Carbon::createFromFormat('Ymd', $val['date']);
                    if ($strSync->isBetween($strAWeekAge, $strAWeekLater, true)) {
                        $client->collections[$division]->synonyms->upsert($key, $val);
                        $this->info("Synonym '{$key}' updated successfully");
                    } else {
                        $iNotUpload++;
                    }
                } catch (\Exception $e) {
                    $this->error("Failed to update synonym '{$key}': " . $e->getMessage());
                }
            }
        }
        $this->info('일주일 이내가 아님 count(' . $iNotUpload . ')');
    }
}
