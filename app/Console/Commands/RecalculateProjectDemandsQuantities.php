<?php

namespace App\Console\Commands;

use App\Models\ProjectDemand;
use Illuminate\Console\Command;

class RecalculateProjectDemandsQuantities extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'projects:recalculate-demands';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalcular quantidades entregues de todas as demandas baseado nas entregas aprovadas';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Recalculando quantidades entregues...');
        
        $demands = ProjectDemand::with('deliveries')->get();
        $bar = $this->output->createProgressBar($demands->count());
        $bar->start();
        
        foreach ($demands as $demand) {
            $demand->updateDeliveredQuantity();
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine(2);
        $this->info('✓ Recálculo concluído com sucesso!');
        
        return Command::SUCCESS;
    }
}
