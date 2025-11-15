<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Table\ClientCompanyRelationshipsTable;
use App\Model\Table\CompaniesTable;
use App\Model\Table\UserCompanyMappingsTable;
use Cake\Datasource\ConnectionManager;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;

/**
 * Company Mapping Service
 * 
 * Service class to handle company ID mapping logic across different systems
 */
class CompanyMappingService
{
    private ClientCompanyRelationshipsTable $relationshipsTable;
    private CompaniesTable $companiesTable;
    private UserCompanyMappingsTable $userMappingsTable;

    public function __construct()
    {
        try {
            // Get default connection (workmatica database)
            $connection = ConnectionManager::get('default');
            $locator = TableRegistry::getTableLocator();
            
            $this->relationshipsTable = $locator->get('ClientCompanyRelationships', [
                'connection' => $connection
            ]);
            
            $this->companiesTable = $locator->get('Companies', [
                'connection' => $connection
            ]);
            
            $this->userMappingsTable = $locator->get('UserCompanyMappings', [
                'connection' => $connection
            ]);
        } catch (\Exception $e) {
            Log::error('Error initializing CompanyMappingService: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Get mapped company ID for target system
     *
     * @param int $sourceCompanyId Source company ID
     * @param string $sourceSystem Source system name (orgtrakker, scorecardtrakker, etc.)
     * @param string $targetSystem Target system name (orgtrakker, scorecardtrakker, etc.)
     * @param string $relationshipType Relationship type (default: 'affiliate')
     * @return int|null Mapped company ID or null if not found
     */
    public function getMappedCompanyId(
        int $sourceCompanyId,
        string $sourceSystem,
        string $targetSystem,
        string $relationshipType = 'affiliate'
    ): ?int {
        try {
            // Find active relationship
            $relationship = $this->relationshipsTable->find()
                ->where([
                    'company_id_from' => $sourceCompanyId,
                    'relationship_type' => $relationshipType,
                    'status' => 'active',
                    'deleted' => false,
                    'end_date IS' => null
                ])
                ->order(['is_primary' => 'DESC', 'start_date' => 'DESC'])
                ->first();

            if ($relationship) {
                // Verify the target company exists in the target system
                $targetCompany = $this->companiesTable->find()
                    ->where([
                        'company_id' => $relationship->company_id_to,
                        'system_product_name' => $targetSystem,
                        'deleted' => false
                    ])
                    ->first();

                if ($targetCompany) {
                    return $relationship->company_id_to;
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Error getting mapped company ID: ' . $e->getMessage(), [
                'source_company_id' => $sourceCompanyId,
                'source_system' => $sourceSystem,
                'target_system' => $targetSystem
            ]);
            return null;
        }
    }

    /**
     * Get source company ID from mapped ID
     *
     * @param int $mappedCompanyId Mapped company ID in target system
     * @param string $targetSystem Target system name
     * @param string $sourceSystem Source system name
     * @param string $relationshipType Relationship type (default: 'affiliate')
     * @return int|null Source company ID or null if not found
     */
    public function getSourceCompanyId(
        int $mappedCompanyId,
        string $targetSystem,
        string $sourceSystem,
        string $relationshipType = 'affiliate'
    ): ?int {
        try {
            // Find relationship where company_id_to matches the mapped ID
            $relationship = $this->relationshipsTable->find()
                ->where([
                    'company_id_to' => $mappedCompanyId,
                    'relationship_type' => $relationshipType,
                    'status' => 'active',
                    'deleted' => false,
                    'end_date IS' => null
                ])
                ->order(['is_primary' => 'DESC', 'start_date' => 'DESC'])
                ->first();

            if ($relationship) {
                // Verify the source company exists in the source system
                $sourceCompany = $this->companiesTable->find()
                    ->where([
                        'company_id' => $relationship->company_id_from,
                        'system_product_name' => $sourceSystem,
                        'deleted' => false
                    ])
                    ->first();

                if ($sourceCompany) {
                    return $relationship->company_id_from;
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Error getting source company ID: ' . $e->getMessage(), [
                'mapped_company_id' => $mappedCompanyId,
                'target_system' => $targetSystem,
                'source_system' => $sourceSystem
            ]);
            return null;
        }
    }

    /**
     * Create new company mapping
     *
     * @param int $companyIdFrom Source company ID
     * @param int $companyIdTo Target company ID
     * @param string $systemFrom Source system name
     * @param string $systemTo Target system name
     * @param string $relationshipType Relationship type (default: 'affiliate')
     * @param int|null $createdBy User ID who created this mapping
     * @return \App\Model\Entity\ClientCompanyRelationship|false Created relationship entity or false on failure
     */
    public function createCompanyMapping(
        int $companyIdFrom,
        int $companyIdTo,
        string $systemFrom,
        string $systemTo,
        string $relationshipType = 'affiliate',
        ?int $createdBy = null
    ) {
        try {
            // Verify both companies exist in their respective systems
            $sourceCompany = $this->companiesTable->find()
                ->where([
                    'company_id' => $companyIdFrom,
                    'system_product_name' => $systemFrom,
                    'deleted' => false
                ])
                ->first();

            $targetCompany = $this->companiesTable->find()
                ->where([
                    'company_id' => $companyIdTo,
                    'system_product_name' => $systemTo,
                    'deleted' => false
                ])
                ->first();

            if (!$sourceCompany || !$targetCompany) {
                Log::warning('Cannot create mapping: one or both companies not found', [
                    'company_id_from' => $companyIdFrom,
                    'company_id_to' => $companyIdTo,
                    'system_from' => $systemFrom,
                    'system_to' => $systemTo
                ]);
                return false;
            }

            // Check if mapping already exists
            $existing = $this->relationshipsTable->find()
                ->where([
                    'company_id_from' => $companyIdFrom,
                    'company_id_to' => $companyIdTo,
                    'relationship_type' => $relationshipType,
                    'deleted' => false
                ])
                ->first();

            if ($existing) {
                Log::info('Mapping already exists, updating instead', [
                    'id' => $existing->id
                ]);
                $existing->status = 'active';
                $existing->end_date = null;
                $existing->updated_by = $createdBy;
                return $this->relationshipsTable->save($existing);
            }

            // Create new relationship
            $relationship = $this->relationshipsTable->newEntity([
                'company_id_from' => $companyIdFrom,
                'company_id_to' => $companyIdTo,
                'relationship_type' => $relationshipType,
                'status' => 'active',
                'is_primary' => false,
                'start_date' => date('Y-m-d'),
                'end_date' => null,
                'notes' => "Mapping between {$systemFrom} (ID: {$companyIdFrom}) and {$systemTo} (ID: {$companyIdTo})",
                'metadata' => json_encode([
                    'system_from' => $systemFrom,
                    'system_to' => $systemTo
                ]),
                'created_by' => $createdBy,
                'updated_by' => $createdBy,
                'deleted' => false
            ]);

            return $this->relationshipsTable->save($relationship);
        } catch (\Exception $e) {
            Log::error('Error creating company mapping: ' . $e->getMessage(), [
                'company_id_from' => $companyIdFrom,
                'company_id_to' => $companyIdTo,
                'system_from' => $systemFrom,
                'system_to' => $systemTo
            ]);
            return false;
        }
    }

    /**
     * Get orgtrakker company ID from scorecardtrakker company ID
     *
     * @param int $scorecardtrakkerCompanyId ScorecardTrakker company ID
     * @return int|null Orgtrakker company ID or null if not found
     */
    public function getOrgtrakkerCompanyId(int $scorecardtrakkerCompanyId): ?int
    {
        return $this->getMappedCompanyId(
            $scorecardtrakkerCompanyId,
            'scorecardtrakker',
            'orgtrakker',
            'affiliate'
        );
    }

    /**
     * Get scorecardtrakker company ID from orgtrakker company ID
     *
     * @param int $orgtrakkerCompanyId Orgtrakker company ID
     * @return int|null ScorecardTrakker company ID or null if not found
     */
    public function getScorecardtrakkerCompanyId(int $orgtrakkerCompanyId): ?int
    {
        return $this->getMappedCompanyId(
            $orgtrakkerCompanyId,
            'orgtrakker',
            'scorecardtrakker',
            'affiliate'
        );
    }

    /**
     * Get orgtrakker company ID from scorecardtrakker company ID (reverse lookup)
     * This is used when we have scorecardtrakker ID and need orgtrakker ID
     *
     * @param int $scorecardtrakkerCompanyId ScorecardTrakker company ID
     * @return int|null Orgtrakker company ID or null if not found
     */
    public function getOrgtrakkerCompanyIdFromScorecardtrakker(int $scorecardtrakkerCompanyId): ?int
    {
        try {
            // Check if tables exist and are accessible
            if (!isset($this->relationshipsTable) || !isset($this->companiesTable)) {
                Log::warning('CompanyMappingService tables not initialized, returning null');
                return null;
            }

            // Find relationship where scorecardtrakker is the target
            $relationship = $this->relationshipsTable->find()
                ->where([
                    'company_id_to' => $scorecardtrakkerCompanyId,
                    'relationship_type' => 'affiliate',
                    'status' => 'active',
                    'deleted' => false,
                    'end_date IS' => null
                ])
                ->order(['is_primary' => 'DESC', 'start_date' => 'DESC'])
                ->first();

            if ($relationship) {
                // Verify the source company exists in orgtrakker
                $orgtrakkerCompany = $this->companiesTable->find()
                    ->where([
                        'company_id' => $relationship->company_id_from,
                        'system_product_name' => 'orgtrakker',
                        'deleted' => false
                    ])
                    ->first();

                if ($orgtrakkerCompany) {
                    return $relationship->company_id_from;
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Error getting orgtrakker company ID from scorecardtrakker: ' . $e->getMessage(), [
                'scorecardtrakker_company_id' => $scorecardtrakkerCompanyId,
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Get mapped company ID for a user
     *
     * @param int $userId User ID
     * @param string $username Username
     * @param string $systemType System type (e.g., 'scorecardtrakker')
     * @return int|null Mapped company ID if mapping exists and is active, null otherwise
     */
    public function getMappedCompanyIdForUser(int $userId, string $username, string $systemType): ?int
    {
        try {
            if (!isset($this->userMappingsTable)) {
                Log::warning('CompanyMappingService userMappingsTable not initialized, returning null');
                return null;
            }

            $mapping = $this->userMappingsTable->find()
                ->where([
                    'user_id' => $userId,
                    'username' => $username,
                    'system_type' => $systemType,
                    'active' => true,
                    'deleted' => false
                ])
                ->first();

            if ($mapping) {
                return $mapping->mapped_company_id;
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Error getting mapped company ID for user: ' . $e->getMessage(), [
                'user_id' => $userId,
                'username' => $username,
                'system_type' => $systemType,
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Create user company mapping
     *
     * @param int $userId User ID
     * @param string $username Username
     * @param int $sourceCompanyId Source company ID (e.g., orgtrakker company ID)
     * @param int $mappedCompanyId Mapped company ID (e.g., scorecardtrakker company ID)
     * @param string $systemType System type (e.g., 'scorecardtrakker')
     * @return bool True if mapping was created/updated successfully, false otherwise
     */
    public function createUserCompanyMapping(
        int $userId,
        string $username,
        int $sourceCompanyId,
        int $mappedCompanyId,
        string $systemType
    ): bool {
        try {
            if (!isset($this->userMappingsTable)) {
                Log::warning('CompanyMappingService userMappingsTable not initialized, cannot create mapping');
                return false;
            }

            // Check if mapping already exists
            $existing = $this->userMappingsTable->find()
                ->where([
                    'user_id' => $userId,
                    'username' => $username,
                    'system_type' => $systemType
                ])
                ->first();

            if ($existing) {
                // Update existing mapping if it's inactive or deleted
                if (!$existing->active || $existing->deleted) {
                    $existing->active = true;
                    $existing->deleted = false;
                    $existing->mapped_company_id = $mappedCompanyId;
                    $existing->source_company_id = $sourceCompanyId;
                    $existing->modified = date('Y-m-d H:i:s');
                    
                    if ($this->userMappingsTable->save($existing)) {
                        Log::info('User company mapping reactivated', [
                            'user_id' => $userId,
                            'username' => $username,
                            'system_type' => $systemType,
                            'mapped_company_id' => $mappedCompanyId
                        ]);
                        return true;
                    }
                } else {
                    // Mapping already exists and is active, skip creation
                    Log::debug('User company mapping already exists and is active', [
                        'user_id' => $userId,
                        'username' => $username,
                        'system_type' => $systemType
                    ]);
                    return true;
                }
            } else {
                // Create new mapping
                $mapping = $this->userMappingsTable->newEntity([
                    'user_id' => $userId,
                    'username' => $username,
                    'mapped_company_id' => $mappedCompanyId,
                    'source_company_id' => $sourceCompanyId,
                    'system_type' => $systemType,
                    'active' => true,
                    'deleted' => false,
                    'created' => date('Y-m-d H:i:s'),
                    'modified' => date('Y-m-d H:i:s')
                ]);

                if ($this->userMappingsTable->save($mapping)) {
                    Log::info('User company mapping created', [
                        'user_id' => $userId,
                        'username' => $username,
                        'system_type' => $systemType,
                        'mapped_company_id' => $mappedCompanyId,
                        'source_company_id' => $sourceCompanyId
                    ]);
                    return true;
                }
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Error creating user company mapping: ' . $e->getMessage(), [
                'user_id' => $userId,
                'username' => $username,
                'source_company_id' => $sourceCompanyId,
                'mapped_company_id' => $mappedCompanyId,
                'system_type' => $systemType,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
}

