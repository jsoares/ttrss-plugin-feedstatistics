<?php
class FeedStatistics extends Plugin {

	function about() {
		return array(1.0,
			"Provides simple statistics on your feeds",
			"jsoares",
			false,
			"");
	}

	function init($host) {
		$host->add_hook($host::HOOK_PREFS_TAB, $this);
	}

	function hook_prefs_tab($args) {
		if ($args != "prefFeeds") return;

		print "<div dojoType=\"dijit.layout.AccordionPane\" title=\"".__("Statistics")."\">"; # start pane
		
		$owner_uid = $_SESSION["uid"] ? $_SESSION["uid"] : "NULL";
		
		// By default, use previous 30 days for statistics. 
		$interval = 30;
		// However, if the purge limit is lower, adjust accordingly
		$result = db_query("SELECT value FROM ttrss_user_prefs
							WHERE pref_name = 'PURGE_OLD_DAYS' AND owner_uid = $owner_uid");
		if (db_num_rows($result) == 1) {
			$purge_limit = db_fetch_result($result, 0, "value");
			$interval = min($interval,$purge_limit);
		}
		
		// Google Reader-like one-line summary
		$result = db_query("SELECT COUNT(DISTINCT feed_id) AS `Feeds`, COUNT(DISTINCT ref_id) as `Items`, SUM(marked) as `Starred`, SUM(published) AS `Published`
							FROM ttrss_user_entries 				
							WHERE last_read > DATE_SUB(CURDATE(),INTERVAL {$interval} DAY)
							AND owner_uid = {$owner_uid}");
		if(db_num_rows($result)) {		
			$row = db_fetch_assoc($result);
			print_notice("From your " . $row['Feeds'] . " subscriptions, over the last {$interval} days you read " . $row['Items'] . " items, starred " . $row['Starred'] . " items, and published " .  $row['Published'] . " items.");
		}
		
		// Per-feed statistics
		$result = db_query("SELECT ttrss_feeds.title as `Feed`, ttrss_feed_categories.title as `Category`, COUNT(ref_id) as `Items`, 
							SUM(marked) as `Starred`, SUM(published) AS `Published`, ROUND(COUNT(ref_id)/{$interval},2) as `Items/day`
							FROM ttrss_user_entries 
							INNER JOIN ttrss_feeds ON ttrss_user_entries.feed_id = ttrss_feeds.id
							INNER JOIN ttrss_entries ON ttrss_user_entries.ref_id = ttrss_entries.id
							INNER JOIN ttrss_feed_categories ON ttrss_feeds.cat_id=ttrss_feed_categories.id							
							WHERE ttrss_entries.date_entered > DATE_SUB(CURDATE(),INTERVAL {$interval} DAY)
							AND ttrss_user_entries.owner_uid = {$owner_uid}
							GROUP BY feed_id
							ORDER BY `Items/day` DESC");
		if(db_num_rows($result)) {
			print "<table cellpadding=\"5\" class=\"feed-table\">";
			print "<tr class=\"title\"><td>Feed</td><td>Category</td><td>Items</td><td>Starred</td><td>Published</td><td>Items/day</td></tr>";
			while($row = db_fetch_assoc($result)) {
				print "<tr>";
				foreach($row as $key=>$value) {
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
