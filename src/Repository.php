<?php

namespace UniMapper;

use UniMapper\Entity\Filter;
use UniMapper\Entity\Reflection\Property;
use UniMapper\Exception\QueryException;
use UniMapper\Exception\RepositoryException;
use UniMapper\Convention;

abstract class Repository
{

    /** @var Connection */
    protected $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Insert/update entity
     *
     * @param Entity $entity
     * @param array  $associate
     *
     * @return \UniMapper\Entity
     * @throws \UniMapper\Exception\RepositoryException
     */
    public function save(Entity $entity)
    {
        $requiredClass = Convention::nameToClass($this->getEntityName(), Convention::ENTITY_MASK);
        if (!$entity instanceof $requiredClass) {
            throw new Exception\RepositoryException(
                "Entity must be instance of ". $requiredClass . "!"
            );
        }

        $reflection = $entity::getReflection();
        if (!$reflection->hasPrimary()) {
            throw new Exception\RepositoryException(
                "Can not save entity without primary property!"
            );
        }

        $primaryName = $reflection->getPrimaryProperty()->getName();
        $primaryValue = $entity->{$primaryName};

        if ($primaryValue === null) {

            $primaryValue = $this->create($entity);
            if (Entity\Reflection\Property\Option\Primary::isEmpty($primaryValue)) {
                throw new RepositoryException(
                    "Entity was successfully created but returned primary is empty!"
                );
            }
            $entity->{$primaryName} = $primaryValue;
        } else {
            $this->update($entity, $primaryValue);
        }

        return $entity;
    }

    /**
     * Create a new record
     *
     * @param Entity $entity
     *
     * @return mixed
     * @throws \UniMapper\Exception\RepositoryException
     * @throws \UniMapper\Exception\ValidatorException
     */
    public function create(Entity $entity)
    {
        if (!$entity->getValidator()->validate()) {
            throw new Exception\ValidatorException($entity->getValidator());
        }

        $values = $entity->getData();

        $reflection = $entity::getReflection();

        // Prevent to force empty primary property
        if ($reflection->hasPrimary()) {

            $primaryName = $reflection->getPrimaryProperty()->getName();

            if (empty($values[$primaryName])) {
                unset($values[$primaryName]);
            }
        }

        try {
            $primaryValue = $this->query()->insert($values)->run($this->connection);
        } catch (Exception\QueryException $e) {
            throw new Exception\RepositoryException($e->getMessage());
        }

        $this->_saveAssociated($primaryValue, $entity);

        return $primaryValue;
    }

    /**
     * Update single record
     *
     * @param Entity $entity
     * @param mixed  $primaryValue
     *
     * @throws \UniMapper\Exception\RepositoryException
     * @throws \UniMapper\Exception\ValidatorException
     */
    public function update(Entity $entity, $primaryValue)
    {
        if (!$entity->getValidator()->validate()) {
            throw new Exception\ValidatorException($entity->getValidator());
        }

        try {

            if (!$this->query()->updateOne($primaryValue, $entity->getData())->run($this->connection)) {
                throw new Exception\RepositoryException("Entity was not successfully updated!");
            }
        } catch (Exception\QueryException $e) {
            throw new Exception\RepositoryException($e->getMessage());
        }

        $this->_saveAssociated($primaryValue, $entity);
    }

    /**
     * Update records
     *
     * @param Entity $entity
     * @param array  $filter
     *
     * @return int Affected records count
     *
     * @throws Exception\RepositoryException
     * @throws Exception\ValidatorException
     */
    public function updateBy(Entity $entity, array $filter = [])
    {
        if (!$entity->getValidator()->validate()) {
            throw new Exception\ValidatorException($entity->getValidator());
        }

        try {
            return $this->query()->update($entity->getData())
                ->setFilter($filter)
                ->run($this->connection);
        } catch (Exception\QueryException $e) {
            throw new Exception\RepositoryException($e->getMessage());
        }
    }

    private function _saveAssociated($primaryValue, Entity $entity)
    {
        $reflection =  $entity::getReflection();
        foreach ($entity->getChanges() as $name => $associated) {

            $option = $reflection->getProperty($name)
                ->getOption(Property\Option\Assoc::KEY);

            Association::create($option)->saveChanges(
                $primaryValue,
                $this->connection,
                $associated
            );
        }
    }

    /**
     * Delete single record
     *
     * @param Entity $entity
     *
     * @return boolean
     *
     * @throws Exception\RepositoryException
     */
    public function destroy(Entity $entity)
    {
        $requiredClass = Convention::nameToClass($this->getEntityName(), Convention::ENTITY_MASK);
        if (!$entity instanceof $requiredClass) {
            throw new Exception\RepositoryException(
                "Entity must be instance of ". $requiredClass . "!"
            );
        }

        $reflection = $entity::getReflection();
        if (!$reflection->hasPrimary()) {
            throw new Exception\RepositoryException(
                "Can not delete entity without primary property!"
            );
        }

        $primaryName = $reflection->getPrimaryProperty()->getName();

        try {
            return $this->query()->deleteOne($entity->{$primaryName})->run($this->connection);
        } catch (Exception\QueryException $e) {
            throw new Exception\RepositoryException($e->getMessage());
        }
    }

    /**
     * Delete records
     *
     * @param array $filter
     *
     * @return int Deleted records count
     *
     * @throws Exception\RepositoryException
     */
    public function destroyBy(array $filter = [])
    {
        try {
            return $this->query()->delete()
                ->setFilter($filter)
                ->run($this->connection);
        } catch (Exception\QueryException $e) {
            throw new Exception\RepositoryException($e->getMessage());
        }
    }

    public function count(array $filter = [])
    {
        try {
            return $this->query()->count()
                ->setFilter($filter)
                ->run($this->connection);
        } catch (Exception\QueryException $e) {
            throw new Exception\RepositoryException($e->getMessage());
        }
    }

    /**
     * Find all records
     *
     * @param array $filter
     * @param array $orderBy
     * @param int   $limit
     * @param int   $offset
     * @param array $associate
     *
     * @return Entity\Collection
     *
     * @throws Exception\RepositoryException
     */
    public function find(array $filter = [], array $orderBy = [], $limit = 0,
        $offset = 0, array $associate = [], array $selection = []
    ) {
        try {

            $query = $this->query()
                ->select($selection)
                ->associate($associate)
                ->setFilter($filter);

            foreach ($orderBy as $orderByRule) {
                $query->orderBy($orderByRule[0], $orderByRule[1]);
            }

            return $query->limit($limit)->offset($offset)->run($this->connection);
        } catch (Exception\QueryException $e) {
            throw new Exception\RepositoryException($e->getMessage());
        }
    }

    public function findOne($primaryValue, array $associate = [], array $selection = [])
    {
        try {
            return $this->query()->selectOne($primaryValue, $selection)
                ->associate($associate)
                ->run($this->connection);
        } catch (QueryException $e) {
            throw new RepositoryException($e->getMessage());
        }
    }

    /**
     * Find records by set of primary values
     *
     * @param array $primaryValues
     * @param array $associate
     *
     * @return Entity\Collection
     *
     * @throws Exception\RepositoryException
     */
    public function findPrimaries(array $primaryValues, array $associate = [])
    {
        $reflection = Entity\Reflection::load($this->getEntityName());

        if (!$reflection->hasPrimary()) {
            throw new Exception\RepositoryException(
                "Method can not be used because entity " . $this->getEntityName()
                . " has no primary property defined!"
            );
        }

        if (empty($primaryValues)) {
            throw new Exception\RepositoryException(
                "Values can not be empty!"
            );
        }

        try {

            return $this->query()
                ->select()
                ->setFilter(
                    [
                        $reflection->getPrimaryProperty()->getName() => [
                            Filter::EQUAL => $primaryValues
                        ]
                    ]
                )
                ->associate($associate)
                ->run($this->connection);
        } catch (Exception\QueryException $e) {
            throw new Exception\RepositoryException($e->getMessage());
        }
    }

    /**
     * Get adapter
     *
     * @param string $name
     *
     * @return \UniMapper\Adapter
     */
    protected function getAdapter($name)
    {
        return $this->connection->getAdapters()[$name];
    }

    /**
     * Get related entity name
     *
     * @return string
     */
    public function getEntityName()
    {
        return $this->getName();
    }

    public function getName()
    {
        return Convention::classToName(get_called_class(), Convention::REPOSITORY_MASK);
    }

    /**
     * Create query
     *
     * @return QueryBuilder
     */
    public function query()
    {
        $class = Convention::nameToClass($this->getEntityName(), Convention::ENTITY_MASK);
        return $class::query();
    }

    public function executeQuery(\UniMapper\Query $query)
    {
        return $query->run($this->connection);
    }

}