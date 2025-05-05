<?php

namespace rdx\graphdb;

use ArrayAccess;
use JsonSerializable;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use Laudis\Neo4j\Types\Node;

/**
 * @implements ArrayAccess<string, mixed>
 */
class Container implements ArrayAccess, JsonSerializable {

	protected string $_id;
	/** @var list<string> */
	protected array $_labels;
	protected string $_type;
	/** @var AssocArray */
	protected array $_attributes;
	/** @var AssocArray */
	protected array $_nodes;

	/**
	 * @param CypherMap<mixed> $record
	 * @return AssocArray
	 */
	static public function record2array(CypherMap $record) : array {
		$keys = $record->keys()->toArray();
		$values = $record->values()->toArray();

		$data = [];
		foreach ($keys as $n => $key) {
			$data[$key] = $values[$n];
		}

		return $data;
	}

	/**
	 * @return AssocArray
	 */
	static public function node2array(Node $node) : array {
		$data = ['_id' => $node->getElementId()] + $node->getProperties()->toArray();
		// if ($node instanceof Relationship) {
		// 	$data['_type'] = $node->type();
		// }
		// else if ($node instanceof Node) {
			$data['_labels'] = $node->getLabels()->toArray();
		// }
		return $data;
	}

	/**
	 * @param AssocArray $data
	 */
	final public function __construct(array $data) {
		foreach (['_id', '_labels', '_type'] as $property) {
			if (array_key_exists($property, $data)) {
				$this->$property = $data[$property];
				unset($data[$property]);
			}
		}

		foreach ($data as $name => $value) {
			// Single node
			if ($value instanceof Node) {
				$this->_nodes[$name] = new static(static::node2array($value));
				continue;
			}

			// List
			if ($value instanceof CypherList) {
				$value = $value->toArray();
			}

			// List of nodes
			if (is_array($value) && isset($value[0]) && $value[0] instanceof Node) {
				$this->_nodes[$name] = array_map(function(Node $node) {
					return new static(static::node2array($node));
				}, $value);
				continue;
			}

			// Single attribute, or list of attributes
			$this->_attributes[$name] = $value;

			// @todo Handle empty lists: what's the difference between an empty list of
			// attributes and an empty list of nodes?
		}
	}

	public function id() : string {
		return $this->_id;
	}

	/**
	 * @return list<string>
	 */
	public function labels() : array {
		return $this->_labels;
	}

	public function label() : ?string {
		return count($this->_labels) ? reset($this->_labels) : null;
	}

	/**
	 * @return AssocArray
	 */
	public function attributes() : array {
		return $this->_attributes;
	}

	/**
	 * @return AssocArray
	 */
	public function nodes() : array {
		return $this->_nodes;
	}

	public function node() : ?self {
		return count($this->_nodes) ? reset($this->_nodes) : null;
	}

	public function jsonSerialize() : mixed {
		return $this->_attributes;
	}

	/**
	 * @return ?Container
	 */
	public function __get(string $name) : mixed {
		return $this->_nodes[$name] ?? null;
	}

	// public function __set(string $name, mixed $value) : void {
	// 	$this->_nodes[$name] = $value;
	// }

	public function offsetExists(mixed $offset) : bool {
		return isset($this->_attributes[$offset]);
	}

	public function offsetGet(mixed $offset) : mixed {
		return $this->_attributes[$offset] ?? null;
	}

	public function offsetSet(mixed $offset, mixed $value) : void {
		$this->_attributes[$offset] = $value;
	}

	public function offsetUnset(mixed $offset) : void {}

}
