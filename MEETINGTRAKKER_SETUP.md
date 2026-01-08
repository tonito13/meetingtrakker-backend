# MeetingTrakker Setup Guide

## Overview
This document outlines the setup steps for MeetingTrakker, including database configuration and system references.

## Database Setup

### 1. Run the Initial Data SQL Script

Execute the SQL script to create the initial company, admin user, and relationships:

```bash
psql -h localhost -p 5433 -U workmatica_user -d workmatica -f database/migrations/data/insert_meetingtrakker_initial_data.sql
```

Or run it directly in pgAdmin or your PostgreSQL client.

### 2. What Gets Created

The script creates:

1. **Company Record** (company_id: 500000)
   - Name: Test Company MeetingTrakker
   - System Product: meetingtrakker
   - Code: TC05
   - Email: meetingtrakker@test.com

2. **Admin User**
   - Username: admin
   - Password: admin123 (bcrypt hashed)
   - Company ID: 500000
   - Role: admin

3. **Company Relationships**
   - Orgtrakker (100000) → MeetingTrakker (500000) [primary]
   - MeetingTrakker (500000) → Orgtrakker (100000) [reverse]

4. **User Company Mapping**
   - Maps admin user from orgtrakker (if exists) to meetingtrakker

## System Configuration

### Company ID
- **MeetingTrakker Company ID**: 500000
- **Orgtrakker Company ID**: 100000 (source)

### AWS S3 Configuration
- **Folder Prefix**: `meetingtrakker/`
- All file uploads will be stored under the `meetingtrakker/` prefix in S3

### System Type
- **System Type**: `meetingtrakker`
- Added to valid system types in `UserCompanyMappingsTable`

## API Methods

### CompanyMappingService
New methods added for MeetingTrakker:
- `getOrgtrakkerCompanyIdFromMeetingtrakker(int $meetingtrakkerCompanyId): ?int`
- `getMeetingtrakkerCompanyId(int $orgtrakkerCompanyId): ?int`

## Migration File

The migration file `20250113_000004_insert_initial_company_mappings.php` has been updated to:
- Insert MeetingTrakker company (500000) instead of ScorecardTrakker (300000)
- Create relationships between Orgtrakker and MeetingTrakker

## Verification

After running the SQL script, verify the setup:

```sql
-- Check company
SELECT * FROM companies WHERE company_id = 500000 AND system_product_name = 'meetingtrakker';

-- Check admin user
SELECT * FROM users WHERE company_id = 500000 AND username = 'admin';

-- Check relationships
SELECT * FROM client_company_relationships 
WHERE (company_id_from = 100000 AND company_id_to = 500000) 
   OR (company_id_from = 500000 AND company_id_to = 100000);
```

## Default Login Credentials

- **Username**: admin
- **Password**: admin123
- **Company ID**: 500000

## Notes

- The AWS S3 service has been configured to use `meetingtrakker/` as the folder prefix
- All references to "scorecardtrakker" have been replaced with "meetingtrakker" in critical paths
- The system is configured to work with the workmatica network and shared database

