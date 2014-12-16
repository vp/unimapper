<?php

namespace UniMapper\Reflection;

use UniMapper\EntityCollection,
    UniMapper\Validator,
    UniMapper\NamingConvention as UNC,
    UniMapper\Exception;

class Property
{

    const TYPE_DATETIME = "DateTime";
    const OPTION_ASSOC = "assoc",
          OPTION_ASSOC_BY = "assoc-by",
          OPTION_COMPUTED = "computed",
          OPTION_ENUM = "enum",
          OPTION_MAP = "map",
          OPTION_MAP_BY = "map-by",
          OPTION_MAP_FILTER = "map-filter",
          OPTION_PRIMARY = "primary";

    /** @var string $type */
    private $type;

    /** @var string $name */
    private $name;

    /** @var array $basicTypes */
    private $basicTypes = ["boolean", "integer", "double", "string", "array"];

    /** @var Entity */
    private $entityReflection;

    /** @var array $assocTypes List of available association types */
    private $assocTypes = [
        "M:N" => "ManyToMany",
        "M<N" => "ManyToMany",
        "M>N" => "ManyToMany",
        "N:1" => "ManyToOne",
        "1:1" => "OneToOne",
        "1:N" => "OneToMany"
    ];

    /** @var boolean $readonly */
    private $readonly = false;

    /** @var array */
    private $options = [];

    /**
     * @param string $type
     * @param string $name
     * @param Entity $entityReflection
     * @param bool   $readonly
     * @param string $options
     */
    public function __construct(
        $type,
        $name,
        Entity $entityReflection,
        $readonly = false,
        $options = null
    ) {
        $this->entityReflection = $entityReflection;
        $this->type = $this->_detectType($type);
        $this->name = $name;
        $this->options = AnnotationParser::parseOptions($options);
        $this->readonly = (bool) $readonly;

        $this->_initComputed();
        $this->_initMapping();
        $this->_initEnumeration();
        $this->_initAssociation();
    }

    public function isWritable()
    {
        return $this->readonly;
    }

    /**
     * Get list of supported basic types
     *
     * @return array
     */
    public function getBasicTypes()
    {
        return $this->basicTypes;
    }

    /**
     * Get entity reflection
     *
     * @return Entity
     */
    public function getEntityReflection()
    {
        return $this->entityReflection;
    }

    /**
     * Get property name
     *
     * @param bool $unmapped
     *
     * @return string
     */
    public function getName($unmapped = false)
    {
        if ($unmapped && $this->hasOption(self::OPTION_MAP_BY)) {
            return $this->getOption(self::OPTION_MAP_BY);
        }
        return $this->name;
    }

    /**
     * Get property type
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Detect property type from string definition
     *
     * @param string $definition
     *
     * @return mixed
     *
     * @throws Exception\PropertyException
     */
    private function _detectType($definition)
    {
        if (in_array($definition, $this->basicTypes)) {
            // Basic

            return $definition;
        } elseif ($definition === self::TYPE_DATETIME) {
            // DateTime

            return $definition;
        } elseif (class_exists(UNC::nameToClass($definition, UNC::$entityMask))) {
            // Entity

            return $this->_loadEntityReflection(
                UNC::nameToClass($definition, UNC::$entityMask)
            );
        } elseif (substr($definition, -2) === "[]") {
            // Collection

            try {
                $entityReflection = $this->_loadEntityReflection(
                    UNC::nameToClass(rtrim($definition, "[]"), UNC::$entityMask)
                );
            } catch (Exception\InvalidArgumentException $exception) {

            }
            return new EntityCollection($entityReflection);
        }

        throw new Exception\PropertyException(
            "Unsupported type '" . $definition . "'!"
        );
    }

    /**
     * Load lazy entity reflection
     *
     * @param string $entityClass
     *
     * @return Entity
     */
    private function _loadEntityReflection($entityClass)
    {
        if ($this->entityReflection->getClassName() === $entityClass) {
            return $this->entityReflection;
        } elseif (isset($this->entityReflection->getRelated()[$entityClass])) {
            return $this->entityReflection->getRelated()[$entityClass];
        }

        $related = $this->entityReflection->getRelated();
        $related[$this->entityReflection->getClassName()]
            = $this->entityReflection;

        $reflection = new Entity($entityClass, $related);

        $this->entityReflection->addRelated($reflection);

        return $reflection;
    }

    private function _initMapping()
    {
        if ($this->hasOption(self::OPTION_MAP)) {

            // Mapping disabled
            if ($this->getOption(self::OPTION_MAP) === "false") {
                return;
            }
        }

        // Init filter
        if ($this->hasOption(self::OPTION_MAP_FILTER)) {

            $filter = explode("|", $this->getOption(self::OPTION_MAP_FILTER));
            if (!isset($filter[0]) || !isset($filter[1])) {
                throw new Exception\PropertyException("Invalid filter definition!");
            }
            $this->options[self::OPTION_MAP_FILTER] = [
                $this->_createCallback($this->entityReflection->getClassName(), $filter[0]),
                $this->_createCallback($this->entityReflection->getClassName(), $filter[1])
            ];
        }
    }

    private function _initEnumeration()
    {
        if ($this->hasOption(self::OPTION_ENUM)) {

            if (!preg_match("/^\s*(\S+)::(\S*)\*\s*$/", $this->getOption(self::OPTION_ENUM), $matched)) {
                throw new Exception\PropertyException(
                    "Invalid enumeration definition!"
                );
            }

            // Find out enumeration class
            if ($matched[1] === 'self') {
                $class = $this->entityReflection->getClassName();
            } else {

                $class = $matched[1];
                if (!class_exists($class)) {
                    throw new Exception\PropertyException(
                        "Enumeration class " . $class . " not found!"
                    );
                }
            }

            $this->options[self::OPTION_ENUM] = new Enumeration($class, $matched[2]);
        }
    }

    private function _initAssociation()
    {
        if ($this->hasOption(self::OPTION_ASSOC)) {

            if ($this->hasOption(self::OPTION_MAP)
                || $this->hasOption(self::OPTION_ENUM)
                || $this->hasOption(self::OPTION_COMPUTED)
            ) {
                throw new Exception\PropertyException(
                    "Association can not be combined with mapping, computed or "
                    . "enumeration!"
                );
            }

            if (!$this->entityReflection->hasAdapter()) {
                throw new Exception\PropertyException(
                    "Can not use associations while entity "
                    . $this->entityReflection->getClassName()
                    . " has no adapter defined!"
                );
            }

            // Get target entity class
            if ($this->type instanceof EntityCollection) {
                $targetEntityReflection = $this->type->getEntityReflection();
            } elseif ($this->type instanceof Entity) {
                $targetEntityReflection = $this->type;
            } else {
                throw new Exception\PropertyException(
                    "Property type must be collection or entity if association "
                    . "defined!"
                );
            }
            if (!$targetEntityReflection->hasAdapter()) {
                throw new Exception\PropertyException(
                    "Can not use associations while target entity "
                    . $targetEntityReflection->getClassName()
                    . " has no adapter defined!"
                );
            }

            if (!$this->hasOption(self::OPTION_ASSOC)) {
                throw new Exception\PropertyException(
                    "You must define association type!"
                );
            }

            if (!$this->hasOption(self::OPTION_ASSOC_BY)) {
                throw new Exception\PropertyException(
                    "You must define association by!"
                );
            }

            $class = __NAMESPACE__ . "\Association\\"
                . $this->assocTypes[$this->getOption(self::OPTION_ASSOC)];

            try {

                $this->options[self::OPTION_ASSOC] = new $class(
                    $this,
                    $targetEntityReflection,
                    explode("|", $this->getOption(self::OPTION_ASSOC_BY)),
                    $this->getOption(self::OPTION_ASSOC) === "M<N" ? false : true
                );
            } catch (Exception\DefinitionException $e) {
                throw new Exception\PropertyException($e->getMessage());
            }
        }
    }

    private function _initComputed()
    {
        if ($this->hasOption(self::OPTION_COMPUTED)) {

            if ($this->hasOption(self::OPTION_MAP)
                || $this->hasOption(self::OPTION_ENUM)
                || $this->hasOption(self::OPTION_PRIMARY)
            ) {
                throw new Exception\PropertyException(
                    "Computed property can not be combined with mapping, "
                    . "enumeration or primary!"
                );
            }

            $method = "compute" . ucfirst($this->name);
            if (!method_exists($this->entityReflection->getClassName(), $method)) {
                throw new Exception\PropertyException(
                    "Computed method " . $this->entityReflection->getClassName()
                    . "->" . $method . " not found!"
                );
            }
            $this->options[self::OPTION_COMPUTED] = $method;
        }
    }

    /**
     * Validate value type
     *
     * @param mixed $value Given value
     *
     * @throws Exception\PropertyValueException
     * @throws \Exception
     */
    public function validateValueType($value)
    {
        $expectedType = $this->type;

        // Enumeration
        if ($this->hasOption(self::OPTION_ENUM) && !$this->hasOption(self::OPTION_ENUM)->isValid($value)) {
            throw new Exception\PropertyValueException(
                "Value " . $value . " is not from defined entity enumeration "
                . "range on property " . $this->name . "!",
                $this->entityReflection->getClassName(),
                null,
                Exception\PropertyValueException::ENUMERATION
            );
        }

        // Basic type
        if ($this->isTypeBasic()) {

            if (gettype($value) === $expectedType) {
                return;
            }
            throw new Exception\PropertyValueException(
                "Expected " . $expectedType . " but " . gettype($value)
                . " given on property " . $this->name . "!",
                $this->entityReflection->getClassName(),
                null,
                Exception\PropertyValueException::TYPE
            );
        }

        // Object validation
        $givenType = gettype($value);
        if ($givenType === "object") {
            $givenType = get_class($value);
        }

        if ($expectedType instanceof Entity) {
            // Entity

            $expectedType = $expectedType->getClassName();
            if ($value instanceof $expectedType) {
                return;
            } else {
                throw new Exception\PropertyValueException(
                    "Expected entity " . $expectedType . " but " . $givenType
                    . " given on property " . $this->name . "!",
                    $this->entityReflection->getClassName(),
                    null,
                    Exception\PropertyValueException::TYPE
                );
            }

        } elseif ($expectedType instanceof EntityCollection) {
            // Collection

            if (!$value instanceof EntityCollection) {

                throw new Exception\PropertyValueException(
                    "Expected entity collection but " . $givenType . " given on"
                    . " property " . $this->name . "!",
                    $this->entityReflection->getClassName(),
                    null,
                    Exception\PropertyValueException::TYPE
                );
            } elseif ($value->getEntityReflection()->getClassName() !== $expectedType->getEntityReflection()->getClassName()) {
                throw new Exception\PropertyValueException(
                    "Expected collection of entity "
                    . $expectedType->getEntityReflection()->getClassName()
                    . " but collection of entity "
                    . $value->getEntityReflection()->getClassName()
                    . " given on property " . $this->name . "!",
                    $this->entityReflection->getClassName(),
                    null,
                    Exception\PropertyValueException::TYPE
                );
            } else {
                return;
            }

        } elseif ($expectedType === self::TYPE_DATETIME) {
            // DateTime

            if ($value instanceof \DateTime) {
                return;
            } else {
                throw new Exception\PropertyValueException(
                    "Expected DateTime but " . $givenType . " given on"
                    . " property " . $this->name . "!",
                    $this->entityReflection->getClassName(),
                    null,
                    Exception\PropertyValueException::TYPE
                );
            }
        }

        throw new Exception\UnexpectedException(
            "Expected " . $expectedType . " but " . $givenType . " given on "
            . "property " . $this->name . ". It could be an internal ORM error!"
        );
    }

    /**
     * Try to convert value on required type automatically
     *
     * @param mixed $value
     *
     * @return mixed
     *
     * @throws Exception\InvalidArgumentException
     */
    public function convertValue($value)
    {
        if ($this->isTypeBasic()) {
            // Basic

            if ($this->type === "boolean" && strtolower($value) === "false") {
                return false;
            }

            if (settype($value, $this->type)) {
                return $value;
            }
        } elseif ($this->type === self::TYPE_DATETIME) {
            // DateTime

            if ($value instanceof \DateTime) {
                return $value;
            } elseif (is_array($value) && isset($value["date"])) {
                $date = $value["date"];
            } elseif (is_object($value) && isset($value->date)) {
                $date = $value->date;
            } else {
                $date = $value;
            }

            if (isset($date)) {
                try {
                    return new \DateTime($date);
                } catch (\Exception $e) {

                }
            }
        } elseif ($this->type instanceof EntityCollection
            && Validator::isTraversable($value)
        ) {
            // Collection

            $collection = clone $this->type;
            foreach ($value as $index => $data) {

                $collection[$index] = $this->type->getEntityReflection()
                    ->createEntity($data);
            }
            return $collection;
        } elseif ($this->type instanceof Entity
            && Validator::isTraversable($value)
        ) {
            // Entity

            return $this->type->createEntity($value);
        }

        throw new Exception\InvalidArgumentException(
            "Can not convert value on property '" . $this->name
            . "' automatically!"
        );
    }

    public function isTypeBasic()
    {
        return in_array($this->type, $this->getBasicTypes());
    }

    public function isTypeEntity()
    {
        return $this->type instanceof Entity;
    }

    public function isTypeCollection()
    {
        return $this->type instanceof EntityCollection;
    }

    private function _createCallback($class, $method)
    {
        if (method_exists($class, $method)) {
            return [$class, $method];
        } elseif (is_callable($method)) {
            return $method;
        }

        return false;
    }

    /**
     * Has option?
     *
     * @param string $key
     *
     * @return boolean
     */
    public function hasOption($key)
    {
        return array_key_exists($key, $this->options);
    }

    /**
     * Get option
     *
     * @param string $key
     *
     * @return mixed
     *
     * @throws Exception\InvalidArgumentException
     */
    public function getOption($key)
    {
        if (!$this->hasOption($key)) {
            throw new Exception\InvalidArgumentException(
                "Option " . $key . " not defined on "
                . $this->entityReflection->getClassName() . "::$"
                . $this->name . "!"
            );
        }
        return $this->options[$key];
    }

}