# Employee Reporting Relationships Fix

## Overview
This migration fixes the employee reporting relationships system by adding the missing `report_to_employee_unique_id` field to the `employee_template_answers` table.

## Problem
The employee system was not properly storing or retrieving reporting relationships because:
1. The `employee_template_answers` table was missing the `report_to_employee_unique_id` field
2. The `EmployeeTemplateAnswer` entity didn't have the field defined
3. The `saveEmployeeAnswers` method wasn't saving the reporting relationship
4. The `getReportingRelationships` API endpoint was missing

## Solution
This fix includes:
1. **Database Migration**: Adds `report_to_employee_unique_id` field to both central and company databases
2. **Entity Update**: Updates `EmployeeTemplateAnswer` entity to include the new field
3. **Controller Updates**: Updates `saveEmployeeAnswers` and `updateEmployee` methods to save reporting relationships
4. **New API Endpoint**: Adds `getReportingRelationships` endpoint for frontend integration

## Migration Files
- `20241201_000003_add_report_to_employee_unique_id_to_employee_template_answers.php` - Central database
- `20241201_000004_add_report_to_employee_unique_id_to_employee_template_answers_company.php` - Company databases

## Running the Migrations

### Central Database
```bash
cd scorecardtrakker-backend
vendor/bin/phinx migrate -c config/phinx.php -e default
```

### Company Databases
```bash
# For each company database (e.g., 200001, 200002, etc.)
vendor/bin/phinx migrate -c config/phinx.php -e client_200001
vendor/bin/phinx migrate -c config/phinx.php -e client_200002
# ... repeat for each company
```

## Verification

After running migrations, verify the field was added:

```sql
-- Check central database
\c scorecardtrakker
\d employee_template_answers

-- Check company database
\c 200001
\d employee_template_answers
```

You should see the new `report_to_employee_unique_id` field in the table structure.

## API Endpoints

### New Endpoint
- `GET /api/employees/getReportingRelationships.json` - Get reporting relationships for job roles

### Updated Endpoints
- `POST /api/employees/addEmployee.json` - Now saves reporting relationships
- `POST /api/employees/updateEmployee.json` - Now updates reporting relationships
- `POST /api/employees/getEmployeeData.json` - Now returns reporting relationship data

## Frontend Integration

The frontend components (`AddEmployee.jsx`, `EditEmployee.jsx`) already have the logic to:
1. Fetch reporting relationships via the new API endpoint
2. Filter employees based on job role reporting relationships
3. Display reporting relationships in the employee view

## Testing

After applying the fix:
1. Create a new employee with a reporting relationship
2. Verify the relationship is saved in the database
3. Edit the employee and change the reporting relationship
4. Verify the relationship is updated
5. View the employee and confirm the reporting relationship displays correctly

## Rollback

If you need to rollback the migrations:

```bash
# Rollback central database
vendor/bin/phinx rollback -c config/phinx.php -e default

# Rollback company databases
vendor/bin/phinx rollback -c config/phinx.php -e client_200001
vendor/bin/phinx rollback -c config/phinx.php -e client_200002
```

## Support

If you encounter issues:
1. Check the migration logs
2. Verify database connections
3. Ensure all prerequisites are met
4. Check the AI Codebase Guide for additional information
