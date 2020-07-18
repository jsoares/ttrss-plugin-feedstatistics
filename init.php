<?php
class FeedStatistics extends Plugin {

	function about() {
		return array(1.10,
			"Generates simple statistics for your feeds",
			"jsoares",
			false,
			"");
	}

	function init($host) {
		$this->host = $host;
		$host->add_hook($host::HOOK_PREFS_TAB, $this);
	}

	function hook_prefs_tab($args) {
		if ($args != "prefFeeds") return;

		print "<div dojoType=\"dijit.layout.AccordionPane\"
			title=\"<i class='material-icons'>extension</i> ".__('Feed stats (feedstatistics)')."\">";

		$owner_uid = $_SESSION["uid"] ? $_SESSION["uid"] : "NULL";

		// By default, use previous 30 days for statistics.
		$interval = 30;
		// However, if the purge limit is lower, adjust accordingly
		$sth = $this->pdo->prepare("SELECT value FROM ttrss_user_prefs
							WHERE pref_name = 'PURGE_OLD_DAYS' AND owner_uid = :owner AND profile IS NULL");
		$sth->execute(['owner'=>$owner_uid]);
		$result = $sth->fetch(PDO::FETCH_OBJ);

		$purge_text = "Your default configuration is to never purge items.";
		if (isset($result->value)) {
			$purge_limit = $result->value;
			if ($purge_limit > 0) {
				$purge_text = "Your default configuration is to purge items after {$purge_limit} days.";
				$interval = min($interval,$purge_limit);
			}
		}

		$date = new DateTime();
		$date->sub(new DateInterval("P{$interval}D"));
		$datestr = $date->format("Y-m-d");

		// Google Reader-like one-line summary for recently read items
		$sth = $this->pdo->prepare("SELECT
							COUNT(DISTINCT ttrss_feeds.id) AS feeds,
							COUNT(NULLIF(last_read > :date, false)) AS items,
							COUNT(NULLIF(last_marked > :date, false)) AS starred,
							COUNT(NULLIF(last_published > :date, false)) AS published
							FROM ttrss_feeds
							LEFT JOIN ttrss_user_entries ON ttrss_feeds.id = ttrss_user_entries.feed_id
							WHERE ttrss_feeds.owner_uid = :owner");
		$sth->execute(['date'=>$datestr, 'owner'=>$owner_uid]);
		$result = $sth->fetch(PDO::FETCH_OBJ);

		print "<h2>Recent items</h2>";

		if (isset($result->feeds)) {
			print_notice("From your {$result->feeds} subscriptions, over the last {$interval} days you read {$result->items} items, starred {$result->starred} items, and published {$result->published} items.");
		}

		// Per-feed reading statistics
		$sth = $this->pdo->prepare("SELECT
							ttrss_feeds.id AS id,
							ttrss_feeds.title AS title,
							ttrss_feed_categories.title AS category,
							COUNT(NULLIF(ttrss_user_entries.last_read > :date, false)) AS items,
							COUNT(NULLIF(ttrss_user_entries.last_marked > :date, false)) AS starred,
							COUNT(NULLIF(ttrss_user_entries.last_published > :date, false)) AS published,
							ROUND(CAST(COUNT(NULLIF(last_read > :date, false)) AS DECIMAL) / :interval, 2) AS items_day
							FROM ttrss_feeds
							LEFT JOIN ttrss_user_entries ON ttrss_feeds.id = ttrss_user_entries.feed_id
							LEFT JOIN ttrss_entries ON ttrss_user_entries.ref_id = ttrss_entries.id
							LEFT JOIN ttrss_feed_categories ON ttrss_feeds.cat_id = ttrss_feed_categories.id
							WHERE ttrss_feeds.owner_uid = :owner
							GROUP BY ttrss_feeds.id, ttrss_feeds.title, ttrss_feed_categories.title
							ORDER BY items_day DESC");
		$sth->execute(['date'=>$datestr, 'interval'=>$interval, 'owner'=>$owner_uid]);
		$result = $sth->fetchAll(PDO::FETCH_ASSOC);

		if (count($result) > 0) {
			print "<details><summary>Details</summary>";
			print "<table cellpadding=\"5\" class=\"feed-table\" style=\"text-align: left;\">";
			print "<tr class=\"title\"><th>Feed</th><th>Category</th><th>Read</th><th>Starred</th><th>Published</th><th>Items/day</th></tr>";
			foreach ($result as $row) {
				array_shift($row);
				print "<tr>";
				foreach ($row as $key=>$value) {
					print "<td>{$value}</td>";
				}
				print "</tr>";
			}
			print "</table></details>";
		}

		// One-line summary for all items
		$sth = $this->pdo->prepare("SELECT
							COUNT(DISTINCT ttrss_feeds.id) AS feeds,
							COUNT(ttrss_user_entries.int_id) AS items,
							COUNT(ttrss_user_entries.last_read) AS read_items,
							COUNT(ttrss_user_entries.last_marked) AS starred_items,
							COUNT(ttrss_user_entries.last_published) AS published_items
							FROM ttrss_feeds
							LEFT JOIN ttrss_user_entries ON ttrss_feeds.id = ttrss_user_entries.feed_id
							WHERE ttrss_feeds.owner_uid = :owner");
		$sth->execute(['owner'=>$owner_uid]);
		$result = $sth->fetch(PDO::FETCH_OBJ);

		print "<h2>All items</h2>";

		if (isset($result->feeds)) {
			print_notice("From your {$result->feeds} subscriptions, there are {$result->items} total items and you read {$result->read_items} items, starred {$result->starred_items} items, and published {$result->published_items} items. " . $purge_text);
		}

		// All items statistics
		$sth = $this->pdo->prepare("SELECT
							ttrss_feeds.id AS id,
							ttrss_feeds.title AS title,
							ttrss_feed_categories.title AS category,
							COUNT(ttrss_user_entries.int_id) AS items,
							COUNT(ttrss_user_entries.last_read) AS read_items,
							COUNT(ttrss_user_entries.last_marked) AS starred_items,
							COUNT(ttrss_user_entries.last_published) AS published_items
							FROM ttrss_feeds
							LEFT JOIN ttrss_user_entries ON ttrss_feeds.id = ttrss_user_entries.feed_id
							LEFT JOIN ttrss_entries ON ttrss_user_entries.ref_id = ttrss_entries.id
							LEFT JOIN ttrss_feed_categories ON ttrss_feeds.cat_id = ttrss_feed_categories.id
							WHERE ttrss_feeds.owner_uid = :owner
							GROUP BY ttrss_feeds.id, ttrss_feeds.title, ttrss_feed_categories.title
							ORDER BY items DESC");
		$sth->execute(['owner'=>$owner_uid]);
		$result = $sth->fetchAll(PDO::FETCH_ASSOC);

		if (count($result) > 0) {
			print "<details><summary>Details</summary>";
			print "<table cellpadding=\"5\" class=\"feed-table\" style=\"text-align: left;\">";
			print "<thead><tr class=\"title\"><th>Feed</th><th>Category</th><th>Total</th><th>Read</th><th>Starred</th><th>Published</th></tr></thead>";
			foreach ($result as $row) {
				array_shift($row);
				print "<tr>";
				foreach ($row as $key=>$value) {
					print "<td>{$value}</td>";
				}
				print "</tr>";
			}
			print "</table></details>";
		}

		print "</div>"; #pane
	}

	function api_version() {
		return 2;
	}

}
?>
