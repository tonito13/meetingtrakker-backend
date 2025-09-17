<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * AuditLog Entity
 *
 * @property string $id
 * @property string $company_id
 * @property int $user_id
 * @property string $username
 * @property string $action
 * @property string $entity_type
 * @property string|null $entity_id
 * @property string|null $entity_name
 * @property string|null $description
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property array|null $request_data
 * @property array|null $response_data
 * @property string $status
 * @property string|null $error_message
 * @property \Cake\I18n\FrozenTime $created
 * @property \Cake\I18n\FrozenTime $modified
 *
 * @property \App\Model\Entity\AuditLogDetail[] $audit_log_details
 */
class AuditLog extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'company_id' => true,
        'user_id' => true,
        'username' => true,
        'user_data' => true,
        'action' => true,
        'entity_type' => true,
        'entity_id' => true,
        'entity_name' => true,
        'description' => true,
        'ip_address' => true,
        'user_agent' => true,
        'request_data' => true,
        'response_data' => true,
        'status' => true,
        'error_message' => true,
        'created' => true,
        'modified' => true,
        'audit_log_details' => true,
    ];

    /**
     * Virtual fields that are computed from other fields
     *
     * @var array<string>
     */
    protected array $_virtual = [
        'employee_name'
    ];

    /**
     * Fields that are excluded from JSON versions of the entity.
     *
     * @var array<string>
     */
    protected array $_hidden = [
        'request_data',
        'response_data',
    ];

    /**
     * Get employee name from user_data
     *
     * @return string
     */
    public function getEmployeeName(): string
    {
        if (empty($this->user_data)) {
            return $this->username ?? 'Unknown';
        }

        $userData = is_string($this->user_data) 
            ? json_decode($this->user_data, true) 
            : $this->user_data;

        return $userData['employee_name'] ?? $this->username ?? 'Unknown';
    }

    /**
     * Get a human-readable description of the audit log
     *
     * @return string
     */
    public function getDisplayDescription(): string
    {
        if (!empty($this->description)) {
            return $this->description;
        }

        $action = strtolower($this->action);
        $entityType = strtolower($this->entity_type);
        
        if ($this->entity_name) {
            return ucfirst($action) . " {$entityType}: {$this->entity_name}";
        }
        
        return ucfirst($action) . " {$entityType}";
    }

    /**
     * Get the status color for UI display
     *
     * @return string
     */
    public function getStatusColor(): string
    {
        return match ($this->status) {
            'success' => 'success',
            'error' => 'error',
            'warning' => 'warning',
            default => 'default',
        };
    }

    /**
     * Get the action icon for UI display
     *
     * @return string
     */
    public function getActionIcon(): string
    {
        return match (strtoupper($this->action)) {
            'CREATE' => 'add',
            'UPDATE' => 'edit',
            'DELETE' => 'delete',
            'LOGIN' => 'login',
            'LOGOUT' => 'logout',
            'EVALUATE' => 'assessment',
            'ASSIGN' => 'assignment',
            'UNASSIGN' => 'assignment_return',
            'UPLOAD' => 'upload',
            'DOWNLOAD' => 'download',
            'EXPORT' => 'file_download',
            'IMPORT' => 'file_upload',
            default => 'info',
        };
    }
}
