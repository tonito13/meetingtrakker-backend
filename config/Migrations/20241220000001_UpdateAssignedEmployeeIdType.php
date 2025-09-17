<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class UpdateAssignedEmployeeIdType extends AbstractMigration
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
        
        // Change assigned_employee_id from integer to string to store employee unique IDs
        $table->changeColumn('assigned_employee_id', 'string', [
            'null' => true,
            'default' => null,
            'length' => 255
        ]);
        
        $table->update();
    }
}
