<?php

namespace OnPage;

use Illuminate\Console\Command;

class Import extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'onpage:import';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->comment('hello-world');
        $token = env('ONPAGE_TOKEN');
        $url = "https://lithos.onpage.it/api/view/$token/dist";
        $info = \json_decode(\file_get_contents($url));
        $fileurl = "https://lithos.onpage.it/api/storage/$info->token";
        $snapshot = json_decode(\file_get_contents($fileurl));
        // print_r($snapshot);
        echo "$fileurl\n\n";
        print_r($snapshot->langs);
    }
}
