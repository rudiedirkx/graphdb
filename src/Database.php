<?php

namespace rdx\graphdb;

use GraphAware\Neo4j\Client\Client;
use GraphAware\Neo4j\Client\Formatter\RecordView;
use rdx\graphdb\Container;
use rdx\graphdb\Query;

class Database {

	protected $client;

	public function __construct(Client $client) {
		$this->client = $client;
	}

	protected function args($query, array $params) {
		return $query instanceof Query ? $query->args() : $params;
	}

	public function execute($query, array $params = []) {
		$params = $this->args($query, $params);
		return $this->client->run((string) $query, $params);
	}

	public function one($query, array $params = []) {
		$params = $this->args($query, $params);
		$result = $this->client->run((string) $query, $params);
		return $result->hasRecord() ? $this->wrap($result->getRecord()) : null;
	}

	public function many($query, array $params = []) {
		$params = $this->args($query, $params);
		return $this->wraps($this->client->run((string) $query, $params)->getRecords());
	}

	protected function wrap(RecordView $record) {
		$wrapped = new Container(Container::record2array($record));
		return $wrapped;
	}

	protected function wraps(array $records) {
		return array_map([$this, 'wrap'], $records);
	}

}
