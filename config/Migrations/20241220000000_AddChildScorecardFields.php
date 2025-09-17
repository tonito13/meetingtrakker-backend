<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddChildScorecardFields extends AbstractMigration
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
        $table = $this->table('scorecard_template_answers');
        
        // Add parent scorecard reference
        $table->addColumn('parent_scorecard_id', 'integer', [
            'null' => true,
            'default' => null
        ]);
        
        // Add assigned employee reference
        $table->addColumn('assigned_employee_id', 'integer', [
            'null' => true,
            'default' => null
        ]);
        
        // Add index for parent scorecard lookups
        $table->addIndex(['parent_scorecard_id'], [
            'name' => 'idx_parent_scorecard_id'
        ]);
        
        // Add index for assigned employee lookups
        $table->addIndex(['assigned_employee_id'], [
            'name' => 'idx_assigned_employee_id'
        ]);
        
        $table->update();
    }
}
