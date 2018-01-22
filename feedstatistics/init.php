<?php
class FeedStatistics extends Plugin {

	function about() {
		return array(1.07,
			"Provides simple statistics on your feeds",
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

		print "<div dojoType=\"dijit.layout.AccordionPane\" title=\"".__("Statistics")."\">"; # start pane
		
		$owner_uid = $_SESSION["uid"] ? $_SESSION["uid"] : "NULL";
		
		// By default, use previous 30 days for statistics. 
		$interval = 30;
		// However, if the purge limit is lower, adjust accordingly
		$sth = $this->pdo->prepare("SELECT value FROM ttrss_user_prefs
							WHERE pref_name = 'PURGE_OLD_DAYS' AND owner_uid = ? AND profile IS NULL");
		$sth->execute([$owner_uid]);
		$result = $sth->fetch(PDO::FETCH_OBJ);
		if (isset($result->value)) {
			$purge_limit = $result->value;
			if ($purge_limit > 0) {
				$interval = min($interval,$purge_limit);
			}
		}
		$date = new DateTime();
		$date->sub(new DateInterval("P{$interval}D"));
		$datestr = $date->format("Y-m-d");
		
		// Google Reader-like one-line summary
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
		
		if (isset($result->feeds)) {		
			print_notice("From your {$result->feeds} subscriptions, over the last {$interval} days you read {$result->items} items, starred {$result->starred} items, and published {$result->published} items.");
		}

		// Per-feed statistics
		$sth = $this->pdo->prepare("SELECT
							ttrss_feeds.title AS feed,
							ttrss_feed_categories.title AS category,
							COUNT(NULLIF(last_read > :date, false)) AS items,
							COUNT(NULLIF(last_marked > :date, false)) AS starred,
							COUNT(NULLIF(last_published > :date, false)) AS published,
							ROUND(CAST(COUNT(NULLIF(last_read > :date, false)) AS DECIMAL) / :interval, 2) AS items_day
							FROM ttrss_feeds
							LEFT JOIN ttrss_user_entries ON ttrss_feeds.id = ttrss_user_entries.feed_id
							LEFT JOIN ttrss_entries ON ttrss_user_entries.ref_id = ttrss_entries.id
							LEFT JOIN ttrss_feed_categories ON ttrss_feeds.cat_id = ttrss_feed_categories.id
							WHERE ttrss_feeds.owner_uid = :owner
							GROUP BY ttrss_feeds.id
							ORDER BY items_day DESC");
		$sth->execute(['date'=>$datestr, 'interval'=>$interval, 'owner'=>$owner_uid]);
		$result = $sth->fetchAll(PDO::FETCH_ASSOC);
		
		if (count($result) > 0) {
			print "<table cellpadding=\"5\" class=\"feed-table\">";
			print "<tr class=\"title\"><td>Feed</td><td>Category</td><td>Read</td><td>Starred</td><td>Published</td><td>Items/day</td></tr>";
			foreach ($result as $row) {
				print "<tr>";
				foreach ($row as $key=>$value) {
					print "<td>{$value}</td>";
				}
				print "</tr>";
			}
			print "</table>";
		}		
		
		print "</div>"; #pane
	}

	function api_version() {
		return 2;
	}

}
?>
