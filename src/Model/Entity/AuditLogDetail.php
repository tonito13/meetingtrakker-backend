<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * AuditLogDetail Entity
 *
 * @property string $id
 * @property string $audit_log_id
 * @property string $field_name
 * @property string|null $field_label
 * @property string|null $old_value
 * @property string|null $new_value
 * @property string $change_type
 * @property \Cake\I18n\FrozenTime $created
 *
 * @property \App\Model\Entity\AuditLog $audit_log
 */
class AuditLogDetail extends Entity
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
        'audit_log_id' => true,
        'field_name' => true,
        'field_label' => true,
        'old_value' => true,
        'new_value' => true,
        'change_type' => true,
        'created' => true,
        'audit_log' => true,
    ];

    /**
     * Fields that are excluded from JSON versions of the entity.
     *
     * @var array<string>
     */
    protected array $_hidden = [];

    /**
     * Get a human-readable description of the change
     *
     * @return string
     */
    public function getChangeDescription(): string
    {
        $fieldLabel = $this->field_label ?: $this->field_name;
        $changeType = strtolower($this->change_type);
        
        return match ($changeType) {
            'added' => "Added {$fieldLabel}: {$this->new_value}",
            'removed' => "Removed {$fieldLabel}: {$this->old_value}",
            'changed' => "Changed {$fieldLabel} from '{$this->old_value}' to '{$this->new_value}'",
            default => "Modified {$fieldLabel}",
        };
    }

    /**
     * Get the change type color for UI display
     *
     * @return string
     */
    public function getChangeTypeColor(): string
    {
        return match (strtolower($this->change_type)) {
            'added' => 'success',
            'removed' => 'error',
            'changed' => 'warning',
            default => 'default',
        };
    }

    /**
     * Get the change type icon for UI display
     *
     * @return string
     */
    public function getChangeTypeIcon(): string
    {
        return match (strtolower($this->change_type)) {
            'added' => 'add',
            'removed' => 'remove',
            'changed' => 'edit',
            default => 'info',
        };
    }
}
