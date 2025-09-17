<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\EmployeeTemplateAnswersTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\EmployeeTemplateAnswersTable Test Case
 */
class EmployeeTemplateAnswersTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\EmployeeTemplateAnswersTable
     */
    protected $EmployeeTemplateAnswers;

    /**
     * Fixtures
     *
     * @var list<string>
     */
    protected array $fixtures = [
        'app.EmployeeTemplateAnswers',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('EmployeeTemplateAnswers') ? [] : ['className' => EmployeeTemplateAnswersTable::class];
        $this->EmployeeTemplateAnswers = $this->getTableLocator()->get('EmployeeTemplateAnswers', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->EmployeeTemplateAnswers);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \App\Model\Table\EmployeeTemplateAnswersTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
