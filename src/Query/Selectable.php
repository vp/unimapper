<?php

namespace UniMapper\Query;

use UniMapper\Association;
use UniMapper\Entity\Filter;
use UniMapper\Exception;
use UniMapper\Entity\Reflection;

trait Selectable
{
    
    public static $KEY_SELECTION = 'selection';
    public static $KEY_FILTER = 'filter';
    public static $KEY_SELECTION_FULL = 'full';
    
    /** @var array */
    protected $associations = [
        "local" => [],
        "remote" => []
    ];

    /** @var array */
    protected $selection = [];

    public function associate($args)
    {
        foreach (func_get_args() as $arg) {

            if (!is_array($arg)) {
                $arg = [$arg];
            }

            foreach ($arg as $key => $name) {

                $selection = null;
                $filter = null;
                if (is_string($key) && is_array($name)) {
                    $selection = isset($name[self::$KEY_SELECTION]) ? $name[self::$KEY_SELECTION] : [];
                    $filter = isset($name[self::$KEY_FILTER]) ? $name[self::$KEY_FILTER] : [];
                    $name = $key;
                }

                if (!$this->entityReflection->hasProperty($name)) {
                    throw new Exception\QueryException(
                        "Property '" . $name . "' is not defined on entity "
                        . $this->entityReflection->getClassName() . "!"
                    );
                }

                $property = $this->entityReflection->getProperty($name);
                if (!$property->hasOption(Reflection\Property::OPTION_ASSOC)) {
                    throw new Exception\QueryException(
                        "Property '" . $name . "' is not defined as association"
                        . " on entity " . $this->entityReflection->getClassName()
                        . "!"
                    );
                }

                $association = $property->getOption(Reflection\Property::OPTION_ASSOC);
                if ($selection) {
                    if (is_string($selection)) {
                        if (is_string($selection)) {
                            switch ($selection) {
                                case self::$KEY_SELECTION_FULL:
                                    $selection = [];
                                    foreach ($association->getTargetReflection()->getProperties() as $property) {
                                        $selection[] = $property->getName(true);
                                    }
                                    break;
                            }
                        }
                    }
                    $association->setTargetSelection($selection);
                }
                
                if ($filter) {
                    $association->setTargetFilter($filter);
                }
                if ($association->isRemote()) {
                    $this->associations["remote"][$name] = $association;
                } else {
                    $this->associations["local"][$name] = $association;
                }
            }
        }

        return $this;
    }

    public function select($args)
    {
        foreach (func_get_args() as $arg) {

            if (!is_array($arg)) {
                $arg = [$arg];
            }

            $this->selection = $arg;
        }

        return $this;
    }

    /**
     * Unmap given selection
     *
     * @param \UniMapper\Connection $connection
     * @param array                 $selection
     *
     * @return array
     */
    protected function unmapSelection(\UniMapper\Connection $connection, array $selection)
    {
        $mapper = $connection->getMapper();

        $selection = $mapper->unmapSelection($this->getEntityReflection(), $selection, $this->associations['local']);

        // Add required keys from remote associations
        foreach ($this->associations['remote'] as $association) {

            if (($association instanceof Association\ManyToOne || $association instanceof Association\OneToOne)
                && !in_array($association->getReferencingKey(), $selection, true)
            ) {
                $selection[] = $association->getReferencingKey();
            }
        }

        return $selection;
    }

    /**
     * Prepare selection for query
     *
     * @return array
     */
    protected function prepareSelection()
    {
        if (empty($this->selection)) {
            // select entity properties
            $selection = \UniMapper\Entity\Selection::generateEntitySelection($this->getEntityReflection());
        } else {
            // use provided
            $selection = $this->selection;
        }

        // select associations properties
        /** @var \UniMapper\Association $association */
        foreach (array_merge($this->associations['local'], $this->associations['remote']) as $association) {
            // if no selection for associated property provided
            if (!isset($selection[$association->getPropertyName()]) || !$association->getTargetSelection()) {
                $targetSelection = $association->getTargetSelection();
                $targetReflection = $association->getTargetReflection();

                if (!$targetSelection) {
                    // no target selection provided get full
                    $targetSelection = \UniMapper\Entity\Selection::generateEntitySelection($targetReflection);
                }

                // set it
                $selection[$association->getPropertyName()] = $targetSelection;
            }
        }

        // normalize result selection for query
        return \UniMapper\Entity\Selection::normalizeEntitySelection($this->getEntityReflection(), $selection);
    }

    protected function createQuerySelection(\UniMapper\Connection $connection)
    {
        $selection = $this->prepareSelection();
        return $this->unmapSelection($connection, $selection);
    }


}