<?php

namespace rdx\graphdb;

class Query {

	static public function make() : self {
		return new self;
	}

	/** @var AssocArray */
	protected array $args = [];
	/** @var list<string> */
	protected array $match = [];
	/** @var list<string> */
	protected array $merge = [];
	/** @var list<string> */
	protected array $where = [];
	/** @var list<string> */
	protected array $set = [];
	/** @var list<string> */
	protected array $onCreateSet = [];
	/** @var list<string> */
	protected array $onMatchSet = [];
	/** @var list<string> */
	protected array $delete = [];
	/** @var list<string> */
	protected array $detachDelete = [];
	/** @var list<string> */
	protected array $create = [];
	/** @var list<string> */
	protected array $return = [];
	/** @var list<string> */
	protected array $order = [];

	/**
	 * @param AssocArray $params
	 * @return AssocArray
	 */
	public function args(array $params = []) : array {
		return $this->args = $params + $this->args;
	}

	/**
	 * @param AssocArray $params
	 * @return $this
	 */
	public function match(string $flow, array $params = []) {
		$this->match[] = $flow;
		$this->args($params);
		return $this;
	}

	/**
	 * @param AssocArray $params
	 * @return $this
	 */
	public function merge(string $flow, array $params = []) {
		$this->merge[] = $flow;
		$this->args($params);
		return $this;
	}

	/**
	 * @param AssocArray $params
	 * @return $this
	 */
	public function where(string $condition, array $params = []) {
		$this->where[] = $condition;
		$this->args($params);
		return $this;
	}

	/**
	 * @param AssocArray $params
	 * @return $this
	 */
	public function onCreateSet(string $flow, array $params = []) {
		$this->onCreateSet[] = $flow;
		$this->args($params);
		return $this;
	}

	/**
	 * @param AssocArray $params
	 * @return $this
	 */
	public function onMatchSet(string $flow, array $params = []) {
		$this->onMatchSet[] = $flow;
		$this->args($params);
		return $this;
	}

	/**
	 * @param AssocArray $params
	 * @return $this
	 */
	public function set(string $flow, array $params = []) {
		$this->set[] = $flow;
		$this->args($params);
		return $this;
	}

	/**
	 * @return $this
	 */
	public function delete(string ...$aliases) {
		$this->delete = array_merge($this->delete, $aliases);
		return $this;
	}

	/**
	 * @return $this
	 */
	public function detachDelete(string ...$aliases) {
		$this->detachDelete = array_merge($this->detachDelete, $aliases);
		return $this;
	}

	/**
	 * @param AssocArray $params
	 * @return $this
	 */
	public function create(string $flow, array $params = []) {
		$this->create[] = $flow;
		$this->args($params);
		return $this;
	}

	/**
	 * @return $this
	 */
	public function return(string ...$statements) {
		$this->return = array_merge($this->return, $statements);
		return $this;
	}

	/**
	 * @return $this
	 */
	public function order(string ...$statements) {
		$this->order = array_merge($this->order, $statements);
		return $this;
	}

	protected function build() : string {
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

	public function __toString() : string {
		return $this->build();
	}

	public function __debugInfo() : array {
		return [
			'_string' => $this->build(),
		];
	}

}
