<?php

use GraphAware\Neo4j\Client\ClientBuilder;
use rdx\graphdb\Database;

require 'inc.bootstrap.php';

$client = ClientBuilder::create()
	->addConnection('default', GRAPHENE_CONNECTION_FRIENDS)
	->build();

$db = new Database($client);
