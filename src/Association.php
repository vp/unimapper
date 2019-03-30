<?php

namespace UniMapper;

use UniMapper\Association\ManyToMany;
use UniMapper\Association\ManyToOne;
use UniMapper\Association\OneToMany;
use UniMapper\Association\OneToOne;
use UniMapper\Entity\Collection;
use UniMapper\Entity\Reflection;
use UniMapper\Entity\Reflection\Property\Option\Assoc;
use UniMapper\Exception\AssociationException;

class Association
{

    const JOINER = "_";

    /** @var Reflection */
    protected $sourceReflection;

    /** @var Reflection */
    protected $targetReflection;

    /** @var  array */
    private static $_customAssociations;

    /** @var array */
    private $definitions = [];
    
    /**
     * @param Reflection $sourceReflection
     * @param Reflection $targetReflection
     *
     * @throws AssociationException
     */
    public function __construct(
        Reflection $sourceReflection,
        Reflection $targetReflection,
        array $definitions = []
    ) {
        $this->sourceReflection = $sourceReflection;
        $this->targetReflection = $targetReflection;
        $this->definitions = $definitions;
    }

    public function getDefinition($name, $default = null) {
        return isset($this->definitions[$name]) ? $this->definitions[$name] : $default;
    }
    
    /**
     * Key name that refers target results to source entity
     *
     * @return string
     */
    public function getKey()
    {
        return $this->sourceReflection->getPrimaryProperty()->getUnmapped();
    }

    /**
     * @param string          $name  Assoc name/type
     * @param string|callable $value Class name or callable
     */
    public static function registerAssocType($name, $value)
    {
        self::$_customAssociations[strtolower($name)] = $value;
    }

    /**
     * @param Assoc $option
     *
     * @return Association
     *
     * @throws AssociationException
     */
    public static function create(Assoc $option)
    {
        $definition = $option->getDefinition();

        switch ($option->getType()) {
            case "m:n":
            case "m>n":
            case "m<n":
                return new ManyToMany(
                    $option->getSourceReflection(),
                    $option->getTargetReflection(),
                    $definition
                );
            case "1:1":
                return new OneToOne(
                    $option->getSourceReflection(),
                    $option->getTargetReflection(),
                    $definition
                );
            case "1:n":
                return new OneToMany(
                    $option->getSourceReflection(),
                    $option->getTargetReflection(),
                    $definition
                );
            case "n:1":
                return new ManyToOne(
                    $option->getSourceReflection(),
                    $option->getTargetReflection(),
                    $definition
                );
            default:
                if (isset(self::$_customAssociations[$option->getType()])) {
                    if (is_callable(self::$_customAssociations[$option->getType()])) {
                        return call_user_func_array(
                            self::$_customAssociations[$option->getType()],
                            [
                                $option->getSourceReflection(),
                                $option->getTargetReflection(),
                                $definition
                            ]
                        );
                    } else {
                        $class = self::$_customAssociations[$option->getType()];
                        return new $class(
                            $option->getSourceReflection(),
                            $option->getTargetReflection(),
                            $definition
                        );
                    }
                } else {
                    throw new AssociationException("Unsupported association type");
                }
        }
    }

    /**
     * Group associative array
     *
     * @param array $original
     * @param array $keys
     * @param int   $level
     *
     * @return array
     *
     * @link http://tigrou.nl/2012/11/26/group-a-php-array-to-a-tree-structure/
     *
     * @throws \Exception
     */
    public static function groupResult(array $original, array $keys, $level = 0)
    {
        $converted = [];
        $key = $keys[$level];
        $isDeepest = sizeof($keys) - 1 == $level;

        $level++;

        $filtered = [];
        foreach ($original as $k => $subArray) {

            $subArray = (array) $subArray;
            if (!isset($subArray[$key])) {
                throw new AssociationException(
                    "Index '" . $key . "' not found on level '" . $level . "'!"
                );
            }

            $thisLevel = $subArray[$key];

            if (is_object($thisLevel)) {
                $thisLevel = (string) $thisLevel;
            }

            if ($isDeepest) {
                $converted[$thisLevel] = $subArray;
            } else {
                $converted[$thisLevel] = [];
            }
            $filtered[$thisLevel][] = $subArray;
        }

        if (!$isDeepest) {
            foreach (array_keys($converted) as $value) {
                $converted[$value] = self::groupResult(
                    $filtered[$value],
                    $keys,
                    $level
                );
            }
        }

        return $converted;
    }

    /**
     * Load remote association
     *
     * @param Connection $connection
     * @param array      $primaryValues
     * @param array      $selection
     *
     * @return array
     * @throws \UniMapper\Exception\AssociationException
     */
    public function load(Connection $connection, array $primaryValues, array $selection = [], $filter = []) {
        throw new AssociationException('Load not implemented for association!');
    }

    /**
     * Save changes in target collection or entity
     *
     * @param string            $primaryValue Primary value from source entity
     * @param Connection        $connection   Connection instance
     * @param Collection|Entity $value        Target collection or entity
     *
     * @throws AssociationException
     * @return void
     */
    public function saveChanges($primaryValue, Connection $connection, $value)
    {
        throw new AssociationException('Save not implemented for association!');
    }


    /**
     * @param \UniMapper\Adapter\IQuery $query
     */
    protected function addQueryOptionFromDefinitions(\UniMapper\Adapter\IQuery $query)
    {
        $queryParameters = $this->getDefinition('query-parameters');
        if ($queryParameters) {
            $rows = explode('|', $queryParameters);
            foreach ($rows as $row) {
                list($k, $v) = explode('=', $row);
                $query->addOption($k, $v);
            }
        }
    }
}