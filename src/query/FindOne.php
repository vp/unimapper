<?php

namespace UniMapper\Query;

use UniMapper\Query\IConditionable,
    UniMapper\Exceptions\PropertyTypeException,
    UniMapper\Exceptions\QueryException,
    UniMapper\Reflection;

class FindOne extends \UniMapper\Query implements IConditionable
{

    /** @var mixed */
    public $primaryValue;

    /** @var array */
    private $associations = [
        "local" => [],
        "remote" => []
    ];

    public function __construct(Reflection\Entity $entityReflection, array $mappers, $primaryValue)
    {
        parent::__construct($entityReflection, $mappers);

        if (!$entityReflection->hasPrimaryProperty()) {
            throw new QueryException("Can not use findOne() on entity without primary property!");
        }

        try {
            $entityReflection->getPrimaryProperty()->validateValue($primaryValue);
        } catch (PropertyTypeException $exception) {
            throw new QueryException($exception->getMessage());
        }

        $this->primaryValue = $primaryValue;
    }

    public function associate($propertyName)
    {
        if (!isset($this->entityReflection->getProperties()[$propertyName])) {
            throw new QueryException("Property '" . $propertyName . "' not defined!");
        }

        $property = $this->entityReflection->getProperties()[$propertyName];
        if (!$property->isAssociation()) {
            throw new QueryException("Property '" . $propertyName . "' is not defined as association!");
        }

        $association = $property->getAssociation();
        if ($association->isRemote()) {
            $this->associations["remote"][$property->getName()] = $association;
        } else {
            $this->associations["local"][$property->getName()] = $association;
        }

        return $this;
    }

    public function onExecute(\UniMapper\Mapper $mapper)
    {
        $result = $mapper->findOne(
            $this->entityReflection->getMapperReflection()->getResource(),
            $this->entityReflection->getPrimaryProperty()->getMappedName(),
            $this->primaryValue,
            $this->associations["local"]
        );

        if (!$result) {
            return false;
        }

        return $mapper->mapEntity($this->entityReflection->getClassName(), $result);
    }

}