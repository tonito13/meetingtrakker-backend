<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateJobRoleTemplateAnswersTable extends AbstractMigration
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
        $table = $this->table('job_role_template_answers');
        $table->addColumn('company_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => false,
        ]);
        $table->addColumn('job_role_unique_id', 'string', [
            'default' => null,
            'limit' => 150,
            'null' => false,
        ]);
        $table->addColumn('template_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => false,
        ]);
        $table->addColumn('answers', 'text', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('deleted', 'boolean', [
            'default' => false,
            'null' => false,
        ]);
        $table->addColumn('created', 'datetime', [
            'default' => 'CURRENT_TIMESTAMP',
            'null' => true,
        ]);
        $table->addColumn('modified', 'datetime', [
            'default' => 'CURRENT_TIMESTAMP',
            'null' => true,
        ]);
        $table->addPrimaryKey(['id']);
        $table->create();
    }
}
