<?php

namespace rdx\graphdb;

class Query {

	static public function make() {
		return new static;
	}

	protected $args = [];
	protected $match = [];
	protected $merge = [];
	protected $where = [];
	protected $set = [];
	protected $onCreateSet = [];
	protected $onMatchSet = [];
	protected $delete = [];
	protected $detachDelete = [];
	protected $create = [];
	protected $return = [];
	protected $order = [];

	public function args(array $params = []) {
		return $this->args = $params + $this->args;
	}

	public function match($flow, array $params = []) {
		$this->match[] = $flow;
		$this->args($params);
		return $this;
	}

	public function merge($flow, array $params = []) {
		$this->merge[] = $flow;
		$this->args($params);
		return $this;
	}

	public function where($condition, array $params = []) {
		$this->where[] = $condition;
		$this->args($params);
		return $this;
	}

	public function onCreateSet($flow, array $params = []) {
		$this->onCreateSet[] = $flow;
		$this->args($params);
		return $this;
	}

	public function onMatchSet($flow, array $params = []) {
		$this->onMatchSet[] = $flow;
		$this->args($params);
		return $this;
	}

	public function set($flow, array $params = []) {
		$this->set[] = $flow;
		$this->args($params);
		return $this;
	}

	public function delete(...$aliases) {
		$this->delete = array_merge($this->delete, $aliases);
		return $this;
	}

	public function detachDelete(...$aliases) {
		$this->detachDelete = array_merge($this->detachDelete, $aliases);
		return $this;
	}

	public function create($flow, array $params = []) {
		$this->create[] = $flow;
		$this->args($params);
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
			'MATCH'			=> ['match',		', '],
			'MERGE'			=> ['merge',		', '],
			'WHERE'			=> ['where',		' AND '],
			'ON CREATE SET'	=> ['onCreateSet',	', '],
			'ON MATCH SET'	=> ['onMatchSet',	', '],
			'SET'			=> ['set',			', '],
			'DELETE'		=> ['delete',		', '],
			'DETACH DELETE'	=> ['detachDelete',	', '],
			'CREATE'		=> ['create',		', '],
			'RETURN'		=> ['return',		', '],
			'ORDER BY'		=> ['order',		', '],
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
