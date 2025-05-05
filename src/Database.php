<?php

namespace rdx\graphdb;

use Closure;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use rdx\graphdb\Container;
use rdx\graphdb\Query;

class Database {

	/** @var array<string, array{int, Closure}> */
	protected array $middleware = [];

	public function __construct(
		/** @var ClientInterface<SummarizedResult<mixed>> */
		protected ClientInterface $client,
	) {}

	/**
	 * @param AssocArray $params
	 * @return AssocArray
	 */
	protected function args(string|Query $query, array $params) : array {
		return $query instanceof Query ? $query->args() : $params;
	}

	public function makeUuid() : string {
		return substr(str_replace(['=', '+', '/'], '', base64_encode(random_bytes(50))), 0, 36);
	}

	/**
	 * @param AssocArray $data
	 * @return CypherList<mixed>
	 */
	public function merge(string $label, ?string $uuid, array $data) : CypherList {
		$data['uuid'] = $uuid ?: $this->makeUuid();
		return $this->execute(Query::make()
			->merge('(x:' . $label . ' {uuid: $uuid})', ['uuid' => $data['uuid']])
			->set('x += $data', ['data' => $data])
		);
	}

	/**
	 * @return CypherList<mixed>
	 */
	public function delete(string $label, string $uuid) : CypherList {
		return $this->execute(Query::make()
			->match('(x:' . $label . ')')
			->where('x.uuid = {uuid}', ['uuid' => $uuid])
			->delete('x')
		);
	}

	/**
	 * @param AssocArray $params
	 * @return CypherList<mixed>
	 */
	public function execute(string|Query $query, array $params = []) : CypherList {
		$params = $this->args($query, $params);
		return $this->run((string) $query, $params);
	}

	/**
	 * @param AssocArray $params
	 */
	public function one(string|Query $query, array $params = []) : ?Container {
		$params = $this->args($query, $params);
		$result = $this->run((string) $query, $params);
		return !$result->isEmpty() ? $this->wrap($result->first()) : null;
	}

	/**
	 * @param AssocArray $params
	 */
	public function oneNode(string|Query $query, array $params = []) : ?Container {
		$object = $this->one($query, $params);
		return $object ? $this->node($object) : null;
	}

	/**
	 * @param AssocArray $params
	 * @return list<Container>
	 */
	public function many(string|Query $query, array $params = []) : array {
		$params = $this->args($query, $params);
		return $this->wraps($this->run((string) $query, $params)->toArray());
	}

	/**
	 * @param AssocArray $params
	 * @return list<Container>
	 */
	public function manyNode(string|Query $query, array $params = []) : array {
		$objects = $this->many($query, $params);
		$subs = array_map(function(Container $object) {
			return $this->node($object);
		}, $objects);
		return $subs;
	}

	protected function node(Container $object) : Container {
		return $object->node();
	}

	/**
	 * @param CypherMap<mixed> $record
	 */
	protected function wrap(CypherMap $record) : Container {
		return new Container(Container::record2array($record));
	}

	/**
	 * @param list<CypherMap<mixed>> $records
	 * @return list<Container>
	 */
	protected function wraps(array $records) : array {
		return array_map([$this, 'wrap'], $records);
	}

	/**
	 * @param AssocArray $params
	 * @return CypherList<mixed>
	 */
	protected function run(string|Query $query, array $params) : CypherList {
		if (count($this->middleware) == 0) {
			return $this->client->run($query, $params)->getResults();
		}

		$queue = $this->queue(function(string|Query $query, array $params) : CypherList {
			return $this->client->run($query, $params)->getResults();
		});

		return $queue($query, $params);
	}

	public function middleware(string $name, Closure $handler, int $weight = 0) : void {
		$this->middleware[$name] = [$weight, $handler];
	}

	protected function queue(Closure $last) : Closure {
		$middleware = $this->middleware;
		uasort($middleware, function($a, $b) {
			return $a[0] <=> $b[0];
		});
		$middleware = array_column($middleware, 1);

		return array_reduce($middleware, function(Closure $next, Closure $middleware) {
			return function(...$args) use ($next, $middleware) {
				return $middleware($next, ...$args);
			};
		}, $last);
	}

}
