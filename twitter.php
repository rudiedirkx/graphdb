<?php

use rdx\graphdb\Database;

$_time = microtime(1);

require 'inc.bootstrap.twitter.php';

$app = new TwitterApp($db);

header('Content-type: text/plain; charset=utf-8');
print_r($app);
exit;

?>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta charset="utf-8" />
<title>Graph Twitter</title>
<style>
button:default {
	font-weight: bold;
}
button.delete {
	color: #b00;
}
</style>

<h2>Tweets</h2>

...

<?php

class TwitterApp {

	protected $db;
	protected $cache = [];

	public function __construct(Database $db) {
		$this->db = $db;
	}

	protected function cache($name, callable $worker) {
		if (!array_key_exists($name, $this->cache)) {
			$this->cache[$name] = $worker();
		}

		return $this->cache[$name];
	}

}
