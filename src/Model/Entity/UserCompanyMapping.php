<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * UserCompanyMapping Entity
 *
 * @property int $id
 * @property int $user_id
 * @property string $username
 * @property int $mapped_company_id
 * @property int $source_company_id
 * @property string $system_type
 * @property bool $active
 * @property bool $deleted
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 */
class UserCompanyMapping extends Entity
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
        'user_id' => true,
        'username' => true,
        'mapped_company_id' => true,
        'source_company_id' => true,
        'system_type' => true,
        'active' => true,
        'deleted' => true,
        'created' => true,
        'modified' => true,
    ];
}

