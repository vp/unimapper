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

            $this->selection = $this->_parseSelection($this->getEntityReflection(), $arg);
        }

        return $this;
    }

    private function _parseSelection(\UniMapper\Entity\Reflection $entityReflection, $selection) {
        $returnSelection = [];
        $map = [];
        foreach ($selection as $index => $name) {

            if (is_array($name)) {
                $partialSelection = $name;
                $name = $index;
            } else {
                $partialSelection = null;
            }

            if (!$entityReflection->hasProperty($name)) {
                throw new Exception\QueryException(
                    "Property '" . $name . "' is not defined on entity "
                    . $entityReflection->getClassName() . "!"
                );
            }

            $property = $entityReflection->getProperty($name);
            if ($property->hasOption(Reflection\Property::OPTION_ASSOC)
                || $property->hasOption(Reflection\Property::OPTION_COMPUTED)
            ) {
                continue;
//                throw new Exception\QueryException(
//                    "Associations and computed properties can not be selected!"
//                );
            }

            if ($partialSelection) {
                $targetReflection =  \UniMapper\Entity\Reflection::load($property->getTypeOption());
                if (isset($map[$name])) {
                    $returnSelection[$map[$name]][1]
                        = array_merge( $returnSelection[$map[$name]][1], $this->_parseSelection($targetReflection, $partialSelection));
                } else {
                    $returnSelection[] = [$name, $this->_parseSelection($targetReflection, $partialSelection)];
                }
                $map[$name] = count($returnSelection)-1;
            } else if (!array_search($name, $this->selection)) {
                $returnSelection[] = $name;
            }
        }

        return $returnSelection;
    }

}