<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class RemovePeriodColumnsFromScorecardEvaluations extends AbstractMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('scorecard_evaluations');
        
        // Drop the index that depends on the columns we're removing
        $table->removeIndex(['performance_period', 'period_value'], ['name' => 'idx_scorecard_evaluations_period']);
        
        // Remove the period-related columns
        $table->removeColumn('performance_period');
        $table->removeColumn('period_value');
        
        // Add a new index for better performance on evaluation_date
        $table->addIndex(['evaluation_date'], ['name' => 'idx_scorecard_evaluations_date_improved']);
        
        $table->update();
    }
}
