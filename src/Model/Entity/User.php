<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * User Entity
 *
 * @property int $id
 * @property int $company_id
 * @property string $first_name
 * @property string|null $middle_name
 * @property string $last_name
 * @property \Cake\I18n\Date $birth_date
 * @property string|null $birth_place
 * @property string $sex
 * @property string $civil_status
 * @property string|null $nationality
 * @property string|null $blood_type
 * @property string $email_address
 * @property string|null $contact_number
 * @property string|null $street_number
 * @property string|null $street_name
 * @property string|null $barangay
 * @property string|null $city_municipality
 * @property string|null $province
 * @property string|null $zipcode
 * @property string $username
 * @property string $password
 * @property string $system_user_role
 * @property bool $system_access_enabled
 * @property bool $active
 * @property bool $deleted
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 */
class User extends Entity
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
        'first_name' => true,
        'middle_name' => true,
        'last_name' => true,
        'birth_date' => true,
        'birth_place' => true,
        'sex' => true,
        'civil_status' => true,
        'nationality' => true,
        'blood_type' => true,
        'email_address' => true,
        'contact_number' => true,
        'street_number' => true,
        'street_name' => true,
        'barangay' => true,
        'city_municipality' => true,
        'province' => true,
        'zipcode' => true,
        'username' => true,
        'password' => true,
        'system_user_role' => true,
        'system_access_enabled' => true,
        'active' => true,
        'deleted' => true,
        'created' => true,
        'modified' => true,
    ];

    /**
     * Fields that are excluded from JSON versions of the entity.
     *
     * @var list<string>
     */
    protected array $_hidden = [
        'password',
    ];
}
