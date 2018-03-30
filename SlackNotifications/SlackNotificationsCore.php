<?php
use MediaWiki\MediaWikiServices;

class SlackNotifications
{
	/** @var The services object */
	private static $mwservices = null;

	/** @var Config $mwconfig The mediawiki site config object */
	private static $mwconfig = null;

	/** @var Config $snconfig The extension config object */
	private static $snconfig = null;


	/**
	 * Initializes (if needed) and returns the site config object
	 *
	 * @return Config
	 */
	private static function getMwConfig()
	{
		if (self::$mwconfig == null) {
			if (self::$mwservices === null) {
				self::$mwservices = MediaWikiServices::getInstance();
			}
			self::$mwconfig = self::$mwservices->getMainConfig();
		}
		return self::$mwconfig;
	}

	/**
	 * Initializes (if needed) and returns the extension config object
	 *
	 * @return Config
	 */
	private static function getExtConfig()
	{
		if (self::$snconfig == null) {
			if (self::$mwservices === null) {
				self::$mwservices = MediaWikiServices::getInstance();
			}
			self::$snconfig = self::$mwservices->getConfigFactory()->makeConfig('SlackNotifications');
		}
		return self::$snconfig;
	}

	/**
	 * Gets nice HTML text for user containing the link to user page
	 * and also links to user site, groups editing, talk and contribs pages.
	 */
	private static function getSlackUserText($user)
	{
		$config                           = self::getExtConfig();
		$wgWikiUrl                        = $config->get("WikiUrl");
		$wgWikiUrlEnding                  = $config->get("WikiUrlEnding");
		$wgSlackIncludeUserUrls           = $config->get("SlackIncludeUserUrls");
		$wgWikiUrlEndingUserPage          = $config->get("WikiUrlEndingUserPage");
		$wgWikiUrlEndingBlockUser         = $config->get("WikiUrlEndingBlockUser");
		$wgWikiUrlEndingUserRights        = $config->get("WikiUrlEndingUserRights");
		$wgWikiUrlEndingUserTalkPage      = $config->get("WikiUrlEndingUserTalkPage");
		$wgWikiUrlEndingUserContributions = $config->get("WikiUrlEndingUserContributions");

		if ($wgSlackIncludeUserUrls) {
			return sprintf(
				"<%s|%s> (<%s|block> | <%s|groups> | <%s|talk> | <%s|contribs>)",
				$wgWikiUrl . $wgWikiUrlEnding . $wgWikiUrlEndingUserPage . urlencode($user),
				$user,
				$wgWikiUrl . $wgWikiUrlEnding . $wgWikiUrlEndingBlockUser . urlencode($user),
				$wgWikiUrl . $wgWikiUrlEnding . $wgWikiUrlEndingUserRights . urlencode($user),
				$wgWikiUrl . $wgWikiUrlEnding . $wgWikiUrlEndingUserTalkPage . urlencode($user),
				$wgWikiUrl . $wgWikiUrlEnding . $wgWikiUrlEndingUserContributions . urlencode($user)
			);
		} else {
			return sprintf(
				"<%s|%s>",
				$wgWikiUrl . $wgWikiUrlEnding . $wgWikiUrlEndingUserPage . $user,
				$user
			);
		}
	}

	/**
	 * Gets nice HTML text for article containing the link to article page
	 * and also into edit, delete and article history pages.
	 */
	private static function getSlackArticleText(WikiPage $article, $diff = false)
	{
		$config                       = self::getExtConfig();
		$wgWikiUrl                    = $config->get("WikiUrl");
		$wgWikiUrlEnding              = $config->get("WikiUrlEnding");
		$wgWikiUrlEndingDiff          = $config->get("WikiUrlEndingDiff");
		$wgWikiUrlEndingHistory       = $config->get("WikiUrlEndingHistory");
		$wgSlackIncludePageUrls       = $config->get("SlackIncludePageUrls");
		$wgWikiUrlEndingEditArticle   = $config->get("WikiUrlEndingEditArticle");
		$wgWikiUrlEndingDeleteArticle = $config->get("WikiUrlEndingDeleteArticle");

		$prefix = $wgWikiUrl . $wgWikiUrlEnding . urlencode($article->getTitle()->getFullText());
		if ($wgSlackIncludePageUrls)
		{
			$out = sprintf(
				"<%s|%s> (<%s|edit> | <%s|delete> | <%s|history>",
				$prefix,
				$article->getTitle()->getFullText(),
				$prefix . "&" . $wgWikiUrlEndingEditArticle,
				$prefix . "&" . $wgWikiUrlEndingDeleteArticle,
				$prefix . "&" . $wgWikiUrlEndingHistory
			);
			if ($diff) {
				$out .= sprintf(" | <%s|diff>)", $prefix."&".$wgWikiUrlEndingDiff.$article->getRevision()->getID());
			} else {
				$out .= ")";
			}
			return $out."\\n";
		} else {
			return sprintf("<%s|%s>", $prefix, $article->getTitle()->getFullText());
		}
	}

	/**
	 * Gets nice HTML text for title object containing the link to article page
	 * and also into edit, delete and article history pages.
	 */
	private static function getSlackTitleText(Title $title)
	{
		$config                       = self::getExtConfig();
		$wgWikiUrl                    = $config->get("WikiUrl");
		$wgWikiUrlEnding              = $config->get("WikiUrlEnding");
		$wgSlackIncludePageUrls       = $config->get("SlackIncludePageUrls");
		$wgWikiUrlEndingHistory       = $config->get("WikiUrlEndingHistory");
		$wgWikiUrlEndingEditArticle   = $config->get("WikiUrlEndingEditArticle");
		$wgWikiUrlEndingDeleteArticle = $config->get("WikiUrlEndingDeleteArticle");

		$titleName = $title->getFullText();
		if ($wgSlackIncludePageUrls) {
			return sprintf(
				"<%s|%s> (<%s|edit> | <%s|delete> | <%s|history>)",
				$wgWikiUrl . $wgWikiUrlEnding . $titleName,
				$titleName,
				$wgWikiUrl . $wgWikiUrlEnding . $titleName . "&" . $wgWikiUrlEndingEditArticle,
				$wgWikiUrl . $wgWikiUrlEnding . $titleName . "&" . $wgWikiUrlEndingDeleteArticle,
				$wgWikiUrl . $wgWikiUrlEnding . $titleName . "&" . $wgWikiUrlEndingHistory
			);
		} else {
			return sprintf("<%s|%s>", $wgWikiUrl . $wgWikiUrlEnding . $titleName, $titleName);
		}
	}

	/**
	 * Occurs after the save page request has been processed.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageContentSaveComplete
	 */
	public static function slack_article_saved(
		WikiPage $article,
		User $user,
		Content $content,
		$summary,
		$isMinor,
		$isWatch,
		$section,
		$flags,
		Revision $revision,
		Status $status,
		$baseRevId,
		$undidRevId = 0
	) {
		$config                           = self::getExtConfig();
		$wgSlackIncludeDiffSize           = $config->get("SlackIncludeDiffSize");
		$wgSlackIgnoreMinorEdits          = $config->get("SlackIgnoreMinorEdits");
		$wgSlackExcludeNotificationsFrom  = $config->get("SlackExcludeNotificationsFrom");
		$wgSlackNotificationEditedArticle = $config->get("SlackNotificationEditedArticle");;

		if (!$wgSlackNotificationEditedArticle) {
			return;
		}

		// Discard notifications from excluded pages
		if (is_array($wgSlackExcludeNotificationsFrom)) {
			foreach ($wgSlackExcludeNotificationsFrom as $currentExclude) {
				if (0 === strpos($article->getTitle(), $currentExclude)) {
					return;
				}
			}
		}

		// Skip new articles that have view count below 1. Adding new articles is already handled in article_added function and
		// calling it also here would trigger two notifications!
		// Skip minor edits if user wanted to ignore them
		if ((int)$status->value['new'] === 1 || ($isMinor && $wgSlackIgnoreMinorEdits)) {
			return true;
		}

		if ($article->getRevision()->getPrevious() === null) {
			return; // Skip edits that are just refreshing the page
		}
		
		$message = sprintf(
			"%s has %s article %s %s",
			self::getSlackUserText($user),
			$isMinor === true ? "made minor edit to" : "edited",
			self::getSlackArticleText($article, true),
			$summary === "" ? "" : "Summary: $summary"
		);
		if ($wgSlackIncludeDiffSize) {
			$message .= sprintf(
				" (%+d bytes)",
				$article->getRevision()->getSize() - $article->getRevision()->getPrevious()->getSize()
			);
		}
		self::send_slack_notification($message, "yellow", $user);
		return true;
	}

	/**
	 * Occurs after a new article has been created.
	 * @see http://www.mediawiki.org/wiki/Manual:Hooks/ArticleInsertComplete
	 */
	public static function slack_article_inserted(
		WikiPage $article,
		User $user,
		Content $text,
		$summary,
		$isminor,
		$iswatch,
		$section,
		$flags,
		Revision $revision
	) {
		$config                           = self::getExtConfig();
		$wgSlackIncludeDiffSize           = $config->get("SlackIncludeDiffSize");
		$wgSlackExcludeNotificationsFrom  = $config->get("SlackExcludeNotificationsFrom");
		$wgSlackNotificationAddedArticle  = $config->get("SlackNotificationAddedArticle");;

		if (!$wgSlackNotificationAddedArticle) {
			return;
		}

		// Discard notifications from excluded pages
		if (is_array($wgSlackExcludeNotificationsFrom)) {
			foreach ($wgSlackExcludeNotificationsFrom as $currentExclude) {
				if (0 === strpos($article->getTitle(), $currentExclude)) {
					return;
				}
			}
		}

		// Do not announce newly added file uploads as articles...
		if ($article->getTitle()->getNsText() === "File") {
			return;
		}

		$message = sprintf(
			"%s has created article %s %s",
			self::getSlackUserText($user),
			self::getSlackArticleText($article),
			$summary == "" ? "" : "Summary: $summary"
		);

		if ($wgSlackIncludeDiffSize) {
			$message .= sprintf(
				" (%d bytes)",
				$article->getRevision()->getSize()
			);
		}
		self::send_slack_notification($message, "green", $user);
		return true;
	}

	/**
	 * Occurs after the delete article request has been processed.
	 * @see http://www.mediawiki.org/wiki/Manual:Hooks/ArticleDeleteComplete
	 */
	public static function slack_article_deleted(
		WikiPage $article,
		User $user,
		$reason,
		$id,
		Content $content,
		LogEntry $logEntry
	) {
		$config                            = self::getExtConfig();
		$wgSlackExcludeNotificationsFrom   = $config->get("SlackExcludeNotificationsFrom");
		$wgSlackNotificationRemovedArticle = $config->get("SlackNotificationRemovedArticle");;

		if (!$wgSlackNotificationRemovedArticle) {
			return;
		}

		// Discard notifications from excluded pages
		if (is_array($wgSlackExcludeNotificationsFrom)) {
			foreach ($wgSlackExcludeNotificationsFrom as $currentExclude) {
				if (0 === strpos($article->getTitle(), $currentExclude)) {
					return;
				}
			}
		}

		$message = sprintf(
			"%s has deleted article %s Reason: %s",
			self::getSlackUserText($user),
			self::getSlackArticleText($article),
			$reason
		);
		self::send_slack_notification($message, "red", $user);
		return true;
	}

	/**
	 * Occurs after a page has been moved.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/TitleMoveComplete
	 */
	public static function slack_article_moved(
		Title $title,
		Title $newtitle,
		User $user,
		$oldid,
		$newid,
		$reason = null,
		Revision $revision = null
	) {
		$config                           = self::getExtConfig();
		$wgSlackExcludeNotificationsFrom  = $config->get("SlackExcludeNotificationsFrom");
		$wgSlackNotificationMovedArticle  = $config->get("SlackNotificationMovedArticle");;

		if (!$wgSlackNotificationMovedArticle) {
			return;
		}

		// Discard notifications from excluded pages
		if (is_array($wgSlackExcludeNotificationsFrom)) {
			foreach ($wgSlackExcludeNotificationsFrom as $currentExclude) {
				if (0 === strpos($title, $currentExclude)) {
					return;
				}
				if (0 === strpos($newtitle, $currentExclude)) {
					return;
				}
			}
		}

		$message = sprintf(
			"%s has moved article %s to %s. Reason: %s",
			self::getSlackUserText($user),
			self::getSlackTitleText($title),
			self::getSlackTitleText($newtitle),
			$reason
		);
		self::send_slack_notification($message, "green", $user);
		return true;
	}

	/**
	 * Occurs after the protect article request has been processed.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ArticleProtectComplete
	 */
	public static function slack_article_protected(
		WikiPage $article,
		User $user,
		$protect,
		$reason,
		$moveonly = false
	) {
		$config                              = self::getExtConfig();
		$wgSlackExcludeNotificationsFrom     = $config->get("SlackExcludeNotificationsFrom");
		$wgSlackNotificationProtectedArticle = $config->get("SlackNotificationProtectedArticle");;

		if (!$wgSlackNotificationProtectedArticle) {
			return;
		}

		// Discard notifications from excluded pages
		if (is_array($wgSlackExcludeNotificationsFrom)) {
			foreach ($wgSlackExcludeNotificationsFrom as $currentExclude) {
				if (0 === strpos($article->getTitle(), $currentExclude)) {
					return;
				}
			}
		}

		$message = sprintf(
			"%s has %s article %s. Reason: %s",
			self::getSlackUserText($user),
			$protect ? "changed protection of" : "removed protection of",
			self::getSlackArticleText($article),
			$reason
		);
		self::send_slack_notification($message, "yellow", $user);
		return true;
	}

	/**
	 * Called after a user account is created.
	 * @see http://www.mediawiki.org/wiki/Manual:Hooks/AddNewAccount
	 */
	public static function slack_new_user_account(User $user, $byEmail)
	{
		$config                     = self::getExtConfig();
		$wgSlackShowNewUserIP       = $config->get("SlackShowNewUserIP");
		$wgSlackShowNewUserEmail    = $config->get("SlackShowNewUserEmail");
		$wgSlackNotificationNewUser = $config->get("SlackNotificationNewUser");
		$wgSlackShowNewUserFullName = $config->get("SlackShowNewUserFullName");

		if (!$wgSlackNotificationNewUser) {
			return;
		}

		try {
			$email = $user->getEmail();
		} catch (Exception $e) {
			$email = "";
		}
		try {
			$realname = $user->getRealName();
		} catch (Exception $e) {
			$realname = "";
		}
		try {
			$ipaddress = $user->getRequest()->getIP();
		} catch (Exception $e) {
			$ipaddress = "";
		}

		$messageExtra = "";
		if ($wgSlackShowNewUserEmail || $wgSlackShowNewUserFullName || $wgSlackShowNewUserIP) {
			$messageExtra = "(";
			if ($wgSlackShowNewUserEmail && $email) {
				$messageExtra .= $email . ", ";
			}
			if ($wgSlackShowNewUserFullName && $realname) {
				$messageExtra .= $realname . ", ";
			}
			if ($wgSlackShowNewUserIP && $ipaddress) {
				$messageExtra .= $ipaddress . ", ";
			}
			$messageExtra = substr($messageExtra, 0, -2); // Remove trailing , 
			$messageExtra .= ")";
		}

		$message = sprintf(
			"New user account %s was just created %s",
			self::getSlackUserText($user),
			$messageExtra
		);
		self::send_slack_notification($message, "green", $user);
		return true;
	}

	/**
	 * Called when a file upload has completed.
	 * @see http://www.mediawiki.org/wiki/Manual:Hooks/UploadComplete
	 */
	public static function slack_file_uploaded(UploadBase $image)
	{
		$config                        = self::getExtConfig();
		$wgWikiUrl                     = $config->get("WikiUrl");
		$wgWikiUrlEnding               = $config->get("WikiUrlEnding");
		$wgSlackNotificationFileUpload = $config->get("SlackNotificationFileUpload");

		if (!$wgSlackNotificationFileUpload) {
			return;
		}

		global $wgUser;
		$message = sprintf(
			"%s has uploaded file <%s|%s> (format: %s, size: %s MB, summary: %s)",
			self::getSlackUserText($wgUser->mName),
			$wgWikiUrl . $wgWikiUrlEnding . $image->getLocalFile()->getTitle(),
			$image->getLocalFile()->getTitle(),
			$image->getLocalFile()->getMimeType(),
			round($image->getLocalFile()->size / 1024 / 1024, 3),
			$image->getLocalFile()->getDescription()
		);

		self::send_slack_notification($message, "green", $wgUser);
		return true;
	}

	/**
	 * Occurs after the request to block an IP or user has been processed
	 * @see http://www.mediawiki.org/wiki/Manual:MediaWiki_hooks/BlockIpComplete
	 */
	public static function slack_user_blocked(Block $block, User $user)
	{
		$config                         = self::getExtConfig();
		$wgWikiUrl                      = $config->get("WikiUrl");
		$wgWikiUrlEnding                = $config->get("WikiUrlEnding");
		$wgWikiUrlEndingBlockList       = $config->get("WikiUrlEndingBlockList");
		$wgSlackNotificationBlockedUser = $config->get("SlackNotificationBlockedUser");

		if (!$wgSlackNotificationBlockedUser) {
			return;
		}

		$message = sprintf(
			"%s has blocked %s%s. Block expiration: %s. <%s|List of all blocks>.",
			self::getSlackUserText($user),
			self::getSlackUserText($block->getTarget()),
			$block->mReason == "" ? "" : "with reason '$block->mReason'",
			$block->mExpiry,
			$wgWikiUrl . $wgWikiUrlEnding . $wgWikiUrlEndingBlockList
		);
		self::send_slack_notification($message, "red", $user);
		return true;
	}

	/**
	 * Sends the message to the Slack webhook
	 *
	 * @param string $message Message to be sent.
	 * @param string $colour Deprecated
	 * @param User $user The Mediawiki user object.
	 * @param array $attach Array of attachment objects to be sent.
	 * @return void
	 * @see https://api.slack.com/incoming-webhooks
	 */
	private static function send_slack_notification($message, $colour, $user, $attach = array())
	{
		$mwconfig = self::getMwConfig();
		$config   = self::getExtConfig();
		$wgExcludedPermission      = $mwconfig->get("ExcludedPermission");
		$wgSitename                = $mwconfig->get("Sitename");
		$wgHTTPProxy               = $mwconfig->get("HTTPProxy");
		$wgSlackIncomingWebhookUrl = $config->get("SlackIncomingWebhookUrl");
		$wgSlackFromName           = $config->get("SlackFromName");
		$wgSlackRoomName           = $config->get("SlackRoomName");
		$wgSlackSendMethod         = $config->get("SlackSendMethod");
		$wgSlackEmoji              = $config->get("SlackEmoji");
		
		if ($wgExcludedPermission && $user->isAllowed($wgExcludedPermission)) {
			return; // Users with the permission suppress notifications
		}

		$post_data = array(
			"text"        => $message,
			"channel"     => $wgSlackRoomName ?: null,
			"username"    => $wgSlackFromName ?: $wgSitename,
			"icon_emoji"  => $wgSlackEmoji    ?: null,
			"attachments" => $attach,
		);
		$post_data = json_encode($post_data);

		// Use file_get_contents to send the data. Note that you will need to have allow_url_fopen enabled in php.ini for this to work.
		if ($wgSlackSendMethod == "file_get_contents") {
			$options = array(
				"http" => array(
					"header"  => "Content-type: application/json",
					"method"  => "POST",
					"content" => $post_data,
				),
			);
			if ($wgHTTPProxy) {
				$options["http"]["proxy"]           = $wgHTTPProxy;
				$options["http"]["request_fulluri"] = true;
			}
			$context = stream_context_create($options);
			$result = file_get_contents($wgSlackIncomingWebhookUrl, false, $context);
		}
		// Call the Slack API through cURL (default way). Note that you will need to have cURL enabled for this to work.
		else {
			$h = curl_init();
			curl_setopt_array($h, array(
				CURLOPT_URL        => $wgSlackIncomingWebhookUrl,
				CURLOPT_POST       => true,
				CURLOPT_POSTFIELDS => $post_data,
				CURLOPT_HTTPHEADER => array("Content-Type: application/json"),
				CURLOPT_PROXY      => $wgHTTPProxy ?: null,
			));
			curl_exec($h);
			curl_close($h);
		}
	}
}
