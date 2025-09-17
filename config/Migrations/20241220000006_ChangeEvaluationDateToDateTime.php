<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class ChangeEvaluationDateToDateTime extends AbstractMigration
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
        
        // Change evaluation_date from date to datetime
        $table->changeColumn('evaluation_date', 'datetime', [
            'default' => null,
            'null' => false,
            'comment' => 'Date and time when the evaluation was performed'
        ]);
        
        $table->update();
    }
}
