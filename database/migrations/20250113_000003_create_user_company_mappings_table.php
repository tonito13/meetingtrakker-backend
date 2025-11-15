<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateUserCompanyMappingsTable extends AbstractMigration
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
        $table = $this->table('user_company_mappings', [
            'id' => false,
            'primary_key' => ['id']
        ]);
        
        $table->addColumn('id', 'integer', [
            'autoIncrement' => true,
            'limit' => 11,
            'null' => false,
        ]);
        
        $table->addColumn('user_id', 'integer', [
            'limit' => 11,
            'null' => false,
            'comment' => 'User ID in the target system'
        ]);
        
        $table->addColumn('username', 'string', [
            'limit' => 100,
            'null' => false,
            'comment' => 'Username for identification'
        ]);
        
        $table->addColumn('mapped_company_id', 'integer', [
            'limit' => 11,
            'null' => false,
            'comment' => 'Company ID in the target system (where user is mapped)'
        ]);
        
        $table->addColumn('source_company_id', 'integer', [
            'limit' => 11,
            'null' => false,
            'comment' => 'Company ID in the source system (where user originated)'
        ]);
        
        $table->addColumn('system_type', 'string', [
            'limit' => 50,
            'null' => false,
            'comment' => 'System type: orgtrakker, scorecardtrakker, skiltrakker, tickettrakker'
        ]);
        
        $table->addColumn('active', 'boolean', [
            'null' => false,
            'default' => true,
            'comment' => 'Whether this mapping is active'
        ]);
        
        $table->addColumn('deleted', 'boolean', [
            'null' => false,
            'default' => false,
            'comment' => 'Soft delete flag'
        ]);
        
        $table->addColumn('created', 'timestamp', [
            'default' => 'CURRENT_TIMESTAMP',
            'null' => false,
        ]);
        
        $table->addColumn('modified', 'timestamp', [
            'default' => 'CURRENT_TIMESTAMP',
            'null' => false,
        ]);
        
        // Add indexes
        $table->addIndex(['user_id'], ['name' => 'idx_user_company_mappings_user_id']);
        $table->addIndex(['username'], ['name' => 'idx_user_company_mappings_username']);
        $table->addIndex(['mapped_company_id'], ['name' => 'idx_user_company_mappings_mapped_company_id']);
        $table->addIndex(['source_company_id'], ['name' => 'idx_user_company_mappings_source_company_id']);
        $table->addIndex(['system_type'], ['name' => 'idx_user_company_mappings_system_type']);
        $table->addIndex(['active'], ['name' => 'idx_user_company_mappings_active']);
        $table->addIndex(['deleted'], ['name' => 'idx_user_company_mappings_deleted']);
        
        // Unique constraint: one mapping per user, company, and system type
        $table->addIndex(['user_id', 'mapped_company_id', 'system_type'], [
            'unique' => true,
            'name' => 'idx_user_company_mappings_unique'
        ]);
        
        $table->create();
    }
}

