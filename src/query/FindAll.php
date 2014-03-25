<?php

namespace UniMapper\Query;

use UniMapper\Exceptions\QueryException,
    UniMapper\Reflection,
    UniMapper\EntityCollection,
    UniMapper\Query\IConditionable;

/**
 * ORM query object
 */
class FindAll extends \UniMapper\Query implements IConditionable
{

    public $limit = 0;
    public $offset = 0;
    public $orderBy = array();
    public $selection = array();

    public function __construct(Reflection\Entity $entityReflection, array $mappers)
    {
        parent::__construct($entityReflection, $mappers);

        // Set selection
        $this->selection = array_slice(func_get_args(), 2);

        // Add primary property automatically if not set in selection
        if (count($this->selection) > 0) {
            $primaryPropertyName = $entityReflection->getPrimaryProperty()->getName();
            if (!in_array($primaryPropertyName, $this->selection)) {
                $this->selection[] = $primaryPropertyName;
            }
        }
    }

    public function limit($limit)
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset($offset)
    {
        $this->offset = $offset;
        return $this;
    }

    public function orderBy($propertyName, $direction = "asc")
    {
        if (!$this->entityReflection->hasProperty($propertyName)) {
            throw new QueryException("Invalid property name '" . $propertyName . "'!");
        }

        $direction = strtolower($direction);
        if ($direction !== "asc" && $direction !== "desc") {
            throw new QueryException("Order direction must be 'asc' or 'desc'!");
        }
        $this->orderBy[] = array($propertyName, $direction);
        return $this;
    }

    public function onExecute()
    {
        // Add properties from conditions to the selection if not set
        if (count($this->conditions) > 0 && count($this->selection) > 0) {
            foreach ($this->conditions as $condition) {

                list($propertyName) = $condition;
                if (!in_array($propertyName, $this->selection)) {
                    $this->selection[] = $propertyName;
                }
            }
        }

        if ($this->entityReflection->isHybrid()) {
            return $this->executeHybrid();
        }

        foreach ($this->entityReflection->getMappers() as $mapperName => $mapperReflection) {

            $result = $this->mappers[$mapperName]->findAll($this);
            if ($result === false) {
                return $this->mappers[$mapperName]->createCollection($this->entityReflection->getName(), array());
            }
            return $result;
        }
    }

    public function executeHybrid()
    {
        $result = false;

        foreach ($this->entityReflection->getMappers() as $mapperName => $mapperReflection) {

            $mapper = $this->mappers[$mapperName];

            if ($result instanceof EntityCollection && $this->entityReflection->getPrimaryProperty()) {
                $this->conditions["hybrid"] = array(
                    $this->entityReflection->getPrimaryProperty()->getName(),
                    "IN",
                    $this->getPrimaryValuesFromCollection($result),
                    "AND"
                );
            }

            $data = $mapper->findAll($this);

            if (isset($this->conditions["hybrid"])) {
                unset($this->conditions["hybrid"]);
            }

            if ($data === false) {
                continue;
            }

            if ($result instanceof EntityCollection && $data instanceof EntityCollection) {
                // There are some results from previous queries, so merge it
                $result->merge($data);
            } else {
                $result = $data;
            }
        }

        if ($result === false) {
            return $mapper->createCollection($this->entityReflection->getName(), array());
        }

        return $result;
    }

}