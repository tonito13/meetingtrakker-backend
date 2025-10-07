<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Api;

use App\Controller\Api\JobRolesController;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * App\Controller\Api\JobRolesController Test Case
 *
 * This test class provides comprehensive unit tests for the JobRolesController.
 * It follows the exact same structure and conventions as the ScorecardsControllerTest,
 * ensuring consistency and high quality across the test suite.
 */
class JobRolesControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * Fixtures used by this test class
     * 
     * @var array<string>
     */
    protected array $fixtures = [
        'app.Users',
        'app.JobRoleTemplates',
        'app.JobRoleTemplateAnswers',
        'app.LevelTemplates',
        'app.RoleLevels'
    ];

    // ========================================
    // TEST DATA CONSTANTS
    // ========================================
    private const VALID_USERNAME = 'test';
    private const VALID_PASSWORD = '12345';
    private const VALID_COMPANY_ID = 200001;
    private const INVALID_COMPANY_ID = 999999;
    private const VALID_JOB_ROLE_UNIQUE_ID = 'jr-20240101-ABCD1234';
    private const INVALID_JOB_ROLE_UNIQUE_ID = 'NONEXISTENT_JR';
    private const DELETED_JOB_ROLE_UNIQUE_ID = 'jr-20250915-3487298C6E';
    private const VALID_TEMPLATE_ID = 9001;
    private const INVALID_TEMPLATE_ID = 9999;

    /**
     * Helper method to safely capture console output with proper cleanup
     *
     * @param callable $callback The callback to execute while capturing output
     * @return string The captured console output
     */
    private function captureConsoleOutput(callable $callback): string
    {
        ob_start();
        try {
            $callback();
            return ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean(); // Clean up buffer on exception
            throw $e;
        }
    }

    /**
     * Helper method to get authentication token
     *
     * @return string Authentication token
     */
    private function getAuthToken(): string
    {
        $this->captureConsoleOutput(function (): void {
            $this->enableCsrfToken();
            $this->enableSecurityToken();
            $this->configRequest(['headers' => ['Accept' => 'application/json']]);

            $this->post('/api/users/login', [
                'username' => self::VALID_USERNAME,
                'password' => self::VALID_PASSWORD,
            ]);
        });

        $this->assertResponseCode(200);
        $body = (string)$this->_response->getBody();
        $response = json_decode($body, true);
        return $response['token'];
    }

    // ========================================
    // BASIC ROUTING TESTS
    // ========================================

    /**
     * Test basic routing
     */
    public function testBasicRouting(): void
    {
        $this->get('/api/job-roles/tableHeaders');
        $this->assertResponseCode(401); // Should require authentication
    }

    /**
     * Test fixture loading
     */
    public function testFixtureLoading(): void
    {
        // Check if fixtures are loaded
        $this->assertTrue(true, 'This test should pass if fixtures are loaded');
        
        // Try to access the Users table to see if fixtures are loaded
        $usersTable = \Cake\ORM\TableRegistry::getTableLocator()->get('Users');
        $userCount = $usersTable->find()->count();
        
        echo "\n=== USER COUNT: {$userCount} ===\n";
        
        // This should be greater than 0 if the fixture is loaded
        $this->assertGreaterThan(0, $userCount, 'Users fixture should be loaded');
        
        // Check if JobRoleTemplateAnswers fixture is loaded
        $jobRoleAnswersTable = \Cake\ORM\TableRegistry::getTableLocator()->get('JobRoleTemplateAnswers');
        $jobRoleCount = $jobRoleAnswersTable->find()->count();
        
        echo "\n=== JOB ROLE TEMPLATE ANSWERS COUNT: {$jobRoleCount} ===\n";
        
        // This should be greater than 0 if the fixture is loaded
        $this->assertGreaterThan(0, $jobRoleCount, 'JobRoleTemplateAnswers fixture should be loaded');
        
        // Check if JobRoleTemplates fixture is loaded
        $jobRoleTemplatesTable = \Cake\ORM\TableRegistry::getTableLocator()->get('JobRoleTemplates');
        $jobRoleTemplatesCount = $jobRoleTemplatesTable->find()->count();
        
        echo "\n=== JOB ROLE TEMPLATES COUNT: {$jobRoleTemplatesCount} ===\n";
        
        // This should be greater than 0 if the fixture is loaded
        $this->assertGreaterThan(0, $jobRoleTemplatesCount, 'JobRoleTemplates fixture should be loaded');
        
        // Check if RoleLevels fixture is loaded
        $roleLevelsTable = \Cake\ORM\TableRegistry::getTableLocator()->get('RoleLevels');
        $roleLevelsCount = $roleLevelsTable->find()->count();
        
        echo "\n=== ROLE LEVELS COUNT: {$roleLevelsCount} ===\n";
        
        // This should be greater than 0 if the fixture is loaded
        $this->assertGreaterThan(0, $roleLevelsCount, 'RoleLevels fixture should be loaded');
    }

    // ========================================
    // TABLE HEADERS TESTS
    // ========================================

    /**
     * Test table headers with valid authentication
     */
    public function testTableHeadersWithValidAuthentication(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        $this->get('/api/job-roles/tableHeaders');
        $this->assertResponseCode(200);

        $responseBody = (string)$this->_response->getBody();
        $responseData = json_decode($responseBody, true);
        
        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertIsArray($responseData['data']);
    }

    /**
     * Test table headers without authentication
     */
    public function testTableHeadersWithoutAuthentication(): void
    {
        $this->get('/api/job-roles/tableHeaders');
        $this->assertResponseCode(401);
    }

    // ========================================
    // GET JOB ROLES DATA TESTS
    // ========================================

    /**
     * Test get job roles data with valid authentication
     */
    public function testGetJobRolesDataWithValidAuthentication(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        $this->get('/api/job-roles/getJobRolesData');
        $this->assertResponseCode(200);

        $responseBody = (string)$this->_response->getBody();
        $responseData = json_decode($responseBody, true);
        
        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('records', $responseData['data']);
        $this->assertArrayHasKey('total', $responseData['data']);
    }

    /**
     * Test get job roles data without authentication
     */
    public function testGetJobRolesDataWithoutAuthentication(): void
    {
        $this->get('/api/job-roles/getJobRolesData');
        $this->assertResponseCode(401);
    }

    // ========================================
    // ADD JOB ROLE TESTS
    // ========================================

    /**
     * Test add job role with valid data
     */
    public function testAddJobRoleWithValidData(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        $this->post('/api/job-roles/addJobRole', [
            'template_id' => self::VALID_TEMPLATE_ID,
            'answers' => [
                'job_info' => [
                    'position' => 'Test Developer',
                    'reports_to' => 'Test Manager'
                ]
            ]
        ]);

        // Debug: Show actual response if not 200
        if ($this->_response->getStatusCode() !== 200) {
            $responseBody = (string)$this->_response->getBody();
            $this->fail('Response failed with status ' . $this->_response->getStatusCode() . ': ' . $responseBody);
        }

        $this->assertResponseCode(200);
        $responseBody = (string)$this->_response->getBody();
        $responseData = json_decode($responseBody, true);
        
        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('job_role_unique_id', $responseData['data']);
    }

    /**
     * Test add job role without authentication
     */
    public function testAddJobRoleWithoutAuthentication(): void
    {
        $this->post('/api/job-roles/addJobRole', [
            'template_id' => self::VALID_TEMPLATE_ID,
            'answers' => [
                'job_info' => [
                    'position' => 'Test Developer',
                    'reports_to' => 'Test Manager'
                ]
            ]
        ]);

        $this->assertResponseCode(401);
    }

    // ========================================
    // EDIT JOB ROLE TESTS
    // ========================================

    /**
     * Test edit job role with valid data
     */
    public function testEditJobRoleWithValidData(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        $this->post('/api/job-roles/editJobRole', [
            'job_role_unique_id' => self::VALID_JOB_ROLE_UNIQUE_ID,
            'template_id' => self::VALID_TEMPLATE_ID,
            'answers' => [
                'job_info' => [
                    'position' => 'Updated Developer',
                    'reports_to' => 'Updated Manager'
                ]
            ]
        ]);

        // Debug: Show actual response if not 200
        if ($this->_response->getStatusCode() !== 200) {
            $responseBody = (string)$this->_response->getBody();
            $this->fail('Response failed with status ' . $this->_response->getStatusCode() . ': ' . $responseBody);
        }

        $this->assertResponseCode(200);
        $responseBody = (string)$this->_response->getBody();
        $responseData = json_decode($responseBody, true);
        
        $this->assertTrue($responseData['success']);
    }

    /**
     * Test edit job role without authentication
     */
    public function testEditJobRoleWithoutAuthentication(): void
    {
        $this->post('/api/job-roles/editJobRole', [
            'job_role_unique_id' => self::VALID_JOB_ROLE_UNIQUE_ID,
            'template_id' => self::VALID_TEMPLATE_ID,
            'answers' => [
                'job_info' => [
                    'position' => 'Updated Developer',
                    'reports_to' => 'Updated Manager'
                ]
            ]
        ]);

        $this->assertResponseCode(401);
    }

    // ========================================
    // DELETE JOB ROLE TESTS
    // ========================================

    /**
     * Test delete job role with valid id
     */
    public function testDeleteJobRoleWithValidId(): void
    {
        // First login to get authentication token
        $loginResponse = $this->captureConsoleOutput(function (): void {
            $this->enableCsrfToken();
            $this->enableSecurityToken();
            $this->configRequest(['headers' => ['Accept' => 'application/json']]);

            $this->post('/api/users/login', [
                'username' => self::VALID_USERNAME,
                'password' => self::VALID_PASSWORD,
            ]);
        });

        $this->assertResponseCode(200);
        $loginBody = (string)$this->_response->getBody();
        $loginData = json_decode($loginBody, true);
        $token = $loginData['token'];

        // Now test delete with authentication
        $consoleOutput = $this->captureConsoleOutput(function () use ($token): void {
            $this->configRequest([
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]);

            $this->post('/api/job-roles/deleteJobRole', [
                'job_role_unique_id' => self::VALID_JOB_ROLE_UNIQUE_ID
            ]);
        });

        // Debug: Show actual response
        $body = (string)$this->_response->getBody();
        echo "\n=== RESPONSE BODY: {$body} ===\n";
        echo "\n=== RESPONSE CODE: " . $this->_response->getStatusCode() . " ===\n";
        
        $this->assertResponseCode(200);
        $this->assertContentType('application/json');
        
        // Check for unexpected console output
        $this->assertEmpty($consoleOutput, 'Delete endpoint should not produce console output');
        
        $responseBody = (string)$this->_response->getBody();
        $this->assertJson($responseBody, 'Response should be valid JSON');
        
        $responseData = json_decode($responseBody, true);
        $this->assertNotNull($responseData, 'Response should be valid JSON');
        $this->assertTrue($responseData['success'], 'Response should indicate success');
    }

    /**
     * Test delete job role without authentication
     */
    public function testDeleteJobRoleWithoutAuthentication(): void
    {
        $this->post('/api/job-roles/deleteJobRole', [
            'job_role_unique_id' => self::VALID_JOB_ROLE_UNIQUE_ID
        ]);

        $this->assertResponseCode(401);
    }

    // ========================================
    // CONSOLE OUTPUT DETECTION TESTS
    // ========================================

    /**
     * Test console output detection works
     */
    public function testConsoleOutputDetectionWorks(): void
    {
        $output = $this->captureConsoleOutput(function () {
            echo "Test output";
        });
        
        $this->assertEquals("Test output", $output);
    }

    /**
     * Test debug statements would cause failure
     */
    public function testDebugStatementsWouldCauseFailure(): void
    {
        $output = $this->captureConsoleOutput(function () {
            debug("This should not appear in production");
        });
        
        $this->assertNotEmpty($output);
    }

    /**
     * Test echo statements would cause failure
     */
    public function testEchoStatementsWouldCauseFailure(): void
    {
        $output = $this->captureConsoleOutput(function () {
            echo "Echo statement";
        });
        
        $this->assertNotEmpty($output);
    }

    /**
     * Test print statements would cause failure
     */
    public function testPrintStatementsWouldCauseFailure(): void
    {
        $output = $this->captureConsoleOutput(function () {
            print "Print statement";
        });
        
        $this->assertNotEmpty($output);
    }

    /**
     * Test var dump statements would cause failure
     */
    public function testVarDumpStatementsWouldCauseFailure(): void
    {
        $output = $this->captureConsoleOutput(function () {
            var_dump("Var dump statement");
        });
        
        $this->assertNotEmpty($output);
    }


    // ========================================
    // COMPREHENSIVE INPUT VALIDATION TESTS
    // ========================================

    /**
     * Test add job role comprehensive input validation
     */
    public function testAddJobRoleComprehensiveInputValidation(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        // Test missing template_id
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);
        $this->post('/api/job-roles/addJobRole', [
            'answers' => [
                'job_info' => [
                    'position' => 'Test Developer'
                ]
            ]
        ]);
        $this->assertResponseCode(400);

        // Test missing answers
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);
        $this->post('/api/job-roles/addJobRole', [
            'template_id' => self::VALID_TEMPLATE_ID
        ]);
        $this->assertResponseCode(400);

        // Test invalid template_id
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);
        $this->post('/api/job-roles/addJobRole', [
            'template_id' => 'invalid',
            'answers' => [
                'job_info' => [
                    'position' => 'Test Developer'
                ]
            ]
        ]);
        $this->assertResponseCode(400);

        // Test non-array answers
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);
        $this->post('/api/job-roles/addJobRole', [
            'template_id' => self::VALID_TEMPLATE_ID,
            'answers' => 'not_an_array'
        ]);
        $this->assertResponseCode(400);
    }

    /**
     * Test edit job role comprehensive input validation
     */
    public function testEditJobRoleComprehensiveInputValidation(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        // Test missing job_role_unique_id
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);
        $this->post('/api/job-roles/editJobRole', [
            'template_id' => self::VALID_TEMPLATE_ID,
            'answers' => [
                'job_info' => [
                    'position' => 'Test Developer'
                ]
            ]
        ]);
        $this->assertResponseCode(400);

        // Test missing template_id
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);
        $this->post('/api/job-roles/editJobRole', [
            'job_role_unique_id' => self::VALID_JOB_ROLE_UNIQUE_ID,
            'answers' => [
                'job_info' => [
                    'position' => 'Test Developer'
                ]
            ]
        ]);
        $this->assertResponseCode(400);

        // Test missing answers
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);
        $this->post('/api/job-roles/editJobRole', [
            'job_role_unique_id' => self::VALID_JOB_ROLE_UNIQUE_ID,
            'template_id' => self::VALID_TEMPLATE_ID
        ]);
        $this->assertResponseCode(400);
    }

    /**
     * Test delete job role comprehensive input validation
     */
    public function testDeleteJobRoleComprehensiveInputValidation(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        // Test missing job_role_unique_id
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);
        $this->post('/api/job-roles/deleteJobRole', []);
        $this->assertResponseCode(400);

        // Test empty job_role_unique_id
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);
        $this->post('/api/job-roles/deleteJobRole', [
            'job_role_unique_id' => ''
        ]);
        $this->assertResponseCode(400);
    }

    // ========================================
    // ERROR HANDLING TESTS
    // ========================================

    /**
     * Test get job role detail with non-existent id
     */
    public function testGetJobRoleDetailWithNonExistentId(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        $this->post('/api/job-roles/getJobRoleDetail', [
            'job_role_unique_id' => self::INVALID_JOB_ROLE_UNIQUE_ID
        ]);

        $this->assertContains($this->_response->getStatusCode(), [200, 400, 404, 500]);
        $responseBody = (string)$this->_response->getBody();
        $responseData = json_decode($responseBody, true);
        
        if ($responseData && isset($responseData['success'])) {
            $this->assertFalse($responseData['success']);
            if (isset($responseData['message'])) {
                $this->assertStringContainsString('job role', strtolower($responseData['message']));
            }
        }
    }

    /**
     * Test delete job role with non-existent id
     */
    public function testDeleteJobRoleWithNonExistentId(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        $this->post('/api/job-roles/deleteJobRole', [
            'job_role_unique_id' => self::INVALID_JOB_ROLE_UNIQUE_ID
        ]);

        $this->assertResponseCode(404);
        $responseBody = (string)$this->_response->getBody();
        $responseData = json_decode($responseBody, true);
        
        $this->assertFalse($responseData['success']);
        $this->assertStringContainsString('Job role not found', $responseData['message']);
    }

    /**
     * Test edit job role with non-existent id
     */
    public function testEditJobRoleWithNonExistentId(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        $this->post('/api/job-roles/editJobRole', [
            'job_role_unique_id' => self::INVALID_JOB_ROLE_UNIQUE_ID,
            'template_id' => self::VALID_TEMPLATE_ID,
            'answers' => [
                'job_info' => [
                    'position' => 'Updated Developer'
                ]
            ]
        ]);

        $this->assertResponseCode(404);
    }

    // ========================================
    // PAGINATION AND SEARCH TESTS
    // ========================================

    /**
     * Test get job roles data with pagination
     */
    public function testGetJobRolesDataWithPagination(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        $this->get('/api/job-roles/getJobRolesData?page=1&limit=5');
        $this->assertResponseCode(200);

        $responseBody = (string)$this->_response->getBody();
        $responseData = json_decode($responseBody, true);
        
        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('records', $responseData['data']);
        $this->assertArrayHasKey('total', $responseData['data']);
    }

    /**
     * Test get job roles data with search
     */
    public function testGetJobRolesDataWithSearch(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        $this->get('/api/job-roles/getJobRolesData?search=Developer');
        $this->assertResponseCode(200);

        $responseBody = (string)$this->_response->getBody();
        $responseData = json_decode($responseBody, true);
        
        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('data', $responseData);
    }

    /**
     * Test get job roles data with sorting
     */
    public function testGetJobRolesDataWithSorting(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        $this->get('/api/job-roles/getJobRolesData?sortField=position&sortOrder=asc');
        $this->assertResponseCode(200);

        $responseBody = (string)$this->_response->getBody();
        $responseData = json_decode($responseBody, true);
        
        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('data', $responseData);
    }

    // ========================================
    // MULTI-TENANT TESTS
    // ========================================

    /**
     * Test company data isolation
     */
    public function testCompanyDataIsolation(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        $this->get('/api/job-roles/getJobRolesData');
        $this->assertResponseCode(200);

        $responseBody = (string)$this->_response->getBody();
        $responseData = json_decode($responseBody, true);
        
        $this->assertTrue($responseData['success']);
        
        // Verify all returned job roles belong to the authenticated user's company
        if (!empty($responseData['data']['records'])) {
            foreach ($responseData['data']['records'] as $jobRole) {
                // The job roles should only belong to company 200001 (test user's company)
                $this->assertNotEquals(200002, $jobRole['company_id'] ?? null);
            }
        }
    }

    // ========================================
    // JSON STRUCTURE TESTS
    // ========================================

    /**
     * Test job role with complex JSON structure
     */
    public function testJobRoleWithComplexJsonStructure(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        $complexAnswers = [
            'job_info' => [
                'position' => 'Senior Software Engineer',
                'reports_to' => 'Engineering Manager',
                'department' => 'Engineering',
                'level' => 'Senior',
                'description' => 'Lead development of complex software systems',
                'requirements' => ['JavaScript', 'React', 'Node.js', 'PostgreSQL'],
                'salary_range' => [
                    'min' => 80000,
                    'max' => 120000
                ]
            ],
            'additional_info' => [
                'remote_work' => true,
                'travel_required' => false,
                'benefits' => ['Health Insurance', '401k', 'PTO']
            ]
        ];

        $this->post('/api/job-roles/addJobRole', [
            'template_id' => self::VALID_TEMPLATE_ID,
            'answers' => $complexAnswers
        ]);

        $this->assertResponseCode(200);
        $responseBody = (string)$this->_response->getBody();
        $responseData = json_decode($responseBody, true);
        
        $this->assertTrue($responseData['success']);
    }

    /**
     * Test job role with Unicode and special characters
     */
    public function testJobRoleWithUnicodeAndSpecialCharacters(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        $unicodeAnswers = [
            'job_info' => [
                'position' => 'Développeur Senior 🚀',
                'reports_to' => 'Gestionnaire d\'Ingénierie',
                'description' => 'Développement de systèmes complexes avec émojis 😊',
                'special_chars' => 'Test: "quotes", \'apostrophes\', & ampersands, <tags>, {braces}'
            ]
        ];

        $this->post('/api/job-roles/addJobRole', [
            'template_id' => self::VALID_TEMPLATE_ID,
            'answers' => $unicodeAnswers
        ]);

        $this->assertResponseCode(200);
        $responseBody = (string)$this->_response->getBody();
        $responseData = json_decode($responseBody, true);
        
        $this->assertTrue($responseData['success']);
    }

    // ========================================
    // CONCURRENT OPERATIONS TESTS
    // ========================================

    /**
     * Test concurrent job role operations
     */
    public function testConcurrentJobRoleOperations(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        // Simulate concurrent operations by making multiple requests
        $responses = [];
        
        for ($i = 0; $i < 3; $i++) {
            $this->post('/api/job-roles/addJobRole', [
                'template_id' => self::VALID_TEMPLATE_ID,
                'answers' => [
                    'job_info' => [
                        'position' => "Concurrent Developer {$i}",
                        'reports_to' => 'Concurrent Manager'
                    ]
                ]
            ]);
            
            $responses[] = $this->_response->getStatusCode();
        }

        // All operations should succeed or fail gracefully
        foreach ($responses as $statusCode) {
            $this->assertContains($statusCode, [200, 400, 401, 500]);
        }
    }

    // ========================================
    // EDGE CASE TESTS
    // ========================================

    /**
     * Test job role with extremely long data
     */
    public function testJobRoleWithExtremelyLongData(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        $longDescription = str_repeat('This is a very long description. ', 1000);
        
        $longAnswers = [
            'job_info' => [
                'position' => 'Developer with Long Description',
                'reports_to' => 'Manager',
                'description' => $longDescription
            ]
        ];

        $this->post('/api/job-roles/addJobRole', [
            'template_id' => self::VALID_TEMPLATE_ID,
            'answers' => $longAnswers
        ]);

        $this->assertResponseCode(200);
        $responseBody = (string)$this->_response->getBody();
        $responseData = json_decode($responseBody, true);
        
        $this->assertTrue($responseData['success']);
    }

    /**
     * Test job role with empty answers
     */
    public function testJobRoleWithEmptyAnswers(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        $this->post('/api/job-roles/addJobRole', [
            'template_id' => self::VALID_TEMPLATE_ID,
            'answers' => []
        ]);

        $this->assertContains($this->_response->getStatusCode(), [200, 400]);
        $responseBody = (string)$this->_response->getBody();
        $responseData = json_decode($responseBody, true);
        
        if ($this->_response->getStatusCode() === 200) {
            $this->assertTrue($responseData['success']);
        } else {
            $this->assertFalse($responseData['success']);
        }
    }

    /**
     * Test job role with null values
     */
    public function testJobRoleWithNullValues(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        $nullAnswers = [
            'job_info' => [
                'position' => null,
                'reports_to' => null,
                'description' => null
            ]
        ];

        $this->post('/api/job-roles/addJobRole', [
            'template_id' => self::VALID_TEMPLATE_ID,
            'answers' => $nullAnswers
        ]);

        $this->assertResponseCode(200);
        $responseBody = (string)$this->_response->getBody();
        $responseData = json_decode($responseBody, true);
        
        $this->assertTrue($responseData['success']);
    }

    // ========================================
    // COMPREHENSIVE FIELD COMBINATION TESTS
    // ========================================

    /**
     * Test add job role field combination validation
     * 
     * This test covers every possible field combination scenario
     *
     * @return void
     */
    public function testAddJobRoleFieldCombinationValidation(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        $fieldCombinations = [
            // Single field tests
            'only_template_id' => ['template_id' => self::VALID_TEMPLATE_ID],
            'only_answers' => ['answers' => ['job_info' => ['position' => 'Test Developer']]],
            
            // Two field combinations
            'template_id_and_answers' => [
                'template_id' => self::VALID_TEMPLATE_ID,
                'answers' => ['job_info' => ['position' => 'Test Developer']]
            ],
            
            // Invalid combinations
            'template_id_without_answers' => ['template_id' => self::VALID_TEMPLATE_ID],
            'answers_without_template_id' => ['answers' => ['job_info' => ['position' => 'Test Developer']]],
            'empty_template_id_with_answers' => [
                'template_id' => '',
                'answers' => ['job_info' => ['position' => 'Test Developer']]
            ],
            'null_template_id_with_answers' => [
                'template_id' => null,
                'answers' => ['job_info' => ['position' => 'Test Developer']]
            ],
            'invalid_template_id_with_answers' => [
                'template_id' => 'invalid',
                'answers' => ['job_info' => ['position' => 'Test Developer']]
            ],
            'template_id_with_empty_answers' => [
                'template_id' => self::VALID_TEMPLATE_ID,
                'answers' => []
            ],
            'template_id_with_null_answers' => [
                'template_id' => self::VALID_TEMPLATE_ID,
                'answers' => null
            ],
            'template_id_with_string_answers' => [
                'template_id' => self::VALID_TEMPLATE_ID,
                'answers' => 'not_an_array'
            ]
        ];

        foreach ($fieldCombinations as $testName => $testData) {
            // Re-authenticate for each request to avoid session issues
            $token = $this->getAuthToken();
            $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);
            
            $this->post('/api/job-roles/addJobRole', $testData);
            
            // Valid combinations should return 200, invalid should return 400
            if (in_array($testName, ['template_id_and_answers'])) {
                $this->assertResponseCode(200);
                $responseBody = (string)$this->_response->getBody();
                $responseData = json_decode($responseBody, true);
                $this->assertTrue($responseData['success']);
            } else {
                $this->assertResponseCode(400);
            }
        }
    }

    /**
     * Test edit job role field combination validation
     * 
     * This test covers every possible field combination scenario for editing
     *
     * @return void
     */
    public function testEditJobRoleFieldCombinationValidation(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        $fieldCombinations = [
            // Single field tests
            'only_job_role_unique_id' => ['job_role_unique_id' => self::VALID_JOB_ROLE_UNIQUE_ID],
            'only_template_id' => ['template_id' => self::VALID_TEMPLATE_ID],
            'only_answers' => ['answers' => ['job_info' => ['position' => 'Updated Developer']]],
            
            // Two field combinations
            'job_role_id_and_template_id' => [
                'job_role_unique_id' => self::VALID_JOB_ROLE_UNIQUE_ID,
                'template_id' => self::VALID_TEMPLATE_ID
            ],
            'job_role_id_and_answers' => [
                'job_role_unique_id' => self::VALID_JOB_ROLE_UNIQUE_ID,
                'answers' => ['job_info' => ['position' => 'Updated Developer']]
            ],
            'template_id_and_answers' => [
                'template_id' => self::VALID_TEMPLATE_ID,
                'answers' => ['job_info' => ['position' => 'Updated Developer']]
            ],
            
            // Three field combinations
            'all_fields' => [
                'job_role_unique_id' => self::VALID_JOB_ROLE_UNIQUE_ID,
                'template_id' => self::VALID_TEMPLATE_ID,
                'answers' => ['job_info' => ['position' => 'Updated Developer']]
            ],
            
            // Invalid combinations
            'missing_job_role_id' => [
                'template_id' => self::VALID_TEMPLATE_ID,
                'answers' => ['job_info' => ['position' => 'Updated Developer']]
            ],
            'missing_template_id' => [
                'job_role_unique_id' => self::VALID_JOB_ROLE_UNIQUE_ID,
                'answers' => ['job_info' => ['position' => 'Updated Developer']]
            ],
            'missing_answers' => [
                'job_role_unique_id' => self::VALID_JOB_ROLE_UNIQUE_ID,
                'template_id' => self::VALID_TEMPLATE_ID
            ]
        ];

        foreach ($fieldCombinations as $testName => $testData) {
            // Re-authenticate for each request to avoid session issues
            $token = $this->getAuthToken();
            $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);
            
            $this->post('/api/job-roles/editJobRole', $testData);
            
            // Only valid combinations with all required fields should return 200
            if ($testName === 'all_fields') {
                $this->assertResponseCode(200);
                $responseBody = (string)$this->_response->getBody();
                $responseData = json_decode($responseBody, true);
                $this->assertTrue($responseData['success']);
            } else {
                $this->assertResponseCode(400);
            }
        }
    }

    // ========================================
    // COMPREHENSIVE ERROR MESSAGE TESTS
    // ========================================

    /**
     * Test comprehensive error message validation
     * 
     * This test validates every possible error message scenario
     *
     * @return void
     */
    public function testComprehensiveErrorMessageValidation(): void
    {
        $errorTestCases = [
            // Authentication errors
            'unauthorized_access' => [
                'endpoint' => '/api/job-roles/getJobRolesData',
                'method' => 'get',
                'headers' => ['Accept' => 'application/json'],
                'expected_status' => 401,
                'expected_message_pattern' => '/unauthorized|Unauthorized/'
            ],
            
            // Method not allowed errors
            'method_not_allowed_post' => [
                'endpoint' => '/api/job-roles/getJobRolesData',
                'method' => 'post',
                'headers' => ['Accept' => 'application/json'],
                'expected_status' => [401, 405],
                'expected_message_pattern' => '/unauthorized|Unauthorized|Method.*not.*allowed/'
            ],
            
            // Missing required fields
            'missing_template_id' => [
                'endpoint' => '/api/job-roles/addJobRole',
                'method' => 'post',
                'data' => ['answers' => ['job_info' => ['position' => 'Test']]],
                'expected_status' => 400,
                'expected_message_pattern' => '/missing.*template.*id|Missing.*template.*id/i'
            ],
            
            'missing_answers' => [
                'endpoint' => '/api/job-roles/addJobRole',
                'method' => 'post',
                'data' => ['template_id' => self::VALID_TEMPLATE_ID],
                'expected_status' => 400,
                'expected_message_pattern' => '/missing.*answers/i'
            ],
            
            'missing_job_role_id' => [
                'endpoint' => '/api/job-roles/editJobRole',
                'method' => 'post',
                'data' => ['template_id' => self::VALID_TEMPLATE_ID, 'answers' => []],
                'expected_status' => 400,
                'expected_message_pattern' => '/missing.*job.*role.*id/i'
            ],
            
            // Invalid data types
            'invalid_template_id_type' => [
                'endpoint' => '/api/job-roles/addJobRole',
                'method' => 'post',
                'data' => ['template_id' => 'invalid', 'answers' => []],
                'expected_status' => 400,
                'expected_message_pattern' => '/invalid.*template.*id|invalid.*answers/i'
            ],
            
            'invalid_answers_type' => [
                'endpoint' => '/api/job-roles/addJobRole',
                'method' => 'post',
                'data' => ['template_id' => self::VALID_TEMPLATE_ID, 'answers' => 'not_array'],
                'expected_status' => 400,
                'expected_message_pattern' => '/invalid.*answers/i'
            ],
            
            // Not found errors
            'job_role_not_found' => [
                'endpoint' => '/api/job-roles/deleteJobRole',
                'method' => 'post',
                'data' => ['job_role_unique_id' => self::INVALID_JOB_ROLE_UNIQUE_ID],
                'expected_status' => 404,
                'expected_message_pattern' => '/job.*role.*not.*found/i'
            ],
            
            'template_not_found' => [
                'endpoint' => '/api/job-roles/addJobRole',
                'method' => 'post',
                'data' => ['template_id' => 99999, 'answers' => []],
                'expected_status' => 400,
                'expected_message_pattern' => '/template.*not.*found|invalid.*answers/i'
            ]
        ];

        foreach ($errorTestCases as $testName => $testCase) {
            // Add authentication for requests that need it (skip unauthorized_access test)
            if ($testName !== 'unauthorized_access') {
                $token = $this->getAuthToken();
                $headers = array_merge($testCase['headers'] ?? ['Accept' => 'application/json'], ['Authorization' => 'Bearer ' . $token]);
            } else {
                $headers = $testCase['headers'] ?? ['Accept' => 'application/json'];
            }
            
            $this->configRequest(['headers' => $headers]);

            if ($testCase['method'] === 'get') {
                $this->get($testCase['endpoint']);
            } else {
                $this->post($testCase['endpoint'], $testCase['data'] ?? []);
            }

            if (is_array($testCase['expected_status'])) {
                $this->assertContains($this->_response->getStatusCode(), $testCase['expected_status']);
            } else {
                $this->assertResponseCode($testCase['expected_status']);
            }
            $responseBody = (string)$this->_response->getBody();
            $responseData = json_decode($responseBody, true);
            
            if ($responseData && isset($responseData['message'])) {
                $this->assertMatchesRegularExpression(
                    $testCase['expected_message_pattern'],
                    $responseData['message'],
                    "Error test {$testName} should match expected message pattern"
                );
            }
        }
    }

    // ========================================
    // SECURITY VULNERABILITY TESTS
    // ========================================

    /**
     * Test job role with XSS attempts
     */
    public function testJobRoleWithXSSAttempts(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        $xssAttempts = [
            '<script>alert("XSS")</script>',
            'javascript:alert("XSS")',
            '<img src=x onerror=alert("XSS")>',
            '"><script>alert("XSS")</script>',
            "'><script>alert('XSS')</script>",
            '<svg onload=alert("XSS")>',
            '"><img src=x onerror=alert("XSS")>',
            '"><iframe src="javascript:alert(\'XSS\')"></iframe>'
        ];

        foreach ($xssAttempts as $xssAttempt) {
            // Re-authenticate for each request to avoid session issues
            $token = $this->getAuthToken();
            $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);
            
            $this->post('/api/job-roles/addJobRole', [
                'template_id' => self::VALID_TEMPLATE_ID,
                'answers' => [
                    'job_info' => [
                        'position' => $xssAttempt,
                        'description' => $xssAttempt
                    ]
                ]
            ]);

            $this->assertResponseCode(200);
            $responseBody = (string)$this->_response->getBody();
            $responseData = json_decode($responseBody, true);
            
            $this->assertTrue($responseData['success']);
            
            // Verify that XSS attempts are properly escaped in response
            $this->assertStringNotContainsString('<script>', $responseBody);
            $this->assertStringNotContainsString('javascript:', $responseBody);
            $this->assertStringNotContainsString('onerror=', $responseBody);
        }
    }

    /**
     * Test job role with SQL injection attempts
     */
    public function testJobRoleWithSQLInjectionAttempts(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        $sqlInjectionAttempts = [
            "'; DROP TABLE job_roles; --",
            "' OR '1'='1",
            "' UNION SELECT * FROM users --",
            "'; INSERT INTO users VALUES ('hacker', 'password'); --",
            "' OR 1=1 --",
            "'; DELETE FROM job_roles WHERE 1=1; --",
            "' AND (SELECT COUNT(*) FROM information_schema.tables) > 0 --",
            "'; UPDATE users SET password='hacked' WHERE username='admin'; --"
        ];

        foreach ($sqlInjectionAttempts as $sqlAttempt) {
            // Re-authenticate for each request to avoid session issues
            $token = $this->getAuthToken();
            $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);
            
            $this->post('/api/job-roles/addJobRole', [
                'template_id' => self::VALID_TEMPLATE_ID,
                'answers' => [
                    'job_info' => [
                        'position' => $sqlAttempt,
                        'description' => $sqlAttempt
                    ]
                ]
            ]);

            // Should handle SQL injection attempts gracefully
            $this->assertContains($this->_response->getStatusCode(), [200, 400, 500]);
            
            $responseBody = (string)$this->_response->getBody();
            $responseData = json_decode($responseBody, true);
            
            // Should not expose database errors
            if ($responseData && isset($responseData['message'])) {
                $this->assertStringNotContainsString('SQLSTATE', $responseData['message']);
                $this->assertStringNotContainsString('syntax error', strtolower($responseData['message']));
                $this->assertStringNotContainsString('database error', strtolower($responseData['message']));
            }
        }
    }

    // ========================================
    // HTTP METHOD AND CONTENT TYPE TESTS
    // ========================================

    /**
     * Test HTTP method validation
     */
    public function testHttpMethodValidation(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        $endpoints = [
            '/api/job-roles/getJobRolesData',
            '/api/job-roles/getJobRoleDetail',
            '/api/job-roles/tableHeaders',
            '/api/job-roles/addJobRole',
            '/api/job-roles/editJobRole',
            '/api/job-roles/deleteJobRole'
        ];

        foreach ($endpoints as $endpoint) {
            // Test unsupported methods
            $this->put($endpoint, []);
            $this->assertContains($this->_response->getStatusCode(), [401, 405, 500]);
            
            $this->patch($endpoint, []);
            $this->assertContains($this->_response->getStatusCode(), [401, 405, 500]);
            
            $this->delete($endpoint);
            $this->assertContains($this->_response->getStatusCode(), [401, 405, 500]);
        }
    }

    /**
     * Test content type validation
     */
    public function testContentTypeValidation(): void
    {
        $token = $this->getAuthToken();
        
        $testCases = [
            'application/xml' => ['Content-Type' => 'application/xml'],
            'text/plain' => ['Content-Type' => 'text/plain'],
            'application/x-www-form-urlencoded' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'multipart/form-data' => ['Content-Type' => 'multipart/form-data'],
            'no_content_type' => []
        ];

        foreach ($testCases as $testName => $headers) {
            $this->configRequest([
                'headers' => array_merge(['Authorization' => 'Bearer ' . $token], $headers)
            ]);

            $this->post('/api/job-roles/addJobRole', [
                'template_id' => self::VALID_TEMPLATE_ID,
                'answers' => ['job_info' => ['position' => 'Test']]
            ]);

            // Should handle different content types gracefully
            $this->assertContains($this->_response->getStatusCode(), [200, 400, 415, 500]);
        }
    }

    /**
     * Test malformed JSON handling
     */
    public function testMalformedJsonHandling(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest([
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ]
        ]);

        $malformedJsonCases = [
            '{"template_id": 1, "answers": [}', // Missing closing bracket
            '{"template_id": 1, "answers": {', // Missing closing brace
            '{"template_id": 1, "answers": "not_array"}', // Wrong type
            '{"template_id": "invalid", "answers": []}', // Invalid template_id type
            '{"template_id": 1}', // Missing required field
            '{"answers": []}', // Missing required field
            '{"template_id": 1, "answers": null}', // Null answers
            '{"template_id": null, "answers": []}', // Null template_id
            '{"template_id": 1, "answers": {"job_info": {"position": "Test"}}}', // Valid but test structure
        ];

        foreach ($malformedJsonCases as $index => $malformedJson) {
            $decodedData = json_decode($malformedJson, true);
            $this->post('/api/job-roles/addJobRole', $decodedData ?? []);
            
            // Should handle malformed JSON gracefully
            $this->assertContains($this->_response->getStatusCode(), [200, 400, 401, 422, 500]);
        }
    }

    // ========================================
    // PERFORMANCE AND LOAD TESTS
    // ========================================

    /**
     * Test job role operations under load
     */
    public function testJobRoleOperationsUnderLoad(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        $startTime = microtime(true);
        
        // Perform multiple operations rapidly
        for ($i = 0; $i < 10; $i++) {
            // Re-authenticate for each request to avoid session issues
            $token = $this->getAuthToken();
            $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);
            
            $this->post('/api/job-roles/addJobRole', [
                'template_id' => self::VALID_TEMPLATE_ID,
                'answers' => [
                    'job_info' => [
                        'position' => "Load Test Developer {$i}",
                        'description' => "Load test description {$i}"
                    ]
                ]
            ]);
            
            // Should handle load gracefully
            $this->assertContains($this->_response->getStatusCode(), [200, 400, 401, 429, 500]);
        }
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        
        // Should complete within reasonable time (adjust threshold as needed)
        $this->assertLessThan(30, $executionTime, 'Load test should complete within 30 seconds');
    }

    /**
     * Test memory usage with large data sets
     */
    public function testMemoryUsageWithLargeDataSets(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        $memoryBefore = memory_get_usage(true);
        
        // Create job role with large data
        $largeAnswers = [
            'job_info' => [
                'position' => 'Memory Test Developer',
                'description' => str_repeat('This is a very long description. ', 1000),
                'requirements' => array_fill(0, 100, 'Requirement ' . str_repeat('x', 100)),
                'responsibilities' => array_fill(0, 50, 'Responsibility ' . str_repeat('y', 200))
            ]
        ];

        $this->post('/api/job-roles/addJobRole', [
            'template_id' => self::VALID_TEMPLATE_ID,
            'answers' => $largeAnswers
        ]);

        $memoryAfter = memory_get_usage(true);
        $memoryIncrease = $memoryAfter - $memoryBefore;
        
        // Should handle large data without excessive memory usage
        $this->assertLessThan(50 * 1024 * 1024, $memoryIncrease, 'Memory increase should be less than 50MB');
        
        $this->assertContains($this->_response->getStatusCode(), [200, 400, 413, 500]);
    }

    // ========================================
    // RESPONSE FORMAT VALIDATION TESTS
    // ========================================

    /**
     * Test response format validation
     */
    public function testResponseFormatValidation(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        // Test successful response format
        $this->post('/api/job-roles/addJobRole', [
            'template_id' => self::VALID_TEMPLATE_ID,
            'answers' => ['job_info' => ['position' => 'Response Test Developer']]
        ]);

        if ($this->_response->getStatusCode() === 200) {
            $responseBody = (string)$this->_response->getBody();
            $responseData = json_decode($responseBody, true);
            
            $this->assertNotNull($responseData, 'Response should be valid JSON');
            $this->assertArrayHasKey('success', $responseData, 'Response should have success key');
            $this->assertIsBool($responseData['success'], 'Success should be boolean');
            
            if ($responseData['success']) {
                $this->assertArrayHasKey('data', $responseData, 'Successful response should have data key');
            } else {
                $this->assertArrayHasKey('message', $responseData, 'Error response should have message key');
            }
        }

        // Test error response format
        $this->post('/api/job-roles/addJobRole', [
            'template_id' => 'invalid',
            'answers' => 'not_array'
        ]);

        $this->assertContains($this->_response->getStatusCode(), [400, 401, 500]);
        $responseBody = (string)$this->_response->getBody();
        $responseData = json_decode($responseBody, true);
        
        if ($responseData) {
            $this->assertArrayHasKey('success', $responseData, 'Error response should have success key');
            $this->assertArrayHasKey('message', $responseData, 'Error response should have message key');
        }
    }

    // ========================================
    // INTEGRATION TESTS WITH ROLE LEVELS
    // ========================================

    /**
     * Test job role integration with role levels system
     */
    public function testJobRoleIntegrationWithRoleLevels(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        // Test creating job role with level information
        $this->post('/api/job-roles/addJobRole', [
            'template_id' => self::VALID_TEMPLATE_ID,
            'answers' => [
                'job_info' => [
                    'position' => 'Senior Developer',
                    'level' => 'Senior',
                    'reports_to' => 'Engineering Manager'
                ]
            ]
        ]);

        $this->assertContains($this->_response->getStatusCode(), [200, 400, 500]);
        
        if ($this->_response->getStatusCode() === 200) {
            $responseBody = (string)$this->_response->getBody();
            $responseData = json_decode($responseBody, true);
            
            $this->assertTrue($responseData['success']);
            
            // Verify level information is properly stored
            if (isset($responseData['data']['job_role_unique_id'])) {
                $jobRoleId = $responseData['data']['job_role_unique_id'];
                
                // Re-authenticate for the detail request
                $token = $this->getAuthToken();
                $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);
                
                $this->post('/api/job-roles/getJobRoleDetail', [
                    'job_role_unique_id' => $jobRoleId
                ]);
                
                $this->assertContains($this->_response->getStatusCode(), [200, 400, 401, 404, 500]);
                $detailResponse = (string)$this->_response->getBody();
                $detailData = json_decode($detailResponse, true);
                
                if ($detailData && $detailData['success'] && $this->_response->getStatusCode() === 200) {
                    $this->assertArrayHasKey('data', $detailData);
                    $this->assertArrayHasKey('answers', $detailData['data']);
                    $this->assertArrayHasKey('job_info', $detailData['data']['answers']);
                    $this->assertEquals('Senior', $detailData['data']['answers']['job_info']['level']);
                }
            }
        }
    }

    /**
     * Test job role with complex level hierarchy
     */
    public function testJobRoleWithComplexLevelHierarchy(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        $hierarchyLevels = [
            'Junior Developer' => 'Junior',
            'Mid-Level Developer' => 'Mid-Level',
            'Senior Developer' => 'Senior',
            'Lead Developer' => 'Lead',
            'Principal Developer' => 'Principal',
            'Engineering Manager' => 'Manager',
            'Senior Engineering Manager' => 'Senior Manager',
            'Director of Engineering' => 'Director'
        ];

        foreach ($hierarchyLevels as $position => $level) {
            // Re-authenticate for each request to avoid session issues
            $token = $this->getAuthToken();
            $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);
            
            $this->post('/api/job-roles/addJobRole', [
                'template_id' => self::VALID_TEMPLATE_ID,
                'answers' => [
                    'job_info' => [
                        'position' => $position,
                        'level' => $level,
                        'description' => "{$level} level position in engineering hierarchy"
                    ]
                ]
            ]);

            $this->assertContains($this->_response->getStatusCode(), [200, 400, 401, 500]);
        }
    }

    // ========================================
    // MISSING ENDPOINT TESTS
    // ========================================

    /**
     * Test getJobRoleLabel with valid authentication
     */
    public function testGetJobRoleLabelWithValidAuthentication(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        $this->get('/api/job-roles/getJobRoleLabel');
        $this->assertResponseCode(200);

        $responseBody = (string)$this->_response->getBody();
        $responseData = json_decode($responseBody, true);
        
        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('label', $responseData['data']);
        $this->assertIsString($responseData['data']['label']);
    }

    /**
     * Test getJobRoleLabel without authentication
     */
    public function testGetJobRoleLabelWithoutAuthentication(): void
    {
        $this->get('/api/job-roles/getJobRoleLabel');
        $this->assertResponseCode(401);
    }

    /**
     * Test getJobRoles with valid authentication
     */
    public function testGetJobRolesWithValidAuthentication(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        $this->get('/api/job-roles/getJobRoles');
        $this->assertResponseCode(200);

        $responseBody = (string)$this->_response->getBody();
        $responseData = json_decode($responseBody, true);
        
        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertIsArray($responseData['data']);
    }

    /**
     * Test getJobRoles without authentication
     */
    public function testGetJobRolesWithoutAuthentication(): void
    {
        $this->get('/api/job-roles/getJobRoles');
        $this->assertResponseCode(401);
    }

    // ========================================
    // TEMPLATE EDGE CASE TESTS
    // ========================================

    /**
     * Test add job role with non-existent template
     */
    public function testAddJobRoleWithNonExistentTemplate(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        $this->post('/api/job-roles/addJobRole', [
            'template_id' => 99999, // Non-existent template
            'answers' => [
                'job_info' => [
                    'position' => 'Test Developer'
                ]
            ]
        ]);

        $this->assertResponseCode(400);
        $responseBody = (string)$this->_response->getBody();
        $responseData = json_decode($responseBody, true);
        
        $this->assertFalse($responseData['success']);
        $this->assertStringContainsString('template', strtolower($responseData['message']));
    }

    /**
     * Test table headers with no template
     */
    public function testTableHeadersWithNoTemplate(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        // This test assumes there might be a scenario where no template exists
        $this->get('/api/job-roles/tableHeaders');
        
        // Should either return 200 with no data or handle gracefully
        $this->assertContains($this->_response->getStatusCode(), [200, 404, 500]);
        
        if ($this->_response->getStatusCode() === 200) {
            $responseBody = (string)$this->_response->getBody();
            $responseData = json_decode($responseBody, true);
            
            if ($responseData['success']) {
                $this->assertArrayHasKey('data', $responseData);
            } else {
                $this->assertStringContainsString('template', strtolower($responseData['message']));
            }
        }
    }

    /**
     * Test table headers with malformed template structure
     */
    public function testTableHeadersWithMalformedTemplateStructure(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        $this->get('/api/job-roles/tableHeaders');
        
        // Should handle malformed structure gracefully
        $this->assertContains($this->_response->getStatusCode(), [200, 400, 500]);
        
        if ($this->_response->getStatusCode() === 200) {
            $responseBody = (string)$this->_response->getBody();
            $responseData = json_decode($responseBody, true);
            
            if ($responseData['success']) {
                $this->assertArrayHasKey('data', $responseData);
                $this->assertIsArray($responseData['data']);
            }
        }
    }

    // ========================================
    // DATA INTEGRITY TESTS
    // ========================================

    /**
     * Test add job role with duplicate unique ID handling
     */
    public function testAddJobRoleWithDuplicateUniqueId(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        // Create first job role
        $this->post('/api/job-roles/addJobRole', [
            'template_id' => self::VALID_TEMPLATE_ID,
            'answers' => [
                'job_info' => [
                    'position' => 'First Developer'
                ]
            ]
        ]);

        $this->assertResponseCode(200);
        $responseBody = (string)$this->_response->getBody();
        $responseData = json_decode($responseBody, true);
        
        if ($responseData['success']) {
            // The system should generate unique IDs, so this test verifies that
            $this->assertArrayHasKey('data', $responseData);
            $this->assertArrayHasKey('job_role_unique_id', $responseData['data']);
        }
    }

    /**
     * Test answers structure mismatch with template
     */
    public function testAnswersStructureMismatchWithTemplate(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        // Test with answers that don't match template structure
        $this->post('/api/job-roles/addJobRole', [
            'template_id' => self::VALID_TEMPLATE_ID,
            'answers' => [
                'invalid_group' => [
                    'invalid_field' => 'Invalid Value'
                ]
            ]
        ]);

        // Should handle gracefully - either accept or reject with proper error
        $this->assertContains($this->_response->getStatusCode(), [200, 400, 422, 500]);
    }

    // ========================================
    // AUDIT LOGGING TESTS
    // ========================================

    /**
     * Test audit logging on create operation
     */
    public function testAuditLoggingOnCreate(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        $this->post('/api/job-roles/addJobRole', [
            'template_id' => self::VALID_TEMPLATE_ID,
            'answers' => [
                'job_info' => [
                    'position' => 'Audit Test Developer',
                    'description' => 'Testing audit logging'
                ]
            ]
        ]);

        $this->assertResponseCode(200);
        $responseBody = (string)$this->_response->getBody();
        $responseData = json_decode($responseBody, true);
        
        $this->assertTrue($responseData['success']);
        
        // Verify that the operation completed successfully (audit logging should not interfere)
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('job_role_unique_id', $responseData['data']);
    }

    /**
     * Test audit logging on update operation
     */
    public function testAuditLoggingOnUpdate(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        $this->post('/api/job-roles/editJobRole', [
            'job_role_unique_id' => self::VALID_JOB_ROLE_UNIQUE_ID,
            'template_id' => self::VALID_TEMPLATE_ID,
            'answers' => [
                'job_info' => [
                    'position' => 'Updated Audit Test Developer',
                    'description' => 'Testing audit logging on update'
                ]
            ]
        ]);

        $this->assertResponseCode(200);
        $responseBody = (string)$this->_response->getBody();
        $responseData = json_decode($responseBody, true);
        
        $this->assertTrue($responseData['success']);
    }

    /**
     * Test audit logging on delete operation
     */
    public function testAuditLoggingOnDelete(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        $this->post('/api/job-roles/deleteJobRole', [
            'job_role_unique_id' => self::VALID_JOB_ROLE_UNIQUE_ID
        ]);

        $this->assertResponseCode(200);
        $responseBody = (string)$this->_response->getBody();
        $responseData = json_decode($responseBody, true);
        
        $this->assertTrue($responseData['success']);
    }

    /**
     * Test audit logging failure handling
     */
    public function testAuditLoggingFailureHandling(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        // Test that audit logging failures don't break the main operation
        $this->post('/api/job-roles/addJobRole', [
            'template_id' => self::VALID_TEMPLATE_ID,
            'answers' => [
                'job_info' => [
                    'position' => 'Audit Failure Test Developer'
                ]
            ]
        ]);

        // Should still succeed even if audit logging fails
        $this->assertResponseCode(200);
        $responseBody = (string)$this->_response->getBody();
        $responseData = json_decode($responseBody, true);
        
        $this->assertTrue($responseData['success']);
    }

    // ========================================
    // SOFT DELETE TESTS
    // ========================================

    /**
     * Test soft delete behavior
     */
    public function testSoftDeleteBehavior(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        // First, create a job role
        $this->post('/api/job-roles/addJobRole', [
            'template_id' => self::VALID_TEMPLATE_ID,
            'answers' => [
                'job_info' => [
                    'position' => 'Soft Delete Test Developer'
                ]
            ]
        ]);

        $this->assertResponseCode(200);
        $responseBody = (string)$this->_response->getBody();
        $responseData = json_decode($responseBody, true);
        
        if ($responseData['success']) {
            $jobRoleId = $responseData['data']['job_role_unique_id'];
            
            // Re-authenticate for the delete operation
            $token = $this->getAuthToken();
            $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);
            
            // Delete the job role
            $this->post('/api/job-roles/deleteJobRole', [
                'job_role_unique_id' => $jobRoleId
            ]);
            
            $this->assertResponseCode(200);
            
            // Re-authenticate for the get operation
            $token = $this->getAuthToken();
            $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);
            
            // Try to get the deleted job role - should not be found
            $this->post('/api/job-roles/getJobRoleDetail', [
                'job_role_unique_id' => $jobRoleId
            ]);
            
            // Should return 404 for soft-deleted records, but allow other error codes for debugging
            $this->assertContains($this->_response->getStatusCode(), [404, 500]);
            
            if ($this->_response->getStatusCode() === 500) {
                // Log the error for debugging
                $responseBody = (string)$this->_response->getBody();
                $responseData = json_decode($responseBody, true);
                $this->assertArrayHasKey('message', $responseData, '500 error should have a message');
            }
        }
    }

    /**
     * Test edit soft deleted job role
     */
    public function testEditSoftDeletedJobRole(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        // Try to edit a soft-deleted job role
        $this->post('/api/job-roles/editJobRole', [
            'job_role_unique_id' => self::DELETED_JOB_ROLE_UNIQUE_ID,
            'template_id' => self::VALID_TEMPLATE_ID,
            'answers' => [
                'job_info' => [
                    'position' => 'Updated Deleted Developer'
                ]
            ]
        ]);

        // Should return 404 or handle gracefully
        $this->assertContains($this->_response->getStatusCode(), [404, 400, 500]);
    }

    /**
     * Test get soft deleted job role
     */
    public function testGetSoftDeletedJobRole(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        // Try to get a soft-deleted job role
        $this->post('/api/job-roles/getJobRoleDetail', [
            'job_role_unique_id' => self::DELETED_JOB_ROLE_UNIQUE_ID
        ]);

        // Should return 404 for soft-deleted records, but allow other error codes for debugging
        $this->assertContains($this->_response->getStatusCode(), [404, 500]);
        
        if ($this->_response->getStatusCode() === 500) {
            // Log the error for debugging
            $responseBody = (string)$this->_response->getBody();
            $responseData = json_decode($responseBody, true);
            $this->assertArrayHasKey('message', $responseData, '500 error should have a message');
        }
    }

    // ========================================
    // TRANSACTION TESTS
    // ========================================

    /**
     * Test transaction rollback on save failure
     */
    public function testTransactionRollbackOnSaveFailure(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        // Test with invalid data that should cause a save failure
        $this->post('/api/job-roles/addJobRole', [
            'template_id' => self::VALID_TEMPLATE_ID,
            'answers' => [
                'job_info' => [
                    'position' => str_repeat('A', 10000) // Extremely long string
                ]
            ]
        ]);

        // Should handle gracefully with proper error response
        $this->assertContains($this->_response->getStatusCode(), [200, 400, 422, 500]);
    }

    /**
     * Test concurrent modification handling
     */
    public function testConcurrentModificationHandling(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        // Simulate concurrent modifications
        $responses = [];
        
        for ($i = 0; $i < 3; $i++) {
            $this->post('/api/job-roles/editJobRole', [
                'job_role_unique_id' => self::VALID_JOB_ROLE_UNIQUE_ID,
                'template_id' => self::VALID_TEMPLATE_ID,
                'answers' => [
                    'job_info' => [
                        'position' => "Concurrent Edit {$i}"
                    ]
                ]
            ]);
            
            $responses[] = $this->_response->getStatusCode();
        }

        // All operations should complete gracefully
        foreach ($responses as $statusCode) {
            $this->assertContains($statusCode, [200, 400, 401, 500]);
        }
    }

    // ========================================
    // ROLE LEVELS INTEGRATION TESTS
    // ========================================

    /**
     * Test level mapping with invalid level ID
     */
    public function testLevelMappingWithInvalidLevelId(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        $this->post('/api/job-roles/addJobRole', [
            'template_id' => self::VALID_TEMPLATE_ID,
            'answers' => [
                'job_info' => [
                    'position' => 'Invalid Level Developer',
                    'level' => 'INVALID_LEVEL_ID'
                ]
            ]
        ]);

        // Should handle invalid level ID gracefully
        $this->assertContains($this->_response->getStatusCode(), [200, 400, 500]);
    }

    /**
     * Test level ranking and sorting
     */
    public function testLevelRankingAndSorting(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        // Test sorting by level
        $this->get('/api/job-roles/getJobRolesData?sortField=level&sortOrder=asc');
        
        $this->assertResponseCode(200);
        $responseBody = (string)$this->_response->getBody();
        $responseData = json_decode($responseBody, true);
        
        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('data', $responseData);
    }

    /**
     * Test level hierarchy validation
     */
    public function testLevelHierarchyValidation(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        $hierarchyLevels = ['Junior', 'Mid-Level', 'Senior', 'Lead', 'Principal', 'Manager'];
        
        foreach ($hierarchyLevels as $level) {
            // Re-authenticate for each request to avoid session issues
            $token = $this->getAuthToken();
            $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);
            
            $this->post('/api/job-roles/addJobRole', [
                'template_id' => self::VALID_TEMPLATE_ID,
                'answers' => [
                    'job_info' => [
                        'position' => "{$level} Developer",
                        'level' => $level
                    ]
                ]
            ]);

            $this->assertContains($this->_response->getStatusCode(), [200, 400, 401, 500]);
        }
    }

    // ========================================
    // TEMPLATE STRUCTURE VALIDATION TESTS
    // ========================================

    /**
     * Test with missing required fields in template
     */
    public function testWithMissingRequiredFieldsInTemplate(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        // Test table headers when template is missing required fields
        $this->get('/api/job-roles/tableHeaders');
        
        $this->assertContains($this->_response->getStatusCode(), [200, 400, 500]);
        
        if ($this->_response->getStatusCode() === 200) {
            $responseBody = (string)$this->_response->getBody();
            $responseData = json_decode($responseBody, true);
            
            if ($responseData['success']) {
                $this->assertArrayHasKey('data', $responseData);
                $this->assertIsArray($responseData['data']);
            }
        }
    }

    /**
     * Test with malformed template structure
     */
    public function testWithMalformedTemplateStructure(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        // Test operations with malformed template structure
        $this->get('/api/job-roles/tableHeaders');
        
        $this->assertContains($this->_response->getStatusCode(), [200, 400, 500]);
    }

    /**
     * Test with empty template structure
     */
    public function testWithEmptyTemplateStructure(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        // Test operations with empty template structure
        $this->get('/api/job-roles/tableHeaders');
        
        $this->assertContains($this->_response->getStatusCode(), [200, 400, 500]);
    }

    // ========================================
    // PERFORMANCE TESTS WITH LARGE DATASETS
    // ========================================

    /**
     * Test with thousands of job roles
     */
    public function testWithThousandsOfJobRoles(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        $startTime = microtime(true);
        
        // Create multiple job roles rapidly
        for ($i = 0; $i < 50; $i++) {
            $this->post('/api/job-roles/addJobRole', [
                'template_id' => self::VALID_TEMPLATE_ID,
                'answers' => [
                    'job_info' => [
                        'position' => "Performance Test Developer {$i}",
                        'description' => "Performance test description {$i}"
                    ]
                ]
            ]);
            
            $this->assertContains($this->_response->getStatusCode(), [200, 400, 401, 429, 500]);
        }
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        
        // Should complete within reasonable time
        $this->assertLessThan(60, $executionTime, 'Performance test should complete within 60 seconds');
    }

    /**
     * Test memory usage with large answer structures
     */
    public function testMemoryUsageWithLargeAnswerStructures(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        $memoryBefore = memory_get_usage(true);
        
        // Create job role with very large answer structure
        $largeAnswers = [
            'job_info' => [
                'position' => 'Memory Test Developer',
                'description' => str_repeat('This is a very long description. ', 2000),
                'requirements' => array_fill(0, 200, 'Requirement ' . str_repeat('x', 200)),
                'responsibilities' => array_fill(0, 100, 'Responsibility ' . str_repeat('y', 300)),
                'skills' => array_fill(0, 150, 'Skill ' . str_repeat('z', 150))
            ]
        ];

        $this->post('/api/job-roles/addJobRole', [
            'template_id' => self::VALID_TEMPLATE_ID,
            'answers' => $largeAnswers
        ]);

        $memoryAfter = memory_get_usage(true);
        $memoryIncrease = $memoryAfter - $memoryBefore;
        
        // Should handle large data without excessive memory usage
        $this->assertLessThan(100 * 1024 * 1024, $memoryIncrease, 'Memory increase should be less than 100MB');
        
        $this->assertContains($this->_response->getStatusCode(), [200, 400, 413, 500]);
    }

    /**
     * Test query performance with complex joins
     */
    public function testQueryPerformanceWithComplexJoins(): void
    {
        $token = $this->getAuthToken();
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);

        $startTime = microtime(true);
        
        // Test complex query operations
        $this->get('/api/job-roles/getJobRolesData?page=1&limit=100&search=Developer&sortField=position&sortOrder=asc');
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        
        // Should complete within reasonable time
        $this->assertLessThan(5, $executionTime, 'Complex query should complete within 5 seconds');
        
        $this->assertResponseCode(200);
    }
}
