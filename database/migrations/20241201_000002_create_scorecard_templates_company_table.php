<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateScorecardTemplatesCompanyTable extends AbstractMigration
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
        $table = $this->table('scorecard_templates', [
            'id' => false,
            'primary_key' => ['id']
        ]);
        
        $table->addColumn('id', 'integer', [
            'autoIncrement' => true,
            'limit' => 11,
            'null' => false,
        ]);
        
        $table->addColumn('name', 'string', [
            'limit' => 255,
            'null' => false,
            'default' => 'Scorecard Template',
        ]);
        
        $table->addColumn('structure', 'json', [
            'null' => false,
            'comment' => 'JSON structure defining the scorecard form layout with groups and fields',
        ]);
        
        $table->addColumn('created_by', 'string', [
            'limit' => 255,
            'null' => false,
            'default' => 'system',
            'comment' => 'Username of the user who created the template',
        ]);
        
        $table->addColumn('created', 'datetime', [
            'null' => false,
            'default' => 'CURRENT_TIMESTAMP',
        ]);
        
        $table->addColumn('modified', 'datetime', [
            'null' => false,
            'default' => 'CURRENT_TIMESTAMP',
        ]);
        
        $table->addColumn('deleted', 'boolean', [
            'null' => false,
            'default' => false,
            'comment' => 'Soft delete flag',
        ]);
        
        // Add indexes for performance
        $table->addIndex(['name'], ['name' => 'idx_scorecard_templates_name']);
        $table->addIndex(['created_by'], ['name' => 'idx_scorecard_templates_created_by']);
        $table->addIndex(['created'], ['name' => 'idx_scorecard_templates_created']);
        $table->addIndex(['deleted'], ['name' => 'idx_scorecard_templates_deleted']);
        
        $table->create();
        
        // Insert default template structure for company databases
        $defaultStructure = json_encode([
            [
                'id' => 1,
                'label' => 'Scorecard Information',
                'customize_group_label' => 'Scorecard Information',
                'fields' => [
                    [
                        'id' => 1,
                        'label' => 'Code',
                        'type' => 'text',
                        'is_required' => true,
                        'options' => [],
                        'customize_field_label' => 'Code',
                    ],
                    [
                        'id' => 2,
                        'label' => 'Strategies/Tactics',
                        'type' => 'text',
                        'is_required' => true,
                        'options' => [],
                        'customize_field_label' => 'Strategies/Tactics',
                    ],
                    [
                        'id' => 3,
                        'label' => 'Measures',
                        'type' => 'textarea',
                        'is_required' => true,
                        'options' => [],
                        'customize_field_label' => 'Measures',
                    ],
                    [
                        'id' => 4,
                        'label' => 'Deadline',
                        'type' => 'date',
                        'is_required' => true,
                        'options' => [],
                        'customize_field_label' => 'Deadline',
                    ],
                    [
                        'id' => 5,
                        'label' => 'Points',
                        'type' => 'number',
                        'is_required' => true,
                        'options' => [],
                        'customize_field_label' => 'Points',
                    ],
                    [
                        'id' => 6,
                        'label' => 'Weight (%)',
                        'type' => 'number',
                        'is_required' => true,
                        'options' => [],
                        'customize_field_label' => 'Weight (%)',
                    ],
                ],
            ],
        ]);
        
        $this->execute("
            INSERT INTO scorecard_templates (name, structure, created_by, created, modified, deleted) 
            VALUES ('Default Scorecard Template', '{$defaultStructure}', 'system', NOW(), NOW(), false)
        ");
    }
}
