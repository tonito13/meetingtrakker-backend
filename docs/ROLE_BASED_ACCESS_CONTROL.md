# Role-Based Access Control (RBAC) Analysis

## Overview

This document provides a comprehensive analysis of the role-based access control system in ScorecardTrakker, detailing what different user roles can and cannot access.

## System User Roles

Based on the database analysis, the system supports the following user roles:

1. **`admin`** - Administrator role with full access
2. **`User`** - Standard user role with limited access
3. **`support`** - Support role (currently not explicitly handled in frontend/backend)

The role is stored in the `users` table in the `system_user_role` field and is returned in the JWT token as `user_role` or `system_user_role`.

---

## Frontend Access Control

### Menu Navigation (MainDrawer.jsx)

**Admin Users:**
- ✅ Full access to all menu items:
  - Dashboard
  - Roles (Role Levels, All Roles)
  - Employee Management (Employees)
  - Scorecard Management (Scorecards, My Scorecards)
  - Audit and History
  - Custom Fields (Level Template Settings, Job Roles Template Settings, Employee Template Settings, Scorecard Template Settings)
  - Settings (Account)

**Non-Admin Users (User, support):**
- ✅ Limited access - Only these menu items:
  - Dashboard
  - Organizational Chart (if exists)
  - Settings (Account)
- ❌ **Cannot access:**
  - Roles section
  - Employee Management
  - Scorecard Management
  - Audit and History
  - Custom Fields/Template Settings

### Dashboard (Dashboard.jsx)

**Admin Users:**
- ✅ Can see "Add Employee" quick action button
- ✅ Full access to all dashboard metrics and features

**Non-Admin Users:**
- ❌ Cannot see "Add Employee" quick action button
- ✅ Can view dashboard metrics (Total Employees, Job Roles, etc.)

### Employees Page (Employees.jsx)

**Admin Users:**
- ✅ Can see "ADD EMPLOYEE" button
- ✅ Can see "IMPORT EMPLOYEES" button
- ✅ Full access to employee management features

**Non-Admin Users:**
- ❌ Cannot see "ADD EMPLOYEE" button
- ❌ Cannot see "IMPORT EMPLOYEES" button
- ⚠️ **Note:** Non-admin users can still access the Employees page if they navigate directly to the URL, but they won't see the action buttons

---

## Backend Access Control

### Current State: **Authentication-Only, No Role-Based Authorization**

**Important Finding:** The backend currently implements **authentication checks only**. There are **no role-based authorization policies** implemented in the API controllers.

### What This Means:

1. **All authenticated users** (regardless of role) can access all API endpoints if they:
   - Have a valid JWT token
   - Pass the authentication check (`$authResult->isValid()`)

2. **No role checks** are performed in backend controllers for:
   - Creating employees
   - Importing employees
   - Managing job roles
   - Managing role levels
   - Managing scorecards
   - Managing templates
   - Viewing audit logs

### Authentication Pattern Used:

All API controllers follow this pattern:

```php
// Authentication check
$authResult = $this->Authentication->getResult();
if (!$authResult || !$authResult->isValid()) {
    return $this->response
        ->withStatus(401)
        ->withType('application/json')
        ->withStringBody(json_encode([
            'success' => false,
            'message' => 'Unauthorized access',
        ]));
}
```

**This only checks if the user is authenticated, NOT their role.**

---

## Security Gap Analysis

### ⚠️ Critical Security Gaps:

1. **Frontend-Only Protection:**
   - Role-based restrictions are **only enforced in the frontend**
   - Backend API endpoints are accessible to any authenticated user
   - A non-admin user could bypass frontend restrictions by:
     - Making direct API calls
     - Using browser developer tools
     - Using API testing tools (Postman, curl, etc.)

2. **No Authorization Policies:**
   - The CakePHP Authorization plugin is loaded but **not used**
   - No authorization policies exist in `src/Policy/` directory
   - No `$this->Authorization->authorize()` calls in controllers

3. **Missing Role Checks:**
   - No validation that only admins can:
     - Create/import employees
     - Modify templates
     - Access audit logs
     - Perform administrative actions

---

## Recommended Access Matrix

### Admin Users (`admin`)

**Should Have Access To:**
- ✅ All dashboard features
- ✅ Create, read, update, delete employees
- ✅ Import employees from orgtrakker
- ✅ Manage job roles (CRUD)
- ✅ Manage role levels (CRUD)
- ✅ Manage scorecards (CRUD)
- ✅ Manage all templates (Employee, Job Role, Level, Scorecard)
- ✅ View audit logs and history
- ✅ Access all settings

**Backend Endpoints:**
- All `/api/employees/*` endpoints
- All `/api/job-roles/*` endpoints
- All `/api/role-levels/*` endpoints
- All `/api/scorecards/*` endpoints
- All `/api/*-templates/*` endpoints
- All `/api/audit-logs/*` endpoints
- All `/api/companies/*` endpoints (company mapping)

### Standard Users (`User`)

**Should Have Access To:**
- ✅ View dashboard (read-only metrics)
- ✅ View own employee record
- ✅ View own scorecards
- ✅ View job roles (read-only)
- ✅ View role levels (read-only)
- ✅ View employees list (read-only)
- ✅ Access account settings

**Should NOT Have Access To:**
- ❌ Create/edit/delete employees
- ❌ Import employees
- ❌ Create/edit/delete job roles
- ❌ Create/edit/delete role levels
- ❌ Create/edit/delete scorecards (except own)
- ❌ Modify templates
- ❌ View audit logs
- ❌ Company mapping management

**Backend Endpoints:**
- ✅ GET `/api/employees/table-headers.json` (read-only)
- ✅ GET `/api/employees/getEmployees.json` (read-only)
- ✅ GET `/api/employees/getEmployeeData.json` (own record only)
- ✅ GET `/api/job-roles/table-headers.json` (read-only)
- ✅ GET `/api/job-roles/getJobRoles.json` (read-only)
- ✅ GET `/api/role-levels/table-headers.json` (read-only)
- ✅ GET `/api/role-levels/getRoleLevels.json` (read-only)
- ✅ GET `/api/dashboard/getDashboardData.json`
- ❌ All POST/PUT/DELETE endpoints
- ❌ All template management endpoints
- ❌ All audit log endpoints
- ❌ All company mapping endpoints

### Support Users (`support`)

**Current Status:** Not explicitly handled. Should be defined based on business requirements.

**Recommendation:**
- Similar to `User` role but potentially with:
  - Read access to audit logs
  - Limited write access for troubleshooting
  - No template modification access

---

## Implementation Recommendations

### 1. Implement Backend Authorization Policies

Create authorization policies in `src/Policy/`:

```php
// src/Policy/EmployeePolicy.php
class EmployeePolicy
{
    public function canCreate($user, $employee)
    {
        return $user->system_user_role === 'admin';
    }
    
    public function canImport($user)
    {
        return $user->system_user_role === 'admin';
    }
    
    public function canView($user, $employee)
    {
        // Users can view their own record, admins can view all
        return $user->system_user_role === 'admin' || 
               $user->id === $employee->user_id;
    }
}
```

### 2. Add Authorization Checks to Controllers

```php
// In EmployeesController
public function addEmployee()
{
    $authResult = $this->Authentication->getResult();
    if (!$authResult || !$authResult->isValid()) {
        return $this->response->withStatus(401)->withStringBody(...);
    }
    
    $user = $authResult->getData();
    
    // Add authorization check
    if (!$this->Authorization->can($user, 'create', 'Employee')) {
        return $this->response
            ->withStatus(403)
            ->withStringBody(json_encode([
                'success' => false,
                'message' => 'Insufficient permissions'
            ]));
    }
    
    // Continue with employee creation...
}
```

### 3. Create Helper Method in ApiController

```php
// In ApiController
protected function requireAdmin()
{
    $authResult = $this->Authentication->getResult();
    if (!$authResult || !$authResult->isValid()) {
        return $this->response->withStatus(401)->withStringBody(...);
    }
    
    $user = $authResult->getData();
    $userRole = is_object($user) ? $user->system_user_role : $user['system_user_role'] ?? null;
    
    if (strtolower($userRole) !== 'admin') {
        return $this->response
            ->withStatus(403)
            ->withType('application/json')
            ->withStringBody(json_encode([
                'success' => false,
                'message' => 'Admin access required'
            ]));
    }
    
    return null; // No error, proceed
}
```

### 4. Protect Sensitive Endpoints

Add role checks to:
- `EmployeesController::importAllEmployeesFromOrgtrakker()`
- `EmployeesController::addEmployee()`
- `EmployeesController::updateEmployee()`
- `EmployeesController::deleteEmployee()`
- All template management endpoints
- `AuditLogsController::getAuditLogs()`
- `CompaniesController::*` (company mapping)

---

## Current Access Summary

| Feature | Admin | User | Support |
|---------|-------|------|---------|
| **Frontend Menu Access** |
| Dashboard | ✅ | ✅ | ✅ |
| Roles | ✅ | ❌ | ❌ |
| Employee Management | ✅ | ❌ | ❌ |
| Scorecard Management | ✅ | ❌ | ❌ |
| Audit & History | ✅ | ❌ | ❌ |
| Custom Fields | ✅ | ❌ | ❌ |
| Settings | ✅ | ✅ | ✅ |
| **Dashboard Actions** |
| Add Employee Button | ✅ | ❌ | ❌ |
| View Metrics | ✅ | ✅ | ✅ |
| **Employees Page** |
| Add Employee Button | ✅ | ❌ | ❌ |
| Import Employees Button | ✅ | ❌ | ❌ |
| View Employees | ✅ | ⚠️* | ⚠️* |
| **Backend API** |
| All GET endpoints | ✅ | ✅** | ✅** |
| All POST/PUT/DELETE | ✅ | ✅** | ✅** |
| Import endpoints | ✅ | ✅** | ✅** |
| Template endpoints | ✅ | ✅** | ✅** |
| Audit log endpoints | ✅ | ✅** | ✅** |

\* Can access if navigates directly to URL (frontend restriction only)  
\*\* **SECURITY GAP:** Backend does not enforce role-based restrictions

---

## Conclusion

The current implementation has **significant security gaps** where role-based access control is only enforced in the frontend. This allows authenticated non-admin users to bypass restrictions by making direct API calls.

**Immediate Action Required:**
1. Implement backend authorization policies
2. Add role checks to all administrative endpoints
3. Test that non-admin users cannot perform admin actions via direct API calls
4. Document the complete access matrix for all roles

**Priority Endpoints to Protect:**
1. Employee import endpoints
2. Employee create/update/delete endpoints
3. Template management endpoints
4. Audit log endpoints
5. Company mapping endpoints

