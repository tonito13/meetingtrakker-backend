<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Companies Model
 *
 * @method \App\Model\Entity\Company newEmptyEntity()
 * @method \App\Model\Entity\Company newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\Company> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Company get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\Company findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\Company patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\Company> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Company|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\Company saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class CompaniesTable extends Table
{
    /**
     * Initialize method
     *
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('companies');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'created' => 'new',
                    'modified' => 'always'
                ]
            ]
        ]);
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('company_id')
            ->requirePresence('company_id', 'create')
            ->notEmptyString('company_id')
            ->range('company_id', [0, 999999]);

        $validator
            ->scalar('company_type')
            ->maxLength('company_type', 150)
            ->requirePresence('company_type', 'create')
            ->notEmptyString('company_type');

        $validator
            ->scalar('company_status')
            ->maxLength('company_status', 150)
            ->requirePresence('company_status', 'create')
            ->notEmptyString('company_status');

        $validator
            ->integer('data_privacy_setup_type_id')
            ->requirePresence('data_privacy_setup_type_id', 'create')
            ->notEmptyString('data_privacy_setup_type_id');

        $validator
            ->scalar('code')
            ->maxLength('code', 150)
            ->requirePresence('code', 'create')
            ->notEmptyString('code');

        $validator
            ->email('email')
            ->maxLength('email', 50)
            ->requirePresence('email', 'create')
            ->notEmptyString('email');

        $validator
            ->integer('maximum_users')
            ->requirePresence('maximum_users', 'create')
            ->notEmptyString('maximum_users');

        $validator
            ->scalar('name')
            ->maxLength('name', 100)
            ->requirePresence('name', 'create')
            ->notEmptyString('name');

        $validator
            ->scalar('system_product_name')
            ->maxLength('system_product_name', 255)
            ->allowEmptyString('system_product_name');

        $validator
            ->boolean('deleted')
            ->notEmptyString('deleted');

        return $validator;
    }

    /**
     * Find companies by system product name
     *
     * @param \Cake\ORM\Query\SelectQuery $query Query object
     * @param array $options Options array containing 'system_product_name'
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findBySystemProductName(SelectQuery $query, array $options): SelectQuery
    {
        return $query->where([
            'system_product_name' => $options['system_product_name'],
            'deleted' => false
        ]);
    }

    /**
     * Find company by company_id and system
     *
     * @param \Cake\ORM\Query\SelectQuery $query Query object
     * @param array $options Options array containing 'company_id' and 'system_product_name'
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findByCompanyIdAndSystem(SelectQuery $query, array $options): SelectQuery
    {
        return $query->where([
            'company_id' => $options['company_id'],
            'system_product_name' => $options['system_product_name'],
            'deleted' => false
        ]);
    }
}

