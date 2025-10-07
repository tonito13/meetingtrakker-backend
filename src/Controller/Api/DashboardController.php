<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\Api\ApiController;
use Cake\Log\Log;
use Exception;

class DashboardController extends ApiController
{
    public function initialize(): void
    {
        parent::initialize();
    }

    public function getDashboardData()
    {
        $this->request->allowMethod(['get']);

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

        $companyId = $this->getCompanyId($authResult);

        try {
            // Minimal template-friendly metrics only
            $totalJobRoles = $this->getJobRoleCount($companyId);
            $totalRoleLevels = $this->getRoleLevelCount($companyId);
            $totalEmployees = $this->getEmployeeCount($companyId);

            $dashboardData = [
                'success' => true,
                'data' => [
                    'totalJobRoles' => $totalJobRoles,
                    'totalRoleLevels' => $totalRoleLevels,
                    'totalEmployees' => $totalEmployees,
                ]
            ];

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode($dashboardData));
        } catch (Exception $e) {
            Log::error('Dashboard data fetch error: ' . $e->getMessage());
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Failed to fetch dashboard data',
                ]));
        }
    }

    private function getJobRoleCount($companyId)
    {
        try {
            $JobRoleTemplateAnswersTable = $this->getTable('JobRoleTemplateAnswers', $companyId);
            return $JobRoleTemplateAnswersTable->find()
                ->where(['company_id' => $companyId, 'deleted' => 0])
                ->count();
        } catch (Exception $e) {
            Log::error('Error getting job role count: ' . $e->getMessage());
            return 0;
        }
    }

    private function getRoleLevelCount($companyId)
    {
        try {
            $RoleLevelsTable = $this->getTable('RoleLevels', $companyId);
            return $RoleLevelsTable->find()
                ->where(['company_id' => $companyId, 'deleted' => 0])
                ->count();
        } catch (Exception $e) {
            Log::error('Error getting role level count: ' . $e->getMessage());
            return 0;
        }
    }

    private function getEmployeeCount($companyId)
    {
        try {
            $EmployeeTemplateAnswersTable = $this->getTable('EmployeeTemplateAnswers', $companyId);
            return $EmployeeTemplateAnswersTable->find()
                ->where(['company_id' => $companyId, 'deleted' => 0])
                ->count();
        } catch (Exception $e) {
            Log::error('Error getting employee count: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Helper method to extract company_id from authentication result
     */
    private function getCompanyId($authResult)
    {
        $data = $authResult->getData();
        
        // Handle both ArrayObject and stdClass
        if (is_object($data)) {
            if (isset($data->company_id)) {
                return $data->company_id;
            }
            // Convert to array if needed
            $data = (array) $data;
        }
        
        if (is_array($data) && isset($data['company_id'])) {
            return $data['company_id'];
        }
        
        // Fallback: try to get from JWT payload
        if (method_exists($authResult, 'getPayload')) {
            $payload = $authResult->getPayload();
            if (isset($payload['company_id'])) {
                return $payload['company_id'];
            }
        }
        
        // Default fallback
        return '200001'; // Default company ID
    }
} 