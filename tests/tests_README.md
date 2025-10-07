# ScorecardTrakker Testing Guide

This comprehensive guide covers the complete testing framework for the ScorecardTrakker application, including implementation patterns, fixture management, and best practices.

## Overview

The ScorecardTrakker test suite provides comprehensive coverage of all API controllers, models, and business logic with a focus on security, performance, and integration testing.

## Test Architecture

### Test Database Configuration
- **Test Database**: `test` connection in `config/datasources.php`
- **Company Test Database**: `client_test` for multi-tenant testing
- **Isolation**: Each test runs in a transaction that's rolled back after completion
- **Fixtures**: Pre-populated test data for consistent testing

### Authentication Testing Pattern
All API controller tests follow a consistent authentication pattern:

```php
protected function setUp(): void
{
    parent::setUp();
    Configure::write('debug', true); // Enable debug mode for testing
}

protected function getAuthToken()
{
    // Generate JWT token for testing
    $payload = [
        'sub' => 'testuser',
        'company_id' => '200001',
        'exp' => time() + 3600
    ];
    return JWT::encode($payload, $this->getPrivateKey(), 'RS256');
}

protected function getCompanyId($authResult)
{
    // Helper method to extract company_id from authentication result
    $data = $authResult->getData();
    
    if (is_object($data)) {
        if (isset($data->company_id)) {
            return $data->company_id;
        }
        $data = (array) $data;
    }
    
    if (is_array($data) && isset($data['company_id'])) {
        return $data['company_id'];
    }
    
    return '200001'; // Default company ID
}
```

## Test Files Structure

### Controller Tests (`TestCase/Controller/Api/`)

#### Core Controller Tests
- **`UsersControllerTest.php`** - User authentication and management
  - Login/logout functionality
  - JWT token generation and validation
  - User CRUD operations
  - Authentication edge cases

- **`EmployeesControllerTest.php`** - Employee management (2811 lines)
  - Complete CRUD operations
  - Template-based form handling
  - File upload functionality
  - Search and pagination
  - Multi-tenant data isolation
  - **Comprehensive Integration Tests**: Cross-controller data validation

- **`JobRolesControllerTest.php`** - Job role management (1005 lines)
  - Job role CRUD operations
  - Unique ID generation (jr-YYYYMMDD-XXXXX)
  - Template integration
  - Validation and error handling

- **`RoleLevelsControllerTest.php`** - Role level management (908 lines)
  - Organizational hierarchy management
  - Level template operations
  - **100% Test Coverage**: 73/73 tests passing
  - **Security Tests**: XSS protection, input sanitization
  - **Performance Tests**: Concurrent operations, load testing

- **`ScorecardsControllerTest.php`** - Scorecard management (930+ lines)
  - Complete scorecard CRUD operations
  - Template-based form generation
  - Search, pagination, and sorting
  - Parent-child scorecard relationships
  - Username-based assignment system

#### Template Controller Tests
- **`JobRoleTemplatesControllerTest.php`** - Job role template management
  - Template CRUD operations
  - Structure validation
  - **Comprehensive Coverage**: Edge cases, security, performance
  - **Cross-Controller Tests**: Integration with JobRolesController

- **`ScorecardTemplatesControllerTest.php`** - Scorecard template management
  - Template structure management
  - Default field validation
  - **Comprehensive Coverage**: All edge cases and security tests
  - **Integration Tests**: Cross-controller validation

- **`LevelTemplatesControllerTest.php`** - Level template management
  - Level template operations
  - **Renamed from**: `TemplatesSettingsControllerTest.php`
  - **Comprehensive Coverage**: Security, performance, integration tests

- **`EmployeeTemplatesControllerTest.php`** - Employee template management
  - Employee template CRUD operations
  - Field configuration testing
  - **Comprehensive Coverage**: All edge cases and security tests

#### Specialized Controller Tests
- **`ScorecardEvaluationsControllerTest.php`** - Scorecard evaluation system
  - Evaluation CRUD operations
  - Period type locking
  - Grade validation

- **`AuditLogsControllerTest.php`** - Audit logging system
  - Audit log retrieval
  - Field change tracking
  - Multi-tenant audit isolation

- **`DashboardControllerTest.php`** - Dashboard data aggregation
  - Statistics calculation
  - Data aggregation testing

### Fixtures (`Fixture/`)

#### Core Fixtures
- **`UsersFixture.php`** - User test data
  - Test users with different roles
  - Company associations
  - Authentication data

- **`EmployeesFixture.php`** - Employee test data
  - Sample employee records
  - Template-based data structure
  - File attachments

- **`JobRolesFixture.php`** - Job role test data
  - Sample job roles
  - Unique ID patterns
  - Template associations

- **`RoleLevelsFixture.php`** - Role level test data
  - Organizational hierarchy
  - Level relationships
  - Template data

#### Template Fixtures
- **`EmployeeTemplatesFixture.php`** - Employee template definitions
- **`JobRoleTemplatesFixture.php`** - Job role template definitions
- **`LevelTemplatesFixture.php`** - Level template definitions
- **`ScorecardTemplatesFixture.php`** - Scorecard template definitions

#### Data Fixtures
- **`ScorecardTemplateAnswersFixture.php`** - Scorecard data responses
- **`ScorecardEvaluationsFixture.php`** - Scorecard evaluation data
- **`AuditLogsFixture.php`** - Audit log test data

## Testing Patterns and Best Practices

### 1. Authentication Testing
```php
public function testAuthenticatedEndpoint()
{
    $token = $this->getAuthToken();
    $this->configRequest([
        'headers' => ['Authorization' => 'Bearer ' . $token]
    ]);
    
    $this->get('/api/endpoint.json');
    $this->assertResponseOk();
}
```

### 2. Response Body Testing
```php
public function testResponseStructure()
{
    $this->get('/api/endpoint.json');
    $response = (string)$this->_response->getBody(); // Correct method
    $data = json_decode($response, true);
    
    $this->assertTrue($data['success']);
    $this->assertArrayHasKey('data', $data);
}
```

### 3. Multi-Request Testing
```php
public function testMultipleRequests()
{
    $token = $this->getAuthToken();
    
    // First request
    $this->configRequest([
        'headers' => ['Authorization' => 'Bearer ' . $token]
    ]);
    $this->get('/api/first.json');
    $this->assertResponseOk();
    
    // Second request (reconfigure auth)
    $this->configRequest([
        'headers' => ['Authorization' => 'Bearer ' . $token]
    ]);
    $this->get('/api/second.json');
    $this->assertResponseOk();
}
```

### 4. Security Testing
```php
public function testXSSProtection()
{
    $maliciousData = [
        'field' => '<script>alert("xss")</script>',
        'description' => '"><img src=x onerror=alert(1)>'
    ];
    
    $this->post('/api/endpoint.json', $maliciousData);
    $response = (string)$this->_response->getBody();
    
    // Verify XSS payloads are sanitized
    $this->assertStringNotContainsString('<script>', $response);
    $this->assertStringNotContainsString('onerror=', $response);
}
```

### 5. Performance Testing
```php
public function testConcurrentOperations()
{
    $token = $this->getAuthToken();
    $promises = [];
    
    for ($i = 0; $i < 10; $i++) {
        $this->configRequest([
            'headers' => ['Authorization' => 'Bearer ' . $token]
        ]);
        
        $startTime = microtime(true);
        $this->get('/api/endpoint.json');
        $endTime = microtime(true);
        
        $this->assertLessThan(1.0, $endTime - $startTime);
    }
}
```

## Critical Testing Fixes Applied

### 1. Authentication Helper Method
**Issue**: Direct access to `$authResult->getData()->company_id` causing "Undefined property" errors
**Solution**: Implemented `getCompanyId()` helper method in all controllers and test classes

### 2. Response Body Retrieval
**Issue**: `$this->_response->getBody()->getContents()` returning empty strings
**Solution**: Changed to `(string)$this->_response->getBody()` for proper response parsing

### 3. Method Naming Standardization
**Issue**: Inconsistent method names (`getValidToken()` vs `getAuthToken()`)
**Solution**: Standardized to `getAuthToken()` across all test classes

### 4. Debug Configuration
**Issue**: `Configure::write('debug', 1)` causing configuration errors
**Solution**: Changed to `Configure::write('debug', true)` for proper boolean values

### 5. Multi-Request Authentication
**Issue**: Tests making multiple requests losing authentication
**Solution**: Reconfigure authentication headers before each request

## Test Categories

### 1. Basic CRUD Operations
- Create, Read, Update, Delete functionality
- Data validation and business rules
- Error handling and edge cases

### 2. Authentication and Authorization
- JWT token validation
- Company-based data isolation
- Permission-based access control

### 3. Security Testing
- XSS protection and input sanitization
- SQL injection prevention
- CSRF protection validation
- Input validation and sanitization

### 4. Integration Testing
- Cross-controller data validation
- Template system integration
- Multi-tenant data isolation
- File upload and management

### 5. Performance Testing
- Concurrent operation handling
- Load testing with multiple requests
- Memory usage optimization
- Response time validation

### 6. Edge Case Testing
- Invalid input handling
- Boundary value testing
- Error condition handling
- Network failure simulation

### 7. Audit and Logging
- Audit log creation and retrieval
- Field change tracking
- User action logging
- Data integrity validation

## Running Tests

### Individual Test Execution
```bash
# Run specific test class
vendor/bin/phpunit tests/TestCase/Controller/Api/UsersControllerTest.php

# Run specific test method
vendor/bin/phpunit --filter testLogin tests/TestCase/Controller/Api/UsersControllerTest.php
```

### Complete Test Suite
```bash
# Run all tests
vendor/bin/phpunit

# Run with coverage report
vendor/bin/phpunit --coverage-html coverage/
```

### Test Database Setup
```bash
# Ensure test database exists
mysql -u root -p -e "CREATE DATABASE scorecardtrakker_test;"

# Run migrations for test database
vendor/bin/cake migrations migrate -e test
```

## Test Data Management

### Fixture Loading
```php
protected $fixtures = [
    'app.Users',
    'app.Employees',
    'app.JobRoles',
    'app.RoleLevels'
];
```

### Dynamic Test Data
```php
public function testWithDynamicData()
{
    $testData = [
        'name' => 'Test Employee ' . uniqid(),
        'email' => 'test' . uniqid() . '@example.com',
        'company_id' => '200001'
    ];
    
    $this->post('/api/employees/add.json', $testData);
    $this->assertResponseOk();
}
```

## Debugging Tests

### Debug Output
```php
public function testWithDebugging()
{
    $this->get('/api/endpoint.json');
    
    // Debug response
    $response = (string)$this->_response->getBody();
    debug($response);
    
    // Debug specific data
    $data = json_decode($response, true);
    debug($data['data']);
}
```

### Error Logging
```php
public function testWithLogging()
{
    // Enable error logging
    Configure::write('debug', true);
    
    $this->get('/api/endpoint.json');
    
    // Check logs
    $this->assertFileExists(LOGS . 'debug.log');
}
```

## Test Maintenance

### Adding New Tests
1. Follow existing naming conventions
2. Use proper fixture loading
3. Implement authentication patterns
4. Include security and edge case testing
5. Add comprehensive assertions

### Updating Existing Tests
1. Maintain backward compatibility
2. Update assertions for new response formats
3. Ensure all test methods pass
4. Update documentation

### Test Data Cleanup
1. Use transactions for test isolation
2. Clean up temporary files
3. Reset configuration after tests
4. Remove test-specific data

## Performance Considerations

### Test Execution Speed
- Use transactions for fast rollback
- Minimize database operations
- Cache frequently used data
- Parallel test execution where possible

### Memory Management
- Clean up large objects after tests
- Use unset() for memory-intensive operations
- Monitor memory usage in long-running tests

### Database Optimization
- Use appropriate indexes for test queries
- Minimize test data volume
- Use efficient query patterns

This comprehensive testing framework ensures the reliability, security, and performance of the ScorecardTrakker application while providing clear patterns for future test development.
