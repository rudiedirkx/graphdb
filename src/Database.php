<?php

namespace rdx\graphdb;

use GraphAware\Neo4j\Client\Client;
use GraphAware\Neo4j\Client\Formatter\RecordView;
use rdx\graphdb\Container;
use rdx\graphdb\Query;

class Database {

	protected $client;
	protected $middleware = [];

	public function __construct(Client $client) {
		$this->client = $client;
	}

	protected function args($query, array $params) {
		return $query instanceof Query ? $query->args() : $params;
	}

	public function makeUuid() {
		return substr(str_replace(['=', '+', '/'], '', base64_encode(random_bytes(50))), 0, 36);
	}

	public function merge($label, $uuid = null, array $data) {
		$data['uuid'] = $uuid ?: $this->makeUuid();
		return $this->execute(Query::make()
			->merge('(x:' . $label . ' {uuid: {uuid}})', ['uuid' => $data['uuid']])
			->set('x += {data}', ['data' => $data])
		);
	}

	public function delete($label, $uuid) {
		return $this->execute(Query::make()
			->match('(x:' . $label . ')')
			->where('x.uuid = {uuid}', ['uuid' => $uuid])
			->delete('x')
		);
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

	public function oneNode($query, array $params = []) {
		$object = $this->one($query, $params);
		return $object ? $this->node($object) : null;
	}

	public function many($query, array $params = []) {
		$params = $this->args($query, $params);
		return $this->wraps($this->run((string) $query, $params)->getRecords());
	}

	public function manyNode($query, array $params = []) {
		$objects = $this->many($query, $params);
		$subs = array_map(function(Container $object) {
			return $this->node($object);
		}, $objects);
		return $subs;
	}

	protected function node(Container $object) {
		return $object->node();
	}

	protected function wrap(RecordView $record) {
		$wrapped = new Container(Container::record2array($record));
		return $wrapped;
	}

	protected function wraps(array $records) {
		return array_map([$this, 'wrap'], $records);
	}

	protected function run($query, array $params) {
		if (count($this->middleware) == 0) {
			return $this->client->run($query, $params);
		}

		$queue = $this->queue(function($query, $params) {
			return $this->client->run($query, $params);
		});

		return $queue($query, $params);
	}

	public function middleware($name, callable $handler, $weight = 0) {
		$this->middleware[$name] = [$weight, $handler];
	}

	protected function queue(callable $last) {
		$middleware = $this->middleware;
		uasort($middleware, function($a, $b) {
			return $a[0] <=> $b[0];
		});
		$middleware = array_column($middleware, 1);

		return array_reduce($middleware, function(callable $next, callable $middleware) {
			return function(...$args) use ($next, $middleware) {
				return $middleware($next, ...$args);
			};
		}, $last);
	}

}
