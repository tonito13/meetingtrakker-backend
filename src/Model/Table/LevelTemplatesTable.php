<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * LevelTemplates Model
 *
 * @method \App\Model\Entity\LevelTemplate newEmptyEntity()
 * @method \App\Model\Entity\LevelTemplate newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\LevelTemplate> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\LevelTemplate get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\LevelTemplate findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\LevelTemplate patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\LevelTemplate> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\LevelTemplate|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\LevelTemplate saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\LevelTemplate>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\LevelTemplate>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\LevelTemplate>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\LevelTemplate> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\LevelTemplate>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\LevelTemplate>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\LevelTemplate>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\LevelTemplate> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class LevelTemplatesTable extends Table
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

        $this->setTable('level_templates');
        $this->setDisplayField('name');
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
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmptyString('name');

        $validator
            ->requirePresence('structure', 'create')
            ->notEmptyString('structure');

        $validator
            ->scalar('created_by')
            ->maxLength('created_by', 150)
            ->requirePresence('created_by', 'create')
            ->notEmptyString('created_by');

        $validator
            ->boolean('deleted')
            ->notEmptyString('deleted');

        return $validator;
    }
}
