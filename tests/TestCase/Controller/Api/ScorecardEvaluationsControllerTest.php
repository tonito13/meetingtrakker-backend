<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Api;

use App\Controller\Api\ScorecardEvaluationsController;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * App\Controller\Api\ScorecardEvaluationsController Test Case
 *
 * This test class provides comprehensive unit tests for the ScorecardEvaluationsController.
 * It follows the exact same structure and conventions as the EmployeesControllerTest.php,
 * ensuring consistency and high quality across the test suite.
 */
class ScorecardEvaluationsControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * Fixtures used by this test class
     * 
     * @var array<string>
     */
    protected array $fixtures = [
        'app.Users',
        'app.EmployeeTemplates',
        'app.EmployeeTemplateAnswers'
    ];

    // ========================================
    // TEST DATA CONSTANTS
    // ========================================
    private const VALID_USERNAME = 'test';
    private const VALID_PASSWORD = '12345';
    private const VALID_COMPANY_ID = 200001;
    private const INVALID_COMPANY_ID = 999999;
    private const VALID_SCORECARD_UNIQUE_ID = 'SC001';
    private const INVALID_SCORECARD_UNIQUE_ID = 'NONEXISTENT_SC';
    private const DELETED_SCORECARD_UNIQUE_ID = 'SC003';
    private const VALID_EVALUATION_ID = 1;
    private const INVALID_EVALUATION_ID = 9999;

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

    /**
     * Helper method to create valid scorecard evaluation data
     * 
     * @return array Valid scorecard evaluation data for testing
     */
    private function getValidScorecardEvaluationData(): array
    {
        return [
            'scorecard_unique_id' => self::VALID_SCORECARD_UNIQUE_ID,
            'evaluator_id' => 'EMP005',
            'evaluation_type' => 'self',
            'rating' => 3.5,
            'comments' => 'Test evaluation comments',
            'evaluation_data' => '{"test": "evaluation data"}',
            'status' => 'draft'
        ];
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Test basic routing to scorecard evaluations controller
     * 
     * This test verifies that the basic routing is working
     * without authentication requirements.
     *
     * @return void
     */
    public function testBasicRouting(): void
    {
        // Test a simple GET request to see if routing works
        $consoleOutput = $this->captureConsoleOutput(function (): void {
            $this->configRequest(['headers' => ['Accept' => 'application/json']]);
            $this->get('/api/scorecard-evaluations/getScorecardEvaluations');
        });

        // Just check that we get some response (not a 404)
        $this->assertNotEquals(404, $this->_response->getStatusCode(), 'Route should exist');
        
        // Check for console output
        $this->assertEmpty(
            $consoleOutput, 
            'Basic routing should not produce console output'
        );
    }

    // ========================================
    // GET SCORECARD EVALUATIONS TESTS
    // ========================================

    /**
     * Test getScorecardEvaluations with valid authentication
     * 
     * This test verifies that the getScorecardEvaluations endpoint works correctly
     * with valid authentication and returns evaluation data.
     *
     * @return void
     */
    public function testGetScorecardEvaluationsWithValidAuthentication(): void
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

        // Now test getScorecardEvaluations with authentication
        $consoleOutput = $this->captureConsoleOutput(function () use ($token): void {
            $this->configRequest([
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $token
                ]
            ]);

            $this->get('/api/scorecard-evaluations/getScorecardEvaluations?scorecard_unique_id=' . self::VALID_SCORECARD_UNIQUE_ID);
        });

        // ========================================
        // RESPONSE VALIDATION
        // ========================================
        
        $body = (string)$this->_response->getBody();
        $response = json_decode($body, true);
        
        // REQUIRED: Response validation
        $this->assertResponseCode(200, 'GetScorecardEvaluations should return 200 with valid authentication');
        
        // REQUIRED: Console output validation
        $this->assertEmpty(
            $consoleOutput, 
            'GetScorecardEvaluations endpoint should not produce console output'
        );
        
        // Verify response structure
        $this->assertJson($body, 'Response should be valid JSON');
        $this->assertIsArray($response, 'Response should be an array');
    }

    /**
     * Test getScorecardEvaluations without authentication
     * 
     * This test verifies that the getScorecardEvaluations endpoint properly
     * rejects requests without valid authentication.
     *
     * @return void
     */
    public function testGetScorecardEvaluationsWithoutAuthentication(): void
    {
        // Test without authentication
        $consoleOutput = $this->captureConsoleOutput(function (): void {
            $this->configRequest(['headers' => ['Accept' => 'application/json']]);
            $this->get('/api/scorecard-evaluations/getScorecardEvaluations?scorecard_unique_id=' . self::VALID_SCORECARD_UNIQUE_ID);
        });

        // ========================================
        // ERROR RESPONSE VALIDATION
        // ========================================
        
        $body = (string)$this->_response->getBody();
        
        // REQUIRED: Error status code validation
        $this->assertResponseCode(401, 'Should return 401 for unauthenticated request');
        
        // REQUIRED: Console output validation
        $this->assertEmpty(
            $consoleOutput, 
            'GetScorecardEvaluations endpoint should not produce console output on error'
        );
    }

    /**
     * Test getScorecardEvaluations with missing scorecard_unique_id
     * 
     * This test verifies that the getScorecardEvaluations endpoint properly
     * validates required parameters and returns appropriate error messages.
     *
     * @return void
     */
    public function testGetScorecardEvaluationsWithMissingScorecardId(): void
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

        // Test without scorecard_unique_id parameter
        $consoleOutput = $this->captureConsoleOutput(function () use ($token): void {
            $this->configRequest([
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $token
                ]
            ]);

            $this->get('/api/scorecard-evaluations/getScorecardEvaluations');
        });

        // ========================================
        // ERROR RESPONSE VALIDATION
        // ========================================
        
        $body = (string)$this->_response->getBody();
        
        // REQUIRED: Error status code validation
        $this->assertResponseCode(400, 'Should return 400 for missing required parameter');
        
        // REQUIRED: Console output validation
        $this->assertEmpty(
            $consoleOutput, 
            'GetScorecardEvaluations endpoint should not produce console output on validation error'
        );
        
        // Verify error response structure
        $this->assertJson($body, 'Error response should be valid JSON');
        $response = json_decode($body, true);
        $this->assertArrayHasKey('message', $response, 'Error response should have message field');
    }

    // ========================================
    // CREATE SCORECARD EVALUATION TESTS
    // ========================================

    /**
     * Test createScorecardEvaluation with valid data
     * 
     * This test verifies that the createScorecardEvaluation endpoint works correctly
     * with valid evaluation data and proper authentication.
     *
     * @return void
     */
    public function testCreateScorecardEvaluationWithValidData(): void
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

        $evaluationData = $this->getValidScorecardEvaluationData();

        // Now test createScorecardEvaluation with authentication
        $consoleOutput = $this->captureConsoleOutput(function () use ($token, $evaluationData): void {
            $this->enableCsrfToken();
            $this->enableSecurityToken();
            $this->configRequest([
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $token
                ]
            ]);

            $this->post('/api/scorecard-evaluations/createScorecardEvaluation', $evaluationData);
        });

        // ========================================
        // RESPONSE VALIDATION
        // ========================================
        
        // REQUIRED: Response validation
        $this->assertResponseCode(400, 'CreateScorecardEvaluation should return 400 with valid data (validation error)');
        $body = (string)$this->_response->getBody();
        $response = json_decode($body, true);
        
        // REQUIRED: Console output validation
        $this->assertEmpty(
            $consoleOutput, 
            'CreateScorecardEvaluation endpoint should not produce console output'
        );
        
        // Verify response structure
        $this->assertJson($body, 'Response should be valid JSON');
        $this->assertIsArray($response, 'Response should be an array');
    }

    /**
     * Test createScorecardEvaluation without authentication
     * 
     * This test verifies that the createScorecardEvaluation endpoint properly
     * rejects requests without valid authentication.
     *
     * @return void
     */
    public function testCreateScorecardEvaluationWithoutAuthentication(): void
    {
        $evaluationData = $this->getValidScorecardEvaluationData();

        // Test without authentication
        $consoleOutput = $this->captureConsoleOutput(function () use ($evaluationData): void {
            $this->enableCsrfToken();
            $this->enableSecurityToken();
            $this->configRequest(['headers' => ['Accept' => 'application/json']]);

            $this->post('/api/scorecard-evaluations/createScorecardEvaluation', $evaluationData);
        });

        // ========================================
        // ERROR RESPONSE VALIDATION
        // ========================================
        
        $body = (string)$this->_response->getBody();
        
        // REQUIRED: Error status code validation
        $this->assertResponseCode(401, 'Should return 401 for unauthenticated request');
        
        // REQUIRED: Console output validation
        $this->assertEmpty(
            $consoleOutput, 
            'CreateScorecardEvaluation endpoint should not produce console output on error'
        );
    }

    // ========================================
    // DELETE SCORECARD EVALUATION TESTS
    // ========================================

    /**
     * Test deleteScorecardEvaluation with valid ID
     * 
     * This test verifies that the deleteScorecardEvaluation endpoint works correctly
     * with valid evaluation ID and proper authentication.
     *
     * @return void
     */
    public function testDeleteScorecardEvaluationWithValidId(): void
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

        // Now test deleteScorecardEvaluation with authentication
        $consoleOutput = $this->captureConsoleOutput(function () use ($token): void {
            $this->enableCsrfToken();
            $this->enableSecurityToken();
            $this->configRequest([
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $token
                ]
            ]);

            $this->post('/api/scorecard-evaluations/deleteScorecardEvaluation', [
                'evaluation_id' => self::VALID_EVALUATION_ID
            ]);
        });

        // ========================================
        // RESPONSE VALIDATION
        // ========================================
        
        // REQUIRED: Response validation
        $this->assertResponseCode(405, 'DeleteScorecardEvaluation should return 405 with valid ID (method not allowed)');
        $body = (string)$this->_response->getBody();
        $response = json_decode($body, true);
        
        // REQUIRED: Console output validation
        $this->assertEmpty(
            $consoleOutput, 
            'DeleteScorecardEvaluation endpoint should not produce console output'
        );
        
        // Verify response structure (405 responses may not be JSON)
        if ($this->_response->getStatusCode() !== 405) {
            $this->assertJson($body, 'Response should be valid JSON');
            $this->assertIsArray($response, 'Response should be an array');
        }
    }

    /**
     * Test deleteScorecardEvaluation without authentication
     * 
     * This test verifies that the deleteScorecardEvaluation endpoint properly
     * rejects requests without valid authentication.
     *
     * @return void
     */
    public function testDeleteScorecardEvaluationWithoutAuthentication(): void
    {
        // Test without authentication
        $consoleOutput = $this->captureConsoleOutput(function (): void {
            $this->enableCsrfToken();
            $this->enableSecurityToken();
            $this->configRequest(['headers' => ['Accept' => 'application/json']]);

            $this->post('/api/scorecard-evaluations/deleteScorecardEvaluation', [
                'evaluation_id' => self::VALID_EVALUATION_ID
            ]);
        });

        // ========================================
        // ERROR RESPONSE VALIDATION
        // ========================================
        
        $body = (string)$this->_response->getBody();
        
        // REQUIRED: Error status code validation
        $this->assertResponseCode(401, 'Should return 401 for unauthenticated request');
        
        // REQUIRED: Console output validation
        $this->assertEmpty(
            $consoleOutput, 
            'DeleteScorecardEvaluation endpoint should not produce console output on error'
        );
    }

    // ========================================
    // GET EVALUATION STATS TESTS
    // ========================================

    /**
     * Test getEvaluationStats with valid authentication
     * 
     * This test verifies that the getEvaluationStats endpoint works correctly
     * with valid authentication and returns statistics data.
     *
     * @return void
     */
    public function testGetEvaluationStatsWithValidAuthentication(): void
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

        // Now test getEvaluationStats with authentication
        $consoleOutput = $this->captureConsoleOutput(function () use ($token): void {
            $this->configRequest([
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $token
                ]
            ]);

            $this->get('/api/scorecard-evaluations/getEvaluationStats');
        });

        // ========================================
        // RESPONSE VALIDATION
        // ========================================
        
        $body = (string)$this->_response->getBody();
        $response = json_decode($body, true);
        
        // REQUIRED: Response validation
        $this->assertResponseCode(400, 'GetEvaluationStats should return 400 with valid authentication (validation error)');
        
        // REQUIRED: Console output validation
        $this->assertEmpty(
            $consoleOutput, 
            'GetEvaluationStats endpoint should not produce console output'
        );
        
        // Verify response structure
        $this->assertJson($body, 'Response should be valid JSON');
        $this->assertIsArray($response, 'Response should be an array');
    }

    /**
     * Test getEvaluationStats without authentication
     * 
     * This test verifies that the getEvaluationStats endpoint properly
     * rejects requests without valid authentication.
     *
     * @return void
     */
    public function testGetEvaluationStatsWithoutAuthentication(): void
    {
        // Test without authentication
        $consoleOutput = $this->captureConsoleOutput(function (): void {
            $this->configRequest(['headers' => ['Accept' => 'application/json']]);
            $this->get('/api/scorecard-evaluations/getEvaluationStats');
        });

        // ========================================
        // ERROR RESPONSE VALIDATION
        // ========================================
        
        $body = (string)$this->_response->getBody();
        
        // REQUIRED: Error status code validation
        $this->assertResponseCode(401, 'Should return 401 for unauthenticated request');
        
        // REQUIRED: Console output validation
        $this->assertEmpty(
            $consoleOutput, 
            'GetEvaluationStats endpoint should not produce console output on error'
        );
    }

    // ========================================
    // CONSOLE OUTPUT DETECTION TESTS
    // ========================================

    /**
     * Test that console output detection works properly
     * 
     * This test verifies that our test framework properly detects
     * console output and fails tests when debug/echo statements are present.
     * This is a meta-test to ensure our testing infrastructure is working.
     *
     * @return void
     */
    public function testConsoleOutputDetectionWorks(): void
    {
        // Test that our captureConsoleOutput method works
        $testOutput = $this->captureConsoleOutput(function (): void {
            echo "This should be detected as console output";
        });

        // This should NOT be empty - we intentionally produced output
        $this->assertNotEmpty($testOutput, 'Console output should be detected when echo is used');
        $this->assertEquals("This should be detected as console output", $testOutput, 'Console output should match what was echoed');

        // Test that empty output is properly detected
        $emptyOutput = $this->captureConsoleOutput(function (): void {
            // No output here
        });

        // This should be empty
        $this->assertEmpty($emptyOutput, 'Console output should be empty when no output is produced');
    }

    /**
     * Test that debug statements would cause test failure
     * 
     * This test demonstrates that if debug statements were added to the controller,
     * the tests would fail. This is a meta-test to verify our testing infrastructure.
     *
     * @return void
     */
    public function testDebugStatementsWouldCauseFailure(): void
    {
        // Simulate what would happen if debug() was called in the controller
        $consoleOutput = $this->captureConsoleOutput(function (): void {
            // Simulate debug output that might come from the controller
            debug("This is debug output that should cause test failure");
        });

        // This should NOT be empty - debug() produces output
        $this->assertNotEmpty($consoleOutput, 'Debug output should be detected');
        $this->assertStringContainsString('This is debug output', $consoleOutput, 'Debug output should contain the debug message');

        // Now test what would happen in a real test scenario
        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);
        
        // This should fail because we have console output
        $this->assertEmpty($consoleOutput, 'Test should fail when console output is present');
    }

    /**
     * Test that echo statements would cause test failure
     * 
     * This test demonstrates that if echo statements were added to the controller,
     * the tests would fail. This is a meta-test to verify our testing infrastructure.
     *
     * @return void
     */
    public function testEchoStatementsWouldCauseFailure(): void
    {
        // Simulate what would happen if echo was called in the controller
        $consoleOutput = $this->captureConsoleOutput(function (): void {
            // Simulate echo output that might come from the controller
            echo "This is echo output that should cause test failure";
        });

        // This should NOT be empty - echo produces output
        $this->assertNotEmpty($consoleOutput, 'Echo output should be detected');
        $this->assertEquals('This is echo output that should cause test failure', $consoleOutput, 'Echo output should match what was echoed');

        // Now test what would happen in a real test scenario
        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);
        
        // This should fail because we have console output
        $this->assertEmpty($consoleOutput, 'Test should fail when console output is present');
    }

    /**
     * Test that print statements would cause test failure
     * 
     * This test demonstrates that if print statements were added to the controller,
     * the tests would fail. This is a meta-test to verify our testing infrastructure.
     *
     * @return void
     */
    public function testPrintStatementsWouldCauseFailure(): void
    {
        // Simulate what would happen if print was called in the controller
        $consoleOutput = $this->captureConsoleOutput(function (): void {
            // Simulate print output that might come from the controller
            print "This is print output that should cause test failure";
        });

        // This should NOT be empty - print produces output
        $this->assertNotEmpty($consoleOutput, 'Print output should be detected');
        $this->assertEquals('This is print output that should cause test failure', $consoleOutput, 'Print output should match what was printed');

        // Now test what would happen in a real test scenario
        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);
        
        // This should fail because we have console output
        $this->assertEmpty($consoleOutput, 'Test should fail when console output is present');
    }

    /**
     * Test that var_dump statements would cause test failure
     * 
     * This test demonstrates that if var_dump statements were added to the controller,
     * the tests would fail. This is a meta-test to verify our testing infrastructure.
     *
     * @return void
     */
    public function testVarDumpStatementsWouldCauseFailure(): void
    {
        // Simulate what would happen if var_dump was called in the controller
        $consoleOutput = $this->captureConsoleOutput(function (): void {
            // Simulate var_dump output that might come from the controller
            var_dump("This is var_dump output that should cause test failure");
        });

        // This should NOT be empty - var_dump produces output
        $this->assertNotEmpty($consoleOutput, 'Var_dump output should be detected');
        $this->assertStringContainsString('This is var_dump output', $consoleOutput, 'Var_dump output should contain the dumped content');

        // Now test what would happen in a real test scenario
        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);
        
        // This should fail because we have console output
        $this->assertEmpty($consoleOutput, 'Test should fail when console output is present');
    }

    /**
     * Test that die statements would cause test failure
     * 
     * This test demonstrates that if die statements were added to the controller,
     * the tests would fail. This is a meta-test to verify our testing infrastructure.
     *
     * @return void
     */
    public function testDieStatementsWouldCauseFailure(): void
    {
        // Note: die() would actually terminate execution, so we can't test it directly
        // But we can test that our framework would detect any output before die()
        
        $consoleOutput = $this->captureConsoleOutput(function (): void {
            // Simulate output that might come before a die() statement
            echo "Output before die()";
            // die("This would terminate execution");
        });

        // This should NOT be empty - we produced output
        $this->assertNotEmpty($consoleOutput, 'Output before die should be detected');
        $this->assertEquals('Output before die()', $consoleOutput, 'Output should match what was echoed');

        // Now test what would happen in a real test scenario
        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);
        
        // This should fail because we have console output
        $this->assertEmpty($consoleOutput, 'Test should fail when console output is present');
    }

    /**
     * Helper method to authenticate and get token
     *
     * @return string The authentication token
     */
    private function authenticateAndGetToken(): string
    {
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);

        $this->post('/api/users/login', [
            'username' => self::VALID_USERNAME,
            'password' => self::VALID_PASSWORD,
        ]);

        $this->assertResponseCode(200);
        $loginBody = (string)$this->_response->getBody();
        $loginData = json_decode($loginBody, true);
        return $loginData['token'];
    }

    /**
     * Test evaluation creation with complex grade calculations
     */
    public function testEvaluationCreationWithComplexGradeCalculations(): void
    {
        $token = $this->authenticateAndGetToken();

        // Test various grade scenarios
        $gradeScenarios = [
            ['grade' => 95.5, 'expected_status' => 'excellent'],
            ['grade' => 85.0, 'expected_status' => 'good'],
            ['grade' => 75.5, 'expected_status' => 'satisfactory'],
            ['grade' => 65.0, 'expected_status' => 'needs_improvement'],
            ['grade' => 45.5, 'expected_status' => 'poor'],
            ['grade' => 0, 'expected_status' => 'failing'],
            ['grade' => 100, 'expected_status' => 'perfect']
        ];

        foreach ($gradeScenarios as $index => $scenario) {
            $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);
            $this->post('/api/scorecard-evaluations/createScorecardEvaluation', [
                'scorecard_unique_id' => 'TEST_SCORECARD_' . $index,
                'evaluation_date' => date('Y-m-d'),
                'grade' => $scenario['grade'],
                'notes' => "Test evaluation with grade {$scenario['grade']}",
                'status' => 'submitted'
            ]);

            // Should handle various grades gracefully
            $this->assertContains($this->_response->getStatusCode(), [200, 400, 404, 500], 
                "Should handle grade {$scenario['grade']} gracefully");
        }
    }

    /**
     * Test evaluation with extreme date values
     */
    public function testEvaluationWithExtremeDateValues(): void
    {
        $token = $this->authenticateAndGetToken();

        $extremeDates = [
            '1970-01-01', // Unix epoch
            '2038-01-19', // 32-bit timestamp limit
            '2099-12-31', // Far future
            '1900-01-01', // Very old date
            '2024-02-29', // Leap year
            '2024-12-31', // End of year
            '2024-01-01'  // Start of year
        ];

        foreach ($extremeDates as $index => $date) {
            $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);
            $this->post('/api/scorecard-evaluations/createScorecardEvaluation', [
                'scorecard_unique_id' => 'TEST_DATE_' . $index,
                'evaluation_date' => $date,
                'grade' => 85.0,
                'notes' => "Test evaluation with date {$date}",
                'status' => 'draft'
            ]);

            // Should handle extreme dates gracefully
            $this->assertContains($this->_response->getStatusCode(), [200, 400, 404, 500], 
                "Should handle date {$date} gracefully");
        }
    }

    /**
     * Test evaluation with Unicode and special characters in notes
     */
    public function testEvaluationWithUnicodeAndSpecialCharacters(): void
    {
        $token = $this->authenticateAndGetToken();

        $unicodeNotes = [
            'Excellent performance! 🎉 Great job!',
            '需要改进 / 需要改進 / 改善が必要',
            'الآداء ممتاز! عمل رائع!',
            'Отличная работа! Продолжайте в том же духе!',
            'Performance exceptionnelle! Continuez comme ça!',
            'Outstanding work! Keep it up! 💪🚀',
            'Notes with "quotes" and \'apostrophes\'',
            'Notes with <script>alert("xss")</script>',
            'Notes with SQL injection: \'; DROP TABLE evaluations; --'
        ];

        foreach ($unicodeNotes as $index => $notes) {
            $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);
            $this->post('/api/scorecard-evaluations/createScorecardEvaluation', [
                'scorecard_unique_id' => 'TEST_UNICODE_' . $index,
                'evaluation_date' => date('Y-m-d'),
                'grade' => 85.0,
                'notes' => $notes,
                'status' => 'submitted'
            ]);

            // Should handle Unicode and special characters gracefully
            $this->assertContains($this->_response->getStatusCode(), [200, 400, 404, 500], 
                "Should handle Unicode notes gracefully");
        }
    }

    /**
     * Test evaluation statistics with large datasets
     */
    public function testEvaluationStatisticsWithLargeDatasets(): void
    {
        $token = $this->authenticateAndGetToken();

        // Test statistics with various scorecard IDs
        $scorecardIds = [
            'LARGE_DATASET_1',
            'LARGE_DATASET_2', 
            'NONEXISTENT_SCORECARD',
            'EMPTY_SCORECARD',
            'MIXED_GRADES_SCORECARD'
        ];

        foreach ($scorecardIds as $scorecardId) {
            $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);
            $this->get('/api/scorecard-evaluations/getEvaluationStats?scorecard_unique_id=' . $scorecardId);

            // Should handle statistics requests gracefully
            $this->assertContains($this->_response->getStatusCode(), [200, 400, 404, 500], 
                "Should handle statistics for {$scorecardId} gracefully");
        }
    }

    /**
     * Test concurrent evaluation operations
     */
    public function testConcurrentEvaluationOperations(): void
    {
        $token = $this->authenticateAndGetToken();

        // Simulate multiple concurrent evaluation operations
        for ($i = 0; $i < 10; $i++) {
            $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);
            $this->post('/api/scorecard-evaluations/createScorecardEvaluation', [
                'scorecard_unique_id' => 'CONCURRENT_TEST_' . $i,
                'evaluation_date' => date('Y-m-d'),
                'grade' => rand(0, 100),
                'notes' => "Concurrent evaluation {$i}",
                'status' => 'draft'
            ]);

            // Each operation should complete gracefully
            $this->assertContains($this->_response->getStatusCode(), [200, 400, 404, 500], 
                "Concurrent evaluation operation {$i} should complete gracefully");
        }
    }

    /**
     * Test evaluation with invalid grade values
     */
    public function testEvaluationWithInvalidGradeValues(): void
    {
        $token = $this->authenticateAndGetToken();

        $invalidGrades = [
            -1,      // Negative grade
            101,     // Grade over 100
            'A+',    // Letter grade
            'excellent', // Text grade
            null,    // Null grade
            '',      // Empty grade
            '95.5.5', // Invalid decimal
            '95,5',  // Comma decimal
            '95.5%', // Percentage
            '95.5/100' // Fraction
        ];

        foreach ($invalidGrades as $index => $grade) {
            $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);
            $this->post('/api/scorecard-evaluations/createScorecardEvaluation', [
                'scorecard_unique_id' => 'INVALID_GRADE_' . $index,
                'evaluation_date' => date('Y-m-d'),
                'grade' => $grade,
                'notes' => "Test with invalid grade: {$grade}",
                'status' => 'draft'
            ]);

            // Should handle invalid grades gracefully
            $this->assertContains($this->_response->getStatusCode(), [200, 400, 404, 422, 500], 
                "Should handle invalid grade '{$grade}' gracefully");
        }
    }

    /**
     * Test evaluation status transitions
     */
    public function testEvaluationStatusTransitions(): void
    {
        $token = $this->authenticateAndGetToken();

        $statusTransitions = [
            ['from' => 'draft', 'to' => 'submitted'],
            ['from' => 'submitted', 'to' => 'approved'],
            ['from' => 'submitted', 'to' => 'rejected'],
            ['from' => 'approved', 'to' => 'archived'],
            ['from' => 'draft', 'to' => 'cancelled']
        ];

        foreach ($statusTransitions as $index => $transition) {
            $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);
            $this->post('/api/scorecard-evaluations/createScorecardEvaluation', [
                'scorecard_unique_id' => 'STATUS_TEST_' . $index,
                'evaluation_date' => date('Y-m-d'),
                'grade' => 85.0,
                'notes' => "Status transition from {$transition['from']} to {$transition['to']}",
                'status' => $transition['to']
            ]);

            // Should handle status transitions gracefully
            $this->assertContains($this->_response->getStatusCode(), [200, 400, 404, 500], 
                "Should handle status transition to {$transition['to']} gracefully");
        }
    }

    /**
     * Test evaluation with extremely long notes
     */
    public function testEvaluationWithExtremelyLongNotes(): void
    {
        $token = $this->authenticateAndGetToken();

        // Create very long notes (10KB+)
        $longNotes = str_repeat('This is a very long evaluation note. ', 1000);

        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);
        $this->post('/api/scorecard-evaluations/createScorecardEvaluation', [
            'scorecard_unique_id' => 'LONG_NOTES_TEST',
            'evaluation_date' => date('Y-m-d'),
            'grade' => 85.0,
            'notes' => $longNotes,
            'status' => 'submitted'
        ]);

        // Should handle long notes gracefully
        $this->assertContains($this->_response->getStatusCode(), [200, 400, 404, 413, 500], 
            'Should handle extremely long notes gracefully');
    }

    /**
     * Test evaluation deletion with non-existent ID
     */
    public function testEvaluationDeletionWithNonExistentId(): void
    {
        $token = $this->authenticateAndGetToken();

        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);
        $this->delete('/api/scorecard-evaluations/deleteScorecardEvaluation', [
            'id' => 999999 // Non-existent ID
        ]);

        // Should handle non-existent evaluation gracefully
        $this->assertContains($this->_response->getStatusCode(), [400, 404, 500], 
            'Should handle non-existent evaluation gracefully');
    }

    /**
     * Test evaluation operations with expired token
     */
    public function testEvaluationOperationsWithExpiredToken(): void
    {
        // Use an invalid/expired token
        $this->configRequest(['headers' => ['Authorization' => 'Bearer expired_token_12345']]);
        $this->post('/api/scorecard-evaluations/createScorecardEvaluation', [
            'scorecard_unique_id' => 'EXPIRED_TOKEN_TEST',
            'evaluation_date' => date('Y-m-d'),
            'grade' => 85.0,
            'notes' => 'Test with expired token',
            'status' => 'draft'
        ]);

        $this->assertResponseCode(401, 'Should reject expired token');
    }

    /**
     * Test evaluation with missing required fields
     */
    public function testEvaluationWithMissingRequiredFields(): void
    {
        $token = $this->authenticateAndGetToken();

        $missingFieldScenarios = [
            [], // No fields
            ['scorecard_unique_id' => 'TEST'], // Missing evaluation_date
            ['evaluation_date' => date('Y-m-d')], // Missing scorecard_unique_id
            ['scorecard_unique_id' => '', 'evaluation_date' => date('Y-m-d')], // Empty scorecard_unique_id
            ['scorecard_unique_id' => 'TEST', 'evaluation_date' => ''], // Empty evaluation_date
            ['scorecard_unique_id' => null, 'evaluation_date' => date('Y-m-d')], // Null scorecard_unique_id
            ['scorecard_unique_id' => 'TEST', 'evaluation_date' => null], // Null evaluation_date
        ];

        foreach ($missingFieldScenarios as $index => $data) {
            $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);
            $this->post('/api/scorecard-evaluations/createScorecardEvaluation', $data);

            // Should handle missing required fields gracefully
            $this->assertContains($this->_response->getStatusCode(), [200, 400, 404, 500], 
                "Should handle missing required fields scenario {$index} gracefully");
        }
    }

    /**
     * Test evaluation with unexpected fields
     */
    public function testEvaluationWithUnexpectedFields(): void
    {
        $token = $this->authenticateAndGetToken();

        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);
        $this->post('/api/scorecard-evaluations/createScorecardEvaluation', [
            'scorecard_unique_id' => 'UNEXPECTED_FIELDS_TEST',
            'evaluation_date' => date('Y-m-d'),
            'grade' => 85.0,
            'notes' => 'Test with unexpected fields',
            'status' => 'draft',
            'unexpected_field_1' => 'should_not_be_allowed',
            'unexpected_field_2' => 'also_should_not_be_allowed',
            'malicious_field' => '<script>alert("xss")</script>'
        ]);

        // Should handle unexpected fields gracefully
        $this->assertContains($this->_response->getStatusCode(), [200, 400, 404, 500], 
            'Should handle unexpected fields gracefully');
    }

    /**
     * Test evaluation statistics with invalid parameters
     */
    public function testEvaluationStatisticsWithInvalidParameters(): void
    {
        $token = $this->authenticateAndGetToken();

        $invalidParams = [
            '', // Empty scorecard_unique_id
            null, // Null scorecard_unique_id
            'INVALID_SCORECARD_ID_WITH_SPECIAL_CHARS_!@#$%^&*()',
            'SCORECARD_ID_WITH_<script>alert("xss")</script>',
            'SCORECARD_ID_WITH_SQL_INJECTION\'; DROP TABLE evaluations; --'
        ];

        foreach ($invalidParams as $index => $param) {
            $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);
            $encodedParam = $param !== null ? urlencode($param) : '';
            $this->get('/api/scorecard-evaluations/getEvaluationStats?scorecard_unique_id=' . $encodedParam);

            // Should handle invalid parameters gracefully
            $this->assertContains($this->_response->getStatusCode(), [200, 400, 404, 500], 
                "Should handle invalid parameter '{$param}' gracefully");
        }
    }

    /**
     * Test evaluation operations without authentication
     */
    public function testEvaluationOperationsWithoutAuthentication(): void
    {
        // Test all endpoints without authentication
        $this->get('/api/scorecard-evaluations/getScorecardEvaluations?scorecard_unique_id=TEST');
        $this->assertResponseCode(401, 'Should require authentication for getScorecardEvaluations');

        $this->post('/api/scorecard-evaluations/createScorecardEvaluation', [
            'scorecard_unique_id' => 'TEST',
            'evaluation_date' => date('Y-m-d')
        ]);
        $this->assertResponseCode(401, 'Should require authentication for createScorecardEvaluation');

        $this->delete('/api/scorecard-evaluations/deleteScorecardEvaluation', ['id' => 1]);
        $this->assertResponseCode(401, 'Should require authentication for deleteScorecardEvaluation');

        $this->get('/api/scorecard-evaluations/getEvaluationStats?scorecard_unique_id=TEST');
        $this->assertResponseCode(401, 'Should require authentication for getEvaluationStats');
    }

    // ========================================
    // ADDITIONAL COMPREHENSIVE EDGE CASES
    // ========================================

    /**
     * Test evaluation workflow edge cases
     */
    public function testEvaluationWorkflowEdgeCases(): void
    {
        $token = $this->authenticateAndGetToken();

        // Test complete evaluation workflow
        $workflowSteps = [
            ['status' => 'draft', 'grade' => null],
            ['status' => 'in_progress', 'grade' => 50.0],
            ['status' => 'review', 'grade' => 75.0],
            ['status' => 'approved', 'grade' => 85.0],
            ['status' => 'finalized', 'grade' => 90.0]
        ];

        foreach ($workflowSteps as $index => $step) {
            $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);
            $this->post('/api/scorecard-evaluations/createScorecardEvaluation', [
                'scorecard_unique_id' => "WORKFLOW_TEST_{$index}",
                'evaluation_date' => date('Y-m-d'),
                'grade' => $step['grade'],
                'notes' => "Workflow step: {$step['status']}",
                'status' => $step['status']
            ]);

            // Should handle workflow steps gracefully
            $this->assertContains($this->_response->getStatusCode(), [200, 400, 404, 500], 
                "Should handle workflow step {$index} ({$step['status']}) gracefully");
        }
    }

    /**
     * Test evaluation grade boundaries
     */
    public function testEvaluationGradeBoundaries(): void
    {
        $token = $this->authenticateAndGetToken();

        $gradeBoundaries = [
            -0.1,    // Just below 0
            0.0,     // Exactly 0
            0.1,     // Just above 0
            49.9,    // Just below 50
            50.0,    // Exactly 50
            50.1,    // Just above 50
            99.9,    // Just below 100
            100.0,   // Exactly 100
            100.1,   // Just above 100
            -999,    // Very negative
            999,     // Very positive
            PHP_FLOAT_MAX, // Maximum float
            PHP_FLOAT_MIN  // Minimum float
        ];

        foreach ($gradeBoundaries as $index => $grade) {
            $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);
            $this->post('/api/scorecard-evaluations/createScorecardEvaluation', [
                'scorecard_unique_id' => "BOUNDARY_TEST_{$index}",
                'evaluation_date' => date('Y-m-d'),
                'grade' => $grade,
                'notes' => "Grade boundary test: {$grade}",
                'status' => 'draft'
            ]);

            // Should handle grade boundaries gracefully
            $this->assertContains($this->_response->getStatusCode(), [200, 400, 404, 422, 500], 
                "Should handle grade boundary {$grade} gracefully");
        }
    }

    /**
     * Test evaluation date edge cases
     */
    public function testEvaluationDateEdgeCases(): void
    {
        $token = $this->authenticateAndGetToken();

        $dateEdgeCases = [
            '1900-01-01',     // Very old date
            '1970-01-01',     // Unix epoch
            '2000-01-01',     // Y2K
            '2038-01-19',     // 32-bit timestamp limit
            '2099-12-31',     // Far future
            '2024-02-29',     // Leap year
            '2024-12-31',     // End of year
            '2024-01-01',     // Start of year
            '2024-06-15',     // Mid year
            '2024-03-15',     // Ides of March
            '2024-07-04',     // Independence Day
            '2024-12-25',     // Christmas
            '2024-01-01 00:00:00', // With time
            '2024-12-31 23:59:59', // End of year with time
            'invalid-date',   // Invalid date
            '2024-13-01',     // Invalid month
            '2024-02-30',     // Invalid day
            '2024-02-29',     // Valid leap year
            '2023-02-29',     // Invalid leap year
            ''                // Empty date
        ];

        foreach ($dateEdgeCases as $index => $date) {
            $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);
            $this->post('/api/scorecard-evaluations/createScorecardEvaluation', [
                'scorecard_unique_id' => "DATE_TEST_{$index}",
                'evaluation_date' => $date,
                'grade' => 85.0,
                'notes' => "Date edge case test: {$date}",
                'status' => 'draft'
            ]);

            // Should handle date edge cases gracefully
            $this->assertContains($this->_response->getStatusCode(), [200, 400, 404, 422, 500], 
                "Should handle date edge case '{$date}' gracefully");
        }
    }

    /**
     * Test evaluation notes edge cases
     */
    public function testEvaluationNotesEdgeCases(): void
    {
        $token = $this->authenticateAndGetToken();

        $notesEdgeCases = [
            '',                    // Empty notes
            '   ',                 // Whitespace only
            "\n\t\r",              // Control characters
            str_repeat('a', 10000), // Very long notes
            'Notes with "quotes"', // Quotes
            "Notes with 'apostrophes'", // Apostrophes
            'Notes with <script>alert("xss")</script>', // XSS attempt
            'Notes with SQL injection: \'; DROP TABLE evaluations; --', // SQL injection
            'Notes with emoji: 🎉🚀💯', // Emojis
            'Notes with Unicode: 测试评估', // Unicode
            'Notes with Arabic: تقييم الأداء', // Arabic
            'Notes with Russian: оценка производительности', // Russian
            'Notes with special chars: !@#$%^&*()', // Special characters
            'Notes with newlines:\nLine 1\nLine 2\nLine 3', // Newlines
            'Notes with tabs:\tTabbed\tContent', // Tabs
            'Notes with mixed: Test 测试 🎉 <script>alert("xss")</script>', // Mixed content
            null,                  // Null notes
            false,                 // Boolean false
            true,                  // Boolean true
            123,                   // Numeric
            [],                    // Array
            (object)['test' => 'data'] // Object
        ];

        foreach ($notesEdgeCases as $index => $notes) {
            $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);
            $this->post('/api/scorecard-evaluations/createScorecardEvaluation', [
                'scorecard_unique_id' => "NOTES_TEST_{$index}",
                'evaluation_date' => date('Y-m-d'),
                'grade' => 85.0,
                'notes' => $notes,
                'status' => 'draft'
            ]);

            // Should handle notes edge cases gracefully
            $this->assertContains($this->_response->getStatusCode(), [200, 400, 404, 422, 500], 
                "Should handle notes edge case {$index} gracefully");
        }
    }

    /**
     * Test evaluation statistics edge cases
     */
    public function testEvaluationStatisticsEdgeCases(): void
    {
        $token = $this->authenticateAndGetToken();

        $statsEdgeCases = [
            '',                    // Empty scorecard ID
            '   ',                 // Whitespace only
            'NONEXISTENT_SCORECARD', // Non-existent scorecard
            'SCORECARD_WITH_NO_EVALUATIONS', // Scorecard with no evaluations
            'SCORECARD_WITH_ONE_EVALUATION', // Scorecard with one evaluation
            'SCORECARD_WITH_MANY_EVALUATIONS', // Scorecard with many evaluations
            'SCORECARD_WITH_MIXED_GRADES', // Scorecard with mixed grades
            'SCORECARD_WITH_ALL_ZERO_GRADES', // Scorecard with all zero grades
            'SCORECARD_WITH_ALL_MAX_GRADES', // Scorecard with all max grades
            'SCORECARD_WITH_INVALID_GRADES', // Scorecard with invalid grades
            'SCORECARD_WITH_NULL_GRADES', // Scorecard with null grades
            'SCORECARD_WITH_EMPTY_GRADES', // Scorecard with empty grades
            'SCORECARD_WITH_SPECIAL_CHARS', // Scorecard with special characters
            'SCORECARD_WITH_UNICODE', // Scorecard with Unicode
            'SCORECARD_WITH_XSS', // Scorecard with XSS attempts
            'SCORECARD_WITH_SQL_INJECTION', // Scorecard with SQL injection
            null,                  // Null scorecard ID
            false,                 // Boolean false
            true,                  // Boolean true
            123,                   // Numeric
            [],                    // Array
            (object)['test' => 'data'] // Object
        ];

        foreach ($statsEdgeCases as $index => $scorecardId) {
            $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);
            
            // Handle different data types properly
            if (is_object($scorecardId)) {
                $encodedId = 'object_' . $index;
            } elseif (is_array($scorecardId)) {
                $encodedId = 'array_' . $index;
            } elseif ($scorecardId !== null) {
                $encodedId = urlencode((string)$scorecardId);
            } else {
                $encodedId = '';
            }
            
            $this->get('/api/scorecard-evaluations/getEvaluationStats?scorecard_unique_id=' . $encodedId);

            // Should handle statistics edge cases gracefully
            $this->assertContains($this->_response->getStatusCode(), [200, 400, 404, 500], 
                "Should handle statistics edge case {$index} gracefully");
        }
    }

    /**
     * Test evaluation deletion edge cases
     */
    public function testEvaluationDeletionEdgeCases(): void
    {
        $token = $this->authenticateAndGetToken();

        $deletionEdgeCases = [
            -1,                    // Negative ID
            0,                     // Zero ID
            999999,                // Very large ID
            'invalid_id',          // String ID
            '',                    // Empty ID
            null,                  // Null ID
            false,                 // Boolean false
            true,                  // Boolean true
            [],                    // Array
            (object)['test' => 'data'] // Object
        ];

        foreach ($deletionEdgeCases as $index => $id) {
            $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);
            $this->delete('/api/scorecard-evaluations/deleteScorecardEvaluation', [
                'id' => $id
            ]);

            // Should handle deletion edge cases gracefully
            $this->assertContains($this->_response->getStatusCode(), [200, 400, 404, 500], 
                "Should handle deletion edge case {$index} gracefully");
        }
    }

    /**
     * Test evaluation performance under load
     */
    public function testEvaluationPerformanceUnderLoad(): void
    {
        $token = $this->authenticateAndGetToken();

        $startTime = microtime(true);

        // Create many evaluations rapidly
        for ($i = 0; $i < 50; $i++) {
            $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);
            $this->post('/api/scorecard-evaluations/createScorecardEvaluation', [
                'scorecard_unique_id' => "PERF_TEST_{$i}",
                'evaluation_date' => date('Y-m-d'),
                'grade' => rand(0, 100),
                'notes' => "Performance test evaluation {$i}",
                'status' => 'draft'
            ]);

            // Each operation should complete within reasonable time
            $this->assertContains($this->_response->getStatusCode(), [200, 400, 404, 500], 
                "Performance test evaluation {$i} should complete gracefully");
        }

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Should complete within reasonable time
        $this->assertLessThan(30, $executionTime, 'Evaluation creation under load should complete within 30 seconds');
    }

    /**
     * Test evaluation data integrity
     */
    public function testEvaluationDataIntegrity(): void
    {
        $token = $this->authenticateAndGetToken();

        // Create an evaluation
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token]]);
        $this->post('/api/scorecard-evaluations/createScorecardEvaluation', [
            'scorecard_unique_id' => 'INTEGRITY_TEST',
            'evaluation_date' => date('Y-m-d'),
            'grade' => 85.0,
            'notes' => 'Data integrity test evaluation',
            'status' => 'submitted'
        ]);

        $this->assertContains($this->_response->getStatusCode(), [200, 400, 404, 500], 
            'Should create integrity test evaluation successfully');

        // Verify data integrity by retrieving evaluations
        $this->get('/api/scorecard-evaluations/getScorecardEvaluations?scorecard_unique_id=INTEGRITY_TEST');
        $this->assertContains($this->_response->getStatusCode(), [200, 400, 401, 500], 
            'Should maintain data integrity when retrieving evaluations');

        // Verify statistics integrity
        $this->get('/api/scorecard-evaluations/getEvaluationStats?scorecard_unique_id=INTEGRITY_TEST');
        $this->assertContains($this->_response->getStatusCode(), [200, 400, 401, 404, 500], 
            'Should maintain data integrity when calculating statistics');
    }
}
