<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * RoleLevel Entity
 *
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property int $rank
 * @property array|null $custom_fields
 * @property string $created_by
 * @property bool|null $deleted
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 */
class RoleLevel extends Entity
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
        'template_id' => true,
        'level_unique_id' => true,
        'name' => true,
        'rank' => true,
        'custom_fields' => true,
        'created_by' => true,
        'deleted' => true,
        'created' => true,
        'modified' => true,
    ];
}
