<?php
/**
 * Created by PhpStorm.
 * User: prokes
 * Date: 6/10/17
 * Time: 10:57 PM
 */

namespace UniMapper\Entity;

use UniMapper\Entity;

/**
 * Entity iterator
 *
 * When selection provided only selected entity properties are iterated
 * otherwise only set properties are iterated
 *
 * @package UniMapper\Entity
 */
class Iterator extends \FilterIterator
{
    const ITERATE_PUBLIC = 'public';
    const ITERATE_DEFINED = 'defined';
    const ITERATE_COMPUTED = 'computed';
    const EXCLUDE_NULL = 'excludeNull';

    public static $ITERATE_OPTIONS = [
        self::ITERATE_PUBLIC => false,
        self::ITERATE_DEFINED => true,
        self::ITERATE_COMPUTED => true,
        self::EXCLUDE_NULL => true,
    ];

    /** @var \UniMapper\Entity\Reflection */
    protected $reflection;

    /** @var  \UniMapper\Entity */
    protected $entity;

    /** @var array */
    protected $selection = [];

    /** @var  callable */
    protected $mapCallback;

    protected $iteratePublic = false;

    protected $iterateDefined = true;

    protected $iterateComputed = true;

    protected $excludeNull = false;

    /**
     * @param array $selection
     *
     * @return $this
     */
    public function setSelection($selection)
    {
        $this->selection = $selection;
        return $this;
    }

    /**
     * @param callable $mapCallback
     *
     * @return $this
     */
    public function setMapCallback($mapCallback)
    {
        $this->mapCallback = $mapCallback;
        return $this;
    }

    public function __construct(Entity $entity, array $options = [])
    {
        $this->entity = $entity;
        $this->reflection = $entity::getReflection();
        $this->selection = $entity->getSelection();
        $options = array_merge(self::$ITERATE_OPTIONS, $options);

        $this->iterateDefined = $options[self::ITERATE_DEFINED];
        $this->iterateComputed = $options[self::ITERATE_COMPUTED];
        $this->iteratePublic = $options[self::ITERATE_PUBLIC];
        $this->excludeNull = $options[self::EXCLUDE_NULL];

        $iterator = new \ArrayIterator($this->init($entity));
        parent::__construct($iterator);
    }

    protected function init(Entity $entity)
    {
        $reflection = $entity::getReflection();

        $properties = array_keys($this->iterateDefined ? $reflection->getProperties() : $entity->getData());

        // include public properties
        if ($this->iteratePublic) {
            $properties = array_merge(
                $properties,
                $reflection->getPublicProperties()
            );
        }

        // include computed properties (if iterateDefined is true then they are already included)
        if (!$this->iterateDefined && $this->iterateComputed) {
            $properties = array_merge(
                $properties,
                $reflection->getComputedProperties()
            );
        }

        // we want keys to be present
        return array_combine($properties, $properties);
    }

    public function current()
    {
        $property = parent::current();
        $value = $this->entity->{$property};
        if ($this->mapCallback) {
            $value = call_user_func($this->mapCallback, $value, $this->getInnerIterator()->key());
        }
        return $value;
    }

    /**
     * Check whether the current element of the iterator is acceptable
     * @link  http://php.net/manual/en/filteriterator.accept.php
     * @return bool true if the current element is acceptable, otherwise false.
     * @since 5.1.0
     */
    public function accept()
    {
        $key = $this->getInnerIterator()->key();

        if ($this->selection) {
            // selection provided so check if in it
            $index = array_search($key, $this->selection);
            if ($index === false && isset($this->selection[$key])) {
                $index = $key;
            }
            if ($index !== false) {
                return true;
            }
        } else if ($this->iterateDefined) {
            // no selection provided so if is existing property accept it
            return true;
        }

        if ($this->excludeNull) {
            $value = $this->current();

            if ($this->iteratePublic
                && !$this->reflection->hasProperty($key)
                && $value === null
            ) {
                return false;
            }

            if ($this->iterateComputed
                && $this->reflection->hasProperty($key)
                && $this->reflection->getProperty($key)->hasOption(Entity\Reflection\Property\Option\Computed::KEY)
                && $value === null
            ) {
                return false;
            }

            return true;
        } else {
            return $this->iterateDefined;
        }
    }

    /**
     * Return's iterator as assoc array
     *
     * @return array
     */
    public function toArray()
    {
        return iterator_to_array($this, true);
    }

}