<?php

namespace OnPage;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class Rollback extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'onpage:rollback';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import data from Storage';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle() {
        
        $files = Storage::disk('local')->files('snapshots');  
        if (count($files) > 0) {
            foreach($files as $key => $file) {
                $time = Storage::lastModified($file);
                echo "[". $key . "] => " . date("Y_m_d_His",$time) . "\n";
            }
            $snap = $this->ask('Which snapshot do you want to rollback?');
            while (!(isset($files[$snap]))) {
                if($snap == 'exit') {
                    return null;
                }
                foreach($files as $key => $file) {
                    $time = Storage::lastModified($file);
                    echo "[". $key . "] => " . date("Y_m_d_His",$time) . "\n";
                }
                $this->error('Insert a valid number');
                $snap = $this->ask('Which snapshot do you want to rollback? [exit for cancel]');
            }

            $this->call('onpage:import', [ 'snapshot_file' => $files[$snap]]);
        } else {
            $this->comment('Nothing to rollback');
        }

    }
}