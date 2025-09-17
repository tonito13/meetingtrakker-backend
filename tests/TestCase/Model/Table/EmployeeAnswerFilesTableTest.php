<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\EmployeeAnswerFilesTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\EmployeeAnswerFilesTable Test Case
 */
class EmployeeAnswerFilesTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\EmployeeAnswerFilesTable
     */
    protected $EmployeeAnswerFiles;

    /**
     * Fixtures
     *
     * @var list<string>
     */
    protected array $fixtures = [
        'app.EmployeeAnswerFiles',
        'app.Answers',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('EmployeeAnswerFiles') ? [] : ['className' => EmployeeAnswerFilesTable::class];
        $this->EmployeeAnswerFiles = $this->getTableLocator()->get('EmployeeAnswerFiles', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->EmployeeAnswerFiles);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \App\Model\Table\EmployeeAnswerFilesTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test buildRules method
     *
     * @return void
     * @uses \App\Model\Table\EmployeeAnswerFilesTable::buildRules()
     */
    public function testBuildRules(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
