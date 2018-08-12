<?php

namespace rdx\graphdb;

use GraphAware\Neo4j\Client\Client;
use GraphAware\Neo4j\Client\Formatter\RecordView;
use rdx\graphdb\Container;
use rdx\graphdb\Query;

class Database {

	protected $client;
	protected $num_queries = 0;
	protected $queries = [];

	public function __construct(Client $client) {
		$this->client = $client;
	}

	protected function args($query, array $params) {
		return $query instanceof Query ? $query->args() : $params;
	}

	public function execute($query, array $params = []) {
		$params = $this->args($query, $params);
		return $this->run((string) $query, $params);
	}

	public function one($query, array $params = []) {
		$params = $this->args($query, $params);
		$result = $this->run((string) $query, $params);
		return $result->hasRecord() ? $this->wrap($result->getRecord()) : null;
	}

	public function many($query, array $params = []) {
		$params = $this->args($query, $params);
		return $this->wraps($this->run((string) $query, $params)->getRecords());
	}

	protected function wrap(RecordView $record) {
		$wrapped = new Container(Container::record2array($record));
		return $wrapped;
	}

	protected function wraps(array $records) {
		return array_map([$this, 'wrap'], $records);
	}

	protected function run($query, array $params) {
		$this->num_queries++;
		if (is_array($this->queries)) {
			$this->queries[] = ['query' => "\n" . trim($query, "\r\n"), 'params' => $params];
		}
		return $this->client->run($query, $params);
	}

	public function getNumQueries() {
		return $this->num_queries;
	}

	public function getQueries() {
		return $this->queries;
	}

}
