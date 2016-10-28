<?php

namespace rdx\graphdb;

use GraphAware\Neo4j\Client\Client;
use GraphAware\Neo4j\Client\Formatter\RecordView;
use GraphAware\Neo4j\Client\Formatter\Type\MapAccess;

class GraphDatabase {

	protected $client;

	public function __construct(Client $client) {
		$this->client = $client;
	}

	public function execute($query, array $params = []) {
		return $this->client->run($query, $params);
	}

	public function one($query) {
		return $this->wrap($this->client->run($query)->getRecord());
	}

	public function many($query) {
		return $this->wraps($this->client->run($query)->getRecords());
	}

	protected function wrap(RecordView $record) {
		return new GraphNode(GraphNode::record2array($record));
	}

	protected function wraps(array $records) {
		return array_map([$this, 'wrap'], $records);
	}

}

class GraphNode implements \ArrayAccess {

	protected $attributes = [];

	static public function record2array(RecordView $record) {
		$keys = $record->keys();
		$values = $record->values();

		$data = [];
		foreach ($keys as $n => $key) {
			$data[$key] = $values[$n];
		}

		return $data;
	}

	static public function node2array(MapAccess $node) {
		return $node->values();
	}

	public function __construct(array $data) {
		foreach ($data as $name => $value) {
			if ($value instanceof MapAccess) {
				$this->$name = new static(static::node2array($value));
			}
			else {
				$this->attributes[$name] = $value;
			}
		}
	}

	public function offsetExists($offset) {
		return isset($this->attributes[$offset]);
	}

	public function offsetGet($offset) {
		return @$this->attributes[$offset];
	}

	public function offsetSet($offset, $value) {}

	public function offsetUnset($offset) {}

}
