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
        $this->select(array_slice(func_get_args(), 3));
    }

    protected function onExecute(\UniMapper\Connection $connection)
    {
        $adapter = $connection->getAdapter($this->reflection->getAdapterName());
        $mapper = $connection->getMapper();
        $primaryProperty = $this->reflection->getPrimaryProperty();
        $selection = $this->createQuerySelection();

        $query = $adapter->createSelectOne(
            $this->reflection->getAdapterResource(),
            $primaryProperty->getUnmapped(),
            $mapper->unmapValue(
                $primaryProperty,
                $this->primaryValue
            ),
            Selection::createAdapterSelection($mapper, $this->reflection, $selection, $this->assocDefinitions)
        );

        $adapterAssociations = $this->getAdapterAssociations();
        if ($adapterAssociations) {
            $query->setAssociations($adapterAssociations);
        }

        $result = $adapter->execute($query);

        if (!$result) {
            return false;
        }

        // Create remote associations
        $remoteAssociations = $this->createRemoteAssociations();
        if ($remoteAssociations) {

            settype($result, "array");

            foreach ($remoteAssociations as $colName => $association) {

                $assocValue = $result[$association->getKey()];

                $definition = $this->assocDefinitions[$colName];

                $associationSelection = Selection::createAdapterSelection(
                    $mapper,
                    $definition->getTargetReflection(),
                    $selection[$colName]
                );

                $associationFilter = $mapper->unmapFilter(
                    $definition->getTargetReflection(),
                    $definition->getTargetFilter()
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