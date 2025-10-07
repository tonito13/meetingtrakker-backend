# 🧪 **ScorecardTrakker Unit Test Creation Guide**
## **Official Template for API Controller Testing**

---

## 📋 **Table of Contents**

1. [Overview](#overview)
2. [Prerequisites](#prerequisites)
3. [File Structure](#file-structure)
4. [Test Class Template](#test-class-template)
5. [Test Method Patterns](#test-method-patterns)
6. [Fixture Creation](#fixture-creation)
7. [Configuration Requirements](#configuration-requirements)
8. [Test Execution](#test-execution)
9. [Quality Standards](#quality-standards)
10. [Troubleshooting](#troubleshooting)

---

## 🎯 **Overview**

This guide provides the exact template and patterns used in the `UsersControllerTest.php` for creating consistent, comprehensive unit tests for all API controllers in the ScorecardTrakker system. Following this guide ensures all tests maintain the same high quality, structure, and reliability.

### **Key Principles**
- **Consistency**: All tests follow identical patterns
- **Completeness**: Every endpoint is thoroughly tested
- **Reliability**: Tests are stable and predictable
- **Maintainability**: Easy to understand and modify
- **Security**: Proper validation and error handling

---

## ✅ **Prerequisites**

Before creating tests, ensure you have:

1. **Docker Environment Running**
   ```bash
   docker ps
   # Should show: scorecardtrakker_backend, scorecardtrakker_postgres_database, etc.
   ```

2. **Test Database Configured**
   - Database: `scorecardtrakker_test`
   - Host: `scorecardtrakker_postgres_database`
   - User: `scorecardtrakker_user`

3. **Required Files Present**
   - `tests/bootstrap.php`
   - `phpunit.xml.dist`
   - `config/datasources.php`

---

## 📁 **File Structure**

### **Required Directory Structure**
```
scorecardtrakker-backend/
├── tests/
│   ├── TestCase/
│   │   └── Controller/
│   │       └── Api/
│   │           └── {ControllerName}ControllerTest.php
│   └── Fixture/
│       └── {ModelName}Fixture.php
├── config/
│   └── datasources.php
├── tests/
│   └── bootstrap.php
└── phpunit.xml.dist
```

### **Naming Conventions**
- **Test Files**: `{ControllerName}ControllerTest.php`
- **Fixture Files**: `{ModelName}Fixture.php`
- **Test Classes**: `{ControllerName}ControllerTest`
- **Fixture Classes**: `{ModelName}Fixture`

---

## 🏗️ **Test Class Template**

### **Complete Test Class Structure**

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Api;

use App\Controller\Api\{ControllerName}Controller;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * App\Controller\Api\{ControllerName}Controller Test Case
 * 
 * This test class follows the ScorecardTrakker testing standards
 * and ensures comprehensive coverage of all controller methods.
 */
class {ControllerName}ControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * Fixtures used by this test class
     * 
     * @var array<string>
     */
    protected array $fixtures = ['app.{ModelName}'];

    // ========================================
    // TEST DATA CONSTANTS
    // ========================================
    
    /**
     * Valid test credentials
     */
    private const VALID_USERNAME = 'test';
    private const VALID_PASSWORD = '12345';
    
    /**
     * Invalid test credentials
     */
    private const INVALID_USERNAME = 'nonexistent';
    private const INVALID_PASSWORD = 'wrongpassword';
    
    /**
     * Test data constants
     */
    private const VALID_FIELD1 = 'valid_value';
    private const INVALID_FIELD1 = 'invalid_value';

    // ========================================
    // HELPER METHODS
    // ========================================

    /**
     * Helper method to safely capture console output with proper cleanup
     * 
     * This method ensures that any console output (echo, print, etc.) is captured
     * and can be validated. It also handles exceptions properly.
     *
     * @param callable $callback The callback to execute while capturing output
     * @return string The captured console output
     * @throws \Throwable Re-throws any exceptions from the callback
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
     * @return string JWT token for authenticated requests
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
    // LIFECYCLE METHODS
    // ========================================

    /**
     * tearDown method
     * 
     * Clean up after each test method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();
    }

    // ========================================
    // TEST METHODS
    // ========================================
    
    // Your test methods go here...
}
```

---

## 🧪 **Test Method Patterns**

### **1. Successful Operation Test Pattern**

```php
/**
 * Test successful {operation}
 * 
 * This test verifies that the endpoint works correctly with valid input
 * and returns the expected response structure.
 *
 * @return void
 */
public function testValid{Operation}(): void
{
    // REQUIRED: Capture console output to ensure no unexpected output
    $consoleOutput = $this->captureConsoleOutput(function () {
        // REQUIRED: Enable security features
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        
        // REQUIRED: Set JSON headers for API requests
        $this->configRequest([
            'headers' => ['Accept' => 'application/json']
        ]);

        // REQUIRED: Make the API request
        $this->post('/api/{endpoint}', [
            'field1' => self::VALID_FIELD1,
            'field2' => self::VALID_FIELD2,
        ]);
    });
    
    // REQUIRED: Get response body for assertions
    $body = (string)$this->_response->getBody();

    // ========================================
    // RESPONSE STRUCTURE ASSERTIONS
    // ========================================
    
    // REQUIRED: Basic response validation
    $this->assertResponseCode(200, 'Response should return 200 status code');
    $this->assertContentType('application/json', 'Response should be JSON');
    $this->assertResponseNotEmpty('Response should not be empty');
    
    // REQUIRED: Console output validation
    $this->assertEmpty(
        $consoleOutput, 
        'Endpoint should not produce console output (echo, print, etc.)'
    );
    
    // REQUIRED: JSON format validation
    $this->assertJson($body, 'Response should be valid JSON');
    
    // ========================================
    // RESPONSE DATA VALIDATION
    // ========================================
    
    // REQUIRED: Parse and validate response data
    $response = json_decode($body, true);
    $this->assertNotNull($response, 'Response should be valid JSON');
    
    // REQUIRED: Success indicator validation
    $this->assertTrue($response['success'], 'Response should indicate success');
    
    // REQUIRED: Required fields validation
    $this->assertArrayHasKey('data', $response, 'Response should contain data field');
    
    // REQUIRED: Specific field validations
    $this->assertEquals('expected_value', $response['data']['field'], 'Field should match expected value');
    
    // REQUIRED: Data type validations
    $this->assertIsString($response['data']['string_field'], 'String field should be string type');
    $this->assertIsInt($response['data']['int_field'], 'Integer field should be int type');
}
```

### **2. Error Response Test Pattern**

```php
/**
 * Test {operation} with invalid input
 * 
 * This test verifies that the endpoint properly handles invalid input
 * and returns appropriate error responses.
 *
 * @return void
 */
public function testInvalid{Input}(): void
{
    // REQUIRED: Capture console output
    $consoleOutput = $this->captureConsoleOutput(function () {
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        
        // REQUIRED: Make request with invalid data
        $this->post('/api/{endpoint}', [
            'invalid_field' => self::INVALID_FIELD1,
            'empty_field' => '',
        ]);
    });
    
    $body = (string)$this->_response->getBody();
    
    // ========================================
    // ERROR RESPONSE ASSERTIONS
    // ========================================
    
    // REQUIRED: Error status code validation
    $this->assertResponseCode(400, 'Should return 400 for invalid input');
    $this->assertContentType('application/json', 'Error response should be JSON');
    
    // REQUIRED: Console output validation
    $this->assertEmpty(
        $consoleOutput, 
        'Endpoint should not produce console output on error'
    );
    
    // REQUIRED: JSON format validation
    $this->assertJson($body, 'Error response should be valid JSON');
    
    // ========================================
    // ERROR DATA VALIDATION
    // ========================================
    
    // REQUIRED: Parse error response
    $response = json_decode($body, true);
    $this->assertNotNull($response, 'Error response should be valid JSON');
    
    // REQUIRED: Error message validation
    $this->assertEquals(
        'Expected error message', 
        $response['message'],
        'Error message should match expected text'
    );
    
    // REQUIRED: Ensure no success fields in error response
    $this->assertArrayNotHasKey('success', $response, 'Error response should not contain success field');
    $this->assertArrayNotHasKey('token', $response, 'Error response should not contain token field');
    $this->assertArrayNotHasKey('data', $response, 'Error response should not contain data field');
}
```

### **3. Authenticated Endpoint Test Pattern**

```php
/**
 * Test {operation} with authentication
 * 
 * This test verifies that protected endpoints work correctly
 * when accessed with valid authentication.
 *
 * @return void
 */
public function test{Operation}WithAuthentication(): void
{
    // REQUIRED: Get authentication token
    $token = $this->getAuthToken();

    // REQUIRED: Test authenticated endpoint
    $consoleOutput = $this->captureConsoleOutput(function () use ($token): void {
        $this->configRequest([
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token
            ]
        ]);

        $this->get('/api/{protected_endpoint}');
    });

    // ========================================
    // AUTHENTICATED RESPONSE VALIDATION
    // ========================================
    
    // REQUIRED: Response validation
    $this->assertResponseCode(200, 'Authenticated request should return 200');
    $body = (string)$this->_response->getBody();
    $response = json_decode($body, true);
    
    // REQUIRED: Data structure validation
    $this->assertIsArray($response, 'Response should be an array');
    
    // REQUIRED: Console output validation
    $this->assertEmpty(
        $consoleOutput, 
        'Authenticated endpoint should not produce console output'
    );
    
    // REQUIRED: Data validation
    if (!empty($response)) {
        foreach ($response as $item) {
            $this->assertArrayHasKey('id', $item, 'Item should have id field');
            $this->assertArrayHasKey('name', $item, 'Item should have name field');
            
            // REQUIRED: Sensitive data validation
            $this->assertArrayNotHasKey('password', $item, 'Password should not be exposed');
        }
    }
}
```

### **4. HTTP Method Validation Test Pattern**

```php
/**
 * Test {endpoint} with wrong HTTP methods
 * 
 * This test verifies that endpoints properly reject
 * unsupported HTTP methods.
 *
 * @return void
 */
public function test{Endpoint}WithWrongHttpMethods(): void
{
    $wrongMethods = ['POST', 'PUT', 'DELETE', 'PATCH'];
    
    foreach ($wrongMethods as $method) {
        $consoleOutput = $this->captureConsoleOutput(function () use ($method): void {
            $this->configRequest(['headers' => ['Accept' => 'application/json']]);
            
            // REQUIRED: Test each unsupported method
            switch ($method) {
                case 'POST':
                    $this->post('/api/{endpoint}');
                    break;
                case 'PUT':
                    $this->put('/api/{endpoint}');
                    break;
                case 'DELETE':
                    $this->delete('/api/{endpoint}');
                    break;
                case 'PATCH':
                    $this->patch('/api/{endpoint}');
                    break;
            }
        });

        // REQUIRED: Method validation
        $this->assertTrue(
            in_array($this->_response->getStatusCode(), [401, 405]),
            "Endpoint should reject {$method} method, got {$this->_response->getStatusCode()}"
        );
        
        // REQUIRED: Console output validation
        $this->assertEmpty(
            $consoleOutput, 
            "Endpoint should not produce console output for {$method} method"
        );
    }
}
```

### **5. Response Structure Test Pattern**

```php
/**
 * Test {endpoint} response data structure
 * 
 * This test verifies that the response contains
 * all required fields with correct data types.
 *
 * @return void
 */
public function test{Endpoint}ResponseDataStructure(): void
{
    // REQUIRED: Get authentication token if needed
    $token = $this->getAuthToken();

    $consoleOutput = $this->captureConsoleOutput(function () use ($token): void {
        $this->configRequest([
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token
            ]
        ]);

        $this->get('/api/{endpoint}');
    });

    $this->assertResponseCode(200);
    $body = (string)$this->_response->getBody();
    $response = json_decode($body, true);
    
    // ========================================
    // RESPONSE STRUCTURE VALIDATION
    // ========================================
    
    // REQUIRED: Basic structure validation
    $this->assertIsArray($response, 'Response should be an array');
    
    // REQUIRED: Console output validation
    $this->assertEmpty($consoleOutput, 'Endpoint should not produce console output');
    
    // REQUIRED: Data validation
    if (!empty($response)) {
        foreach ($response as $item) {
            // REQUIRED: Required fields validation
            $this->assertArrayHasKey('id', $item, 'Item should have id field');
            $this->assertArrayHasKey('name', $item, 'Item should have name field');
            $this->assertArrayHasKey('created', $item, 'Item should have created field');
            
            // REQUIRED: Data type validation
            $this->assertIsInt($item['id'], 'ID should be integer');
            $this->assertIsString($item['name'], 'Name should be string');
            $this->assertIsString($item['created'], 'Created should be string');
            
            // REQUIRED: Sensitive data validation
            $this->assertArrayNotHasKey('password', $item, 'Password should not be exposed');
            $this->assertArrayNotHasKey('secret', $item, 'Secret data should not be exposed');
        }
    }
}
```

---

## 🎭 **Fixture Creation**

### **Complete Fixture Template**

```php
<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * {ModelName}Fixture
 * 
 * Test fixture for {ModelName} model.
 * Provides consistent test data for all {ModelName} related tests.
 */
class {ModelName}Fixture extends TestFixture
{
    /**
     * Table name
     * 
     * @var string
     */
    public $table = '{table_name}';

    /**
     * Fields configuration
     * 
     * Define the table structure for this fixture.
     * This should match the actual database table structure.
     * 
     * @var array
     */
    public array $fields = [
        'id' => ['type' => 'integer', 'length' => null, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null, 'autoIncrement' => true],
        'field1' => ['type' => 'string', 'length' => 255, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'field2' => ['type' => 'text', 'length' => null, 'null' => true, 'default' => null, 'comment' => '', 'precision' => null],
        'created' => ['type' => 'datetime', 'length' => null, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'modified' => ['type' => 'datetime', 'length' => null, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        '_constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id'], 'length' => []],
        ],
        '_options' => [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci'
        ],
    ];

    /**
     * Init method
     * 
     * Initialize the fixture with test data.
     * This method is called before each test runs.
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = [
            [
                // REQUIRED: Use unique IDs to avoid conflicts
                // Let database auto-generate ID or use unique values
                'field1' => 'test_value_1',
                'field2' => 'test_description_1',
                'field3' => 'test_data_1',
                // REQUIRED: Include all required fields
                'created' => '2024-01-01 00:00:00',
                'modified' => '2024-01-01 00:00:00',
            ],
            [
                'field1' => 'test_value_2',
                'field2' => 'test_description_2',
                'field3' => 'test_data_2',
                'created' => '2024-01-02 00:00:00',
                'modified' => '2024-01-02 00:00:00',
            ],
            // Add more test records as needed
        ];
        
        parent::init();
    }
}
```

### **Fixture Best Practices**

1. **Use Unique IDs**: Avoid conflicts by using unique identifiers
2. **Include All Fields**: Ensure all required database fields are present
3. **Realistic Data**: Use data that represents real-world scenarios
4. **Consistent Timestamps**: Use consistent created/modified timestamps
5. **No Sensitive Data**: Never include real passwords or sensitive information

---

## ⚙️ **Configuration Requirements**

### **1. Database Configuration (config/datasources.php)**

```php
<?php
namespace Config;

use Cake\Database\Connection;
use Cake\Database\Driver\Postgres;

function setupDataSource($host, $username, $password, $database) {
    return [
        'className' => Connection::class,
        'driver' => Postgres::class,
        'persistent' => false,
        'host' => $host,
        'port' => 5432,
        'username' => $username,
        'password' => $password,
        'database' => $database,
        'encoding' => 'utf8',
        'timezone' => 'UTC',
        'cacheMetadata' => true,
        'quoteIdentifiers' => false,
        'log' => false,
    ];
}

$dataSources = [];

// REQUIRED: Test database configuration
$dataSources['test'] = setupDataSource(
    'scorecardtrakker_postgres_database', 
    'scorecardtrakker_user', 
    'securepassword', 
    'scorecardtrakker_test'
);

// REQUIRED: Client test database configuration
$dataSources['client_200001_test'] = setupDataSource(
    'scorecardtrakker_postgres_database', 
    'scorecardtrakker_user', 
    'securepassword', 
    '200001_test'
);

return $dataSources;
```

### **2. Test Bootstrap (tests/bootstrap.php)**

```php
<?php
declare(strict_types=1);

use Cake\Chronos\Chronos;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\ConnectionHelper;

// REQUIRED: Load autoloader and main bootstrap
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/config/bootstrap.php';

// REQUIRED: Set base URL for tests
if (empty($_SERVER['HTTP_HOST']) && !Configure::read('App.fullBaseUrl')) {
    Configure::write('App.fullBaseUrl', 'http://localhost');
}

// REQUIRED: DebugKit configuration for CLI
ConnectionManager::setConfig('test_debug_kit', [
    'className' => 'Cake\Database\Connection',
    'driver' => 'Cake\Database\Driver\Sqlite',
    'database' => TMP . 'debug_kit.sqlite',
    'encoding' => 'utf8',
    'cacheMetadata' => true,
    'quoteIdentifiers' => false,
]);

ConnectionManager::alias('test_debug_kit', 'debug_kit');

// REQUIRED: Fixate time to avoid leap second issues
Chronos::setTestNow(Chronos::now());

// REQUIRED: Set session ID for CLI
session_id('cli');

// REQUIRED: Add test connection aliases
ConnectionHelper::addTestAliases();
```

### **3. PHPUnit Configuration (phpunit.xml.dist)**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         colors="true"
         processIsolation="false"
         stopOnFailure="false"
         bootstrap="tests/bootstrap.php"
         cacheDirectory=".phpunit.cache"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/10.1/phpunit.xsd">
    <php>
        <ini name="memory_limit" value="-1"/>
        <ini name="apc.enable_cli" value="1"/>
        <env name="FIXTURE_STRATEGY" value="truncate"/>
    </php>

    <testsuites>
        <testsuite name="app">
            <directory>tests/TestCase/</directory>
        </testsuite>
    </testsuites>

    <extensions>
        <bootstrap class="Cake\TestSuite\Fixture\Extension\PHPUnitExtension"/>
    </extensions>

    <source>
        <include>
            <directory suffix=".php">src/</directory>
            <directory suffix=".php">plugins/*/src/</directory>
        </include>
        <exclude>
            <file>src/Console/Installer.php</file>
        </exclude>
    </source>
</phpunit>
```

---

## 🚀 **Test Execution**

### **Running Tests**

#### **Run All Tests**
```bash
docker exec scorecardtrakker_backend php vendor/bin/phpunit --colors=always
```

#### **Run Specific Test Class**
```bash
docker exec scorecardtrakker_backend php vendor/bin/phpunit tests/TestCase/Controller/Api/ControllerTest.php --colors=always
```

#### **Run Specific Test Method**
```bash
docker exec scorecardtrakker_backend php vendor/bin/phpunit --filter testMethodName tests/TestCase/Controller/Api/ControllerTest.php --colors=always
```

#### **Run Tests with Verbose Output**
```bash
docker exec scorecardtrakker_backend php vendor/bin/phpunit --colors=always --verbose
```

#### **Run Tests with Coverage Report**
```bash
docker exec scorecardtrakker_backend php vendor/bin/phpunit --colors=always --coverage-html coverage/
```

### **Test Output Interpretation**

#### **Successful Test Run**
```
PHPUnit 11.5.19 by Sebastian Bergmann and contributors.

...................S...                                           23 / 23 (100%)

Time: 00:20.144, Memory: 30.00 MB

OK, but some tests were skipped!
Tests: 23, Assertions: 379, Skipped: 1.
```

#### **Failed Test Run**
```
PHPUnit 11.5.19 by Sebastian Bergmann and contributors.

F                                                                   1 / 1 (100%)

Time: 00:04.101, Memory: 18.00 MB

There was 1 failure:

1) App\Test\TestCase\Controller\Api\ControllerTest::testMethodName
Expected: 200
Actual: 401

FAILURES!
Tests: 1, Assertions: 4, Failures: 1.
```

---

## ✅ **Quality Standards**

### **Mandatory Requirements Checklist**

Before submitting any test, ensure all items are checked:

#### **Test Class Requirements**
- [ ] Class extends `TestCase`
- [ ] Uses `IntegrationTestTrait`
- [ ] Defines `$fixtures` array
- [ ] Includes `tearDown()` method
- [ ] Uses constants for test data
- [ ] Includes `captureConsoleOutput()` helper method

#### **Test Method Requirements**
- [ ] Uses `captureConsoleOutput()` wrapper
- [ ] Enables CSRF and Security tokens
- [ ] Sets JSON Accept headers
- [ ] Checks for empty console output
- [ ] Validates JSON response format
- [ ] Uses descriptive assertion messages
- [ ] Tests both success and error scenarios
- [ ] Validates response structure
- [ ] Checks data types
- [ ] Ensures sensitive data is not exposed

#### **Fixture Requirements**
- [ ] Extends `TestFixture`
- [ ] Includes all required database fields
- [ ] Uses unique IDs to avoid conflicts
- [ ] Includes `created` and `modified` timestamps
- [ ] Uses realistic test data
- [ ] No sensitive information included

#### **Code Quality Requirements**
- [ ] Follows PSR-12 coding standards
- [ ] Includes comprehensive PHPDoc comments
- [ ] Uses meaningful variable and method names
- [ ] Handles exceptions properly
- [ ] No hardcoded values (use constants)
- [ ] Proper error handling

### **Test Coverage Requirements**

#### **Minimum Coverage**
- **Success Paths**: 100% coverage
- **Error Paths**: 100% coverage
- **Edge Cases**: 90% coverage
- **Security Scenarios**: 100% coverage

#### **Required Test Types**
1. **Valid Input Tests**: Test successful operations
2. **Invalid Input Tests**: Test error handling
3. **Authentication Tests**: Test protected endpoints
4. **Authorization Tests**: Test permission checks
5. **HTTP Method Tests**: Test method validation
6. **Response Structure Tests**: Test data format
7. **Security Tests**: Test data exposure
8. **Edge Case Tests**: Test boundary conditions

---

## 🔧 **Troubleshooting**

### **Common Issues and Solutions**

#### **1. Database Connection Errors**
```
Error: Connection to Postgres could not be established
```
**Solution**: Ensure Docker containers are running and database configuration is correct.

#### **2. Fixture Loading Errors**
```
Error: Cannot describe schema for table `table_name`
```
**Solution**: Check fixture configuration and ensure table exists in test database.

#### **3. Console Output Errors**
```
Error: Login endpoint should not produce console output
```
**Solution**: Remove any `echo`, `print`, or `var_dump` statements from controller code.

#### **4. Authentication Errors**
```
Error: Invalid credentials
```
**Solution**: Check test credentials in fixture and ensure they match controller expectations.

#### **5. JSON Response Errors**
```
Error: Response should be valid JSON
```
**Solution**: Ensure controller returns proper JSON responses and check for syntax errors.

### **Debugging Tips**

1. **Use Verbose Output**: Add `--verbose` flag to see detailed test information
2. **Check Response Body**: Use `$this->_response->getBody()` to inspect actual responses
3. **Validate JSON**: Use `json_decode()` to check JSON validity
4. **Check Console Output**: Use `captureConsoleOutput()` to find unexpected output
5. **Verify Database**: Check test database has correct schema and data

---

## 📚 **Additional Resources**

### **CakePHP Testing Documentation**
- [CakePHP Testing Guide](https://book.cakephp.org/4/en/development/testing.html)
- [Integration Testing](https://book.cakephp.org/4/en/development/testing.html#integration-testing)
- [Fixtures](https://book.cakephp.org/4/en/development/testing.html#fixtures)

### **PHPUnit Documentation**
- [PHPUnit Manual](https://phpunit.readthedocs.io/)
- [Assertions](https://phpunit.readthedocs.io/en/9.5/assertions.html)
- [Test Doubles](https://phpunit.readthedocs.io/en/9.5/test-doubles.html)

### **ScorecardTrakker Specific**
- `UsersControllerTest.php` - Reference implementation
- `tests/bootstrap.php` - Test configuration
- `config/datasources.php` - Database configuration

---

## 📝 **Conclusion**

This guide provides the complete template for creating high-quality unit tests in the ScorecardTrakker system. By following these patterns and requirements, you ensure:

- **Consistency** across all test implementations
- **Reliability** of test results
- **Maintainability** of test code
- **Comprehensive coverage** of all functionality
- **Security** validation of all endpoints

Remember: **Quality tests are an investment in the future stability and maintainability of your application.**

---

*This guide is based on the proven patterns established in `UsersControllerTest.php` and should be followed for all future test implementations.*
