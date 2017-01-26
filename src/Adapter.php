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
        $selection = [];

        if (!$query->getSelection()) {
            foreach ($entityReflection->getProperties() as $property) {

                // Exclude associations & computed properties
                if (!$property->hasOption(Reflection\Property::OPTION_ASSOC)
                    && !$property->hasOption(Reflection\Property::OPTION_COMPUTED)
                ) {
                    $selection[$property->getName()] = $property->getName(true);
                }
            }
        } else {
            // Unmap all names
            foreach ($query->getSelection() as $name) {
                $property = $entityReflection->getProperty($name);
                $selection[$property->getName()] = $property->getName(true);
            }

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

}