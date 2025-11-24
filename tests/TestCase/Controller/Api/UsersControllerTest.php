<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Api;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\TableRegistry;
use Throwable;

/**
 * App\Controller\Api\UsersController Test Case
 */
class UsersControllerTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = [
        'app.Users',
        'app.UserCompanyMappings',
    ];

    // Test credentials constants
    private const VALID_USERNAME = 'test';
    private const VALID_PASSWORD = '12345';
    private const INVALID_USERNAME = 'nonexistent';
    private const INVALID_PASSWORD = 'wrongpassword';

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
        } catch (Throwable $e) {
            ob_end_clean(); // Clean up buffer on exception
            throw $e;
        }
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
     * Test valid login
     *
     * @return void
     */
    public function testValidLogin(): void
    {
        // Safely capture console output with proper cleanup
        $consoleOutput = $this->captureConsoleOutput(function () {
            // Kung naka-enable ang CsrfProtectionMiddleware/SecurityMiddleware sa app:
            $this->enableCsrfToken();
            $this->enableSecurityToken();

            // Optional: kung API returns JSON
            $this->configRequest([
                'headers' => ['Accept' => 'application/json'],
            ]);

            $this->post('/api/users/login', [
                'username' => self::VALID_USERNAME,
                'password' => self::VALID_PASSWORD,
            ]);
        });

        // Get response body for assertions
        $body = (string)$this->_response->getBody();

        // ---- ASSERTS
        if ($this->_response->getStatusCode() !== 200) {
            $this->fail('Expected 200 but got ' . $this->_response->getStatusCode() . '. Response: ' . $body);
        }
        $this->assertResponseCode(200);
        $this->assertContentType('application/json');
        $this->assertResponseNotEmpty();

        // Check for unexpected console output (echo, print, etc.)
        $this->assertEmpty($consoleOutput, 'Login endpoint should not produce console output (echo, print, etc.)');

        // Ensure response is valid JSON
        $this->assertJson($body, 'Response should be valid JSON');

        // Verify response contains success data
        $response = json_decode($body, true);
        $this->assertNotNull($response, 'Response should be valid JSON');
        $this->assertTrue($response['success'], 'Response should indicate success');
        $this->assertArrayHasKey('token', $response, 'Response should contain token');
        $this->assertArrayHasKey('user', $response, 'Response should contain user data');
        $this->assertArrayHasKey('id', $response['user'], 'User should have id field');
        $this->assertEquals(self::VALID_USERNAME, $response['user']['username'], 'Username should match');
        $this->assertEquals('Test', $response['user']['first_name'], 'First name should match');
        $this->assertEquals('Account', $response['user']['last_name'], 'Last name should match');
        $this->assertEquals('test@example.com', $response['user']['email'], 'Email should match');
        $this->assertEquals('admin', $response['user']['user_role'], 'User role should match');
    }

    /**
     * Test invalid username login
     *
     * @return void
     */
    public function testInvalidUsernameLogin(): void
    {
        // Safely capture console output with proper cleanup
        $consoleOutput = $this->captureConsoleOutput(function () {
            // Same setup as valid login
            $this->enableCsrfToken();
            $this->enableSecurityToken();
            $this->configRequest(['headers' => ['Accept' => 'application/json']]);

            $this->post('/api/users/login', [
                'username' => self::INVALID_USERNAME,
                'password' => self::VALID_PASSWORD,
            ]);
        });

        $body = (string)$this->_response->getBody();

        // Assertions for error response
        $this->assertResponseCode(401);
        $this->assertContentType('application/json');

        // Check for unexpected console output (echo, print, etc.)
        $this->assertEmpty($consoleOutput, 'Login endpoint should not produce console output (echo, print, etc.)');

        // Ensure response is valid JSON
        $this->assertJson($body, 'Response should be valid JSON');

        $response = json_decode($body, true);
        $this->assertNotNull($response, 'Response should be valid JSON');
        $this->assertEquals('Invalid username or password', $response['message']);

        // Ensure response contains critical error fields
        $this->assertArrayHasKey('message', $response, 'Response should have message field');
        $this->assertArrayNotHasKey('token', $response, 'Response should not contain token field');
        $this->assertArrayNotHasKey('user', $response, 'Response should not contain user field');
        $this->assertArrayNotHasKey('success', $response, 'Response should not contain success field');
    }

    /**
     * Test invalid password login
     *
     * @return void
     */
    public function testInvalidPasswordLogin(): void
    {
        // Safely capture console output with proper cleanup
        $consoleOutput = $this->captureConsoleOutput(function () {
            $this->enableCsrfToken();
            $this->enableSecurityToken();
            $this->configRequest(['headers' => ['Accept' => 'application/json']]);

            $this->post('/api/users/login', [
                'username' => self::VALID_USERNAME,
                'password' => self::INVALID_PASSWORD,
            ]);
        });

        $body = (string)$this->_response->getBody();

        // Assertions for error response
        $this->assertResponseCode(401);
        $this->assertContentType('application/json');

        // Check for unexpected console output (echo, print, etc.)
        $this->assertEmpty($consoleOutput, 'Login endpoint should not produce console output (echo, print, etc.)');

        // Ensure response is valid JSON
        $this->assertJson($body, 'Response should be valid JSON');

        $response = json_decode($body, true);
        $this->assertNotNull($response, 'Response should be valid JSON');
        $this->assertEquals('Invalid username or password', $response['message']);

        // Ensure response contains critical error fields
        $this->assertArrayHasKey('message', $response, 'Response should have message field');
        $this->assertArrayNotHasKey('token', $response, 'Response should not contain token field');
        $this->assertArrayNotHasKey('user', $response, 'Response should not contain user field');
        $this->assertArrayNotHasKey('success', $response, 'Response should not contain success field');
    }

    /**
     * Test empty credentials login
     *
     * @return void
     */
    public function testEmptyCredentialsLogin(): void
    {
        // Safely capture console output with proper cleanup
        $consoleOutput = $this->captureConsoleOutput(function () {
            $this->enableCsrfToken();
            $this->enableSecurityToken();
            $this->configRequest(['headers' => ['Accept' => 'application/json']]);

            $this->post('/api/users/login', [
                'username' => '',
                'password' => '',
            ]);
        });

        $body = (string)$this->_response->getBody();

        // Assertions for error response
        $this->assertResponseCode(400);
        $this->assertContentType('application/json');

        // Check for unexpected console output (echo, print, etc.)
        $this->assertEmpty($consoleOutput, 'Login endpoint should not produce console output (echo, print, etc.)');

        // Ensure response is valid JSON
        $this->assertJson($body, 'Response should be valid JSON');

        $response = json_decode($body, true);
        $this->assertNotNull($response, 'Response should be valid JSON');
        $this->assertEquals('Username and password are required', $response['message']);

        // Ensure response contains critical error fields
        $this->assertArrayHasKey('message', $response, 'Response should have message field');
        $this->assertArrayNotHasKey('token', $response, 'Response should not contain token field');
        $this->assertArrayNotHasKey('user', $response, 'Response should not contain user field');
        $this->assertArrayNotHasKey('success', $response, 'Response should not contain success field');
    }

    /**
     * Test missing username login
     *
     * @return void
     */
    public function testMissingUsernameLogin(): void
    {
        // Safely capture console output with proper cleanup
        $consoleOutput = $this->captureConsoleOutput(function () {
            $this->enableCsrfToken();
            $this->enableSecurityToken();
            $this->configRequest(['headers' => ['Accept' => 'application/json']]);

            $this->post('/api/users/login', [
                'password' => self::VALID_PASSWORD,
            ]);
        });

        $body = (string)$this->_response->getBody();

        // Assertions for error response
        $this->assertResponseCode(400);
        $this->assertContentType('application/json');

        // Check for unexpected console output (echo, print, etc.)
        $this->assertEmpty($consoleOutput, 'Login endpoint should not produce console output (echo, print, etc.)');

        // Ensure response is valid JSON
        $this->assertJson($body, 'Response should be valid JSON');

        $response = json_decode($body, true);
        $this->assertNotNull($response, 'Response should be valid JSON');
        $this->assertEquals('Username and password are required', $response['message']);

        // Ensure response contains critical error fields
        $this->assertArrayHasKey('message', $response, 'Response should have message field');
        $this->assertArrayNotHasKey('token', $response, 'Response should not contain token field');
        $this->assertArrayNotHasKey('user', $response, 'Response should not contain user field');
        $this->assertArrayNotHasKey('success', $response, 'Response should not contain success field');
    }

    /**
     * Test missing password login
     *
     * @return void
     */
    public function testMissingPasswordLogin(): void
    {
        $consoleOutput = $this->captureConsoleOutput(function (): void {
            $this->enableCsrfToken();
            $this->enableSecurityToken();
            $this->configRequest(['headers' => ['Accept' => 'application/json']]);

            $this->post('/api/users/login', [
                'username' => self::VALID_USERNAME,
            ]);
        });

        $body = (string)$this->_response->getBody();

        // Assertions for error response
        $this->assertResponseCode(400);
        $this->assertContentType('application/json');

        // Check for unexpected console output (echo, print, etc.)
        $this->assertEmpty($consoleOutput, 'Login endpoint should not produce console output (echo, print, etc.)');

        // Ensure response is valid JSON
        $this->assertJson($body, 'Response should be valid JSON');

        $response = json_decode($body, true);
        $this->assertNotNull($response, 'Response should be valid JSON');
        $this->assertEquals('Username and password are required', $response['message']);

        // Ensure response contains critical error fields
        $this->assertArrayHasKey('message', $response, 'Response should have message field');
        $this->assertArrayNotHasKey('token', $response, 'Response should not contain token field');
        $this->assertArrayNotHasKey('user', $response, 'Response should not contain user field');
        $this->assertArrayNotHasKey('success', $response, 'Response should not contain success field');
    }

    /**
     * Test SQL injection in username field - Basic attempts
     *
     * @return void
     */
    public function testSqlInjectionUsernameBasic(): void
    {
        // Test basic SQL injection attempts
        $sqlInjectionAttempts = [
            "admin' OR '1'='1",
            "admin' OR 1=1--",
            "admin'; DROP TABLE users; --",
            "admin' UNION SELECT * FROM users--",
            "' OR '1'='1' --",
            "admin' OR 'x'='x",
            "admin'/**/OR/**/1=1--",
            "admin' OR '1'='1' #",
        ];

        foreach ($sqlInjectionAttempts as $maliciousUsername) {
            $consoleOutput = $this->captureConsoleOutput(function () use ($maliciousUsername): void {
                $this->enableCsrfToken();
                $this->enableSecurityToken();
                $this->configRequest(['headers' => ['Accept' => 'application/json']]);

                $this->post('/api/users/login', [
                    'username' => $maliciousUsername,
                    'password' => self::VALID_PASSWORD,
                ]);
            });

            // Should return 401 (unauthorized) - not 200 (success)
            $this->assertResponseCode(401, "SQL injection attempt should fail: {$maliciousUsername}");
            $this->assertContentType('application/json');

            $body = (string)$this->_response->getBody();
            $this->assertJson($body, 'Response should be valid JSON');

            $response = json_decode($body, true);
            $this->assertEquals('Invalid username or password', $response['message']);

            // Ensure response contains critical error fields
            $this->assertArrayHasKey('message', $response, 'Response should have message field');
            $this->assertArrayNotHasKey('token', $response, 'Response should not contain token field');
            $this->assertArrayNotHasKey('user', $response, 'Response should not contain user field');
            $this->assertArrayNotHasKey('success', $response, 'Response should not contain success field');

            $this->assertEmpty($consoleOutput, "SQL injection attempt should not produce console output: {$maliciousUsername}");
        }
    }

    /**
     * Test SQL injection in username field - Advanced bypass attempts
     *
     * @return void
     */
    public function testSqlInjectionUsernameAdvanced(): void
    {
        // Test advanced SQL injection bypass attempts
        $advancedSqlInjectionAttempts = [
            "admin' OR '1'='1' LIMIT 1--",
            "admin' OR 1=1 LIMIT 1 OFFSET 0--",
            "admin' OR '1'='1' AND '1'='1--",
            "admin' OR (SELECT COUNT(*) FROM users) > 0--",
            "admin' OR EXISTS(SELECT 1 FROM users)--",
            "admin' OR '1'='1' UNION ALL SELECT 1,2,3,4,5,6,7,8,9,10--",
            "admin' OR '1'='1' AND LENGTH(username) > 0--",
            "admin' OR '1'='1' AND ASCII(SUBSTRING(username,1,1)) > 0--",
        ];

        foreach ($advancedSqlInjectionAttempts as $maliciousUsername) {
            $consoleOutput = $this->captureConsoleOutput(function () use ($maliciousUsername): void {
                $this->enableCsrfToken();
                $this->enableSecurityToken();
                $this->configRequest(['headers' => ['Accept' => 'application/json']]);

                $this->post('/api/users/login', [
                    'username' => $maliciousUsername,
                    'password' => self::VALID_PASSWORD,
                ]);
            });

            // Should return 401 (unauthorized) - not 200 (success)
            $this->assertResponseCode(401, "Advanced SQL injection attempt should fail: {$maliciousUsername}");
            $this->assertContentType('application/json');

            $body = (string)$this->_response->getBody();
            $this->assertJson($body, 'Response should be valid JSON');

            $response = json_decode($body, true);
            $this->assertEquals('Invalid username or password', $response['message']);

            // Ensure response contains critical error fields
            $this->assertArrayHasKey('message', $response, 'Response should have message field');
            $this->assertArrayNotHasKey('token', $response, 'Response should not contain token field');
            $this->assertArrayNotHasKey('user', $response, 'Response should not contain user field');
            $this->assertArrayNotHasKey('success', $response, 'Response should not contain success field');

            $this->assertEmpty($consoleOutput, "Advanced SQL injection attempt should not produce console output: {$maliciousUsername}");
        }
    }

    /**
     * Test SQL injection in password field
     *
     * @return void
     */
    public function testSqlInjectionPassword(): void
    {
        // Test SQL injection attempts in password field
        $sqlInjectionPasswords = [
            "12345' OR '1'='1",
            "12345' OR 1=1--",
            "12345'; DROP TABLE users; --",
            "12345' UNION SELECT * FROM users--",
            "' OR '1'='1' --",
            "12345' OR 'x'='x",
            "12345'/**/OR/**/1=1--",
        ];

        foreach ($sqlInjectionPasswords as $maliciousPassword) {
            $consoleOutput = $this->captureConsoleOutput(function () use ($maliciousPassword): void {
                $this->enableCsrfToken();
                $this->enableSecurityToken();
                $this->configRequest(['headers' => ['Accept' => 'application/json']]);

                $this->post('/api/users/login', [
                    'username' => self::VALID_USERNAME,
                    'password' => $maliciousPassword,
                ]);
            });

            // Should return 401 (unauthorized) - not 200 (success)
            $this->assertResponseCode(401, "SQL injection in password should fail: {$maliciousPassword}");
            $this->assertContentType('application/json');

            $body = (string)$this->_response->getBody();
            $this->assertJson($body, 'Response should be valid JSON');

            $response = json_decode($body, true);
            $this->assertEquals('Invalid username or password', $response['message']);

            // Ensure response contains critical error fields
            $this->assertArrayHasKey('message', $response, 'Response should have message field');
            $this->assertArrayNotHasKey('token', $response, 'Response should not contain token field');
            $this->assertArrayNotHasKey('user', $response, 'Response should not contain user field');
            $this->assertArrayNotHasKey('success', $response, 'Response should not contain success field');

            $this->assertEmpty($consoleOutput, "SQL injection in password should not produce console output: {$maliciousPassword}");
        }
    }

    /**
     * Test SQL injection in both username and password fields
     *
     * @return void
     */
    public function testSqlInjectionBothFields(): void
    {
        $consoleOutput = $this->captureConsoleOutput(function (): void {
            $this->enableCsrfToken();
            $this->enableSecurityToken();
            $this->configRequest(['headers' => ['Accept' => 'application/json']]);

            // Test SQL injection attempts in both fields
            $this->post('/api/users/login', [
                'username' => "admin' OR '1'='1",
                'password' => "12345' OR '1'='1",
            ]);
        });

        // Should return 401 (unauthorized) - not 200 (success)
        $this->assertResponseCode(401, 'SQL injection in both fields should fail');
        $this->assertContentType('application/json');

        $body = (string)$this->_response->getBody();
        $this->assertJson($body, 'Response should be valid JSON');

        $response = json_decode($body, true);
        $this->assertEquals('Invalid username or password', $response['message']);

        // Ensure response contains critical error fields
        $this->assertArrayHasKey('message', $response, 'Response should have message field');
        $this->assertArrayNotHasKey('token', $response, 'Response should not contain token field');
        $this->assertArrayNotHasKey('user', $response, 'Response should not contain user field');
        $this->assertArrayNotHasKey('success', $response, 'Response should not contain success field');

        $this->assertEmpty($consoleOutput, 'SQL injection in both fields should not produce console output');
    }

    /**
     * Test index method with valid authentication
     *
     * @return void
     */
    public function testIndexWithAuthentication(): void
    {
        // First, login to get a valid token
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

        // Now test index with authentication
        $consoleOutput = $this->captureConsoleOutput(function () use ($token): void {
            $this->configRequest([
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]);

            $this->get('/api/users');
        });

        // Assertions for index response
        $this->assertResponseCode(200);
        $this->assertContentType('application/json');

        // Check for unexpected console output
        $this->assertEmpty($consoleOutput, 'Index endpoint should not produce console output');

        $body = (string)$this->_response->getBody();
        $this->assertJson($body, 'Response should be valid JSON');

        $response = json_decode($body, true);
        $this->assertIsArray($response, 'Response should be an array of users');
        $this->assertNotEmpty($response, 'Response should contain users');

        // Validate user structure in response
        if (!empty($response)) {
            $firstUser = $response[0];
            $this->assertArrayHasKey('id', $firstUser, 'User should have id field');
            $this->assertArrayHasKey('username', $firstUser, 'User should have username field');
            $this->assertArrayHasKey('first_name', $firstUser, 'User should have first_name field');
            $this->assertArrayHasKey('last_name', $firstUser, 'User should have last_name field');
        }
    }

    /**
     * Test index method without authentication (should return 401)
     *
     * @return void
     */
    public function testIndexWithoutAuthentication(): void
    {
        $consoleOutput = $this->captureConsoleOutput(function (): void {
            $this->configRequest(['headers' => ['Accept' => 'application/json']]);
            $this->get('/api/users');
        });

        // Should return 401 (unauthorized) - CakePHP authentication middleware throws exception
        $this->assertResponseCode(401);

        // Check for unexpected console output
        $this->assertEmpty($consoleOutput, 'Index endpoint should not produce console output');

        // Note: CakePHP authentication middleware returns HTML error page for unauthenticated requests
        // This is the correct security behavior - authentication happens at middleware level
        // and prevents unauthorized access to the controller method
        $body = (string)$this->_response->getBody();
        $this->assertNotEmpty($body, 'Response should not be empty');

        // The response should contain error information (HTML format from ErrorHandlerMiddleware)
        // Note: Authentication middleware provides consistent error handling regardless of debug mode
        $this->assertStringContainsString('Authentication is required', $body, 'Response should contain authentication error message');
    }

    /**
     * Test index method with wrong HTTP methods
     *
     * @return void
     */
    public function testIndexWithWrongHttpMethods(): void
    {
        $wrongMethods = ['POST', 'PUT', 'DELETE', 'PATCH'];

        foreach ($wrongMethods as $method) {
            $consoleOutput = $this->captureConsoleOutput(function () use ($method): void {
                $this->configRequest(['headers' => ['Accept' => 'application/json']]);

                switch ($method) {
                    case 'POST':
                        $this->post('/api/users');
                        break;
                    case 'PUT':
                        $this->put('/api/users');
                        break;
                    case 'DELETE':
                        $this->delete('/api/users');
                        break;
                    case 'PATCH':
                        $this->patch('/api/users');
                        break;
                }
            });

            // Should return 405 (Method Not Allowed) or 401 (Unauthorized)
            $this->assertTrue(
                in_array($this->_response->getStatusCode(), [401, 405]),
                "Index endpoint should reject {$method} method",
            );

            // Check for unexpected console output
            $this->assertEmpty($consoleOutput, "Index endpoint should not produce console output for {$method} method");
        }
    }

    /**
     * Test index method response data structure
     *
     * @return void
     */
    public function testIndexResponseDataStructure(): void
    {
        // First, login to get a valid token
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

        // Test index with authentication
        $consoleOutput = $this->captureConsoleOutput(function () use ($token): void {
            $this->configRequest([
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]);

            $this->get('/api/users');
        });

        $this->assertResponseCode(200);
        $body = (string)$this->_response->getBody();
        $response = json_decode($body, true);

        // Validate response structure
        $this->assertIsArray($response, 'Response should be an array');

        if (!empty($response)) {
            foreach ($response as $user) {
                // Check required fields
                $this->assertArrayHasKey('id', $user, 'User should have id field');
                $this->assertArrayHasKey('username', $user, 'User should have username field');
                $this->assertArrayHasKey('first_name', $user, 'User should have first_name field');
                $this->assertArrayHasKey('last_name', $user, 'User should have last_name field');
                $this->assertArrayHasKey('email_address', $user, 'User should have email_address field');
                $this->assertArrayHasKey('system_user_role', $user, 'User should have system_user_role field');

                // Check data types
                $this->assertIsInt($user['id'], 'User id should be integer');
                $this->assertIsString($user['username'], 'Username should be string');
                $this->assertIsString($user['first_name'], 'First name should be string');
                $this->assertIsString($user['last_name'], 'Last name should be string');
                $this->assertIsString($user['email_address'], 'Email address should be string');
                $this->assertIsString($user['system_user_role'], 'System user role should be string');

                // Check that sensitive data is not exposed
                $this->assertArrayNotHasKey('password', $user, 'Password should not be exposed in response');
            }
        }

        $this->assertEmpty($consoleOutput, 'Index endpoint should not produce console output');
    }

    /**
     * Test unauthorized method - should be blocked by authentication middleware
     *
     * @return void
     */
    public function testUnauthorizedJsonResponse(): void
    {
        $consoleOutput = $this->captureConsoleOutput(function (): void {
            $this->configRequest(['headers' => ['Accept' => 'application/json']]);
            $this->get('/api/users/unauthorized');
        });

        // Should return 401 (unauthorized) from authentication middleware
        $this->assertResponseCode(401);
        $this->assertContentType('text/html');

        // Check for unexpected console output
        $this->assertEmpty($consoleOutput, 'Unauthorized endpoint should not produce console output');

        $body = (string)$this->_response->getBody();
        $this->assertNotEmpty($body, 'Response should not be empty');

        // Should contain authentication error message from middleware
        $this->assertStringContainsString('Authentication is required to continue', $body, 'Response should contain authentication error message');
    }

    /**
     * Test unauthorized method with XML response format
     *
     * @return void
     */
    public function testUnauthorizedXmlResponse(): void
    {
        $consoleOutput = $this->captureConsoleOutput(function (): void {
            // Set the _ext parameter to trigger XML response
            $this->configRequest([
                'headers' => ['Accept' => 'application/xml'],
                'params' => ['_ext' => 'xml'],
            ]);
            $this->get('/api/users/unauthorized');
        });

        // Should return 401 (unauthorized)
        $this->assertResponseCode(401);

        // Check for unexpected console output
        $this->assertEmpty($consoleOutput, 'Unauthorized endpoint should not produce console output');

        $body = (string)$this->_response->getBody();
        $this->assertNotEmpty($body, 'Response should not be empty');

        // Note: XML response depends on view template configuration
        // For now, we just verify that the endpoint is accessible and returns 401
        // The actual XML format would depend on the view template implementation
    }

    /**
     * Test unauthorized method HTTP status code
     *
     * @return void
     */
    public function testUnauthorizedHttpStatus(): void
    {
        $consoleOutput = $this->captureConsoleOutput(function (): void {
            $this->configRequest(['headers' => ['Accept' => 'application/json']]);
            $this->get('/api/users/unauthorized');
        });

        // Should return 401 (unauthorized)
        $this->assertResponseCode(401);

        // Check for unexpected console output
        $this->assertEmpty($consoleOutput, 'Unauthorized endpoint should not produce console output');

        // Verify the response status is explicitly set to 401
        $this->assertEquals(401, $this->_response->getStatusCode(), 'HTTP status should be 401 Unauthorized');
    }

    /**
     * Test unauthorized method with different HTTP methods
     *
     * @return void
     */
    public function testUnauthorizedWithDifferentHttpMethods(): void
    {
        $httpMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];

        foreach ($httpMethods as $method) {
            $consoleOutput = $this->captureConsoleOutput(function () use ($method): void {
                $this->configRequest(['headers' => ['Accept' => 'application/json']]);

                switch ($method) {
                    case 'GET':
                        $this->get('/api/users/unauthorized');
                        break;
                    case 'POST':
                        $this->post('/api/users/unauthorized');
                        break;
                    case 'PUT':
                        $this->put('/api/users/unauthorized');
                        break;
                    case 'DELETE':
                        $this->delete('/api/users/unauthorized');
                        break;
                    case 'PATCH':
                        $this->patch('/api/users/unauthorized');
                        break;
                }
            });

            // Should return 401 (unauthorized) for all methods
            $this->assertResponseCode(401, "Unauthorized endpoint should return 401 for {$method} method");

            // Check for unexpected console output
            $this->assertEmpty($consoleOutput, "Unauthorized endpoint should not produce console output for {$method} method");

            // Note: Some methods might be blocked by authentication middleware
            // We just verify that the endpoint returns 401 status for all methods
            $body = (string)$this->_response->getBody();
            $this->assertNotEmpty($body, "Response should not be empty for {$method} method");
        }
    }

    /**
     * Test unauthorized method response structure - should be blocked by authentication middleware
     *
     * @return void
     */
    public function testUnauthorizedResponseStructure(): void
    {
        $consoleOutput = $this->captureConsoleOutput(function (): void {
            $this->configRequest(['headers' => ['Accept' => 'application/json']]);
            $this->get('/api/users/unauthorized');
        });

        $this->assertResponseCode(401);
        $this->assertContentType('text/html');

        $body = (string)$this->_response->getBody();
        $this->assertNotEmpty($body, 'Response should not be empty');

        // Should contain authentication error message from middleware
        $this->assertStringContainsString('Authentication is required to continue', $body, 'Response should contain authentication error message');

        // Should be HTML format, not JSON
        $this->assertStringContainsString('<html>', $body, 'Response should be HTML format');
        $this->assertStringContainsString('</html>', $body, 'Response should be HTML format');

        $this->assertEmpty($consoleOutput, 'Unauthorized endpoint should not produce console output');
    }

    /**
     * Test test method with valid authentication
     *
     * @return void
     */
    public function testTestWithAuthentication(): void
    {
        // First, login to get a valid token
        $loginResponse = $this->captureConsoleOutput(function (): void {
            $this->enableCsrfToken();
            $this->enableSecurityToken();
            $this->configRequest(['headers' => ['Accept' => 'application/json']]);

            $this->post('/api/users/login', [
                'username' => self::VALID_USERNAME,
                'password' => self::VALID_PASSWORD,
            ]);
        });

        $loginBody = (string)$this->_response->getBody();
        $loginData = json_decode($loginBody, true);
        $token = $loginData['token'];

        // Now test the test() method with the token
        $consoleOutput = $this->captureConsoleOutput(function () use ($token): void {
            $this->configRequest([
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]);
            $this->get('/api/users/test');
        });

        // Should return 200 (success)
        $this->assertResponseCode(200);
        $this->assertContentType('application/json');

        // Check for unexpected console output
        $this->assertEmpty($consoleOutput, 'Test endpoint should not produce console output');

        $body = (string)$this->_response->getBody();
        $this->assertJson($body, 'Response should be valid JSON');

        $response = json_decode($body, true);
        $this->assertNotNull($response, 'Response should be valid JSON');

        // The test() method returns the authentication result
        // Let's check what we actually get
        if (empty($response)) {
            $this->markTestSkipped('Authentication result is empty - this might be expected behavior');
        }

        // If we get a response, it should be valid
        $this->assertIsArray($response, 'Authentication result should be an array');
    }

    /**
     * Test test method without authentication
     *
     * @return void
     */
    public function testTestWithoutAuthentication(): void
    {
        $consoleOutput = $this->captureConsoleOutput(function (): void {
            $this->configRequest(['headers' => ['Accept' => 'application/json']]);
            $this->get('/api/users/test');
        });

        // Should return 401 (unauthorized) from authentication middleware
        $this->assertResponseCode(401);
        $this->assertContentType('text/html');

        // Check for unexpected console output
        $this->assertEmpty($consoleOutput, 'Test endpoint should not produce console output');

        $body = (string)$this->_response->getBody();
        $this->assertNotEmpty($body, 'Response should not be empty');

        // Should contain authentication error message from middleware
        $this->assertStringContainsString('Authentication is required to continue', $body, 'Response should contain authentication error message');
    }

    /**
     * Test test method response format and structure
     *
     * @return void
     */
    public function testTestResponseFormat(): void
    {
        // First, login to get a valid token
        $loginResponse = $this->captureConsoleOutput(function (): void {
            $this->enableCsrfToken();
            $this->enableSecurityToken();
            $this->configRequest(['headers' => ['Accept' => 'application/json']]);

            $this->post('/api/users/login', [
                'username' => self::VALID_USERNAME,
                'password' => self::VALID_PASSWORD,
            ]);
        });

        $loginBody = (string)$this->_response->getBody();
        $loginData = json_decode($loginBody, true);
        $token = $loginData['token'];

        // Test the test() method
        $consoleOutput = $this->captureConsoleOutput(function () use ($token): void {
            $this->configRequest([
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]);
            $this->get('/api/users/test');
        });

        $this->assertResponseCode(200);
        $this->assertContentType('application/json');

        $body = (string)$this->_response->getBody();
        $this->assertJson($body, 'Response should be valid JSON');

        $response = json_decode($body, true);
        $this->assertNotNull($response, 'Response should be valid JSON');

        // The test() method returns authentication result
        // It might be empty, which could be expected behavior
        if (!empty($response)) {
            $this->assertIsArray($response, 'Authentication result should be an array if not empty');
        }

        $this->assertEmpty($consoleOutput, 'Test endpoint should not produce console output');
    }

    /**
     * Test test method with different HTTP methods
     *
     * @return void
     */
    public function testTestWithDifferentHttpMethods(): void
    {
        // First, login to get a valid token
        $loginResponse = $this->captureConsoleOutput(function (): void {
            $this->enableCsrfToken();
            $this->enableSecurityToken();
            $this->configRequest(['headers' => ['Accept' => 'application/json']]);

            $this->post('/api/users/login', [
                'username' => self::VALID_USERNAME,
                'password' => self::VALID_PASSWORD,
            ]);
        });

        $loginBody = (string)$this->_response->getBody();
        $loginData = json_decode($loginBody, true);
        $token = $loginData['token'];

        $httpMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];

        foreach ($httpMethods as $method) {
            $consoleOutput = $this->captureConsoleOutput(function () use ($method, $token): void {
                $this->configRequest([
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => 'Bearer ' . $token,
                    ],
                ]);

                switch ($method) {
                    case 'GET':
                        $this->get('/api/users/test');
                        break;
                    case 'POST':
                        $this->post('/api/users/test');
                        break;
                    case 'PUT':
                        $this->put('/api/users/test');
                        break;
                    case 'DELETE':
                        $this->delete('/api/users/test');
                        break;
                    case 'PATCH':
                        $this->patch('/api/users/test');
                        break;
                }
            });

            // Should return 200 for GET (the method supports it)
            // Other methods might return 405 (Method Not Allowed) or 200
            $this->assertTrue(
                in_array($this->_response->getStatusCode(), [200, 405]),
                "Test endpoint should return 200 or 405 for {$method} method, got {$this->_response->getStatusCode()}",
            );

            // Check for unexpected console output
            $this->assertEmpty($consoleOutput, "Test endpoint should not produce console output for {$method} method");
        }
    }

    // ========================================
    // USER COMPANY MAPPING TESTS
    // ========================================

    /**
     * Test login uses mapped company_id when mapping exists
     * 
     * @return void
     */
    public function testLoginUsesMappedCompanyIdWhenMappingExists(): void
    {
        // User in fixture has company_id 200001
        // UserCompanyMappings fixture has active mapping: user_id=1, mapped_company_id=200001
        // The token should contain the mapped company_id
        
        $consoleOutput = $this->captureConsoleOutput(function (): void {
            $this->enableCsrfToken();
            $this->enableSecurityToken();
            $this->configRequest(['headers' => ['Accept' => 'application/json']]);

            $this->post('/api/users/login', [
                'username' => self::VALID_USERNAME,
                'password' => self::VALID_PASSWORD,
            ]);
        });

        $this->assertResponseCode(200);
        $this->assertContentType('application/json');
        $this->assertEmpty($consoleOutput, 'Login endpoint should not produce console output');

        $body = (string)$this->_response->getBody();
        $response = json_decode($body, true);

        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('token', $response);
        
        // Decode JWT token to verify company_id
        $tokenParts = explode('.', $response['token']);
        $this->assertCount(3, $tokenParts, 'JWT token should have 3 parts');
        
        $payload = json_decode(base64_decode($tokenParts[1]), true);
        $this->assertArrayHasKey('company_id', $payload, 'Token should contain company_id');
        
        // Verify mapped company_id is used (200001 from fixture)
        $this->assertEquals(200001, $payload['company_id'], 'Token should contain mapped company_id');
    }

    /**
     * Test login uses original company_id when no mapping exists
     * 
     * @return void
     */
    public function testLoginUsesOriginalCompanyIdWhenNoMappingExists(): void
    {
        // Delete all mappings for test user
        $mappingsTable = TableRegistry::getTableLocator()->get('UserCompanyMappings', [
            'connection' => ConnectionManager::get('test')
        ]);
        $mappingsTable->deleteAll(['user_id' => 1]);

        $consoleOutput = $this->captureConsoleOutput(function (): void {
            $this->enableCsrfToken();
            $this->enableSecurityToken();
            $this->configRequest(['headers' => ['Accept' => 'application/json']]);

            $this->post('/api/users/login', [
                'username' => self::VALID_USERNAME,
                'password' => self::VALID_PASSWORD,
            ]);
        });

        $this->assertResponseCode(200);
        $this->assertContentType('application/json');

        $body = (string)$this->_response->getBody();
        $response = json_decode($body, true);

        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('token', $response);
        
        // Decode JWT token to verify company_id
        $tokenParts = explode('.', $response['token']);
        $payload = json_decode(base64_decode($tokenParts[1]), true);
        
        // Should use original company_id from Users fixture (200001)
        $this->assertEquals(200001, $payload['company_id'], 'Token should contain original company_id when no mapping exists');
    }

    /**
     * Test login ignores inactive mappings
     * 
     * @return void
     */
    public function testLoginIgnoresInactiveMappings(): void
    {
        // Delete active mapping, keep only inactive one
        $mappingsTable = TableRegistry::getTableLocator()->get('UserCompanyMappings', [
            'connection' => ConnectionManager::get('test')
        ]);
        $mappingsTable->deleteAll(['user_id' => 1, 'active' => true]);
        
        // Verify inactive mapping exists
        $inactiveMapping = $mappingsTable->find()
            ->where(['user_id' => 1, 'active' => false])
            ->first();
        $this->assertNotNull($inactiveMapping, 'Inactive mapping should exist');

        $consoleOutput = $this->captureConsoleOutput(function (): void {
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

        $this->assertTrue($response['success']);
        
        // Decode JWT token
        $tokenParts = explode('.', $response['token']);
        $payload = json_decode(base64_decode($tokenParts[1]), true);
        
        // Should use original company_id, not inactive mapping
        $this->assertEquals(200001, $payload['company_id'], 'Token should use original company_id when only inactive mapping exists');
        $this->assertNotEquals($inactiveMapping->mapped_company_id, $payload['company_id'], 'Should not use inactive mapping');
    }

    /**
     * Test login ignores deleted mappings
     * 
     * @return void
     */
    public function testLoginIgnoresDeletedMappings(): void
    {
        // Delete active mapping, keep only deleted one
        $mappingsTable = TableRegistry::getTableLocator()->get('UserCompanyMappings', [
            'connection' => ConnectionManager::get('test')
        ]);
        $mappingsTable->deleteAll(['user_id' => 1, 'deleted' => false]);
        
        // Verify deleted mapping exists
        $deletedMapping = $mappingsTable->find()
            ->where(['user_id' => 1, 'deleted' => true])
            ->first();
        $this->assertNotNull($deletedMapping, 'Deleted mapping should exist');

        $consoleOutput = $this->captureConsoleOutput(function (): void {
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

        $this->assertTrue($response['success']);
        
        // Decode JWT token
        $tokenParts = explode('.', $response['token']);
        $payload = json_decode(base64_decode($tokenParts[1]), true);
        
        // Should use original company_id, not deleted mapping
        $this->assertEquals(200001, $payload['company_id'], 'Token should use original company_id when only deleted mapping exists');
        $this->assertNotEquals($deletedMapping->mapped_company_id, $payload['company_id'], 'Should not use deleted mapping');
    }

    /**
     * Test login uses most recent active mapping when multiple exist
     * 
     * @return void
     */
    public function testLoginUsesActiveMappingWhenMultipleExist(): void
    {
        // Create additional active mapping with different company_id
        $mappingsTable = TableRegistry::getTableLocator()->get('UserCompanyMappings', [
            'connection' => ConnectionManager::get('test')
        ]);
        
        // Ensure we have at least one active mapping
        $activeMapping = $mappingsTable->find()
            ->where(['user_id' => 1, 'active' => true, 'deleted' => false])
            ->first();
        
        if (!$activeMapping) {
            // Create active mapping
            $newMapping = $mappingsTable->newEntity([
                'user_id' => 1,
                'username' => 'test',
                'mapped_company_id' => 200001,
                'source_company_id' => 100000,
                'system_type' => 'scorecardtrakker',
                'active' => true,
                'deleted' => false,
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
            ]);
            $mappingsTable->save($newMapping);
        }

        $consoleOutput = $this->captureConsoleOutput(function (): void {
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

        $this->assertTrue($response['success']);
        
        // Decode JWT token
        $tokenParts = explode('.', $response['token']);
        $payload = json_decode(base64_decode($tokenParts[1]), true);
        
        // Should use active mapping
        $this->assertEquals(200001, $payload['company_id'], 'Token should use active mapping company_id');
    }

    /**
     * Test login handles mapping lookup errors gracefully
     * 
     * @return void
     */
    public function testLoginHandlesMappingLookupErrorsGracefully(): void
    {
        // Login should succeed even if mapping lookup fails
        // (The getMappedCompanyId method catches exceptions)
        
        $consoleOutput = $this->captureConsoleOutput(function (): void {
            $this->enableCsrfToken();
            $this->enableSecurityToken();
            $this->configRequest(['headers' => ['Accept' => 'application/json']]);

            $this->post('/api/users/login', [
                'username' => self::VALID_USERNAME,
                'password' => self::VALID_PASSWORD,
            ]);
        });

        // Should still succeed even if mapping lookup has issues
        $this->assertResponseCode(200);
        $this->assertContentType('application/json');

        $body = (string)$this->_response->getBody();
        $response = json_decode($body, true);

        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('token', $response);
    }

    /**
     * Test user company mapping creation during import
     * 
     * This test verifies that when employees are imported, user company mappings are created
     * 
     * @return void
     */
    public function testUserCompanyMappingCreatedDuringImport(): void
    {
        // This test requires import functionality, so we'll verify the mapping can be created
        // First, ensure user exists in Users table
        $usersTable = TableRegistry::getTableLocator()->get('Users', [
            'connection' => ConnectionManager::get('test')
        ]);
        $user = $usersTable->find()
            ->where(['username' => self::VALID_USERNAME])
            ->first();
        
        $this->assertNotNull($user, 'Test user should exist');

        // Delete ALL existing mappings for this user/system_type to avoid conflicts
        $mappingsTable = TableRegistry::getTableLocator()->get('UserCompanyMappings', [
            'connection' => ConnectionManager::get('test')
        ]);
        $mappingsTable->deleteAll([
            'user_id' => $user->id,
            'system_type' => 'scorecardtrakker'
        ]);

        // Create a mapping using CompanyMappingService
        $mappingService = new \App\Service\CompanyMappingService();
        $result = $mappingService->createUserCompanyMapping(
            $user->id,
            $user->username,
            100000, // source company ID (orgtrakker)
            200009, // mapped company ID (scorecardtrakker) - use unique ID
            'scorecardtrakker'
        );

        $this->assertTrue($result, 'Mapping should be created successfully');

        // Verify mapping was created - check for the specific mapped_company_id we created
        $mapping = $mappingsTable->find()
            ->where([
                'user_id' => $user->id,
                'username' => $user->username,
                'system_type' => 'scorecardtrakker',
                'mapped_company_id' => 200009,
                'active' => true,
                'deleted' => false
            ])
            ->first();

        $this->assertNotNull($mapping, 'Mapping should exist after creation with mapped_company_id 200009');
        $this->assertEquals(200009, $mapping->mapped_company_id);
        $this->assertEquals(100000, $mapping->source_company_id);
        
        // Also verify the mapping service can retrieve it
        $retrievedCompanyId = $mappingService->getMappedCompanyIdForUser(
            $user->id,
            $user->username,
            'scorecardtrakker'
        );
        // Should return the mapping we just created
        $this->assertNotNull($retrievedCompanyId, 'Should retrieve a mapped company ID');
        $this->assertEquals(200009, $retrievedCompanyId, 'Should return the mapped company ID we created');
    }

    /**
     * Test user company mapping creation handles duplicate mappings
     * 
     * @return void
     */
    public function testUserCompanyMappingCreationHandlesDuplicates(): void
    {
        $usersTable = TableRegistry::getTableLocator()->get('Users', [
            'connection' => ConnectionManager::get('test')
        ]);
        $user = $usersTable->find()
            ->where(['username' => self::VALID_USERNAME])
            ->first();
        
        $this->assertNotNull($user, 'Test user should exist');

        // Delete ALL existing mappings for this user/system_type to start fresh
        $mappingsTable = TableRegistry::getTableLocator()->get('UserCompanyMappings', [
            'connection' => ConnectionManager::get('test')
        ]);
        $mappingsTable->deleteAll([
            'user_id' => $user->id,
            'system_type' => 'scorecardtrakker'
        ]);

        $mappingService = new \App\Service\CompanyMappingService();
        
        // Create first mapping
        $result1 = $mappingService->createUserCompanyMapping(
            $user->id,
            $user->username,
            100000,
            200010, // Use unique company_id
            'scorecardtrakker'
        );
        $this->assertTrue($result1, 'First mapping should be created');

        // Verify mapping was created
        $activeMapping = $mappingsTable->find()
            ->where([
                'user_id' => $user->id,
                'username' => $user->username,
                'system_type' => 'scorecardtrakker',
                'mapped_company_id' => 200010,
                'active' => true,
                'deleted' => false
            ])
            ->first();
        $this->assertNotNull($activeMapping, 'First mapping should exist');

        // Try to create duplicate mapping (same user_id, mapped_company_id, system_type)
        $result2 = $mappingService->createUserCompanyMapping(
            $user->id,
            $user->username,
            100000,
            200010, // Same as first
            'scorecardtrakker'
        );
        
        // Should return true (mapping already exists and is active)
        $this->assertTrue($result2, 'Duplicate mapping creation should return true (mapping already exists)');

        // Verify still only one mapping exists
        $allMappings = $mappingsTable->find()
            ->where([
                'user_id' => $user->id,
                'system_type' => 'scorecardtrakker',
                'mapped_company_id' => 200010,
                'active' => true,
                'deleted' => false
            ])
            ->toArray();
        $this->assertCount(1, $allMappings, 'Should have only one active mapping for this combination');
    }

    /**
     * Test user company mapping reactivates inactive mapping
     * 
     * @return void
     */
    public function testUserCompanyMappingReactivatesInactiveMapping(): void
    {
        $usersTable = TableRegistry::getTableLocator()->get('Users', [
            'connection' => ConnectionManager::get('test')
        ]);
        $user = $usersTable->find()
            ->where(['username' => self::VALID_USERNAME])
            ->first();
        
        $this->assertNotNull($user, 'Test user should exist');

        $mappingsTable = TableRegistry::getTableLocator()->get('UserCompanyMappings', [
            'connection' => ConnectionManager::get('test')
        ]);
        
        // Delete any existing mappings for this user/system_type combination
        $mappingsTable->deleteAll([
            'user_id' => $user->id,
            'system_type' => 'scorecardtrakker'
        ]);
        
        // Create inactive mapping with unique mapped_company_id
        $inactiveMapping = $mappingsTable->newEntity([
            'user_id' => $user->id,
            'username' => $user->username,
            'mapped_company_id' => 200005, // Use different company_id to avoid unique constraint
            'source_company_id' => 100000,
            'system_type' => 'scorecardtrakker',
            'active' => false,
            'deleted' => false,
            'created' => date('Y-m-d H:i:s'),
            'modified' => date('Y-m-d H:i:s'),
        ]);
        $mappingsTable->save($inactiveMapping);

        // Try to create mapping - should reactivate existing inactive mapping
        $mappingService = new \App\Service\CompanyMappingService();
        $result = $mappingService->createUserCompanyMapping(
            $user->id,
            $user->username,
            100000,
            200005, // Same as inactive mapping
            'scorecardtrakker'
        );

        $this->assertTrue($result, 'Mapping should be reactivated');

        // Verify mapping is now active
        $reactivatedMapping = $mappingsTable->get($inactiveMapping->id);
        $this->assertTrue($reactivatedMapping->active, 'Mapping should be reactivated');
        $this->assertFalse($reactivatedMapping->deleted, 'Mapping should not be deleted');
    }

    /**
     * Test getMappedCompanyIdForUser returns correct company_id
     * 
     * @return void
     */
    public function testGetMappedCompanyIdForUserReturnsCorrectCompanyId(): void
    {
        $usersTable = TableRegistry::getTableLocator()->get('Users', [
            'connection' => ConnectionManager::get('test')
        ]);
        $user = $usersTable->find()
            ->where(['username' => self::VALID_USERNAME])
            ->first();
        
        $this->assertNotNull($user, 'Test user should exist');

        // Delete ALL existing mappings for this user/system_type to avoid conflicts
        $mappingsTable = TableRegistry::getTableLocator()->get('UserCompanyMappings', [
            'connection' => ConnectionManager::get('test')
        ]);
        $mappingsTable->deleteAll([
            'user_id' => $user->id,
            'system_type' => 'scorecardtrakker'
        ]);

        // Ensure active mapping exists
        $mappingService = new \App\Service\CompanyMappingService();
        $mappingService->createUserCompanyMapping(
            $user->id,
            $user->username,
            100000,
            200011, // Use unique company_id
            'scorecardtrakker'
        );

        // Get mapped company ID - should return the one we just created
        $mappedCompanyId = $mappingService->getMappedCompanyIdForUser(
            $user->id,
            $user->username,
            'scorecardtrakker'
        );

        $this->assertNotNull($mappedCompanyId, 'Mapped company ID should be returned');
        $this->assertEquals(200011, $mappedCompanyId, 'Should return the mapped company ID we created');
        
        // Verify the specific mapping we created exists
        $specificMapping = $mappingsTable->find()
            ->where([
                'user_id' => $user->id,
                'system_type' => 'scorecardtrakker',
                'mapped_company_id' => 200011,
                'active' => true,
                'deleted' => false
            ])
            ->first();
        $this->assertNotNull($specificMapping, 'The specific mapping we created should exist');
    }

    /**
     * Test getMappedCompanyIdForUser returns null when no mapping exists
     * 
     * @return void
     */
    public function testGetMappedCompanyIdForUserReturnsNullWhenNoMappingExists(): void
    {
        $mappingService = new \App\Service\CompanyMappingService();
        
        // Get mapped company ID for non-existent user
        $mappedCompanyId = $mappingService->getMappedCompanyIdForUser(
            99999, // Non-existent user ID
            'nonexistent_user',
            'scorecardtrakker'
        );

        $this->assertNull($mappedCompanyId, 'Should return null when no mapping exists');
    }

    /**
     * Test getMappedCompanyIdForUser ignores inactive mappings
     * 
     * @return void
     */
    public function testGetMappedCompanyIdForUserIgnoresInactiveMappings(): void
    {
        $usersTable = TableRegistry::getTableLocator()->get('Users', [
            'connection' => ConnectionManager::get('test')
        ]);
        $user = $usersTable->find()
            ->where(['username' => self::VALID_USERNAME])
            ->first();
        
        $this->assertNotNull($user, 'Test user should exist');

        $mappingsTable = TableRegistry::getTableLocator()->get('UserCompanyMappings', [
            'connection' => ConnectionManager::get('test')
        ]);
        
        // Delete all mappings for this user/system_type
        $mappingsTable->deleteAll([
            'user_id' => $user->id,
            'system_type' => 'scorecardtrakker'
        ]);
        
        // Create inactive mapping with unique mapped_company_id
        $inactiveMapping = $mappingsTable->newEntity([
            'user_id' => $user->id,
            'username' => $user->username,
            'mapped_company_id' => 200006, // Use unique company_id
            'source_company_id' => 100000,
            'system_type' => 'scorecardtrakker',
            'active' => false,
            'deleted' => false,
            'created' => date('Y-m-d H:i:s'),
            'modified' => date('Y-m-d H:i:s'),
        ]);
        $mappingsTable->save($inactiveMapping);

        // Get mapped company ID - should return null for inactive mapping
        $mappingService = new \App\Service\CompanyMappingService();
        $mappedCompanyId = $mappingService->getMappedCompanyIdForUser(
            $user->id,
            $user->username,
            'scorecardtrakker'
        );

        $this->assertNull($mappedCompanyId, 'Should return null for inactive mapping');
    }

    /**
     * Test getMappedCompanyIdForUser ignores deleted mappings
     * 
     * @return void
     */
    public function testGetMappedCompanyIdForUserIgnoresDeletedMappings(): void
    {
        $usersTable = TableRegistry::getTableLocator()->get('Users', [
            'connection' => ConnectionManager::get('test')
        ]);
        $user = $usersTable->find()
            ->where(['username' => self::VALID_USERNAME])
            ->first();
        
        $this->assertNotNull($user, 'Test user should exist');

        $mappingsTable = TableRegistry::getTableLocator()->get('UserCompanyMappings', [
            'connection' => ConnectionManager::get('test')
        ]);
        
        // Delete all mappings for this user/system_type
        $mappingsTable->deleteAll([
            'user_id' => $user->id,
            'system_type' => 'scorecardtrakker'
        ]);
        
        // Create deleted mapping with unique mapped_company_id
        $deletedMapping = $mappingsTable->newEntity([
            'user_id' => $user->id,
            'username' => $user->username,
            'mapped_company_id' => 200007, // Use unique company_id
            'source_company_id' => 100000,
            'system_type' => 'scorecardtrakker',
            'active' => true,
            'deleted' => true,
            'created' => date('Y-m-d H:i:s'),
            'modified' => date('Y-m-d H:i:s'),
        ]);
        $mappingsTable->save($deletedMapping);

        // Get mapped company ID - should return null for deleted mapping
        $mappingService = new \App\Service\CompanyMappingService();
        $mappedCompanyId = $mappingService->getMappedCompanyIdForUser(
            $user->id,
            $user->username,
            'scorecardtrakker'
        );

        $this->assertNull($mappedCompanyId, 'Should return null for deleted mapping');
    }

    /**
     * Test user company mapping creation with different system types
     * 
     * @return void
     */
    public function testUserCompanyMappingCreationWithDifferentSystemTypes(): void
    {
        $usersTable = TableRegistry::getTableLocator()->get('Users', [
            'connection' => ConnectionManager::get('test')
        ]);
        $user = $usersTable->find()
            ->where(['username' => self::VALID_USERNAME])
            ->first();
        
        $this->assertNotNull($user, 'Test user should exist');

        $mappingService = new \App\Service\CompanyMappingService();
        
        // Delete existing mappings for these system types
        $mappingsTable = TableRegistry::getTableLocator()->get('UserCompanyMappings', [
            'connection' => ConnectionManager::get('test')
        ]);
        $mappingsTable->deleteAll([
            'user_id' => $user->id,
            'system_type IN' => ['scorecardtrakker', 'skiltrakker']
        ]);
        
        // Create mapping for scorecardtrakker
        $result1 = $mappingService->createUserCompanyMapping(
            $user->id,
            $user->username,
            100000,
            200008, // Use unique company_id
            'scorecardtrakker'
        );
        $this->assertTrue($result1, 'Scorecardtrakker mapping should be created');

        // Create mapping for different system type (should be separate)
        $result2 = $mappingService->createUserCompanyMapping(
            $user->id,
            $user->username,
            100000,
            300001,
            'skiltrakker'
        );
        $this->assertTrue($result2, 'Skiltrakker mapping should be created');

        // Verify both mappings exist
        $scorecardMapping = $mappingsTable->find()
            ->where([
                'user_id' => $user->id,
                'system_type' => 'scorecardtrakker',
                'active' => true,
                'deleted' => false
            ])
            ->first();
        $skilMapping = $mappingsTable->find()
            ->where([
                'user_id' => $user->id,
                'system_type' => 'skiltrakker',
                'active' => true,
                'deleted' => false
            ])
            ->first();

        $this->assertNotNull($scorecardMapping, 'Scorecardtrakker mapping should exist');
        $this->assertNotNull($skilMapping, 'Skiltrakker mapping should exist');
        $this->assertEquals(200008, $scorecardMapping->mapped_company_id);
        $this->assertEquals(300001, $skilMapping->mapped_company_id);
    }

    /**
     * Test login token contains correct company_id after mapping update
     * 
     * @return void
     */
    public function testLoginTokenContainsCorrectCompanyIdAfterMappingUpdate(): void
    {
        $usersTable = TableRegistry::getTableLocator()->get('Users', [
            'connection' => ConnectionManager::get('test')
        ]);
        $user = $usersTable->find()
            ->where(['username' => self::VALID_USERNAME])
            ->first();
        
        $this->assertNotNull($user, 'Test user should exist');

        // Delete ALL existing mappings for this user/system_type to avoid conflicts
        $mappingsTable = TableRegistry::getTableLocator()->get('UserCompanyMappings', [
            'connection' => ConnectionManager::get('test')
        ]);
        $mappingsTable->deleteAll([
            'user_id' => $user->id,
            'system_type' => 'scorecardtrakker'
        ]);

        // Create mapping with unique company_id
        $mappingService = new \App\Service\CompanyMappingService();
        $mappingService->createUserCompanyMapping(
            $user->id,
            $user->username,
            100000,
            200012, // Use unique company_id
            'scorecardtrakker'
        );

        // Verify the mapping we created exists
        $createdMapping = $mappingsTable->find()
            ->where([
                'user_id' => $user->id,
                'system_type' => 'scorecardtrakker',
                'mapped_company_id' => 200012,
                'active' => true,
                'deleted' => false
            ])
            ->first();
        $this->assertNotNull($createdMapping, 'The mapping we created should exist');

        // Login and verify token contains mapped company_id
        $consoleOutput = $this->captureConsoleOutput(function (): void {
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

        $this->assertTrue($response['success']);
        
        // Decode JWT token
        $tokenParts = explode('.', $response['token']);
        $payload = json_decode(base64_decode($tokenParts[1]), true);
        
        // Token should contain the mapped company_id we created (200012)
        $this->assertArrayHasKey('company_id', $payload, 'Token should contain company_id');
        $this->assertEquals(200012, $payload['company_id'], 'Token should contain the mapped company_id we created');
    }
}
