<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateAuditLogsCompanyTable extends AbstractMigration
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
        $table = $this->table('audit_logs', [
            'id' => false,
            'primary_key' => ['id']
        ]);
        
        $table->addColumn('id', 'uuid', [
            'default' => null,
            'null' => false,
        ]);
        
        $table->addColumn('company_id', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
        ]);
        
        $table->addColumn('user_id', 'integer', [
            'default' => null,
            'null' => false,
        ]);
        
        $table->addColumn('username', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
        ]);
        
        $table->addColumn('action', 'string', [
            'default' => null,
            'limit' => 100,
            'null' => false,
            'comment' => 'CREATE, UPDATE, DELETE, LOGIN, LOGOUT, EVALUATE, etc.'
        ]);
        
        $table->addColumn('entity_type', 'string', [
            'default' => null,
            'limit' => 100,
            'null' => false,
            'comment' => 'scorecard, employee, job_role, evaluation, user, etc.'
        ]);
        
        $table->addColumn('entity_id', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => true,
            'comment' => 'The unique identifier of the affected entity'
        ]);
        
        $table->addColumn('entity_name', 'string', [
            'default' => null,
            'limit' => 500,
            'null' => true,
            'comment' => 'Human-readable name of the affected entity'
        ]);
        
        $table->addColumn('description', 'text', [
            'default' => null,
            'null' => true,
            'comment' => 'Human-readable description of what happened'
        ]);
        
        $table->addColumn('ip_address', 'string', [
            'default' => null,
            'limit' => 45,
            'null' => true,
            'comment' => 'IPv4 or IPv6 address'
        ]);
        
        $table->addColumn('user_agent', 'text', [
            'default' => null,
            'null' => true,
            'comment' => 'Browser/client information'
        ]);
        
        $table->addColumn('request_data', 'jsonb', [
            'default' => null,
            'null' => true,
            'comment' => 'Request parameters and data'
        ]);
        
        $table->addColumn('response_data', 'jsonb', [
            'default' => null,
            'null' => true,
            'comment' => 'Response data if relevant'
        ]);
        
        $table->addColumn('status', 'string', [
            'default' => 'success',
            'limit' => 20,
            'null' => false,
            'comment' => 'success, error, warning'
        ]);
        
        $table->addColumn('error_message', 'text', [
            'default' => null,
            'null' => true,
            'comment' => 'Error message if status is error'
        ]);
        
        $table->addColumn('created', 'timestamp', [
            'default' => 'CURRENT_TIMESTAMP',
            'null' => false,
        ]);
        
        $table->addColumn('modified', 'timestamp', [
            'default' => 'CURRENT_TIMESTAMP',
            'null' => false,
        ]);
        
        $table->addIndex(['company_id']);
        $table->addIndex(['user_id']);
        $table->addIndex(['username']);
        $table->addIndex(['action']);
        $table->addIndex(['entity_type']);
        $table->addIndex(['entity_id']);
        $table->addIndex(['created']);
        $table->addIndex(['status']);
        $table->addIndex(['company_id', 'created']);
        $table->addIndex(['company_id', 'entity_type', 'created']);
        $table->addIndex(['company_id', 'action', 'created']);
        
        $table->create();
    }
}
