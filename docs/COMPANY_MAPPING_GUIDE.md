# Company ID Mapping System Guide

## Overview

The Company ID Mapping System allows different services (orgtrakker, scorecardtrakker, skiltrakker, tickettrakker) to map company IDs across systems. This enables consistent company identification during cross-service imports.

For example:
- "Test Company" has ID `100000` in orgtrakker
- "Test Company" has ID `300000` in scorecardtrakker
- The mapping system allows imports to automatically use the correct company ID

## Architecture

### Database Tables

All mapping tables are stored in the main `workmatica` database:

1. **`companies`** - Stores company information per system
   - One record per company per service
   - Fields: `company_id`, `system_product_name`, `name`, etc.

2. **`client_company_relationships`** - Maps company IDs between systems
   - Fields: `company_id_from`, `company_id_to`, `relationship_type`, `status`
   - Supports bidirectional mappings

3. **`user_company_mappings`** - Maps users across systems (for future use)
   - Fields: `user_id`, `username`, `mapped_company_id`, `source_company_id`, `system_type`

### Service Class

**`CompanyMappingService`** - Handles all mapping logic
- `getMappedCompanyId()` - Get mapped company ID for target system
- `getOrgtrakkerCompanyId()` - Helper for orgtrakker → scorecardtrakker
- `getScorecardtrakkerCompanyId()` - Helper for scorecardtrakker → orgtrakker
- `createCompanyMapping()` - Create new mappings

## How It Works

### Company ID Resolution Flow

1. User imports from orgtrakker in scorecardtrakker (company_id 300000)
2. System looks up: "What is the orgtrakker company_id for scorecardtrakker 300000?"
3. Finds mapping in `client_company_relationships` table
4. Uses the mapped orgtrakker company_id (e.g., 100000) for queries

### Example Flow

```
ScorecardTrakker Import Request (company_id: 300000)
    ↓
CompanyMappingService.getOrgtrakkerCompanyIdFromScorecardtrakker(300000)
    ↓
Query: client_company_relationships WHERE company_id_to = 300000
    ↓
Result: company_id_from = 100000 (orgtrakker)
    ↓
Use orgtrakker company_id 100000 for all import queries
```

## Setting Up Mappings

### Step 1: Run Database Migrations

```bash
cd scorecardtrakker-backend
vendor/bin/phinx migrate -c config/phinx.php -e default
```

This creates the three mapping tables in the `workmatica` database.

### Step 2: Create Company Records

For each company, create records in the `companies` table for each system:

```sql
-- Orgtrakker company
INSERT INTO companies (company_id, company_type, company_status, code, email, maximum_users, name, system_product_name, deleted, created, modified)
VALUES (100000, 'principal', 'active', 'TC01', 'test@test.com', 100, 'Test Company', 'orgtrakker', false, NOW(), NOW());

-- ScorecardTrakker company
INSERT INTO companies (company_id, company_type, company_status, code, email, maximum_users, name, system_product_name, deleted, created, modified)
VALUES (300000, 'principal', 'active', 'TC02', 'test@test.com', 100, 'Test Company', 'scorecardtrakker', false, NOW(), NOW());
```

### Step 3: Create Company Mapping

Create a relationship between the two company IDs:

```sql
-- Map orgtrakker 100000 → scorecardtrakker 300000
INSERT INTO client_company_relationships (
    company_id_from, company_id_to, relationship_type, status, is_primary,
    start_date, end_date, notes, deleted, created_at, updated_at
) VALUES (
    100000, 300000, 'affiliate', 'active', true,
    CURRENT_DATE, NULL, 
    'Mapping: Test Company - Orgtrakker to ScorecardTrakker',
    false, NOW(), NOW()
);
```

### Step 4: Run Initial Data Script (Optional)

```bash
php database/migrations/data/create_initial_company_mappings.php
```

This script creates example mappings for "Test Company".

## Using the API

### Get All Mappings

```http
GET /api/companies/mappings.json
Authorization: Bearer <token>
```

Response:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "company_id_from": 100000,
      "company_id_to": 300000,
      "company_from_name": "Test Company Orgtrakker",
      "company_from_system": "orgtrakker",
      "company_to_name": "Test Company ScorecardTrakker",
      "company_to_system": "scorecardtrakker",
      "relationship_type": "affiliate",
      "status": "active"
    }
  ],
  "count": 1
}
```

### Create New Mapping

```http
POST /api/companies/mappings.json
Authorization: Bearer <token>
Content-Type: application/json

{
  "company_id_from": 100000,
  "company_id_to": 300000,
  "system_from": "orgtrakker",
  "system_to": "scorecardtrakker",
  "relationship_type": "affiliate"
}
```

### Get Mapped Company ID

```http
GET /api/companies/mapped-id.json?source_company_id=300000&source_system=scorecardtrakker&target_system=orgtrakker
Authorization: Bearer <token>
```

Response:
```json
{
  "success": true,
  "data": {
    "source_company_id": 300000,
    "source_system": "scorecardtrakker",
    "target_system": "orgtrakker",
    "mapped_company_id": 100000,
    "found": true
  }
}
```

## Import Functions

All import functions in `EmployeesController` now automatically use company ID mapping:

- `importAllEmployeesFromOrgtrakker()` - Uses mapped company ID
- `importAllRoleLevelsFromOrgtrakker()` - Uses mapped company ID
- `importAllJobRolesFromOrgtrakker()` - Uses mapped company ID
- `importAllJobRoleReportingRelationships()` - Uses mapped company ID
- `importAllEmployeeReportingRelationships()` - Uses mapped company ID
- `importEmployees()` - Uses mapped company ID

### How It Works in Code

```php
// Before (hardcoded):
$stmt = $connection->execute(
    'SELECT ... FROM ... WHERE company_id = :company_id',
    ['company_id' => 100000]  // Hardcoded
);

// After (dynamic):
$orgtrakkerCompanyId = $this->getOrgtrakkerCompanyId($companyId);  // Gets mapped ID
$stmt = $connection->execute(
    'SELECT ... FROM ... WHERE company_id = :company_id',
    ['company_id' => $orgtrakkerCompanyId]  // Uses mapping
);
```

## Backward Compatibility

If no mapping exists, the system falls back to the default behavior:
- Uses orgtrakker company_id `100000` as default
- Logs a warning when mapping is missing
- Allows existing setups to continue working

## Adding New Mappings

### Via API

```bash
curl -X POST http://localhost:8085/api/companies/mappings.json \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "company_id_from": 200000,
    "company_id_to": 300000,
    "system_from": "orgtrakker",
    "system_to": "scorecardtrakker",
    "relationship_type": "affiliate"
  }'
```

### Via SQL

```sql
-- 1. Create company records (if not exist)
INSERT INTO companies (...) VALUES (...);

-- 2. Create mapping
INSERT INTO client_company_relationships (
    company_id_from, company_id_to, relationship_type, status, is_primary,
    start_date, notes, deleted, created_at, updated_at
) VALUES (
    200000, 300000, 'affiliate', 'active', true,
    CURRENT_DATE, 'Mapping description', false, NOW(), NOW()
);
```

## Troubleshooting

### Issue: Import uses wrong company ID

**Solution**: Check if mapping exists:
```sql
SELECT * FROM client_company_relationships 
WHERE company_id_from = 100000 OR company_id_to = 300000;
```

### Issue: "No mapping found" warning

**Solution**: Create the mapping using the API or SQL (see above).

### Issue: Mapping exists but import still fails

**Solution**: 
1. Verify both companies exist in `companies` table
2. Check mapping status is 'active'
3. Verify `end_date` is NULL (not expired)
4. Check application logs for detailed error messages

## Best Practices

1. **Create mappings before imports**: Set up mappings before running imports
2. **Use bidirectional mappings**: Create mappings in both directions for flexibility
3. **Set is_primary flag**: Mark the primary mapping relationship
4. **Document mappings**: Use the `notes` field to document why mappings exist
5. **Monitor logs**: Watch for "No mapping found" warnings
6. **Test imports**: Test imports after creating new mappings

## Future Enhancements

- Support for multiple mappings per company (different relationship types)
- Automatic mapping creation during company setup
- UI for managing mappings
- Mapping validation and conflict detection
- Support for mapping hierarchies (parent/child companies)

