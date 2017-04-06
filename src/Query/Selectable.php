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

    /**
     * @return Association[]
     */
    public function getLocalAssociations()
    {
        return $this->associations['local'];
    }

    /**
     * @return Association[]
     */
    public function getRemoteAssociations() {
        return $this->associations['remote'];
    }

    public function getSelection()
    {
        return $this->selection;
    }

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

    protected function prepareSelection(\UniMapper\Connection $connection)
    {
        if (empty($this->selection)) {
            $selection = \UniMapper\Entity\Selection::generateEntitySelection($this->getEntityReflection());
        } else {
            $selection = $this->selection;
        }

        foreach ($this->associations['local'] as $association) {
            if (!isset($selection[$association->getPropertyName()])) {
                $targetSelection = $association->getTargetSelection();
                $targetReflection = $association->getTargetReflection();

                if (!$targetSelection) {
                    $targetSelection = \UniMapper\Entity\Selection::generateEntitySelection($targetReflection);
                }
                $selection[$association->getPropertyName()] = $targetSelection;
            }
        }

        $selection = \UniMapper\Entity\Selection::normalizeEntitySelection($this->getEntityReflection(), $selection);
        
        return $selection;
    }

    protected function createQuerySelection(\UniMapper\Connection $connection)
    {
        $selection = $this->prepareSelection($connection);
        return $this->unmapSelection($connection, $selection);
    }


}