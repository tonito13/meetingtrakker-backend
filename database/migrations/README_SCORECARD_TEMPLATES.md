# Scorecard Templates Migration Guide

This document explains how to run the migrations for the new scorecard template system.

## Migration Files

1. **`20241201_000001_create_scorecard_templates_table.php`** - Creates the table in the central database
2. **`20241201_000002_create_scorecard_templates_company_table.php`** - Creates the table in company databases

## Running Migrations

### Prerequisites
- Ensure your Docker containers are running
- Ensure you have access to both central and company databases

### Step 1: Run Central Database Migration

```bash
# Navigate to the backend directory
cd scorecardtrakker-backend

# Run migration for central database
vendor/bin/phinx migrate -c config/phinx.php -e default
```

### Step 2: Run Company Database Migrations

For each company database (200001, 200002, etc.), you need to run the company migration:

```bash
# For company 200001
vendor/bin/phinx migrate -c config/phinx.php -e client_200001

# For company 200002
vendor/bin/phinx migrate -c config/phinx.php -e client_200002

# Continue for all company databases
```

### Alternative: Run All Migrations at Once

If you have multiple company databases, you can create a script to run all migrations:

```bash
#!/bin/bash
# Run central database migration
vendor/bin/phinx migrate -c config/phinx.php -e default

# Run company database migrations
for company in 200001 200002 200003; do
    echo "Running migration for company $company..."
    vendor/bin/phinx migrate -c config/phinx.php -e client_$company
done
```

## What the Migrations Do

### Central Database (`scorecardtrakker`)
- Creates `scorecard_templates` table with proper schema
- Adds indexes for performance
- Inserts default template structure
- Maintains referential integrity

### Company Databases (`200001`, `200002`, etc.)
- Creates identical `scorecard_templates` table structure
- Inserts company-specific default template
- Allows independent template customization per company

## Table Structure

The `scorecard_templates` table contains:

| Column | Type | Description |
|--------|------|-------------|
| `id` | INTEGER | Primary key, auto-increment |
| `name` | VARCHAR(255) | Template name |
| `structure` | JSON | Template structure with groups and fields |
| `created_by` | VARCHAR(255) | Username of creator |
| `created` | DATETIME | Creation timestamp |
| `modified` | DATETIME | Last modification timestamp |
| `deleted` | BOOLEAN | Soft delete flag |

## Default Template Structure

The migration creates a default template with:
- **Group**: Scorecard Information
- **Fields**: Code, Strategies/Tactics, Measures, Deadline, Points, Weight (%)

## Verification

After running migrations, verify the tables were created:

```sql
-- Check central database
\c scorecardtrakker
\dt scorecard_templates
SELECT * FROM scorecard_templates;

-- Check company database
\c 200001
\dt scorecard_templates
SELECT * FROM scorecard_templates;
```

## Troubleshooting

### Common Issues

1. **Connection Errors**: Ensure Docker containers are running
2. **Permission Errors**: Check database user permissions
3. **Duplicate Table Errors**: Drop existing tables if they exist

### Rollback

To rollback migrations:

```bash
# Rollback central database
vendor/bin/phinx rollback -c config/phinx.php -e default

# Rollback company database
vendor/bin/phinx rollback -c config/phinx.php -e client_200001
```

## Next Steps

After running migrations:
1. The scorecard template system will be fully functional
2. Users can create and customize scorecard templates
3. The frontend component will work with the backend API
4. Templates will be company-specific and isolated

## Support

If you encounter issues:
1. Check the migration logs
2. Verify database connections
3. Ensure all prerequisites are met
4. Check the AI Codebase Guide for additional information
