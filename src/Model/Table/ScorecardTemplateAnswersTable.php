<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * ScorecardTemplateAnswers Model
 *
 * @method \App\Model\Entity\ScorecardTemplateAnswer newEmptyEntity()
 * @method \App\Model\Entity\ScorecardTemplateAnswer newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\ScorecardTemplateAnswer> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\ScorecardTemplateAnswer get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\ScorecardTemplateAnswer findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\ScorecardTemplateAnswer patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\ScorecardTemplateAnswer> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\ScorecardTemplateAnswer|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\ScorecardTemplateAnswer saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\ScorecardTemplateAnswer>|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\ScorecardTemplateAnswer>|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\ScorecardTemplateAnswer>|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\ScorecardTemplateAnswer>|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class ScorecardTemplateAnswersTable extends Table
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

        $this->setTable('scorecard_template_answers');
        $this->setDisplayField('scorecard_unique_id');
        $this->setPrimaryKey('id');

        // Explicitly define parent_scorecard_id in schema to ensure it's recognized
        // This is necessary because CakePHP's schema introspection may not always detect all columns
        $schema = $this->getSchema();
        if (!$schema->hasColumn('parent_scorecard_id')) {
            $schema->addColumn('parent_scorecard_id', [
                'type' => 'integer',
                'length' => null,
                'null' => true,
                'default' => null
            ]);
        }

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
            ->scalar('scorecard_unique_id')
            ->maxLength('scorecard_unique_id', 150)
            ->requirePresence('scorecard_unique_id', 'create')
            ->notEmptyString('scorecard_unique_id');

        $validator
            ->integer('template_id')
            ->requirePresence('template_id', 'create')
            ->notEmptyString('template_id');

        $validator
            ->requirePresence('answers', 'create')
            ->notEmptyString('answers');

        $validator
            ->boolean('deleted')
            ->allowEmptyString('deleted');

        return $validator;
    }
}
