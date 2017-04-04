<?php

namespace UniMapper;

use UniMapper\Entity\Reflection;
use UniMapper\Query\Selectable;

abstract class Adapter implements Adapter\IAdapter
{

    /** @var array */
    private $afterExecute = [];

    /** @var array */
    private $beforeExecute = [];

    /** @var Adapter\Mapping */
    private $mapping;

    public function __construct(Adapter\Mapping $mapping = null)
    {
        $this->mapping = $mapping;
    }

    final public function getMapping()
    {
        return $this->mapping;
    }

    public function beforeExecute(callable $callback)
    {
        $this->beforeExecute[] = $callback;
    }

    final public function execute(Adapter\IQuery $query)
    {
        foreach ($this->beforeExecute as $callback) {
            $callback($query);
        }

        $result = $this->onExecute($query);

        foreach ($this->afterExecute as $callback) {
            $callback($query, $result);
        }

        return $result;
    }

    public function afterExecute(callable $callback)
    {
        $this->afterExecute[] = $callback;
    }

    private function _generateSelection(Reflection $entityReflection) {
        $selection = [];
        foreach ($entityReflection->getProperties() as $property) {

            // Exclude associations & computed properties
            if (!$property->hasOption(Reflection\Property::OPTION_ASSOC)
                && !$property->hasOption(Reflection\Property::OPTION_COMPUTED)
                && !$property->hasOption('map-exclude')
            ) {
                if ($property->getType() === Reflection\Property::TYPE_COLLECTION || $property->getType() === Reflection\Property::TYPE_ENTITY) {
                    $targetReflection = \UniMapper\Entity\Reflection::load($property->getTypeOption());
                    $selection[] = [$property->getName(), $this->_generateSelection($targetReflection)];
                } else {
                    $selection[] = $property->getName();
                }

            }
        }
        return $selection;
    }



    /**
     * Create selection for query
     *
     * @param \UniMapper\Query|Selectable $query
     *
     * @return array
     */
    public function createSelection(Query $query)
    {

        $entityReflection = $query->getEntityReflection();

        if (!$query->getSelection()) {
            $querySelection = $this->_generateSelection($entityReflection);
        } else {
            $querySelection = $query->getSelection();
        }
//        $test = $this->_generateSelection($entityReflection);
//$testSelection =  $this->_unmapSelection($entityReflection, $test);
        // Unmap all names
        $selection = $this->_unmapSelection($entityReflection, $querySelection);

        // Include primary automatically if not provided
        if ($entityReflection->hasPrimary()) {

            $primaryName = $entityReflection
                ->getPrimaryProperty()
                ->getName();

            if (!isset($selection[$primaryName])) {
                $selection[$primaryName] = $entityReflection
                    ->getPrimaryProperty()->getName(true);
            }
        }

        // Add required keys from remote associations
        foreach ($query->getRemoteAssociations() as $association) {

            if (($association instanceof Association\ManyToOne || $association instanceof Association\OneToOne)
                && !in_array($association->getReferencingKey(), $selection, true)
            ) {
                $selection[] = $association->getReferencingKey();
            }
        }

        return $selection;
    }

    private function _unmapSelection($entityReflection, $selection)
    {
        $unmapSelection = [];
        foreach ($selection as $name) {
            if (is_array($name)) {
                $property = $entityReflection->getProperty($name[0]);
                $targetReflection =  \UniMapper\Entity\Reflection::load($property->getTypeOption());
                if (isset($unmapSelection[$property->getName()])) {
                    $unmapSelection[$property->getName()][$property->getName(true)] = array_merge($unmapSelection[$property->getName()], $this->_unmapSelection($targetReflection, $name[1]));
                } else {
                    $unmapSelection[$property->getName()] = [$property->getName(true) => $this->_unmapSelection($targetReflection, $name[1])];
                }
            } else {
                $property = $entityReflection->getProperty($name);
                $unmapSelection[$property->getName()] = $property->getName(true);
            }
        }
        return $unmapSelection;
    }
}