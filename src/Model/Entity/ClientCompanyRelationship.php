<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * ClientCompanyRelationship Entity
 *
 * @property int $id
 * @property int $company_id_from
 * @property int $company_id_to
 * @property string $relationship_type
 * @property string $status
 * @property bool $is_primary
 * @property \Cake\I18n\Date $start_date
 * @property \Cake\I18n\Date|null $end_date
 * @property string|null $notes
 * @property array|null $metadata
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Cake\I18n\DateTime $created_at
 * @property \Cake\I18n\DateTime $updated_at
 * @property bool $deleted
 * @property \Cake\I18n\DateTime|null $deleted_at
 */
class ClientCompanyRelationship extends Entity
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
        'company_id_from' => true,
        'company_id_to' => true,
        'relationship_type' => true,
        'status' => true,
        'is_primary' => true,
        'start_date' => true,
        'end_date' => true,
        'notes' => true,
        'metadata' => true,
        'created_by' => true,
        'updated_by' => true,
        'created_at' => true,
        'updated_at' => true,
        'deleted' => true,
        'deleted_at' => true,
    ];
}

