<?php

use Laudis\Neo4j\ClientBuilder;
use rdx\graphdb\Database;

require 'inc.bootstrap.php';

$client = ClientBuilder::create()
	->withDriver('neo4j', NEO4J_CONNECTION_TWITTER)
	->withDefaultDriver('neo4j')
	->build();

$db = new Database($client);
