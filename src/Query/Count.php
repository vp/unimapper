<?php

namespace UniMapper\Query;

class Count extends \UniMapper\Query
{

    use Filterable;

    protected function onExecute(\UniMapper\Connection $connection)
    {
        $adapter = $connection->getAdapter($this->reflection->getAdapterName());
        $query = $adapter->createCount(
            $this->reflection->getAdapterResource()
        );

        $this->setQueryFilters($this->filter, $query, $connection);

        return (int) $adapter->execute($query);
    }

}