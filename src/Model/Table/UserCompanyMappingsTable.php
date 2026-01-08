<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * UserCompanyMappings Model
 *
 * @method \App\Model\Entity\UserCompanyMapping newEmptyEntity()
 * @method \App\Model\Entity\UserCompanyMapping newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\UserCompanyMapping> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\UserCompanyMapping get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\UserCompanyMapping findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\UserCompanyMapping patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\UserCompanyMapping> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\UserCompanyMapping|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\UserCompanyMapping saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class UserCompanyMappingsTable extends Table
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

        $this->setTable('user_company_mappings');
        $this->setDisplayField('id');
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
            ->integer('user_id')
            ->requirePresence('user_id', 'create')
            ->notEmptyString('user_id');

        $validator
            ->scalar('username')
            ->maxLength('username', 100)
            ->requirePresence('username', 'create')
            ->notEmptyString('username');

        $validator
            ->integer('mapped_company_id')
            ->requirePresence('mapped_company_id', 'create')
            ->notEmptyString('mapped_company_id');

        $validator
            ->integer('source_company_id')
            ->requirePresence('source_company_id', 'create')
            ->notEmptyString('source_company_id');

        $validator
            ->scalar('system_type')
            ->maxLength('system_type', 50)
            ->requirePresence('system_type', 'create')
            ->notEmptyString('system_type')
            ->inList('system_type', ['orgtrakker', 'scorecardtrakker', 'skiltrakker', 'tickettrakker', 'meetingtrakker']);

        $validator
            ->boolean('active')
            ->notEmptyString('active');

        $validator
            ->boolean('deleted')
            ->notEmptyString('deleted');

        return $validator;
    }

    /**
     * Find mappings by username
     *
     * @param \Cake\ORM\Query\SelectQuery $query Query object
     * @param array $options Options array containing 'username'
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findByUsername(SelectQuery $query, array $options): SelectQuery
    {
        return $query->where([
            'username' => $options['username'],
            'active' => true,
            'deleted' => false
        ]);
    }

    /**
     * Find mappings by user ID
     *
     * @param \Cake\ORM\Query\SelectQuery $query Query object
     * @param array $options Options array containing 'user_id'
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findByUserId(SelectQuery $query, array $options): SelectQuery
    {
        return $query->where([
            'user_id' => $options['user_id'],
            'active' => true,
            'deleted' => false
        ]);
    }

    /**
     * Get mapped company ID for a user
     *
     * @param int $userId User ID
     * @param string $systemType System type
     * @return int|null Mapped company ID or null if not found
     */
    public function getMappedCompanyId(int $userId, string $systemType): ?int
    {
        $mapping = $this->find()
            ->where([
                'user_id' => $userId,
                'system_type' => $systemType,
                'active' => true,
                'deleted' => false
            ])
            ->first();

        return $mapping ? $mapping->mapped_company_id : null;
    }
}

