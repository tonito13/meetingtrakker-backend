<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddUserDataToAuditLogs extends AbstractMigration
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
        // Check if column already exists before adding
        $table = $this->table('audit_logs');
        
        if (!$table->hasColumn('user_data')) {
            $table->addColumn('user_data', 'jsonb', [
                'default' => null,
                'null' => true,
                'comment' => 'Additional user information (employee_name, etc.)',
                'after' => 'error_message'
            ]);
            $table->update();
        }
    }
}

