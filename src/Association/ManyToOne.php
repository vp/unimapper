<?php

namespace UniMapper\Association;

use UniMapper\Association;
use UniMapper\Connection;
use UniMapper\Entity\Reflection;
use UniMapper\Exception\AssociationException;
use UniMapper\Query;
use UniMapper\Entity;
use UniMapper\Entity\Filter;

class ManyToOne extends Association
{

    /** @var string */
    private $referencingKey;

    public function __construct(
        Reflection $sourceReflection,
        Reflection $targetReflection,
        array $definition = []
    ) {
        parent::__construct($sourceReflection, $targetReflection);

        if (!$targetReflection->hasPrimary()) {
            throw new AssociationException(
                "Target entity must have defined primary for this relation!"
            );
        }

        $referencingKey = isset($definition['by']) && $definition['by'] && isset($definition['by'][0])
            ? $definition['by'][0]
            : null;

        if (!$referencingKey) {

            $referencingKey = $targetReflection->getAdapterResource()
                . self::JOINER
                . $targetReflection->getPrimaryProperty()->getUnmapped();
        }
        $this->referencingKey = $referencingKey;
    }

    public function getKey()
    {
        return $this->referencingKey;
    }

    public function load(Connection $connection, array $primaryValues, array $selection = [], $filter = [])
    {
        // Remove empty primary values
        $primaryValues = array_filter(array_unique($primaryValues));
        if (empty($primaryValues)) {
            return [];
        }

        $targetAdapter = $connection->getAdapter($this->targetReflection->getAdapterName());

        $query = $targetAdapter->createSelect(
            $this->targetReflection->getAdapterResource(),
            $selection
        );

        $targetFilter = [
            $this->targetReflection->getPrimaryProperty()->getUnmapped() => [
                Filter::EQUAL => $primaryValues
            ]
        ];

        if ($filter) {
            $targetFilter = array_merge(
                $connection->getMapper()->unmapFilter($this->targetReflection, $filter),
                $targetFilter
            );
        }

        $query->setFilter($targetFilter);

        $result = $targetAdapter->execute($query);

        if (empty($result)) {
            return [];
        }

        if (isset($result[0]) && count($result) === 1) {
            $result = $result[0];
        }

        return Association::groupResult(
            $result,
            [$this->targetReflection->getPrimaryProperty()->getUnmapped()]
        );
    }

    /**
     * Save changes in target entity
     *
     * @param string     $primaryValue Primary value from source entity
     * @param Connection $connection   Connection instance
     * @param Entity     $entity       Target entity
     *
     * @throws AssociationException
     * @return void
     */
    public function saveChanges($primaryValue, Connection $connection, $entity)
    {
        if (get_class($entity) !== $this->targetReflection->getClassName()) {
            throw new AssociationException(
                "Input entity should be instance of "
                . $this->targetReflection->getClassName()
                . " but type instance " . get_class($entity) . " given!"
            );
        }

        if (!$entity->getChangeType()) {
            return;
        }

        $sourceAdapter = $connection->getAdapter(
            $this->sourceReflection->getAdapterName()
        );

        $primaryProperty = $this->sourceReflection->getPrimaryProperty();
        $primaryName = $primaryProperty->getName();

        $mapper = $connection->getMapper();

        // Unmap primary value
        $primaryValue = $mapper->unmapValue(
            $this->sourceReflection->getPrimaryProperty(),
            $primaryValue
        );

        switch ($entity->getChangeType()) {

            case Entity::CHANGE_ATTACH:

                $sourceAdapter->execute(
                    $adapterQuery = $sourceAdapter->createUpdateOne(
                        $this->sourceReflection->getAdapterResource(),
                        $this->sourceReflection->getPrimaryProperty()->getUnmapped(),
                        $primaryValue,
                        [
                            $this->referencingKey => $mapper->unmapValue(
                                $primaryProperty,
                                $entity->{$primaryName}
                            )
                        ]
                    )
                );
                break;

            case Entity::CHANGE_ADD:

                $targetAdapter = $connection->getAdapter(
                    $this->targetReflection->getAdapterName()
                );

                $sourceAdapter->execute(
                    $sourceAdapter->createUpdateOne(
                        $this->sourceReflection->getAdapterResource(),
                        $this->sourceReflection->getPrimaryProperty()->getUnmapped(),
                        $primaryValue,
                        [
                            $this->referencingKey => $targetAdapter->execute(
                                $targetAdapter->createInsert(
                                    $this->targetReflection->getAdapterResource(),
                                    $mapper->unmapEntity($entity),
                                    $this->targetReflection->getPrimaryProperty()
                                        ->getUnmapped()
                                )
                            )
                        ]
                    )
                );

                break;

            case Entity::CHANGE_REMOVE:

                $targetAdapter = $connection->getAdapter(
                    $this->targetReflection->getAdapterName()
                );

                $targetAdapter->execute(
                    $targetAdapter->createDeleteOne(
                        $this->targetReflection->getAdapterResource(),
                        $this->targetReflection->getPrimaryProperty()->getUnmapped(),
                        $mapper->unmapValue(
                            $primaryProperty,
                            $entity->{$primaryName}
                        )
                    )
                );

                $sourceAdapter->execute(
                    $sourceAdapter->createUpdateOne(
                        $this->sourceReflection->getAdapterResource(),
                        $this->sourceReflection->getPrimaryProperty()->getUnmapped(),
                        $primaryValue,
                        [$this->referencingKey => null]
                    )
                );
                break;

            case Entity::CHANGE_DETACH:

                $sourceAdapter->execute(
                    $adapterQuery = $sourceAdapter->createUpdateOne(
                        $this->sourceReflection->getAdapterResource(),
                        $this->sourceReflection->getPrimaryProperty()->getUnmapped(),
                        $primaryValue,
                        [$this->referencingKey => null]
                    )
                );
                break;

            default:
                break;
        }
    }

}