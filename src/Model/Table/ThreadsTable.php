<?php
declare(strict_types=1);

/**
 * Copyright 2010 - 2017, Cake Development Corporation (https://www.cakedc.com)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2010 - 2017, Cake Development Corporation (https://www.cakedc.com)
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

namespace CakeDC\Forum\Model\Table;

use ArrayObject;
use Cake\Core\Configure;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;
use Cake\Validation\Validator;
use CakeDC\Forum\Model\Entity\Reply;
use CakeDC\Forum\Model\Entity\Thread;
use InvalidArgumentException;

/**
 * Threads Model
 *
 * @property \CakeDC\Forum\Model\Table\CategoriesTable $Categories
 * @property \Cake\ORM\Association\BelongsTo $Users
 * @property \CakeDC\Forum\Model\Table\RepliesTable $Replies
 * @property \CakeDC\Forum\Model\Table\ReportsTable $Reports
 *
 * @method \CakeDC\Forum\Model\Entity\Thread get($primaryKey, $options = [])
 * @method \CakeDC\Forum\Model\Entity\Thread newEntity($data = null, array $options = [])
 * @method \CakeDC\Forum\Model\Entity\Thread newEmptyEntity()
 * @method \CakeDC\Forum\Model\Entity\Thread[] newEntities(array $data, array $options = [])
 * @method \CakeDC\Forum\Model\Entity\Thread|bool save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \CakeDC\Forum\Model\Entity\Thread patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \CakeDC\Forum\Model\Entity\Thread[] patchEntities($entities, array $data, array $options = [])
 * @method \CakeDC\Forum\Model\Entity\Thread findOrCreate($search, callable $callback = null, $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class ThreadsTable extends Table
{
    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('forum_posts');
        $this->setDisplayField('title');
        $this->setPrimaryKey('id');

        $this->belongsTo('Categories', [
            'className' => 'CakeDC/Forum.Categories',
            'joinType' => 'INNER',
        ]);
        $this->hasMany('Replies', [
            'className' => 'CakeDC/Forum.Replies',
            'foreignKey' => 'parent_id',
            'dependent' => true,
            'cascadeCallbacks' => true,
        ]);
        $this->hasOne('UserReplies', [
            'className' => 'CakeDC/Forum.Replies',
            'foreignKey' => 'parent_id',
        ]);
        $this->belongsTo('LastReplies', [
            'className' => 'CakeDC/Forum.Posts',
            'foreignKey' => 'last_reply_id',
        ]);
        $this->hasOne('ReportedReplies', [
            'className' => 'CakeDC/Forum.Replies',
            'foreignKey' => 'parent_id',
            'conditions' => [
                'ReportedReplies.reports_count >' => 0,
            ],
        ]);
        $this->hasMany('Reports', [
            'className' => 'CakeDC/Forum.Reports',
            'foreignKey' => 'post_id',
        ]);
        $this->hasMany('Likes', [
            'className' => 'CakeDC/Forum.Likes',
            'foreignKey' => 'post_id',
        ]);
        $this->belongsTo('Users', [
            'className' => Configure::read('Forum.userModel'),
            'joinType' => 'INNER',
        ]);

        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'created' => 'new',
                    'last_reply_created' => 'new',
                    'modified' => 'always',
                ],
            ],
        ]);
        $this->addBehavior('Muffin/Slug.Slug');
        $this->addBehavior('Muffin/Orderly.Orderly', [
            'order' => [
                $this->aliasField('is_sticky') => 'DESC',
                $this->aliasField('last_reply_created') => 'DESC',
            ],
        ]);

        $options = [
            'Categories' => [
                'threads_count',
                'last_post_id' => function ($event, Thread $entity, ThreadsTable $table) {
                    $Posts = TableRegistry::getTableLocator()->get('CakeDC/Forum.Posts');
                    $lastPost = $Posts->find()->where(['category_id' => $entity->category_id])->orderDesc('id')->first();
                    if (!$lastPost) {
                        return null;
                    }

                    return $lastPost['id'];
                },
            ],
        ];
        $userPostsCountField = Configure::read('Forum.userPostsCountField');
        if ($userPostsCountField) {
            $options['Users'] = [$userPostsCountField => ['all' => true]];
        }
        $this->addBehavior('CounterCache', $options);
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
            ->integer('id')
            ->allowEmptyString('id', 'create');

        $validator
            ->notEmptyString('category_id');

        $validator
            ->requirePresence('title', 'create')
            ->notEmptyString('title');

        $validator
            ->allowEmptyString('slug');

        $validator
            ->requirePresence('message', 'create')
            ->notEmptyString('message');

        $validator
            ->boolean('is_sticky')
            ->allowEmptyString('is_sticky');

        $validator
            ->boolean('is_locked')
            ->allowEmptyString('is_locked');

        $validator
            ->boolean('is_visible')
            ->allowEmptyString('is_visible');

        return $validator;
    }

    /**
     * Admin move Thread validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationMoveThread(Validator $validator)
    {
        $validator
            ->requirePresence('category_id')
            ->notEmptyString('category_id');

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['category_id'], 'Categories'));
        $rules->add($rules->existsIn(['user_id'], 'Users'));

        return $rules;
    }

    /**
     * beforeFind callback
     *
     * @param \Cake\Event\Event $event Event
     * @param \Cake\ORM\Query $query Query
     * @param \ArrayObject $options Options
     * @param bool $primary Primary
     *
     * @return void
     */
    public function beforeFind(EventInterface $event, Query $query, ArrayObject $options, $primary): void
    {
        if (!Hash::get($options, 'all')) {
            $query->where([$query->newExpr()->isNull($this->aliasField('parent_id'))]);
        }
    }

    /**
     * afterSave callback
     *
     * @param \Cake\Event\Event $event Event
     * @param \Cake\Datasource\EntityInterface $entity Entity
     * @param \ArrayObject $options Options
     *
     * @return void
     */
    public function afterSave(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
    {
        if ($entity->isDirty('category_id')) {
            $this->Replies->find()->where(['parent_id' => $entity['id']])->all()->each(function (Reply $reply) use ($entity) {
                $reply->category_id = $entity->get('category_id');
                $this->Replies->saveOrFail($reply);
            });
        }

        if ($entity->isNew()) {
            $entity->set('last_reply_id', $entity['id']);
            $this->save($entity);
        }
    }

    /**
     * Find threads by category
     *
     * @param \Cake\ORM\Query $query The query builder.
     * @param array $options Options.
     * @return \Cake\ORM\Query
     */
    public function findByCategory(Query $query, $options = [])
    {
        $categoryId = Hash::get($options, 'category_id');
        if (!$categoryId) {
            throw new InvalidArgumentException('category_id is required');
        }

        return $query
            ->where([
                $this->aliasField('category_id') => $categoryId,
            ])
            ->contain(['Users', 'LastReplies' => ['Users'], 'ReportedReplies'])
            ->group($this->aliasField('id'));
    }

    /**
     * Find threads user has started or participated in
     *
     * @param \Cake\ORM\Query $query The query builder.
     * @param array $options Options.
     * @return \Cake\ORM\Query
     */
    public function findByUser(Query $query, $options = [])
    {
        $userId = Hash::get($options, 'user_id');
        if (!$userId) {
            throw new InvalidArgumentException('user_id is required');
        }

        return $query
            ->contain([
                'Users',
                'LastReplies' => ['Users'],
                'ReportedReplies',
                'Categories',
                'UserReplies' => function (Query $q) use ($userId) {
                    return $q->where(['UserReplies.user_id' => $userId]);
                },
            ])
            ->where([
                'OR' => [
                    $this->aliasField('user_id') => $userId,
                    'UserReplies.user_id' => $userId,
                ],
            ])
            ->group($this->aliasField('id'));
    }

    /**
     * Find threads for edit
     *
     * @param \Cake\ORM\Query $query The query builder.
     * @param array $options Options.
     * @return \Cake\ORM\Query
     */
    public function findForEdit(Query $query, $options = [])
    {
        $categoryId = Hash::get($options, 'category_id');
        if (!$categoryId) {
            throw new InvalidArgumentException('category_id is required');
        }
        $slug = Hash::get($options, 'slug');
        if (!$slug) {
            throw new InvalidArgumentException('slug is required');
        }

        return $query
            ->where([$this->aliasField('category_id') => $categoryId])
            ->find('slugged', compact('slug'));
    }
}
