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
     * - MeetingTrakker company ID: 500000
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
            -- Insert MeetingTrakker company (500000)
            INSERT INTO companies (
                company_id, company_type, company_status, data_privacy_setup_type_id,
                code, email, maximum_users, name, system_product_name, deleted, created, modified
            )
            SELECT 
                500000, 'principal', 'active', 1,
                'TC05', 'test@test.com', 100, 'Test Company MeetingTrakker', 'meetingtrakker', false, NOW(), NOW()
            WHERE NOT EXISTS (
                SELECT 1 FROM companies 
                WHERE company_id = 500000 
                AND system_product_name = 'meetingtrakker' 
                AND deleted = false
            );
        ");

        // Insert bidirectional company relationship mapping
        $this->execute("
            -- Create mapping: orgtrakker 100000 → meetingtrakker 500000 (primary)
            INSERT INTO client_company_relationships (
                company_id_from, company_id_to, relationship_type, status, is_primary,
                start_date, end_date, notes, metadata, deleted, created_at, updated_at
            )
            SELECT 
                100000, 500000, 'affiliate', 'active', true,
                CURRENT_DATE, NULL, 
                'Initial mapping: Test Company - Orgtrakker (100000) to MeetingTrakker (500000)',
                '{\"system_from\": \"orgtrakker\", \"system_to\": \"meetingtrakker\"}'::jsonb,
                false, NOW(), NOW()
            WHERE NOT EXISTS (
                SELECT 1 FROM client_company_relationships 
                WHERE company_id_from = 100000 
                AND company_id_to = 500000 
                AND relationship_type = 'affiliate'
                AND deleted = false
            );
        ");

        $this->execute("
            -- Create reverse mapping: meetingtrakker 500000 → orgtrakker 100000 (non-primary)
            INSERT INTO client_company_relationships (
                company_id_from, company_id_to, relationship_type, status, is_primary,
                start_date, end_date, notes, metadata, deleted, created_at, updated_at
            )
            SELECT 
                500000, 100000, 'affiliate', 'active', false,
                CURRENT_DATE, NULL, 
                'Reverse mapping: Test Company - MeetingTrakker (500000) to Orgtrakker (100000)',
                '{\"system_from\": \"meetingtrakker\", \"system_to\": \"orgtrakker\"}'::jsonb,
                false, NOW(), NOW()
            WHERE NOT EXISTS (
                SELECT 1 FROM client_company_relationships 
                WHERE company_id_from = 500000 
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
                (company_id_from = 100000 AND company_id_to = 500000)
                OR (company_id_from = 500000 AND company_id_to = 100000)
            )
            AND relationship_type = 'affiliate'
            AND deleted = false;
        ");

        // Optionally remove companies (commented out to preserve data)
        // $this->execute("
        //     UPDATE companies 
        //     SET deleted = true, modified = NOW()
        //     WHERE company_id IN (100000, 500000)
        //     AND deleted = false;
        // ");
    }
}

