# Test Suite Configuration Guide

This guide explains how the PHPUnit test suites are configured and how to properly create and maintain tests for the ScorecardTrakker application.

## Table of Contents
1. [Test Environment Setup](#test-environment-setup)
2. [Authentication Pattern](#authentication-pattern)
3. [Test Structure](#test-structure)
4. [Common Patterns](#common-patterns)
5. [Best Practices](#best-practices)
6. [Troubleshooting](#troubleshooting)

## Test Environment Setup

### Docker Configuration
Tests run in a Docker container with the following setup:
- **Container**: `scorecardtrakker_backend`
- **Database**: PostgreSQL with test-specific database
- **PHP Version**: 8.2.29
- **PHPUnit Version**: 11.5.19

### Database Configuration
- Tests use a separate test database
- Fixtures are loaded automatically
- Database is reset between test runs
- Transactions are used to ensure test isolation

### Running Tests
```bash
# Run all tests
docker exec scorecardtrakker_backend vendor/bin/phpunit

# Run specific test file
docker exec scorecardtrakker_backend vendor/bin/phpunit tests/TestCase/Controller/Api/EmployeesControllerTest.php

# Run specific test method
docker exec scorecardtrakker_backend vendor/bin/phpunit tests/TestCase/Controller/Api/EmployeesControllerTest.php --filter testMethodName

# Run with testdox output
docker exec scorecardtrakker_backend vendor/bin/phpunit tests/TestCase/Controller/Api/EmployeesControllerTest.php --testdox
```

## Authentication Pattern

### The Critical Authentication Pattern
All API tests MUST follow this pattern for authentication:

```php
public function testExample(): void
{
    // Step 1: Get authentication token
    $token = $this->getAuthToken();

    // Step 2: Make API call with proper authentication
    $consoleOutput = $this->captureConsoleOutput(function () use ($token): void {
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->configRequest([
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token
            ]
        ]);
        
        // Your API call here
        $this->post('/api/endpoint', $data);
    });
    
    // Step 3: Validate response
    $body = (string)$this->_response->getBody();
    
    $this->assertResponseCode(200);
    $this->assertContentType('application/json');
    $this->assertEmpty($consoleOutput);
    $this->assertJson($body);
    
    $responseData = json_decode($body, true);
    $this->assertNotNull($responseData);
    $this->assertTrue($responseData['success']);
}
```

### Key Components Explained

#### 1. `getAuthToken()` Method
```php
private function getAuthToken(): string
{
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
    return $loginData['token'];
}
```

#### 2. `captureConsoleOutput()` Method
This method is CRITICAL for proper test execution:
- Wraps all API calls
- Captures console output
- Ensures proper test isolation
- Must be used for EVERY API call

#### 3. Required Headers
Every authenticated API call must include:
```php
'headers' => [
    'Accept' => 'application/json',
    'Authorization' => 'Bearer ' . $token
]
```

#### 4. CSRF and Security Tokens
Always enable these before making requests:
```php
$this->enableCsrfToken();
$this->enableSecurityToken();
```

## Test Structure

### Basic Test Class Structure
```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Api;

use App\Test\TestCase\AppTestCase;
use Cake\TestSuite\IntegrationTestTrait;

class ExampleControllerTest extends AppTestCase
{
    use IntegrationTestTrait;

    // Constants for test data
    private const VALID_USERNAME = 'test';
    private const VALID_PASSWORD = '12345';
    private const VALID_TEMPLATE_ID = 1001;

    // Fixtures
    protected array $fixtures = [
        'app.Users',
        'app.ExampleTable',
        // Add other required fixtures
    ];

    // Helper method for authentication
    private function getAuthToken(): string
    {
        // Implementation as shown above
    }

    // Test methods
    public function testBasicFunctionality(): void
    {
        // Test implementation
    }
}
```

### Test Method Naming Conventions
- `testMethodNameWithValidData()` - Tests successful operations
- `testMethodNameWithoutAuthentication()` - Tests authentication failures
- `testMethodNameWithInvalidData()` - Tests validation failures
- `testMethodNameComprehensiveValidation()` - Tests multiple scenarios

## Common Patterns

### 1. CRUD Operations Testing

#### Create Operation
```php
public function testAddEntityWithValidData(): void
{
    $token = $this->getAuthToken();
    
    $entityData = [
        'name' => 'Test Entity',
        'description' => 'Test Description',
        'template_id' => self::VALID_TEMPLATE_ID,
    ];

    $consoleOutput = $this->captureConsoleOutput(function () use ($token, $entityData): void {
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->configRequest([
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token
            ]
        ]);
        
        $this->post('/api/entities/addEntity', $entityData);
    });
    
    $body = (string)$this->_response->getBody();
    
    $this->assertResponseCode(200);
    $this->assertContentType('application/json');
    $this->assertEmpty($consoleOutput);
    $this->assertJson($body);
    
    $responseData = json_decode($body, true);
    $this->assertNotNull($responseData);
    $this->assertTrue($responseData['success']);
}
```

#### Read Operation
```php
public function testGetEntityWithValidId(): void
{
    $token = $this->getAuthToken();
    
    $consoleOutput = $this->captureConsoleOutput(function () use ($token): void {
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->configRequest([
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token
            ]
        ]);
        
        $this->post('/api/entities/getEntity', [
            'entity_id' => self::VALID_ENTITY_ID
        ]);
    });
    
    $body = (string)$this->_response->getBody();
    
    $this->assertResponseCode(200);
    $this->assertContentType('application/json');
    $this->assertEmpty($consoleOutput);
    $this->assertJson($body);
    
    $responseData = json_decode($body, true);
    $this->assertNotNull($responseData);
    $this->assertTrue($responseData['success']);
    $this->assertIsArray($responseData['data']);
}
```

#### Update Operation
```php
public function testUpdateEntityWithValidData(): void
{
    $token = $this->getAuthToken();
    
    $updateData = [
        'entity_id' => self::VALID_ENTITY_ID,
        'name' => 'Updated Entity Name',
        'description' => 'Updated Description',
    ];

    $consoleOutput = $this->captureConsoleOutput(function () use ($token, $updateData): void {
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->configRequest([
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token
            ]
        ]);
        
        $this->post('/api/entities/updateEntity', $updateData);
    });
    
    $body = (string)$this->_response->getBody();
    
    $this->assertResponseCode(200);
    $this->assertContentType('application/json');
    $this->assertEmpty($consoleOutput);
    $this->assertJson($body);
    
    $responseData = json_decode($body, true);
    $this->assertNotNull($responseData);
    $this->assertTrue($responseData['success']);
}
```

#### Delete Operation
```php
public function testDeleteEntityWithValidId(): void
{
    $token = $this->getAuthToken();
    
    $consoleOutput = $this->captureConsoleOutput(function () use ($token): void {
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->configRequest([
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token
            ]
        ]);
        
        $this->post('/api/entities/deleteEntity', [
            'entity_id' => self::VALID_ENTITY_ID
        ]);
    });
    
    $body = (string)$this->_response->getBody();
    
    $this->assertResponseCode(200);
    $this->assertContentType('application/json');
    $this->assertEmpty($consoleOutput);
    $this->assertJson($body);
    
    $responseData = json_decode($body, true);
    $this->assertNotNull($responseData);
    $this->assertTrue($responseData['success']);
}
```

### 2. Authentication Failure Testing
```php
public function testMethodWithoutAuthentication(): void
{
    $consoleOutput = $this->captureConsoleOutput(function (): void {
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        
        $this->post('/api/entities/method', $data);
    });
    
    $this->assertResponseCode(401);
    $body = (string)$this->_response->getBody();
    $response = json_decode($body, true);
    
    $this->assertEmpty($consoleOutput);
    $this->assertNotNull($response);
    $this->assertFalse($response['success']);
}
```

### 3. Integration Testing
```php
public function testCrossControllerIntegration(): void
{
    $token = $this->getAuthToken();
    
    // Step 1: Create entity in Controller A
    $consoleOutput1 = $this->captureConsoleOutput(function () use ($token): void {
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->configRequest([
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token
            ]
        ]);
        
        $this->post('/api/controllerA/createEntity', $entityData);
    });
    
    $this->assertResponseCode(200);
    $body1 = (string)$this->_response->getBody();
    $response1 = json_decode($body1, true);
    $this->assertTrue($response1['success']);
    
    // Step 2: Verify entity in Controller B
    $consoleOutput2 = $this->captureConsoleOutput(function () use ($token): void {
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->configRequest([
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token
            ]
        ]);
        
        $this->post('/api/controllerB/getEntity', [
            'entity_id' => $response1['id']
        ]);
    });
    
    $this->assertResponseCode(200);
    $body2 = (string)$this->_response->getBody();
    $response2 = json_decode($body2, true);
    $this->assertTrue($response2['success']);
}
```

## Best Practices

### 1. Test Data Management
- Use constants for reusable test data
- Create helper methods for common data structures
- Use fixtures for consistent test data
- Avoid hardcoded values in tests

### 2. Error Handling
- Always test both success and failure scenarios
- Test authentication failures
- Test validation failures
- Test server errors (500 responses)

### 3. Test Organization
- Group related tests together
- Use descriptive test names
- Keep tests focused on single functionality
- Avoid complex test scenarios that are hard to debug

### 4. Response Validation
- Always validate response codes
- Check content type
- Validate JSON structure
- Verify success/failure status
- Check for console output (should be empty)

### 5. Database Considerations
- Use existing fixture data when possible
- Avoid creating new entities unless necessary
- Clean up test data when appropriate
- Be aware of database transaction issues

## Troubleshooting

### Common Issues and Solutions

#### 1. Authentication Failures (401 errors)
**Problem**: Tests failing with 401 Unauthorized
**Solution**: Ensure proper authentication pattern is followed
- Use `getAuthToken()` method
- Include Authorization header
- Wrap calls in `captureConsoleOutput()`

#### 2. Response Body Issues
**Problem**: "Response is null" or empty response body
**Solution**: Capture response body immediately after API call
```php
$body = (string)$this->_response->getBody();
// Use $body for assertions
```

#### 3. Database Transaction Issues
**Problem**: Tests failing with database errors
**Solution**: 
- Use existing fixture data
- Avoid creating entities that conflict with fixtures
- Check for proper test isolation

#### 4. Console Output Issues
**Problem**: Tests failing due to console output
**Solution**: Always use `captureConsoleOutput()` and assert empty output
```php
$this->assertEmpty($consoleOutput);
```

#### 5. Fixture Data Mismatches
**Problem**: Tests expecting data that doesn't exist in fixtures
**Solution**: 
- Check fixture files for correct data
- Use fixture data IDs in tests
- Verify fixture loading

### Debugging Tips

#### 1. Add Debug Output
```php
// Debug response
if ($this->_response->getStatusCode() !== 200) {
    $this->fail('Expected 200 but got ' . $this->_response->getStatusCode() . 
                '. Response: ' . substr($body, 0, 500));
}
```

#### 2. Check Response Structure
```php
$responseData = json_decode($body, true);
if (!$responseData) {
    $this->fail('Invalid JSON response: ' . $body);
}
```

#### 3. Verify Authentication
```php
// Check if token is valid
if (empty($token)) {
    $this->fail('Authentication token is empty');
}
```

## Creating New Tests

### Step-by-Step Process

1. **Identify the Controller and Method**
   - Determine which controller method to test
   - Understand the expected input/output

2. **Set Up Test Class**
   - Create test class extending `AppTestCase`
   - Add required fixtures
   - Define constants for test data

3. **Implement Authentication**
   - Add `getAuthToken()` method
   - Follow authentication pattern

4. **Write Test Methods**
   - Start with basic functionality test
   - Add authentication failure test
   - Add validation failure tests
   - Add integration tests if needed

5. **Validate Responses**
   - Check response codes
   - Validate JSON structure
   - Verify success/failure status

6. **Test and Debug**
   - Run tests individually
   - Fix any issues
   - Ensure all assertions pass

### Example: Creating a New Controller Test

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Api;

use App\Test\TestCase\AppTestCase;
use Cake\TestSuite\IntegrationTestTrait;

class NewControllerTest extends AppTestCase
{
    use IntegrationTestTrait;

    private const VALID_USERNAME = 'test';
    private const VALID_PASSWORD = '12345';
    private const VALID_ENTITY_ID = 1;

    protected array $fixtures = [
        'app.Users',
        'app.NewTable',
    ];

    private function getAuthToken(): string
    {
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
        return $loginData['token'];
    }

    public function testBasicFunctionality(): void
    {
        $token = $this->getAuthToken();
        
        $consoleOutput = $this->captureConsoleOutput(function () use ($token): void {
            $this->enableCsrfToken();
            $this->enableSecurityToken();
            $this->configRequest([
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $token
                ]
            ]);
            
            $this->post('/api/new/endpoint', $data);
        });
        
        $body = (string)$this->_response->getBody();
        
        $this->assertResponseCode(200);
        $this->assertContentType('application/json');
        $this->assertEmpty($consoleOutput);
        $this->assertJson($body);
        
        $responseData = json_decode($body, true);
        $this->assertNotNull($responseData);
        $this->assertTrue($responseData['success']);
    }

    public function testWithoutAuthentication(): void
    {
        $consoleOutput = $this->captureConsoleOutput(function (): void {
            $this->enableCsrfToken();
            $this->enableSecurityToken();
            $this->configRequest(['headers' => ['Accept' => 'application/json']]);
            
            $this->post('/api/new/endpoint', $data);
        });
        
        $this->assertResponseCode(401);
        $body = (string)$this->_response->getBody();
        $response = json_decode($body, true);
        
        $this->assertEmpty($consoleOutput);
        $this->assertNotNull($response);
        $this->assertFalse($response['success']);
    }
}
```

## Individual Test Suite Guides

### AuditLogsController Test Suite

**Purpose**: Tests audit logging functionality and data retrieval.

**Key Features**:
- Tests audit log data retrieval
- Validates authentication requirements
- Tests data filtering and pagination

**Test Methods**:
- `testIndexWithAuthentication()` - Tests authenticated access to audit logs
- `testIndexWithoutAuthentication()` - Tests unauthenticated access (should fail)
- `testGetAuditLogsWithFilters()` - Tests filtered audit log retrieval

**Fixtures Used**:
- `app.Users` - For authentication
- `app.AuditLogs` - Main audit log data
- `app.AuditLogDetails` - Detailed audit information

**Common Patterns**:
```php
// Test authenticated access
public function testIndexWithAuthentication(): void
{
    $token = $this->getAuthToken();
    
    $consoleOutput = $this->captureConsoleOutput(function () use ($token): void {
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->configRequest([
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token
            ]
        ]);
        
        $this->get('/api/audit-logs');
    });
    
    $this->assertResponseCode(200);
    $body = (string)$this->_response->getBody();
    $response = json_decode($body, true);
    $this->assertTrue($response['success']);
}
```

### EmployeeTemplatesController Test Suite

**Purpose**: Tests employee template management functionality.

**Key Features**:
- Template creation and management
- Field validation and structure
- Template versioning and migration
- Integration with employee data

**Test Methods**:
- `testCreateTemplateWithValidData()` - Tests template creation
- `testUpdateTemplateWithValidData()` - Tests template updates
- `testGetEmployeeTemplateFields()` - Tests field retrieval
- `testTemplateVersioningScenarios()` - Tests version management
- `testTemplateMigrationScenarios()` - Tests data migration

**Fixtures Used**:
- `app.Users` - For authentication
- `app.EmployeeTemplates` - Template data
- `app.EmployeeTemplateAnswers` - Employee data using templates

**Common Patterns**:
```php
// Test template creation
public function testCreateTemplateWithValidData(): void
{
    $token = $this->getAuthToken();
    
    $templateData = [
        'name' => 'Test Template',
        'structure' => [
            'groups' => [
                [
                    'id' => 'group_1',
                    'name' => 'Personal Information',
                    'fields' => [
                        [
                            'id' => 'field_1',
                            'label' => 'First Name',
                            'type' => 'text',
                            'required' => true
                        ]
                    ]
                ]
            ]
        ]
    ];

    $consoleOutput = $this->captureConsoleOutput(function () use ($token, $templateData): void {
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->configRequest([
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token
            ]
        ]);
        
        $this->post('/api/employee-templates/createTemplate.json', $templateData);
    });
    
    $this->assertResponseCode(200);
    $body = (string)$this->_response->getBody();
    $response = json_decode($body, true);
    $this->assertTrue($response['success']);
    $this->assertArrayHasKey('id', $response);
}
```

### JobRoleTemplatesController Test Suite

**Purpose**: Tests job role template management functionality.

**Key Features**:
- Job role template CRUD operations
- Template structure validation
- Integration with employee data
- Cross-controller data consistency

**Test Methods**:
- `testAddJobRoleFormWithValidData()` - Tests template creation
- `testUpdateJobRoleFormWithValidData()` - Tests template updates
- `testGetJobRoleFormWithValidId()` - Tests template retrieval
- `testGetJobRoleTemplate()` - Tests specific template retrieval
- `testCrossControllerDataConsistency()` - Tests data consistency

**Fixtures Used**:
- `app.Users` - For authentication
- `app.JobRoleTemplates` - Template data
- `app.EmployeeTemplateAnswers` - Employee data

**Common Patterns**:
```php
// Test template retrieval by ID
public function testGetJobRoleTemplate(): void
{
    $token = $this->getAuthToken();
    
    $consoleOutput = $this->captureConsoleOutput(function () use ($token): void {
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->configRequest([
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token
            ]
        ]);
        
        $this->get('/api/job-role-templates/getJobRoleTemplate?id=' . self::VALID_TEMPLATE_ID);
    });
    
    $this->assertResponseCode(200);
    $body = (string)$this->_response->getBody();
    $response = json_decode($body, true);
    $this->assertTrue($response['success']);
    $this->assertArrayHasKey('data', $response);
}
```

### LevelTemplatesController Test Suite

**Purpose**: Tests level template management functionality.

**Key Features**:
- Level template CRUD operations
- Template structure validation
- Integration with employee data
- Cross-controller data consistency

**Test Methods**:
- `testAddLevelTemplateWithValidData()` - Tests template creation
- `testUpdateLevelTemplateWithValidData()` - Tests template updates
- `testGetLevelTemplateWithValidId()` - Tests template retrieval
- `testGetLevelTemplate()` - Tests specific template retrieval
- `testCrossControllerDataConsistency()` - Tests data consistency

**Fixtures Used**:
- `app.Users` - For authentication
- `app.LevelTemplates` - Template data
- `app.EmployeeTemplateAnswers` - Employee data

**Common Patterns**:
```php
// Test template update
public function testUpdateLevelTemplateWithValidData(): void
{
    $token = $this->getAuthToken();
    
    $updateData = [
        'id' => self::VALID_TEMPLATE_ID,
        'name' => 'Updated Level Template',
        'structure' => [
            'groups' => [
                [
                    'id' => 'group_1',
                    'name' => 'Level Information',
                    'fields' => [
                        [
                            'id' => 'field_1',
                            'label' => 'Level Name',
                            'type' => 'text',
                            'required' => true
                        ]
                    ]
                ]
            ]
        ]
    ];

    $consoleOutput = $this->captureConsoleOutput(function () use ($token, $updateData): void {
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->configRequest([
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token
            ]
        ]);
        
        $this->post('/api/level-templates/updateLevelTemplate.json', $updateData);
    });
    
    $this->assertResponseCode(200);
    $body = (string)$this->_response->getBody();
    $response = json_decode($body, true);
    $this->assertTrue($response['success']);
}
```

### RoleLevelsController Test Suite

**Purpose**: Tests role level management functionality.

**Key Features**:
- Role level CRUD operations
- Hierarchy management
- Integration with employee data
- Cross-controller data consistency

**Test Methods**:
- `testAddRoleLevelWithValidData()` - Tests role level creation
- `testUpdateRoleLevelWithValidData()` - Tests role level updates
- `testGetRoleLevelWithValidId()` - Tests role level retrieval
- `testDeleteRoleLevelWithValidId()` - Tests role level deletion
- `testRoleLevelHierarchyConsistency()` - Tests hierarchy management

**Fixtures Used**:
- `app.Users` - For authentication
- `app.RoleLevels` - Role level data
- `app.EmployeeTemplateAnswers` - Employee data

**Common Patterns**:
```php
// Test role level creation
public function testAddRoleLevelWithValidData(): void
{
    $token = $this->getAuthToken();
    
    $roleLevelData = [
        'name' => 'Senior Developer',
        'level' => 3,
        'description' => 'Senior level developer role',
        'requirements' => [
            'experience' => '5+ years',
            'skills' => ['PHP', 'JavaScript', 'Database Design']
        ]
    ];

    $consoleOutput = $this->captureConsoleOutput(function () use ($token, $roleLevelData): void {
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->configRequest([
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token
            ]
        ]);
        
        $this->post('/api/role-levels/addRoleLevel', $roleLevelData);
    });
    
    $this->assertResponseCode(200);
    $body = (string)$this->_response->getBody();
    $response = json_decode($body, true);
    $this->assertTrue($response['success']);
    $this->assertArrayHasKey('id', $response);
}
```

### EmployeesController Test Suite

**Purpose**: Tests employee management functionality.

**Key Features**:
- Employee CRUD operations
- Template integration
- Data validation
- Cross-controller integration

**Test Methods**:
- `testAddEmployeeWithValidData()` - Tests employee creation
- `testGetEmployeeWithValidId()` - Tests employee retrieval
- `testUpdateEmployeeWithValidData()` - Tests employee updates
- `testDeleteEmployeeWithValidId()` - Tests employee deletion
- `testEmployeesJobRolesIntegration()` - Tests job role integration
- `testEmployeesScorecardsIntegration()` - Tests scorecard integration

**Fixtures Used**:
- `app.Users` - For authentication
- `app.EmployeeTemplateAnswers` - Employee data
- `app.EmployeeTemplates` - Template data
- `app.JobRoleTemplates` - Job role data
- `app.LevelTemplates` - Level data

**Common Patterns**:
```php
// Test employee creation
public function testAddEmployeeWithValidData(): void
{
    $token = $this->getAuthToken();
    
    $employeeData = [
        'template_id' => self::VALID_TEMPLATE_ID,
        'employeeUniqueId' => 'EMP-' . time(),
        'answers' => [
            'personal_info' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john.doe@company.com',
                'phone' => '+639123456789'
            ],
            'job_info' => [
                'position' => 'Software Developer',
                'department' => 'IT',
                'manager' => 'Jane Smith'
            ]
        ]
    ];

    $consoleOutput = $this->captureConsoleOutput(function () use ($token, $employeeData): void {
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->configRequest([
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token
            ]
        ]);
        
        $this->post('/api/employees/addEmployee', $employeeData);
    });
    
    $this->assertResponseCode(200);
    $body = (string)$this->_response->getBody();
    $response = json_decode($body, true);
    $this->assertTrue($response['success']);
}
```

### ScorecardsController Test Suite

**Purpose**: Tests scorecard management functionality.

**Key Features**:
- Scorecard CRUD operations
- Template integration
- Data validation
- Cross-controller integration

**Test Methods**:
- `testAddScorecardWithValidData()` - Tests scorecard creation
- `testGetScorecardDataWithValidId()` - Tests scorecard retrieval
- `testUpdateScorecardWithValidData()` - Tests scorecard updates
- `testDeleteScorecardWithValidId()` - Tests scorecard deletion
- `testTableHeadersWithValidAuthentication()` - Tests table headers
- `testScorecardHierarchyConsistency()` - Tests hierarchy management

**Fixtures Used**:
- `app.Users` - For authentication
- `app.ScorecardTemplates` - Template data
- `app.Scorecards` - Scorecard data
- `app.ScorecardEvaluations` - Evaluation data

**Common Patterns**:
```php
// Test scorecard creation
public function testAddScorecardWithValidData(): void
{
    $token = $this->getAuthToken();
    
    $scorecardData = [
        'template_id' => self::VALID_TEMPLATE_ID,
        'scorecardUniqueId' => 'SC-' . time(),
        'answers' => [
            'scorecard_info' => [
                'scorecard_name' => 'Q1 2024 Scorecard',
                'department' => 'Engineering',
                'quarter' => 'Q1 2024'
            ],
            'metrics' => [
                'code_quality' => 85,
                'delivery_speed' => 90,
                'team_collaboration' => 88
            ]
        ]
    ];

    $consoleOutput = $this->captureConsoleOutput(function () use ($token, $scorecardData): void {
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->configRequest([
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token
            ]
        ]);
        
        $this->post('/api/scorecards/addScorecard', $scorecardData);
    });
    
    $this->assertResponseCode(200);
    $body = (string)$this->_response->getBody();
    $response = json_decode($body, true);
    $this->assertTrue($response['success']);
}
```

## Test Suite Specific Considerations

### Template Controllers (EmployeeTemplates, JobRoleTemplates, LevelTemplates)
- **Structure Validation**: Always test template structure validation
- **Versioning**: Test template versioning scenarios
- **Migration**: Test data migration between template versions
- **Cross-Controller**: Test data consistency across controllers

### Data Controllers (Employees, Scorecards)
- **Template Integration**: Test integration with template controllers
- **Data Validation**: Test field validation and requirements
- **CRUD Operations**: Test all CRUD operations thoroughly
- **Integration**: Test cross-controller data consistency

### Audit Controllers (AuditLogs)
- **Authentication**: Test authentication requirements
- **Data Filtering**: Test data filtering and pagination
- **Access Control**: Test access control and permissions

## Conclusion

Following this guide will ensure that:
- Tests are properly authenticated
- Responses are correctly validated
- Test isolation is maintained
- Common issues are avoided
- New tests integrate seamlessly with existing test suites

The key is to always follow the authentication pattern and use `captureConsoleOutput()` for all API calls. This ensures consistent behavior and proper test execution.

Each test suite has its own specific patterns and considerations, but they all follow the same fundamental authentication and response validation patterns outlined in this guide.
