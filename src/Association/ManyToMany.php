<?php

namespace UniMapper\Association;

use UniMapper\Adapter;
use UniMapper\Connection;
use UniMapper\Entity;
use UniMapper\Exception;

class ManyToMany extends Multi
{

    public function __construct(
        $propertyName,
        Entity\Reflection $sourceReflection,
        Entity\Reflection $targetReflection,
        array $mapBy,
        $dominant = true
    ) {
        parent::__construct(
            $propertyName,
            $sourceReflection,
            $targetReflection,
            $mapBy,
            $dominant
        );

        if (!$targetReflection->hasPrimary()) {
            throw new Exception\AssociationException(
                "Target entity must have defined primary when M:N relation used!"
            );
        }

        if (!isset($mapBy[0])) {
            throw new Exception\AssociationException(
                "You must define join key!"
            );
        }

        if (!isset($mapBy[1])) {
            throw new Exception\AssociationException(
                "You must define join resource!"
            );
        }

        if (!isset($mapBy[2])) {
            throw new Exception\AssociationException(
                "You must define referencing key!!"
            );
        }
    }

    /**
     * Column on join resource with targeting source referencing key
     *
     * @return int|string
     */
    public function getJoinReferencingKey() {
        return $this->mapBy[0];
    }

    /**
     * Column on join resource with targeting target referenced key
     *
     * @return int|string
     */
    public function getJoinReferencedKey() {
        return $this->mapBy[2];
    }

    /**
     * Join resource name (join table name etc...)
     *
     * @return string
     */
    public function getJoinResource()
    {
        return$this->mapBy[1];
    }

    public function getReferencingKey()
    {
        return $this->getPrimaryKey();
    }

    public function getReferencedKey() {
        return $this->targetReflection->getPrimaryProperty()->getName(true);
    }

    public function isDominant()
    {
        return $this->dominant;
    }

    /**
     * @todo should be optimized with 1 query only on same adapters
     */
    public function load(Connection $connection, array $primaryValues, array $selection = [])
    {
        $currentAdapter = $connection->getAdapter($this->sourceReflection->getAdapterName());
        $targetAdapter = $connection->getAdapter($this->targetReflection->getAdapterName());

        if (!$this->isDominant()) {
            $currentAdapter = $targetAdapter;
        }

        $joinQuery = $currentAdapter->createSelect(
            $this->getJoinResource(),
            [$this->getJoinReferencingKey(), $this->getJoinReferencedKey()]
        );
        $joinQuery->setFilter(
            [$this->getJoinReferencingKey() => [Entity\Filter::EQUAL => $primaryValues]]
        );

        $joinResult = $currentAdapter->execute($joinQuery);

        if (!$joinResult) {
            return [];
        }

        $joinResult = $this->groupResult(
            $joinResult,
            [
                $this->getJoinReferencedKey(),
                $this->getJoinReferencingKey()
            ]
        );

        $targetQuery = $targetAdapter->createSelect(
            $this->getTargetResource(),
            $selection,
            $this->orderBy,
            $this->limit,
            $this->offset
        );

        // Set target conditions
        $filter = $this->filter;
        $filter[$this->getReferencedKey()][Entity\Filter::EQUAL] = array_keys($joinResult);
        if ($this->getTargetFilter()) {
            $filter = array_merge($connection->getMapper()->unmapFilter($this->getTargetReflection(), $this->getTargetFilter()), $filter);
        }
        $targetQuery->setFilter($filter);

        $targetResult = $targetAdapter->execute($targetQuery);
        if (!$targetResult) {
            return [];
        }

        $targetResult = $this->groupResult(
            $targetResult,
            [$this->getReferencedKey()]
        );

        $result = [];
        foreach ($joinResult as $targetKey => $join) {

            foreach ($join as $originKey => $data) {
                if (!isset($targetResult[$targetKey])) {
                    throw new \Exception(
                        "Can not merge associated result key '" . $targetKey
                        . "' not found in result from '"
                        . $this->getTargetResource()
                        . "'! Maybe wrong value in join resource."
                    );
                }
                $result[$originKey][] = $targetResult[$targetKey];
            }
        }

        return $result;
    }

    /**
     * Save changes in target collection
     *
     * @param string            $primaryValue Primary value from source entity
     * @param Connection        $connection
     * @param Entity\Collection $collection   Target collection
     */
    public function saveChanges($primaryValue, Connection $connection, $collection)
    {
        $sourceAdapter = $connection->getAdapter($this->sourceReflection->getAdapterName());
        $targetAdapter = $connection->getAdapter($this->targetReflection->getAdapterName());

        if ($this->isRemote() && !$this->isDominant()) {
            $sourceAdapter = $targetAdapter;
        }

        $this->_save($primaryValue, $sourceAdapter, $targetAdapter, $collection);
        $this->_save(
            $primaryValue,
            $sourceAdapter,
            $targetAdapter,
            $collection,
            Adapter\IAdapter::ASSOC_REMOVE
        );
    }

    private function _save(
        $primaryValue,
        Adapter $joinAdapter,
        Adapter $targetAdapter,
        Entity\Collection $collection,
        $action = Adapter\IAdapter::ASSOC_ADD
    ) {
        if ($action === Adapter\IAdapter::ASSOC_REMOVE) {

            $assocKeys = $collection->getChanges()[Entity::CHANGE_DETACH];
            foreach ($collection->getChanges()[Entity::CHANGE_REMOVE] as $targetPrimary) {

                $targetAdapter->execute(
                    $targetAdapter->createDeleteOne(
                        $this->targetReflection->getAdapterResource(),
                        $this->getReferencedKey(),
                        $targetPrimary
                    )
                );
                $assocKeys[] = $targetPrimary;
            }
        } else {

            $assocKeys = $collection->getChanges()[Entity::CHANGE_ATTACH];
            foreach ($collection->getChanges()[Entity::CHANGE_ADD] as $entity) {

                $assocKeys[] = $targetAdapter->execute(
                    $targetAdapter->createInsert(
                        $this->targetReflection->getAdapterResource(),
                        $entity->getData(),
                        $this->getReferencedKey()
                    )
                );
            }
        }

        if ($assocKeys) {

            $adapterQuery = $joinAdapter->createModifyManyToMany(
                $this,
                $primaryValue,
                array_unique($assocKeys),
                $action
            );
            $joinAdapter->execute($adapterQuery);
        }
    }

}