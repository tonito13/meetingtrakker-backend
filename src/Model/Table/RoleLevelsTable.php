<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * RoleLevels Model
 *
 * @method \App\Model\Entity\RoleLevel newEmptyEntity()
 * @method \App\Model\Entity\RoleLevel newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\RoleLevel> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\RoleLevel get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\RoleLevel findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\RoleLevel patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\RoleLevel> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\RoleLevel|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\RoleLevel saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\RoleLevel>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\RoleLevel>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\RoleLevel>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\RoleLevel> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\RoleLevel>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\RoleLevel>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\RoleLevel>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\RoleLevel> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class RoleLevelsTable extends Table
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

        $this->setTable('role_levels');
        $this->setDisplayField('level_unique_id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
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
            ->notEmptyString('company_id');

        $validator
            ->scalar('level_unique_id')
            ->maxLength('level_unique_id', 150)
            ->requirePresence('level_unique_id', 'create')
            ->notEmptyString('level_unique_id');

        $validator
            ->integer('template_id')
            ->requirePresence('template_id', 'create')
            ->notEmptyString('template_id');

        $validator
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmptyString('name');

        $validator
            ->integer('rank')
            ->requirePresence('rank', 'create')
            ->notEmptyString('rank');

        $validator
            ->allowEmptyString('custom_fields');

        $validator
            ->scalar('created_by')
            ->maxLength('created_by', 150)
            ->requirePresence('created_by', 'create')
            ->notEmptyString('created_by');

        $validator
            ->boolean('deleted')
            ->allowEmptyString('deleted');

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    
}
