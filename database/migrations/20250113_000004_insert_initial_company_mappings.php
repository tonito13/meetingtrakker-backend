<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class InsertInitialCompanyMappings extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Inserts initial company mapping data for test companies:
     * - Orgtrakker company ID: 100000
     * - ScorecardTrakker company ID: 300000
     *
     * @return void
     */
    public function up(): void
    {
        // Insert companies into the companies table
        $this->execute("
            -- Insert Orgtrakker company (100000)
            INSERT INTO companies (
                company_id, company_type, company_status, data_privacy_setup_type_id,
                code, email, maximum_users, name, system_product_name, deleted, created, modified
            )
            SELECT 
                100000, 'principal', 'active', 1,
                'TC01', 'test@test.com', 100, 'Test Company Orgtrakker', 'orgtrakker', false, NOW(), NOW()
            WHERE NOT EXISTS (
                SELECT 1 FROM companies 
                WHERE company_id = 100000 
                AND system_product_name = 'orgtrakker' 
                AND deleted = false
            );
        ");

        $this->execute("
            -- Insert ScorecardTrakker company (300000)
            INSERT INTO companies (
                company_id, company_type, company_status, data_privacy_setup_type_id,
                code, email, maximum_users, name, system_product_name, deleted, created, modified
            )
            SELECT 
                300000, 'principal', 'active', 1,
                'TC02', 'test@test.com', 100, 'Test Company ScorecardTrakker', 'scorecardtrakker', false, NOW(), NOW()
            WHERE NOT EXISTS (
                SELECT 1 FROM companies 
                WHERE company_id = 300000 
                AND system_product_name = 'scorecardtrakker' 
                AND deleted = false
            );
        ");

        // Insert bidirectional company relationship mapping
        $this->execute("
            -- Create mapping: orgtrakker 100000 → scorecardtrakker 300000 (primary)
            INSERT INTO client_company_relationships (
                company_id_from, company_id_to, relationship_type, status, is_primary,
                start_date, end_date, notes, metadata, deleted, created_at, updated_at
            )
            SELECT 
                100000, 300000, 'affiliate', 'active', true,
                CURRENT_DATE, NULL, 
                'Initial mapping: Test Company - Orgtrakker (100000) to ScorecardTrakker (300000)',
                '{\"system_from\": \"orgtrakker\", \"system_to\": \"scorecardtrakker\"}'::jsonb,
                false, NOW(), NOW()
            WHERE NOT EXISTS (
                SELECT 1 FROM client_company_relationships 
                WHERE company_id_from = 100000 
                AND company_id_to = 300000 
                AND relationship_type = 'affiliate'
                AND deleted = false
            );
        ");

        $this->execute("
            -- Create reverse mapping: scorecardtrakker 300000 → orgtrakker 100000 (non-primary)
            INSERT INTO client_company_relationships (
                company_id_from, company_id_to, relationship_type, status, is_primary,
                start_date, end_date, notes, metadata, deleted, created_at, updated_at
            )
            SELECT 
                300000, 100000, 'affiliate', 'active', false,
                CURRENT_DATE, NULL, 
                'Reverse mapping: Test Company - ScorecardTrakker (300000) to Orgtrakker (100000)',
                '{\"system_from\": \"scorecardtrakker\", \"system_to\": \"orgtrakker\"}'::jsonb,
                false, NOW(), NOW()
            WHERE NOT EXISTS (
                SELECT 1 FROM client_company_relationships 
                WHERE company_id_from = 300000 
                AND company_id_to = 100000 
                AND relationship_type = 'affiliate'
                AND deleted = false
            );
        ");
    }

    /**
     * Rollback Method.
     *
     * Removes the inserted company mapping data.
     *
     * @return void
     */
    public function down(): void
    {
        // Remove company relationships
        $this->execute("
            UPDATE client_company_relationships 
            SET deleted = true, updated_at = NOW()
            WHERE (
                (company_id_from = 100000 AND company_id_to = 300000)
                OR (company_id_from = 300000 AND company_id_to = 100000)
            )
            AND relationship_type = 'affiliate'
            AND deleted = false;
        ");

        // Optionally remove companies (commented out to preserve data)
        // $this->execute("
        //     UPDATE companies 
        //     SET deleted = true, modified = NOW()
        //     WHERE company_id IN (100000, 300000)
        //     AND deleted = false;
        // ");
    }
}

