<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\JobRoleTemplatesTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\JobRoleTemplatesTable Test Case
 */
class JobRoleTemplatesTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\JobRoleTemplatesTable
     */
    protected $JobRoleTemplates;

    /**
     * Fixtures
     *
     * @var list<string>
     */
    protected array $fixtures = [
        'app.JobRoleTemplates',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('JobRoleTemplates') ? [] : ['className' => JobRoleTemplatesTable::class];
        $this->JobRoleTemplates = $this->getTableLocator()->get('JobRoleTemplates', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->JobRoleTemplates);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \App\Model\Table\JobRoleTemplatesTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
