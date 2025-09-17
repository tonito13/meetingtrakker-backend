<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class UpdateScorecardAssignmentSystem extends AbstractMigration
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
        
        // Change assigned_employee_id to assigned_employee_username
        $table->changeColumn('assigned_employee_id', 'string', [
            'null' => true,
            'default' => null,
            'length' => 255,
            'comment' => 'Username of the assigned employee'
        ]);
        
        // Rename the column to be more descriptive
        $table->renameColumn('assigned_employee_id', 'assigned_employee_username');
        
        // Add created_by column to track who created the scorecard
        $table->addColumn('created_by', 'string', [
            'null' => true,
            'default' => null,
            'length' => 255,
            'comment' => 'Username of the user who created the scorecard'
        ]);
        
        // Add index for assigned employee username lookups
        $table->addIndex(['assigned_employee_username'], [
            'name' => 'idx_assigned_employee_username'
        ]);
        
        // Add index for created_by lookups
        $table->addIndex(['created_by'], [
            'name' => 'idx_created_by'
        ]);
        
        $table->update();
    }
}
