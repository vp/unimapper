<?php

namespace UniMapper\Cache;

interface ICache
{

    /** Options */
    const CALLBACKS = "callbacks",
          EXPIRE = "expire",
          FILES = "files",
          ITEMS = "items",
          PRIORITY = "priority",
          SLIDING = "sliding",
          TAGS = "tags";

    const TAG_QUERY = "query",
          TAG_REFLECTION = "reflection";

    public function load(\UniMapper\Query\ICachableQuery $query);

    public function save(\UniMapper\Query\ICachableQuery $query, $data, array $options = []);

}