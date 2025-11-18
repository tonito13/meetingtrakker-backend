<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Company Entity
 *
 * @property int $id
 * @property int $company_id
 * @property string $company_type
 * @property string $company_status
 * @property int $data_privacy_setup_type_id
 * @property string $code
 * @property string $email
 * @property int $maximum_users
 * @property string $name
 * @property string|null $street_number
 * @property string|null $street_name
 * @property string|null $barangay
 * @property string|null $city
 * @property string|null $province
 * @property string|null $postal_code
 * @property string|null $system_product_name
 * @property bool $deleted
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 */
class Company extends Entity
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
        'company_type' => true,
        'company_status' => true,
        'data_privacy_setup_type_id' => true,
        'code' => true,
        'email' => true,
        'maximum_users' => true,
        'name' => true,
        'street_number' => true,
        'street_name' => true,
        'barangay' => true,
        'city' => true,
        'province' => true,
        'postal_code' => true,
        'system_product_name' => true,
        'deleted' => true,
        'created' => true,
        'modified' => true,
    ];
}

