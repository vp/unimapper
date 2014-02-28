<?php

namespace UniMapper\Query;

use UniMapper\Reflection\EntityReflection,
    UniMapper\Exceptions\QueryException,
    UniMapper\Query\Object\Condition,
    UniMapper\Utils\Property;

/**
 * Update query object
 */
class Update extends \UniMapper\Query implements \UniMapper\Query\IConditionable
{

    public $entity;

    /**
     * Constructor
     *
     * @param \UniMapper\Entity $entity Input entity
     *
     * @return void
     */
    public function __construct(EntityReflection $entityReflection, array $mappers, array $data)
    {
        parent::__construct($entityReflection, $mappers);
        $class = $entityReflection->getName();
        $this->entity = new $class(null, $data);
    }

    protected function onExecute()
    {
        if (count($this->conditions) === 0) {
            throw new QueryException("At least one condition must be set!");
        }

        // @todo primary property
        $primaryProperty = $this->entityReflection->getPrimaryProperty();
        if ($primaryProperty === null) {
            throw new QueryException("Entity does not have primary property!");
        }

        // Ignore primary proparty value
        if (isset($this->entity->{$primaryProperty->getName()})) {
            unset($this->entity->{$primaryProperty->getName()});
        }

        if ($this->entityReflection->isHybrid()) {
            return $this->updateHybrid($primaryProperty);
        } else {
            return $this->update();
        }
    }

    private function update()
    {
        foreach ($this->entityReflection->getMappers() as $mapperName => $mapperReflection) {
            return $this->mappers[$mapperName]->update($this);
        }
    }

    private function updateHybrid(Property $primaryProperty)
    {
        // Try to get appropriate records first
        $query = new FindAll($this->entityReflection, $this->mappers, array($primaryProperty->getName()));
        $query->conditions = $this->conditions;
        $entities = $query->execute();

        if ($entities === false) {
            return false;
        }

        $status = false;
        $this->conditions = array(new Condition($primaryProperty->getName(), "IN", $entities->getKeys()));
        foreach ($this->entityReflection->getMappers() as $mapperName => $mapperReflection) {
            if ($this->mappers[$mapperName]->update($this)) {
                $status = true;
            }
        }
        return true;
    }

}