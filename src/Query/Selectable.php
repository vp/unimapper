<?php

namespace UniMapper\Query;

use UniMapper\Association;
use UniMapper\Exception;
use UniMapper\Entity\Reflection;
use UniMapper\Entity\Reflection\Property\Option\Assoc;

/**
 * Selectable trait
 *
 * @property Reflection $reflection
 */
trait Selectable
{

    public static $KEY_SELECTION = 'selection';
    public static $KEY_FILTER = 'filter';

    /** @var array */
    protected $selection = [];

    /** @var  Assoc[] */
    protected $assocDefinitions = [];

    /** @var array  */
    protected $assocSelections = [];

    /** @var array  */
    protected $assocFilters = [];

    public function associate($args)
    {
        $this->assocDefinitions = [];

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
                } else {
                    $selection = [];
                    $filter = [];
                }

                if (!$this->reflection->hasProperty($name)) {
                    throw new Exception\QueryException(
                        "Property '" . $name . "' is not defined on entity "
                        . $this->reflection->getClassName() . "!"
                    );
                }

                $property = $this->reflection->getProperty($name);
                if (!$property->hasOption(Assoc::KEY)) {
                    throw new Exception\QueryException(
                        "Property '" . $name . "' is not defined as association"
                        . " on entity " . $this->reflection->getClassName()
                        . "!"
                    );
                }


                /** @var Assoc $option */
                $option = $property->getOption(Assoc::KEY);

                $this->assocDefinitions[$name] = $option;
                $this->assocFilters[$name] = $filter;
                $this->assocSelections[$name] = $selection;
            }
        }

        return $this;
    }

    public function select($args)
    {
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

        \UniMapper\Entity\Selection::validateInputSelection($this->reflection, $this->selection);

        return $this;
    }


    /**
     * Return's query adapter associations
     *
     * @return array|\UniMapper\Entity\Reflection\Property\Option\Assoc[]
     */
    protected function getAdapterAssociations(\UniMapper\Mapper $mapper)
    {
        $associations = [];
        $associationsFilters = [];
        foreach ($this->assocDefinitions as $propertyName => $definition) {
            if (!$definition->isRemote()) {
                $associations[$propertyName] = $definition;
                $associationsFilters[$propertyName] = isset($this->assocFilters[$propertyName])
                    ? $mapper->unmapFilter(
                        $definition->getTargetReflection(),
                        $this->assocFilters[$propertyName]
                    )
                    : [];
            }
        }
        return [$associations, $associationsFilters];
    }


    /**
     * Create's and return remote associations
     *
     * @return array|\UniMapper\Association[]
     */
    protected function createRemoteAssociations()
    {
        $associations = [];
        foreach ($this->assocDefinitions as $propertyName => $definition) {
            if ($definition->isRemote()) {
                $associations[$propertyName] = Association::create(
                    $definition
                );
            }
        }
        return $associations;
    }

    /**
     * Return's final query selection
     *
     * Generates full selection for all entity properties if no selection provided
     * Merge or generate selection for associations
     *
     * @return array
     */
    public function createQuerySelection()
    {
        if (empty($this->selection)) {
            // select entity properties
            $selection = \UniMapper\Entity\Selection::generateEntitySelection($this->reflection);
        } else if (count($this->selection) === 1 && $this->selection[0] === '*' ) {
            // select all
            $selection = [];
        } else {
            // use provided
            $selection = \UniMapper\Entity\Selection::checkEntitySelection($this->reflection, $this->selection);
        }

        // Include primary automatically if not provided
        if ($this->reflection->hasPrimary()) {

            $primaryName = $this->reflection
                ->getPrimaryProperty()
                ->getName();

            if (!in_array($primaryName, $selection, true)) {
                $selection[] = $primaryName;
            }
        }
        
        return $selection;
    }

    /**
     * Create's unmapped selection for adapter query
     *
     * @param \UniMapper\Mapper                                    $mapper             Mapper instance
     * @param \UniMapper\Entity\Reflection                         $reflection         Target entity reflection
     * @param array                                                $selection          Query selection
     * @param \UniMapper\Entity\Reflection\Property\Option\Assoc[] $associations       All associations definitions
     * @param \UniMapper\Association[]                             $remoteAssociations Remote associations instances
     *
     * @return array Unmapped selection for adapter query
     */
    public static function createAdapterSelection(\UniMapper\Mapper $mapper, Reflection $reflection, array $selection = [], array $associations = [], array $remoteAssociations = [])
    {
        return \UniMapper\Entity\Selection::createAdapterSelection(
            $mapper,
            $reflection,
            $selection,
            $associations,
            $remoteAssociations
        );
    }

    protected function addAssocSelections(array $selection)
    {
        $result = $selection;
        foreach ($this->assocDefinitions as $propertyName => $definition) {
            //- get from selection
            $targetSelection = isset($result[$propertyName]) ? $result[$propertyName] : [];

            //- look if is set on association annotation
            if (isset($this->assocSelections[$propertyName]) && $this->assocSelections[$propertyName]) {
                $targetSelection = array_unique(array_merge($targetSelection, $this->assocSelections[$propertyName]));
            }

            // if no selection for associated property provided
            if (!$targetSelection) {
                //- then generate it
                $targetReflection = $definition->getTargetReflection();
                $targetSelection = \UniMapper\Entity\Selection::generateEntitySelection($targetReflection);
            }

            // set it
            $result[$propertyName] = $targetSelection;
        }
        return $result;
    }

}