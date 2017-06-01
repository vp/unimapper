<?php

namespace UniMapper\Query;

use UniMapper\Entity\Reflection;
use UniMapper\Convention;
use UniMapper\Cache\ICache;
use UniMapper\Association;

class Select extends \UniMapper\Query
{

    use Filterable;
    use Limit;
    use Selectable;
    use Sortable;

    const ASC = "asc",
          DESC = "desc";

    protected $cached = false;
    protected $cachedOptions = [];

    public function __construct(Reflection $reflection)
    {
        parent::__construct($reflection);
        $this->select(array_slice(func_get_args(), 3));
    }

    public function cached($enable = true, array $options = [])
    {
        $this->cached = (bool) $enable;
        $this->cachedOptions = $options;
        return $this;
    }

    protected function onExecute(\UniMapper\Connection $connection)
    {
        $adapter = $connection->getAdapter($this->reflection->getAdapterName());
        $mapper = $connection->getMapper();
        $cache = null;

        if ($this->cached) {
            $cache = $connection->getCache();
        }

        if ($cache) {

            $cachedResult = $cache->load($this->_getQueryChecksum());
            if ($cachedResult) {
                return $mapper->mapCollection(
                    $this->reflection->getName(),
                    $cachedResult
                );
            }
        }

        $query = $adapter->createSelect(
            $this->reflection->getAdapterResource(),
            $this->createSelection(),
            $this->orderBy,
            $this->limit,
            $this->offset
        );

        $this->setQueryFilters($this->filter, $query, $connection);

        if ($this->adapterAssociations) {
            $query->setAssociations($this->adapterAssociations);
        }

        // Execute adapter query
        $result = $adapter->execute($query);

        // Get remote associations
        if ($this->remoteAssociations && !empty($result)) {

            settype($result, "array");

            foreach ($this->remoteAssociations as $colName => $association) {

                $assocKey = $association->getKey();

                $assocValues = [];
                foreach ($result as $item) {

                    if (is_array($item)) {
                        $assocValues[] = $item[$assocKey];
                    } else {
                        $assocValues[] = $item->{$assocKey};
                    }
                }

                $associated = $association->load($connection, $assocValues);

                // Merge returned associations
                if (!empty($associated)) {

                    $result = $this->_mergeAssociated(
                        $result,
                        $associated,
                        $assocKey,
                        $colName
                    );
                }
            }
        }

        if ($cache) {

            $cachedOptions = $this->cachedOptions;

            // Cache invalidation should depend on entity changes
            if (isset($cachedOptions[ICache::FILES])) {
                $cachedOptions[ICache::FILES] += $this->reflection->getRelatedFiles();
            } else {
                $cachedOptions[ICache::FILES] = $this->reflection->getRelatedFiles();
            }

            $cache->save(
                $this->_getQueryChecksum(),
                $result,
                $cachedOptions
            );
        }

        return $mapper->mapCollection(
            $this->reflection->getName(),
            empty($result) ? [] : $result
        );
    }

    /**
     * Merge associated data with result
     *
     * @param array  $result
     * @param array  $associated
     * @param string $refKey
     * @param string $colName
     *
     * @return array
     */
    private function _mergeAssociated(
        array $result,
        array $associated,
        $refKey,
        $colName
    ) {
        foreach ($result as $index => $item) {

            if (is_array($item)) {
                $refValue = $item[$refKey];
            } else {
                $refValue = $item->{$refKey};
            }

            if (isset($associated[$refValue])) {

                if (is_array($result[$index])) {
                    $result[$index][$colName] = $associated[$refValue];
                } else {
                    $result[$index]->{$colName} = $associated[$refValue];
                }
            }
        }
        return $result;
    }

    /**
     * Get a unique query checksum
     *
     * @return integer
     */
    private function _getQueryChecksum()
    {
        return md5(
            serialize(
                [
                    "name" => $this->getName(),
                    "entity" => Convention::classToName(
                        $this->reflection->getClassName(), Convention::ENTITY_MASK
                    ),
                    "limit" => $this->limit,
                    "offset" => $this->offset,
                    "selection" => $this->selection,
                    "orderBy" => $this->orderBy,
                    "adapterAssociations" => array_keys($this->adapterAssociations),
                    "remoteAssociations" => array_keys($this->remoteAssociations),
                    "conditions" => $this->filter
                ]
            )
        );
    }

    protected function createSelection()
    {
        $selection = [];

        if (empty($this->selection)) {

            foreach ($this->reflection->getProperties() as $property) {

                // Exclude associations & computed properties & disabled mapping
                if (!$property->hasOption(Reflection\Property\Option\Assoc::KEY)
                    && !$property->hasOption(Reflection\Property\Option\Computed::KEY)
                    && !($property->hasOption(Reflection\Property\Option\Map::KEY)
                        && !$property->getOption(Reflection\Property\Option\Map::KEY))
                ) {
                    $selection[] = $property->getUnmapped();
                }
            }
        } else {

            // Add properties from filter
            $selection = $this->selection;

            // Include primary automatically if not provided
            if ($this->reflection->hasPrimary()) {

                $primaryName = $this->reflection
                    ->getPrimaryProperty()
                    ->getName();

                if (!in_array($primaryName, $selection, true)) {
                    $selection[] = $primaryName;
                }
            }

            // Unmap all names
            foreach ($selection as $index => $name) {
                $selection[$index] = $this->reflection->getProperty($name)->getUnmapped();
            }
        }

        // Add required keys from remote associations
        foreach ($this->remoteAssociations as $association) {

            if (($association instanceof Association\ManyToOne || $association instanceof Association\OneToOne)
                && !in_array($association->getKey(), $selection, true)
            ) {
                $selection[] = $association->getKey();
            }
        }

        return $selection;
    }

}