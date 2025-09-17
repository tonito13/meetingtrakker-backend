<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\JobRoleTemplateAnswersTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\JobRoleTemplateAnswersTable Test Case
 */
class JobRoleTemplateAnswersTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\JobRoleTemplateAnswersTable
     */
    protected $JobRoleTemplateAnswers;

    /**
     * Fixtures
     *
     * @var list<string>
     */
    protected array $fixtures = [
        'app.JobRoleTemplateAnswers',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('JobRoleTemplateAnswers') ? [] : ['className' => JobRoleTemplateAnswersTable::class];
        $this->JobRoleTemplateAnswers = $this->getTableLocator()->get('JobRoleTemplateAnswers', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->JobRoleTemplateAnswers);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \App\Model\Table\JobRoleTemplateAnswersTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
