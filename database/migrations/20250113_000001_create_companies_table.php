<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateCompaniesTable extends AbstractMigration
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
        $table = $this->table('companies', [
            'id' => false,
            'primary_key' => ['id']
        ]);
        
        $table->addColumn('id', 'integer', [
            'autoIncrement' => true,
            'limit' => 11,
            'null' => false,
        ]);
        
        $table->addColumn('company_id', 'integer', [
            'limit' => 11,
            'null' => false,
            'comment' => 'Company ID within the specific system (e.g., 100000, 300000)'
        ]);
        
        $table->addColumn('company_type', 'string', [
            'limit' => 150,
            'null' => false,
            'default' => 'principal',
            'comment' => 'Type of company (principal, subsidiary, etc.)'
        ]);
        
        $table->addColumn('company_status', 'string', [
            'limit' => 150,
            'null' => false,
            'default' => 'active',
            'comment' => 'Status of the company (active, inactive, etc.)'
        ]);
        
        $table->addColumn('data_privacy_setup_type_id', 'integer', [
            'limit' => 11,
            'null' => false,
            'default' => 1,
        ]);
        
        $table->addColumn('code', 'string', [
            'limit' => 150,
            'null' => false,
            'comment' => 'Company code/identifier'
        ]);
        
        $table->addColumn('email', 'string', [
            'limit' => 50,
            'null' => false,
            'comment' => 'Company email address'
        ]);
        
        $table->addColumn('maximum_users', 'integer', [
            'limit' => 11,
            'null' => false,
            'default' => 100,
            'comment' => 'Maximum number of users allowed'
        ]);
        
        $table->addColumn('name', 'string', [
            'limit' => 100,
            'null' => false,
            'comment' => 'Company name'
        ]);
        
        $table->addColumn('street_number', 'string', [
            'limit' => 8,
            'null' => true,
        ]);
        
        $table->addColumn('street_name', 'string', [
            'limit' => 255,
            'null' => true,
        ]);
        
        $table->addColumn('barangay', 'string', [
            'limit' => 255,
            'null' => true,
        ]);
        
        $table->addColumn('city', 'string', [
            'limit' => 255,
            'null' => true,
        ]);
        
        $table->addColumn('province', 'string', [
            'limit' => 255,
            'null' => true,
        ]);
        
        $table->addColumn('postal_code', 'string', [
            'limit' => 6,
            'null' => true,
        ]);
        
        $table->addColumn('system_product_name', 'string', [
            'limit' => 255,
            'null' => true,
            'comment' => 'System/service name (orgtrakker, scorecardtrakker, skiltrakker, tickettrakker)'
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
        
        // Add constraints
        $table->addIndex(['company_id'], ['name' => 'idx_companies_company_id']);
        $table->addIndex(['system_product_name'], ['name' => 'idx_companies_system_product_name']);
        $table->addIndex(['code'], ['name' => 'idx_companies_code']);
        $table->addIndex(['deleted'], ['name' => 'idx_companies_deleted']);
        $table->addIndex(['company_id', 'system_product_name'], ['name' => 'idx_companies_company_id_system']);
        $table->addIndex(['company_status'], ['name' => 'idx_companies_status']);
        
        // Unique constraint: company_id + system_product_name should be unique
        $table->addIndex(['company_id', 'system_product_name'], [
            'unique' => true,
            'name' => 'uq_companies_company_id_system'
        ]);
        
        // Check constraint for company_id range
        $this->execute("
            ALTER TABLE companies 
            ADD CONSTRAINT companies_company_id_check 
            CHECK (company_id >= 0 AND company_id <= 999999)
        ");
        
        $table->create();
    }
}

