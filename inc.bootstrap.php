<?php

require 'vendor/autoload.php';
require 'env.php';
require 'inc.graph.php';
require 'inc.functions.php';

use GraphAware\Neo4j\Client\ClientBuilder;
use rdx\graphdb\GraphDatabase;

$client = ClientBuilder::create()
	->addConnection('default', GRAPHENE_CONNECTION_URI)
	->build();

$db = new GraphDatabase($client);
