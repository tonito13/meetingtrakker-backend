<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateClientCompanyRelationshipsTable extends AbstractMigration
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
        $table = $this->table('client_company_relationships', [
            'id' => false,
            'primary_key' => ['id']
        ]);
        
        $table->addColumn('id', 'biginteger', [
            'autoIncrement' => true,
            'limit' => 20,
            'null' => false,
        ]);
        
        $table->addColumn('company_id_from', 'biginteger', [
            'limit' => 20,
            'null' => false,
            'comment' => 'Source company ID'
        ]);
        
        $table->addColumn('company_id_to', 'biginteger', [
            'limit' => 20,
            'null' => false,
            'comment' => 'Target company ID'
        ]);
        
        $table->addColumn('relationship_type', 'text', [
            'null' => false,
            'comment' => 'Type of relationship: vendor, partner, prospect, customer, affiliate, other'
        ]);
        
        $table->addColumn('status', 'text', [
            'null' => false,
            'default' => 'active',
            'comment' => 'Status: active, inactive, pending, terminated'
        ]);
        
        $table->addColumn('is_primary', 'boolean', [
            'null' => false,
            'default' => false,
            'comment' => 'Whether this is the primary relationship'
        ]);
        
        $table->addColumn('start_date', 'date', [
            'null' => false,
            'comment' => 'Start date of the relationship'
        ]);
        
        $table->addColumn('end_date', 'date', [
            'null' => true,
            'comment' => 'End date of the relationship (null for ongoing)'
        ]);
        
        $table->addColumn('notes', 'text', [
            'null' => true,
            'comment' => 'Additional notes about the relationship'
        ]);
        
        $table->addColumn('metadata', 'jsonb', [
            'null' => true,
            'comment' => 'Additional metadata in JSON format'
        ]);
        
        $table->addColumn('created_by', 'biginteger', [
            'limit' => 20,
            'null' => true,
            'comment' => 'User ID who created this relationship'
        ]);
        
        $table->addColumn('updated_by', 'biginteger', [
            'limit' => 20,
            'null' => true,
            'comment' => 'User ID who last updated this relationship'
        ]);
        
        $table->addColumn('created_at', 'timestamp', [
            'default' => 'CURRENT_TIMESTAMP',
            'null' => false,
        ]);
        
        $table->addColumn('updated_at', 'timestamp', [
            'default' => 'CURRENT_TIMESTAMP',
            'null' => false,
        ]);
        
        $table->addColumn('deleted', 'boolean', [
            'null' => false,
            'default' => false,
            'comment' => 'Soft delete flag'
        ]);
        
        $table->addColumn('deleted_at', 'timestamp', [
            'null' => true,
            'comment' => 'Timestamp when record was soft deleted'
        ]);
        
        // Add indexes
        $table->addIndex(['company_id_from'], ['name' => 'idx_ccr_company_from']);
        $table->addIndex(['company_id_to'], ['name' => 'idx_ccr_company_to']);
        $table->addIndex(['status'], ['name' => 'idx_ccr_status']);
        $table->addIndex(['deleted'], ['name' => 'idx_ccr_deleted']);
        $table->addIndex(['start_date', 'end_date'], ['name' => 'idx_ccr_dates']);
        
        // Unique constraint: one active relationship per pair and type
        $table->addIndex(['company_id_from', 'company_id_to', 'relationship_type'], [
            'unique' => true,
            'name' => 'uq_ccr_one_active_per_pair_type',
            'where' => 'end_date IS NULL'
        ]);
        
        // Unique constraint: one primary active relationship per pair and type
        $table->addIndex(['company_id_from', 'company_id_to', 'relationship_type'], [
            'unique' => true,
            'name' => 'uq_ccr_one_primary_active_per_pair_type',
            'where' => 'is_primary = true AND end_date IS NULL'
        ]);
        
        // Unique constraint: prevent duplicate pair-type-start_date combinations
        $table->addIndex(['company_id_from', 'company_id_to', 'relationship_type', 'start_date'], [
            'unique' => true,
            'name' => 'uq_ccr_pair_type_start'
        ]);
        
        $table->create();
        
        // Add check constraints
        $this->execute("
            ALTER TABLE client_company_relationships 
            ADD CONSTRAINT ccr_no_self_link 
            CHECK (company_id_from <> company_id_to)
        ");
        
        $this->execute("
            ALTER TABLE client_company_relationships 
            ADD CONSTRAINT ccr_date_range 
            CHECK (end_date IS NULL OR end_date >= start_date)
        ");
        
        $this->execute("
            ALTER TABLE client_company_relationships 
            ADD CONSTRAINT client_company_relationships_relationship_type_check 
            CHECK (relationship_type IN ('vendor', 'partner', 'prospect', 'customer', 'affiliate', 'other'))
        ");
        
        $this->execute("
            ALTER TABLE client_company_relationships 
            ADD CONSTRAINT client_company_relationships_status_check 
            CHECK (status IN ('active', 'inactive', 'pending', 'terminated'))
        ");
    }
}

