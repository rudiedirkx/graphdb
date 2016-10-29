<?php

namespace rdx\graphdb;

use GraphAware\Neo4j\Client\Formatter\RecordView;
use GraphAware\Neo4j\Client\Formatter\Type\MapAccess;
use GraphAware\Neo4j\Client\Formatter\Type\Node;
use GraphAware\Neo4j\Client\Formatter\Type\Relationship;

class Container implements \ArrayAccess {

	protected $_id;
	protected $_labels;
	protected $_type;
	protected $_attributes;

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
		$data = ['_id' => $node->identity()] + $node->values();
		if ($node instanceof Relationship) {
			$data['_type'] = $node->type();
		}
		elseif ($node instanceof Node) {
			$data['_labels'] = $node->labels();
		}
		return $data;
	}

	public function __construct(array $data) {
		foreach (['_id', '_labels', '_type'] as $property) {
			if (array_key_exists($property, $data)) {
				$this->$property = $data[$property];
				unset($data[$property]);
			}
		}

		foreach ($data as $name => $value) {
			// Single node
			if ($value instanceof MapAccess) {
				$this->$name = new static(static::node2array($value));
			}
			// List of nodes
			elseif (is_array($value) && isset($value[0]) && $value[0] instanceof MapAccess) {
				$this->$name = array_map(function($node) {
					return new static(static::node2array($node));
				}, $value);
			}
			// Single attribute, or list of attributes
			else {
				$this->_attributes[$name] = $value;
			}

			// @todo Handle empty lists: what's the difference between an empty list of
			// attributes and an empty list of nodes?
		}
	}

	public function id() {
		return $this->_id;
	}

	public function labels() {
		return (array) $this->_labels;
	}

	public function label() {
		return $this->_labels ? reset($this->_labels) : null;
	}

	public function offsetExists($offset) {
		return isset($this->_attributes[$offset]);
	}

	public function offsetGet($offset) {
		return @$this->_attributes[$offset];
	}

	public function offsetSet($offset, $value) {}

	public function offsetUnset($offset) {}

}
