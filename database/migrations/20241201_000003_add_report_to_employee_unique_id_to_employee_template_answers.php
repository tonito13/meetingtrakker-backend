<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddReportToEmployeeUniqueIdToEmployeeTemplateAnswers extends AbstractMigration
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
        $table = $this->table('employee_template_answers');
        
        $table->addColumn('report_to_employee_unique_id', 'string', [
            'limit' => 150,
            'null' => true,
            'default' => null,
            'comment' => 'Employee unique ID of the person this employee reports to',
        ]);
        
        // Add index for performance
        $table->addIndex(['report_to_employee_unique_id'], [
            'name' => 'idx_employee_template_answers_report_to'
        ]);
        
        $table->update();
    }
}
