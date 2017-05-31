<?php

namespace UniMapper\Query;

use UniMapper\Exception;
use UniMapper\Entity\Reflection;

/**
 * Selectable trait
 *
 * @property Reflection $entityReflection
 */
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

    /** @var  array */
    protected $querySelection;

    public function associate($args)
    {
        $this->querySelection = null;
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
                if (!$property->hasAssociation()) {
                    throw new Exception\QueryException(
                        "Property '" . $name . "' is not defined as association"
                        . " on entity " . $this->entityReflection->getClassName()
                        . "!"
                    );
                }

                $association = $property->getAssociation();
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
        $this->querySelection = null;

        if (func_num_args() > 1) {
            $this->selection = func_get_args();
        } else if ($args) {
            if (!is_array($args)) {
                $args = [$args];
            }
            if (count($args) === 1 && isset($args[0]) && is_array($args[0])) {
                $args = $args[0];
            }
            $this->selection = $args;
        } else {
            $this->selection = [];
        }

        \UniMapper\Entity\Selection::validateInputSelection($this->entityReflection, $this->selection);

        return $this;
    }
    /**
     * Return's final query selection
     *
     * Generates full selection for all entity properties if no selection provided
     * Merge or generate selection for associations
     *
     * @return array
     */
    public function getQuerySelection()
    {
        if (!$this->querySelection) {
            if (empty($this->selection)) {
                // select entity properties
                $selection = \UniMapper\Entity\Selection::generateEntitySelection($this->entityReflection);
            } else {
                // use provided
                $selection = \UniMapper\Entity\Selection::checkEntitySelection($this->entityReflection, $this->selection);
            }

            // select associations properties
            /** @var \UniMapper\Association $association */
            foreach (array_merge($this->associations['local'], $this->associations['remote']) as $association) {
                //- get from selection
                $targetSelection = isset($selection[$association->getPropertyName()]) ? $selection[$association->getPropertyName()] : [];

                //- look if is set on association annotation
                if ($association->getTargetSelection()) {
                    $targetSelection = array_unique(array_merge($targetSelection, $association->getTargetSelection()));
                }

                // if no selection for associated property provided
                if (!$targetSelection) {
                    //- then generate it
                    $targetReflection = $association->getTargetReflection();
                    $targetSelection = \UniMapper\Entity\Selection::generateEntitySelection($targetReflection);
                }

                // set's association target selection (to be select on association fetch)
                // important for local selections witch are handled internally in adapters
                $association->setTargetSelection($targetSelection);

                // set it
                $selection[$association->getPropertyName()] = $targetSelection;
                
            }
            
            $this->querySelection = $selection;
        }

        return $this->querySelection;
    }

    /**
     * Create adapter selection
     *
     * @param \UniMapper\Mapper $mapper Mapper instance
     *
     * @return array Unmapped selection for adapter query
     */
    public function createAdapterSelection(\UniMapper\Mapper $mapper)
    {
        return \UniMapper\Entity\Selection::createAdapterSelection(
            $mapper,
            $this->entityReflection,
            $this->getQuerySelection(),
            $this->associations
        );
    }

}