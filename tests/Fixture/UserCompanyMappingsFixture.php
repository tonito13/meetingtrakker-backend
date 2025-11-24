<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * UserCompanyMappingsFixture
 * 
 * This fixture provides user company mapping data for testing.
 * Maps users from orgtrakker (company_id 100000) to scorecardtrakker (company_id 200001).
 */
class UserCompanyMappingsFixture extends TestFixture
{
    /**
     * Connection name to use for this fixture
     * User company mappings are stored in the central workmatica database
     * 
     * @var string
     */
    public string $connection = 'test';

    /**
     * Table name
     *
     * @var string
     */
    public string $table = 'user_company_mappings';

    /**
     * Fields configuration
     * 
     * @var array
     */
    public array $fields = [
        'id' => ['type' => 'integer', 'length' => 11, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null, 'autoIncrement' => true],
        'user_id' => ['type' => 'integer', 'length' => 11, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => 'User ID in the target system', 'precision' => null],
        'username' => ['type' => 'string', 'length' => 100, 'null' => false, 'default' => null, 'comment' => 'Username for identification', 'precision' => null],
        'mapped_company_id' => ['type' => 'integer', 'length' => 11, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => 'Company ID in the target system (where user is mapped)', 'precision' => null],
        'source_company_id' => ['type' => 'integer', 'length' => 11, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => 'Company ID in the source system (where user originated)', 'precision' => null],
        'system_type' => ['type' => 'string', 'length' => 50, 'null' => false, 'default' => null, 'comment' => 'System type: orgtrakker, scorecardtrakker, skiltrakker, tickettrakker', 'precision' => null],
        'active' => ['type' => 'boolean', 'length' => null, 'null' => false, 'default' => true, 'comment' => 'Whether this mapping is active', 'precision' => null],
        'deleted' => ['type' => 'boolean', 'length' => null, 'null' => false, 'default' => false, 'comment' => '', 'precision' => null],
        'created' => ['type' => 'datetime', 'length' => null, 'precision' => null, 'null' => false, 'default' => null, 'comment' => ''],
        'modified' => ['type' => 'datetime', 'length' => null, 'precision' => null, 'null' => false, 'default' => null, 'comment' => ''],
    ];

    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = [
            [
                'id' => 1,
                'user_id' => 1, // Maps to Users fixture id=1 (username='test')
                'username' => 'test',
                'mapped_company_id' => 200001, // scorecardtrakker company ID
                'source_company_id' => 100000, // orgtrakker company ID
                'system_type' => 'scorecardtrakker',
                'active' => true,
                'deleted' => false,
                'created' => '2024-01-01 10:00:00',
                'modified' => '2024-01-01 10:00:00',
            ],
            [
                'id' => 2,
                'user_id' => 1, // Same user, different mapping (inactive)
                'username' => 'test',
                'mapped_company_id' => 200002,
                'source_company_id' => 100000,
                'system_type' => 'scorecardtrakker',
                'active' => false, // Inactive mapping
                'deleted' => false,
                'created' => '2024-01-01 09:00:00',
                'modified' => '2024-01-01 09:00:00',
            ],
            [
                'id' => 3,
                'user_id' => 1, // Same user, deleted mapping
                'username' => 'test',
                'mapped_company_id' => 200003,
                'source_company_id' => 100000,
                'system_type' => 'scorecardtrakker',
                'active' => true,
                'deleted' => true, // Deleted mapping
                'created' => '2024-01-01 08:00:00',
                'modified' => '2024-01-01 08:00:00',
            ],
        ];
        parent::init();
    }
}

