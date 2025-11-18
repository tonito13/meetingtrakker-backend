# Audit Log Connection Verification Report

## Summary
This document verifies that all audit logging operations use the correct company-specific database connections.

## ✅ Verified Components

### 1. AuditService Constructor
**File:** `src/Service/AuditService.php`

**Status:** ✅ CORRECT

The `AuditService` constructor:
- Accepts `$companyId` parameter
- Calls `getConnection($companyId)` which returns:
  - `'default'` connection for company ID `'default'`
  - `'client_' . $companyId` connection for other company IDs
- Creates table instances with the correct connection:
  ```php
  $this->auditLogsTable = $locator->get('AuditLogs', ['connection' => $connection]);
  $this->auditLogDetailsTable = $locator->get('AuditLogDetails', ['connection' => $connection]);
  ```

### 2. AuditHelper Methods
**File:** `src/Helper/AuditHelper.php`

**Status:** ✅ CORRECT

All helper methods extract `company_id` from `$userData` and pass it to `AuditService`:

- `logScorecardAction()` - Line 56: `$companyId = $userData['company_id'] ?? 'default';`
- `logEmployeeAction()` - Line 109: `$companyId = $userData['company_id'] ?? 'default';`
- `logAuthAction()` - Line 153: `$companyId = $userData['company_id'] ?? 'default';`
- `logAction()` - Line 196: `$companyId = $userData['company_id'] ?? 'default';`
- `logRoleLevelAction()` - Line 612: `$companyId = $userData['company_id'] ?? 'default';`
- `logJobRoleAction()` - Line 678: `$companyId = $userData['company_id'] ?? 'default';`

All methods then instantiate: `$auditService = new AuditService($companyId);`

### 3. extractUserData Method
**File:** `src/Helper/AuditHelper.php` (Line 374)

**Status:** ✅ CORRECT

The `extractUserData()` method:
- Extracts `company_id` from authentication result
- Falls back to `'default'` if not found
- Returns user data array with `company_id` included:
  ```php
  'company_id' => (string)($data->company_id ?? 'default'),
  ```

### 4. AuditMiddleware
**File:** `src/Middleware/AuditMiddleware.php`

**Status:** ✅ CORRECT (with note)

The middleware:
- Extracts company ID using `extractCompanyId()` method (Line 68)
- Attempts to get from:
  1. `$user->company_id` (from authenticated user)
  2. Request body `$data['company_id']`
  3. Query parameters `$queryParams['company_id']`
- If company ID is found, instantiates: `$auditService = new AuditService($companyId);` (Line 74)
- **Note:** If company ID is not found, it returns `null` and skips logging (Line 70-72). This is intentional to avoid logging requests without company context.

### 5. AuditLogsController
**File:** `src/Controller/Api/AuditLogsController.php`

**Status:** ✅ CORRECT

All methods:
- Extract company ID using `getCompanyId($authResult)` method
- Pass company ID to `AuditService` constructor:
  ```php
  $companyId = (string)$this->getCompanyId($authResult);
  $this->auditService = new AuditService($companyId);
  ```

### 6. Controller Usage
**Status:** ✅ CORRECT

Controllers that use `AuditHelper` methods:
- **ScorecardsController**: Uses `extractUserData()` which includes `company_id`
- **EmployeesController**: Uses `extractUserData()` which includes `company_id`
- **RoleLevelsController**: Passes `$userData` with `company_id` extracted from auth
- **JobRolesController**: Uses helper methods that extract `company_id` from `$userData`

## Connection Flow

```
Request → Controller/AuditHelper
  ↓
Extract company_id from:
  - Authentication result (user->company_id)
  - OR userData['company_id']
  - OR fallback to 'default'
  ↓
AuditService($companyId)
  ↓
getConnection($companyId)
  ↓
ConnectionManager::get('client_' . $companyId)
  ↓
Table instances created with correct connection
  ↓
Audit logs saved to correct company database
```

## Database Connection Mapping

- Company ID: `'default'` → Connection: `'default'` → Database: `workmatica`
- Company ID: `'300000'` → Connection: `'client_300000'` → Database: `scorecardtrakker_300000`
- Company ID: `'XXXXXX'` → Connection: `'client_XXXXXX'` → Database: `scorecardtrakker_XXXXXX`

## Verification Result

✅ **ALL AUDIT LOGGING OPERATIONS USE CORRECT COMPANY-SPECIFIC CONNECTIONS**

All components:
1. Extract `company_id` from authentication or user data
2. Pass `company_id` to `AuditService` constructor
3. `AuditService` uses the correct database connection
4. Audit logs are saved to the correct company-specific database

## Potential Edge Cases

1. **AuditMiddleware**: If `company_id` cannot be extracted, logging is skipped. This is intentional for requests without company context.

2. **Default Fallback**: If `company_id` is not found, it defaults to `'default'`, which uses the `workmatica` database. This should only happen for system-level operations.

3. **Table Instance Caching**: The `AuditService` constructor removes existing table instances before creating new ones to avoid connection conflicts. This ensures each service instance uses the correct connection.

## Recommendations

1. ✅ No changes needed - all audit logging operations are correctly configured
2. Consider adding logging when `company_id` defaults to `'default'` to track unexpected cases
3. Consider adding validation to ensure `company_id` is always present in authenticated requests

