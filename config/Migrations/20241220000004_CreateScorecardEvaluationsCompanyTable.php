<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateScorecardEvaluationsCompanyTable extends AbstractMigration
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
        $table = $this->table('scorecard_evaluations', [
            'id' => false,
            'primary_key' => ['id']
        ]);
        
        $table->addColumn('id', 'integer', [
            'autoIncrement' => true,
            'default' => null,
            'limit' => 11,
            'null' => false,
        ]);
        
        $table->addColumn('scorecard_unique_id', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
            'comment' => 'Reference to the scorecard being evaluated'
        ]);
        
        $table->addColumn('evaluator_username', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
            'comment' => 'Username of the person performing the evaluation'
        ]);
        
        $table->addColumn('evaluated_employee_username', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
            'comment' => 'Username of the employee being evaluated'
        ]);
        
        $table->addColumn('performance_period', 'string', [
            'default' => null,
            'limit' => 50,
            'null' => false,
            'comment' => 'Monthly, Quarterly, or Annual'
        ]);
        
        $table->addColumn('period_value', 'string', [
            'default' => null,
            'limit' => 50,
            'null' => false,
            'comment' => 'M1-M12 for monthly, Q1-Q4 for quarterly, Y1 for annual'
        ]);
        
        $table->addColumn('grade', 'decimal', [
            'precision' => 5,
            'scale' => 2,
            'default' => null,
            'null' => true,
            'comment' => 'Numerical grade given (0-100)'
        ]);
        
        $table->addColumn('notes', 'text', [
            'default' => null,
            'null' => true,
            'comment' => 'Evaluation notes and comments'
        ]);
        
        $table->addColumn('evaluation_date', 'date', [
            'default' => null,
            'null' => false,
            'comment' => 'Date when the evaluation was performed'
        ]);
        
        $table->addColumn('status', 'string', [
            'default' => 'draft',
            'limit' => 20,
            'null' => false,
            'comment' => 'draft, submitted, approved, rejected'
        ]);
        
        $table->addColumn('created', 'datetime', [
            'default' => null,
            'null' => false,
        ]);
        
        $table->addColumn('modified', 'datetime', [
            'default' => null,
            'null' => false,
        ]);
        
        $table->addColumn('deleted', 'boolean', [
            'default' => false,
            'null' => false,
        ]);
        
        $table->addIndex(['scorecard_unique_id'], ['name' => 'idx_scorecard_evaluations_scorecard_id']);
        $table->addIndex(['evaluator_username'], ['name' => 'idx_scorecard_evaluations_evaluator']);
        $table->addIndex(['evaluated_employee_username'], ['name' => 'idx_scorecard_evaluations_employee']);
        $table->addIndex(['performance_period', 'period_value'], ['name' => 'idx_scorecard_evaluations_period']);
        $table->addIndex(['status'], ['name' => 'idx_scorecard_evaluations_status']);
        $table->addIndex(['evaluation_date'], ['name' => 'idx_scorecard_evaluations_date']);
        
        $table->create();
    }
}
