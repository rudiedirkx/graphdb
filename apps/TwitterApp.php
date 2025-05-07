<?php

use rdx\graphdb\Container;
use rdx\graphdb\Database;
use rdx\graphdb\Query;

class TwitterApp {

	/** @var AssocArray */
	protected array $cache = [];

	public function __construct(
		protected Database $db,
	) {
		$db->middleware('log', function(Closure $next, $query, array $params) {
			$t = hrtime(true);
			$out = $next($query, $params);
			$t = hrtime(true) - $t;
			// @phpstan-ignore offsetAccess.nonOffsetAccessible
			$_SESSION['queries'][] = [
				'time' => $t / 1e6,
				'query' => "\n" . trim($query, "\r\n"),
				'params' => $params,
			];
			return $out;
		}, 1000);

		// CREATE CONSTRAINT FOR (u:User) REQUIRE u.name IS UNIQUE
		// CREATE CONSTRAINT FOR (t:Tweet) REQUIRE t.uuid IS UNIQUE
	}

	protected function cache(string $name, Closure $worker) : mixed {
		if (!array_key_exists($name, $this->cache)) {
			$this->cache[$name] = $worker();
		}

		return $this->cache[$name];
	}

	public function uuid() : string {
		return str_replace('.', '_', (string) microtime(true));
	}

	/**
	 * @return array<string, Container>
	 */
	public function getTweets() : array {
		return $this->cache(__FUNCTION__, function() {
			return array_reduce($this->db->many('
				MATCH (t:Tweet)-[:AUTHORED_BY]->(u:User)
				OPTIONAL MATCH (t)-[:REPLIES_TO]->(p:Tweet)
				RETURN t AS tweet, u AS author, elementId(p) AS pid
				ORDER BY t.created ASC
			'), function($tweets, $tweet) {
				return $tweets + [$tweet->tweet->id() => $tweet];
			}, []);
		});
	}

	/**
	 * @param list<Container> $tweets
	 * @return list<array{int, Container}>
	 */
	public function makeTweetHierarchy(array $tweets) : array {
		foreach ($tweets as $tweet) {
			$tweet['replies'] = [];
		}

		// Create hierarchy with automatic references
		$parents = [];
		foreach ($tweets as $tid => $tweet) {
			if ($pid = $tweet['pid']) {
				// @phpstan-ignore arrayUnpacking.nonIterable
				$tweets[$pid]['replies'] = [...$tweets[$pid]['replies'], $tweet];
			}
			else {
				$parents[] = $tweet;
			}
		}

		// Flatten & remove hierarchy
		$ordered = [];
		$add = function(int $level, array $tweets) use (&$add, &$ordered) : void {
			/** @var array<Container> $tweets */

			foreach ($tweets as $tweet) {
				$ordered[] = [$level, $tweet];

				if (count($tweet['replies'])) {
					$add($level + 1, $tweet['replies']);
					unset($tweet['replies']);
				}
			}
		};
		$add(0, $parents);

		foreach ($tweets as $tweet) {
			$tweet['replies'] = [];
		}

		return $ordered;
	}

	/**
	 * @return array<string, string>
	 */
	public function getTweetOptions() : array {
		return $this->cache(__FUNCTION__, function() {
			return array_reduce($this->getTweets(), function(array $options, Container $tweet) : array {
				return $options + [$tweet->tweet->id() => sprintf('%s: %s', $tweet->author['name'], $tweet->tweet['text'])];
			}, []);
		});
	}

	public function createTweet(string $authorName, string $parentId, string $text) : void {
		$tweet = ['uuid' => $this->uuid(), 'text' => $text, 'created' => time()];
		$query = Query::make()
			->match('(u:User {name: $authorName})', compact('authorName'))
			->create('(t:Tweet $tweet)', compact('tweet'))
			->create('(t)-[:AUTHORED_BY]->(u)');

		if ($parentId !== '') {
			$query
				->match('(p:Tweet)')
				->where('elementId(p) = $pid', ['pid' => $parentId])
				->create('(t)-[:REPLIES_TO]->(p)');
		}

		$this->db->execute($query);
	}

	public function deleteTweet(string $id) : void {
		$this->db->execute(Query::make()
			->match('(t:Tweet)')
			->where('elementId(t) = $tid', ['tid' => $id])
			->detachDelete('t')
		);
	}

	/**
	 * @param AssocArray $data
	 */
	public function createUser(array $data) : void {
		$data = array_map('trim', $data);

		// User.name is UNIQUE, so this is a very easy MERGE/REPLACE/UPSERT
		$this->db->execute(Query::make()
			->merge('(u:User {name: $name})', ['name' => $data['name']])
			// ->set('u += {data}', ['data' => $data])
		);
	}

	/**
	 * @return list<Container>
	 */
	public function getUsers() : array {
		return $this->cache(__FUNCTION__, function() {
			return $this->db->many('
				MATCH (u:User)
				RETURN u AS user
				ORDER BY u.name
			');
		});
	}

	/**
	 * @return array<string, string>
	 */
	public function getUserOptions() : array {
		return $this->cache(__FUNCTION__, function() {
			return array_reduce($this->getUsers(), function($options, $user) {
				return $options + [$user->user['name'] => $user->user['name']];
			}, []);
		});
	}

	/**
	 * @return list<AssocArray>
	 */
	public function getQueries() : array {
		$sessionQueries = $_SESSION['queries'] ?? [];
		$_SESSION['queries'] = [];
		return $sessionQueries;
	}

}
