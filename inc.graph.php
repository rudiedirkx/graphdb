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
		return $this->client->run((string) $query, $params);
	}

	public function one($query) {
		return $this->wrap($this->client->run((string) $query)->getRecord());
	}

	public function many($query) {
		return $this->wraps($this->client->run((string) $query)->getRecords());
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

class GraphQuery {

	static public function make() {
		return new static;
	}

	protected $match = [];
	protected $merge = [];
	protected $where = [];
	protected $set = [];
	protected $delete = [];
	protected $create = [];
	protected $return = [];
	protected $order = [];

	public function match($flow) {
		$this->match[] = $flow;
		return $this;
	}

	public function merge($flow) {
		$this->merge[] = $flow;
		return $this;
	}

	public function where($condition) {
		$this->where[] = $condition;
		return $this;
	}

	public function set($flow) {
		$this->set[] = $flow;
		return $this;
	}

	public function delete(...$aliases) {
		$this->delete = array_merge($this->delete, $aliases);
		return $this;
	}

	public function create($flow) {
		$this->create[] = $flow;
		return $this;
	}

	public function return(...$statements) {
		$this->return = array_merge($this->return, $statements);
		return $this;
	}

	public function order(...$statements) {
		$this->order = array_merge($this->order, $statements);
		return $this;
	}

	protected function build() {
		$parts = [
			'MATCH'		=> ['match',	', '],
			'MERGE'		=> ['merge',	', '],
			'WHERE'		=> ['where',	' AND '],
			'SET'		=> ['set',		', '],
			'DELETE'	=> ['delete',	', '],
			'CREATE'	=> ['create',	', '],
			'RETURN'	=> ['return',	', '],
			'ORDER BY'	=> ['order',	', '],
		];

		$query = [];
		foreach ($parts as $keyword => $info) {
			list($source, $glue) = $info;
			if ($this->$source) {
				$query[] = $keyword . ' ' . implode($glue, $this->$source);
			}
		}

		return implode("\n", $query);
	}

	public function __toString() {
		return $this->build();
	}

}
