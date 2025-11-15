<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * ClientCompanyRelationships Model
 *
 * @method \App\Model\Entity\ClientCompanyRelationship newEmptyEntity()
 * @method \App\Model\Entity\ClientCompanyRelationship newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\ClientCompanyRelationship> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\ClientCompanyRelationship get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\ClientCompanyRelationship findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\ClientCompanyRelationship patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\ClientCompanyRelationship> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\ClientCompanyRelationship|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\ClientCompanyRelationship saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class ClientCompanyRelationshipsTable extends Table
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

        $this->setTable('client_company_relationships');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'created_at' => 'new',
                    'updated_at' => 'always'
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
            ->integer('company_id_from')
            ->requirePresence('company_id_from', 'create')
            ->notEmptyString('company_id_from');

        $validator
            ->integer('company_id_to')
            ->requirePresence('company_id_to', 'create')
            ->notEmptyString('company_id_to')
            ->notEquals('company_id_to', 'company_id_from', 'Cannot map company to itself');

        $validator
            ->scalar('relationship_type')
            ->requirePresence('relationship_type', 'create')
            ->notEmptyString('relationship_type')
            ->inList('relationship_type', ['vendor', 'partner', 'prospect', 'customer', 'affiliate', 'other']);

        $validator
            ->scalar('status')
            ->requirePresence('status', 'create')
            ->notEmptyString('status')
            ->inList('status', ['active', 'inactive', 'pending', 'terminated']);

        $validator
            ->boolean('is_primary')
            ->notEmptyString('is_primary');

        $validator
            ->date('start_date')
            ->requirePresence('start_date', 'create')
            ->notEmptyDate('start_date');

        $validator
            ->date('end_date')
            ->allowEmptyDate('end_date')
            ->add('end_date', 'dateRange', [
                'rule' => function ($value, $context) {
                    if ($value && isset($context['data']['start_date'])) {
                        return $value >= $context['data']['start_date'];
                    }
                    return true;
                },
                'message' => 'End date must be after or equal to start date'
            ]);

        $validator
            ->boolean('deleted')
            ->notEmptyString('deleted');

        return $validator;
    }

    /**
     * Find active relationship between two companies
     *
     * @param \Cake\ORM\Query\SelectQuery $query Query object
     * @param array $options Options array containing 'company_id_from', 'company_id_to', 'relationship_type'
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findActiveRelationship(SelectQuery $query, array $options): SelectQuery
    {
        return $query->where([
            'company_id_from' => $options['company_id_from'],
            'company_id_to' => $options['company_id_to'],
            'relationship_type' => $options['relationship_type'] ?? 'affiliate',
            'status' => 'active',
            'deleted' => false,
            'end_date IS' => null
        ])->order(['is_primary' => 'DESC', 'start_date' => 'DESC']);
    }

    /**
     * Get mapped company ID for a given source company ID
     *
     * @param int $sourceCompanyId Source company ID
     * @param string $relationshipType Relationship type (default: 'affiliate')
     * @return int|null Mapped company ID or null if not found
     */
    public function getMappedCompanyId(int $sourceCompanyId, string $relationshipType = 'affiliate'): ?int
    {
        $relationship = $this->find()
            ->where([
                'company_id_from' => $sourceCompanyId,
                'relationship_type' => $relationshipType,
                'status' => 'active',
                'deleted' => false,
                'end_date IS' => null
            ])
            ->order(['is_primary' => 'DESC', 'start_date' => 'DESC'])
            ->first();

        return $relationship ? $relationship->company_id_to : null;
    }

    /**
     * Find relationships by company ID (from or to)
     *
     * @param \Cake\ORM\Query\SelectQuery $query Query object
     * @param array $options Options array containing 'company_id'
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findByCompanyId(SelectQuery $query, array $options): SelectQuery
    {
        return $query->where([
            'OR' => [
                'company_id_from' => $options['company_id'],
                'company_id_to' => $options['company_id']
            ],
            'deleted' => false
        ]);
    }
}

