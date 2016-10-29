<?php

use GraphAware\Neo4j\Client\ClientBuilder;
use rdx\graphdb\GraphDatabase;

require 'inc.bootstrap.php';

$client = ClientBuilder::create()
	->addConnection('default', GRAPHENE_CONNECTION_TWITTER)
	->build();

$db = new GraphDatabase($client);
