<?php

namespace UniMapper\Adapter;

use UniMapper\Association\ManyToMany;

interface IAdapter
{

    const ASSOC_REMOVE = "remove",
          ASSOC_ADD = "add";

    public function createDelete($resource);

    public function createSelectOne($resource, $column, $primaryValue, $selection = []);

    public function createSelect($resource, array $selection = [], array $orderBy = [], $limit = 0, $offset = 0);

    public function createCount($resource);

    public function createInsert($resource, array $values, $primaryName = null);

    public function createUpdate($resource, array $values);

    public function createUpdateOne($resource, $column, $primaryValue, array $values);

    public function createModifyManyToMany(ManyToMany $association, $primaryValue, array $keys, $action = self::ASSOC_ADD);

    public function execute(IQuery $query);

}