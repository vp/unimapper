<?php

namespace UniMapper\Query;

use UniMapper\Entity\Selection;
use UniMapper\Exception,
    UniMapper\Entity\Reflection;

class SelectOne extends \UniMapper\Query
{

    use Selectable;

    /** @var mixed */
    protected $primaryValue;

    public function __construct(
        Reflection $reflection,
        $primaryValue
    ) {
        parent::__construct($reflection);

        // Primary
        if (!$reflection->hasPrimary()) {
            throw new Exception\QueryException(
                "Can not use query on entity without primary property!"
            );
        }

        try {
            $reflection->getPrimaryProperty()->validateValueType($primaryValue);
        } catch (Exception\InvalidArgumentException $e) {
            throw new Exception\QueryException($e->getMessage());
        }

        $this->primaryValue = $primaryValue;

        // Selection
        $this->select(array_slice(func_get_args(), 2));
    }

    protected function onExecute(\UniMapper\Connection $connection)
    {
        $adapter = $connection->getAdapter($this->reflection->getAdapterName());
        $mapper = $connection->getMapper();
        $primaryProperty = $this->reflection->getPrimaryProperty();
        $selection = $this->createQuerySelection();
        $finalSelection = $this->addAssocSelections($selection);
        $remoteAssociations = $this->createRemoteAssociations();

        $query = $adapter->createSelectOne(
            $this->reflection->getAdapterResource(),
            $primaryProperty->getUnmapped(),
            $mapper->unmapValue(
                $primaryProperty,
                $this->primaryValue
            ),
            $this->createAdapterSelection($mapper, $this->reflection, $finalSelection, $this->assocDefinitions, $remoteAssociations)
        );

        list($adapterAssociations, $adapterAssociationsFilters) = $this->getAdapterAssociations($mapper);
        if ($adapterAssociations) {
            $query->setAssociations($adapterAssociations, $adapterAssociationsFilters);
        }

        $result = $adapter->execute($query);

        if (!$result) {
            return false;
        }

        // Create remote associations
        if ($remoteAssociations) {

            settype($result, "array");

            foreach ($remoteAssociations as $colName => $association) {

                $assocValue = $result[$association->getKey()];

                $definition = $this->assocDefinitions[$colName];

                $associationSelection = $this->createAdapterSelection(
                    $mapper,
                    $definition->getTargetReflection(),
                    $finalSelection[$colName]
                );

                $associationFilter = $mapper->unmapFilter(
                    $definition->getTargetReflection(),
                    $this->assocFilters[$colName]
                );

                $associated = $association->load(
                    $connection,
                    [$assocValue],
                    $associationSelection,
                    $associationFilter
                );

                // Merge returned associations
                if (isset($associated[$assocValue])) {
                    $result[$colName] = $associated[$assocValue];
                }
            }
        }

        $entity = $mapper->mapEntity($this->reflection->getName(), $result);

        $entity->setSelection($selection);

        return $entity;
    }

}