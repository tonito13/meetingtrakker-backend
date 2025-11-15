<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\Api\ApiController;
use App\Service\CompanyMappingService;
use Cake\Log\Log;
use Exception;

class CompaniesController extends ApiController
{
    private ?CompanyMappingService $companyMappingService = null;

    public function initialize(): void
    {
        parent::initialize();
    }

    /**
     * Get CompanyMappingService instance
     *
     * @return CompanyMappingService
     */
    private function getCompanyMappingService(): CompanyMappingService
    {
        if ($this->companyMappingService === null) {
            $this->companyMappingService = new CompanyMappingService();
        }
        return $this->companyMappingService;
    }

    /**
     * List all company mappings
     *
     * @return \Cake\Http\Response
     */
    public function getMappings()
    {
        $this->request->allowMethod(['get']);

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

        try {
            // Use default connection (workmatica database) for mapping tables
            $relationshipsTable = $this->getTable('ClientCompanyRelationships', 'default');
            $companiesTable = $this->getTable('Companies', 'default');

            $relationships = $relationshipsTable->find()
                ->where(['deleted' => false])
                ->order(['created_at' => 'DESC'])
                ->toArray();

            $mappings = [];
            foreach ($relationships as $relationship) {
                // Get company details
                $companyFrom = $companiesTable->find()
                    ->where(['company_id' => $relationship->company_id_from, 'deleted' => false])
                    ->first();

                $companyTo = $companiesTable->find()
                    ->where(['company_id' => $relationship->company_id_to, 'deleted' => false])
                    ->first();

                $mappings[] = [
                    'id' => $relationship->id,
                    'company_id_from' => $relationship->company_id_from,
                    'company_id_to' => $relationship->company_id_to,
                    'company_from_name' => $companyFrom ? $companyFrom->name : null,
                    'company_from_system' => $companyFrom ? $companyFrom->system_product_name : null,
                    'company_to_name' => $companyTo ? $companyTo->name : null,
                    'company_to_system' => $companyTo ? $companyTo->system_product_name : null,
                    'relationship_type' => $relationship->relationship_type,
                    'status' => $relationship->status,
                    'is_primary' => $relationship->is_primary,
                    'start_date' => $relationship->start_date,
                    'end_date' => $relationship->end_date,
                    'notes' => $relationship->notes,
                    'created_at' => $relationship->created_at,
                    'updated_at' => $relationship->updated_at,
                ];
            }

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'data' => $mappings,
                    'count' => count($mappings),
                ]));
        } catch (Exception $e) {
            Log::error('Error fetching company mappings: ' . $e->getMessage());
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Error fetching company mappings: ' . $e->getMessage(),
                ]));
        }
    }

    /**
     * Create new company mapping
     *
     * @return \Cake\Http\Response
     */
    public function createMapping()
    {
        $this->request->allowMethod(['post']);

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

        try {
            $data = $this->request->getData();
            $companyIdFrom = $data['company_id_from'] ?? null;
            $companyIdTo = $data['company_id_to'] ?? null;
            $systemFrom = $data['system_from'] ?? null;
            $systemTo = $data['system_to'] ?? null;
            $relationshipType = $data['relationship_type'] ?? 'affiliate';

            if (!$companyIdFrom || !$companyIdTo || !$systemFrom || !$systemTo) {
                return $this->response
                    ->withStatus(400)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Missing required fields: company_id_from, company_id_to, system_from, system_to',
                    ]));
            }

            $mappingService = $this->getCompanyMappingService();
            $currentUser = $this->Authentication->getIdentity();
            $createdBy = $currentUser ? ($currentUser->get('id') ?? null) : null;

            $result = $mappingService->createCompanyMapping(
                (int)$companyIdFrom,
                (int)$companyIdTo,
                $systemFrom,
                $systemTo,
                $relationshipType,
                $createdBy
            );

            if ($result) {
                return $this->response
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => true,
                        'message' => 'Company mapping created successfully',
                        'data' => [
                            'id' => $result->id,
                            'company_id_from' => $result->company_id_from,
                            'company_id_to' => $result->company_id_to,
                            'relationship_type' => $result->relationship_type,
                        ],
                    ]));
            } else {
                return $this->response
                    ->withStatus(400)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Failed to create company mapping. Please check that both companies exist in their respective systems.',
                    ]));
            }
        } catch (Exception $e) {
            Log::error('Error creating company mapping: ' . $e->getMessage());
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Error creating company mapping: ' . $e->getMessage(),
                ]));
        }
    }

    /**
     * Get specific mapping by ID
     *
     * @param int $id Mapping ID
     * @return \Cake\Http\Response
     */
    public function getMapping($id)
    {
        $this->request->allowMethod(['get']);

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

        try {
            // Use default connection (workmatica database) for mapping tables
            $relationshipsTable = $this->getTable('ClientCompanyRelationships', 'default');
            $companiesTable = $this->getTable('Companies', 'default');

            $relationship = $relationshipsTable->find()
                ->where(['id' => $id, 'deleted' => false])
                ->first();

            if (!$relationship) {
                return $this->response
                    ->withStatus(404)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Mapping not found',
                    ]));
            }

            // Get company details
            $companyFrom = $companiesTable->find()
                ->where(['company_id' => $relationship->company_id_from, 'deleted' => false])
                ->first();

            $companyTo = $companiesTable->find()
                ->where(['company_id' => $relationship->company_id_to, 'deleted' => false])
                ->first();

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'data' => [
                        'id' => $relationship->id,
                        'company_id_from' => $relationship->company_id_from,
                        'company_id_to' => $relationship->company_id_to,
                        'company_from_name' => $companyFrom ? $companyFrom->name : null,
                        'company_from_system' => $companyFrom ? $companyFrom->system_product_name : null,
                        'company_to_name' => $companyTo ? $companyTo->name : null,
                        'company_to_system' => $companyTo ? $companyTo->system_product_name : null,
                        'relationship_type' => $relationship->relationship_type,
                        'status' => $relationship->status,
                        'is_primary' => $relationship->is_primary,
                        'start_date' => $relationship->start_date,
                        'end_date' => $relationship->end_date,
                        'notes' => $relationship->notes,
                        'metadata' => $relationship->metadata,
                        'created_at' => $relationship->created_at,
                        'updated_at' => $relationship->updated_at,
                    ],
                ]));
        } catch (Exception $e) {
            Log::error('Error fetching company mapping: ' . $e->getMessage());
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Error fetching company mapping: ' . $e->getMessage(),
                ]));
        }
    }

    /**
     * Update company mapping
     *
     * @param int $id Mapping ID
     * @return \Cake\Http\Response
     */
    public function updateMapping($id)
    {
        $this->request->allowMethod(['put', 'post']);

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

        try {
            // Use default connection (workmatica database) for mapping tables
            $relationshipsTable = $this->getTable('ClientCompanyRelationships', 'default');
            $relationship = $relationshipsTable->find()
                ->where(['id' => $id, 'deleted' => false])
                ->first();

            if (!$relationship) {
                return $this->response
                    ->withStatus(404)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Mapping not found',
                    ]));
            }

            $data = $this->request->getData();
            $currentUser = $this->Authentication->getIdentity();
            $updatedBy = $currentUser ? ($currentUser->get('id') ?? null) : null;

            // Update allowed fields
            if (isset($data['status'])) {
                $relationship->status = $data['status'];
            }
            if (isset($data['is_primary'])) {
                $relationship->is_primary = $data['is_primary'];
            }
            if (isset($data['end_date'])) {
                $relationship->end_date = $data['end_date'];
            }
            if (isset($data['notes'])) {
                $relationship->notes = $data['notes'];
            }
            if (isset($data['metadata'])) {
                $relationship->metadata = $data['metadata'];
            }

            $relationship->updated_by = $updatedBy;

            if ($relationshipsTable->save($relationship)) {
                return $this->response
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => true,
                        'message' => 'Mapping updated successfully',
                        'data' => [
                            'id' => $relationship->id,
                            'company_id_from' => $relationship->company_id_from,
                            'company_id_to' => $relationship->company_id_to,
                            'status' => $relationship->status,
                        ],
                    ]));
            } else {
                return $this->response
                    ->withStatus(400)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Failed to update mapping',
                        'errors' => $relationship->getErrors(),
                    ]));
            }
        } catch (Exception $e) {
            Log::error('Error updating company mapping: ' . $e->getMessage());
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Error updating company mapping: ' . $e->getMessage(),
                ]));
        }
    }

    /**
     * Delete company mapping (soft delete)
     *
     * @param int $id Mapping ID
     * @return \Cake\Http\Response
     */
    public function deleteMapping($id)
    {
        $this->request->allowMethod(['delete', 'post']);

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

        try {
            // Use default connection (workmatica database) for mapping tables
            $relationshipsTable = $this->getTable('ClientCompanyRelationships', 'default');
            $relationship = $relationshipsTable->find()
                ->where(['id' => $id, 'deleted' => false])
                ->first();

            if (!$relationship) {
                return $this->response
                    ->withStatus(404)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Mapping not found',
                    ]));
            }

            // Soft delete
            $relationship->deleted = true;
            $relationship->deleted_at = date('Y-m-d H:i:s');
            $currentUser = $this->Authentication->getIdentity();
            $relationship->updated_by = $currentUser ? ($currentUser->get('id') ?? null) : null;

            if ($relationshipsTable->save($relationship)) {
                return $this->response
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => true,
                        'message' => 'Mapping deleted successfully',
                    ]));
            } else {
                return $this->response
                    ->withStatus(400)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Failed to delete mapping',
                    ]));
            }
        } catch (Exception $e) {
            Log::error('Error deleting company mapping: ' . $e->getMessage());
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Error deleting company mapping: ' . $e->getMessage(),
                ]));
        }
    }

    /**
     * Get mapped company ID for target system
     *
     * @return \Cake\Http\Response
     */
    public function getMappedId()
    {
        $this->request->allowMethod(['get']);

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

        try {
            $sourceCompanyId = $this->request->getQuery('source_company_id');
            $sourceSystem = $this->request->getQuery('source_system');
            $targetSystem = $this->request->getQuery('target_system');
            $relationshipType = $this->request->getQuery('relationship_type') ?? 'affiliate';

            if (!$sourceCompanyId || !$sourceSystem || !$targetSystem) {
                return $this->response
                    ->withStatus(400)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Missing required query parameters: source_company_id, source_system, target_system',
                    ]));
            }

            $mappingService = $this->getCompanyMappingService();
            $mappedCompanyId = $mappingService->getMappedCompanyId(
                (int)$sourceCompanyId,
                $sourceSystem,
                $targetSystem,
                $relationshipType
            );

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'data' => [
                        'source_company_id' => (int)$sourceCompanyId,
                        'source_system' => $sourceSystem,
                        'target_system' => $targetSystem,
                        'mapped_company_id' => $mappedCompanyId,
                        'found' => $mappedCompanyId !== null,
                    ],
                ]));
        } catch (Exception $e) {
            Log::error('Error getting mapped company ID: ' . $e->getMessage());
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Error getting mapped company ID: ' . $e->getMessage(),
                ]));
        }
    }
}

