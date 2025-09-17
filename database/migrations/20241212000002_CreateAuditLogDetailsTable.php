<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateAuditLogDetailsTable extends AbstractMigration
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
        $table = $this->table('audit_log_details', [
            'id' => false,
            'primary_key' => ['id']
        ]);
        
        $table->addColumn('id', 'uuid', [
            'default' => null,
            'null' => false,
        ]);
        
        $table->addColumn('audit_log_id', 'uuid', [
            'default' => null,
            'null' => false,
            'comment' => 'Foreign key to audit_logs table'
        ]);
        
        $table->addColumn('field_name', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
            'comment' => 'Name of the field that changed'
        ]);
        
        $table->addColumn('field_label', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => true,
            'comment' => 'Human-readable label for the field'
        ]);
        
        $table->addColumn('old_value', 'text', [
            'default' => null,
            'null' => true,
            'comment' => 'Previous value of the field'
        ]);
        
        $table->addColumn('new_value', 'text', [
            'default' => null,
            'null' => true,
            'comment' => 'New value of the field'
        ]);
        
        $table->addColumn('change_type', 'string', [
            'default' => 'changed',
            'limit' => 20,
            'null' => false,
            'comment' => 'added, changed, removed'
        ]);
        
        $table->addColumn('created', 'timestamp', [
            'default' => 'CURRENT_TIMESTAMP',
            'null' => false,
        ]);
        
        $table->addIndex(['audit_log_id']);
        $table->addIndex(['field_name']);
        $table->addIndex(['change_type']);
        $table->addIndex(['created']);
        
        // Foreign key constraint
        $table->addForeignKey('audit_log_id', 'audit_logs', 'id', [
            'delete' => 'CASCADE',
            'update' => 'CASCADE'
        ]);
        
        $table->create();
    }
}
