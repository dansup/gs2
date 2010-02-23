<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009-2010, StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * @package OStatusPlugin
 * @maintainer Brion Vibber <brion@status.net>
 */

class Ostatus_profile extends Memcached_DataObject
{
    public $__table = 'ostatus_profile';

    public $uri;

    public $profile_id;
    public $group_id;

    public $feeduri;
    public $salmonuri;

    public $created;
    public $modified;

    public /*static*/ function staticGet($k, $v=null)
    {
        return parent::staticGet(__CLASS__, $k, $v);
    }

    /**
     * return table definition for DB_DataObject
     *
     * DB_DataObject needs to know something about the table to manipulate
     * instances. This method provides all the DB_DataObject needs to know.
     *
     * @return array array of column definitions
     */

    function table()
    {
        return array('uri' => DB_DATAOBJECT_STR + DB_DATAOBJECT_NOTNULL,
                     'profile_id' => DB_DATAOBJECT_INT,
                     'group_id' => DB_DATAOBJECT_INT,
                     'feeduri' => DB_DATAOBJECT_STR,
                     'salmonuri' =>  DB_DATAOBJECT_STR,
                     'created' => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME + DB_DATAOBJECT_NOTNULL,
                     'modified' => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME + DB_DATAOBJECT_NOTNULL);
    }

    static function schemaDef()
    {
        return array(new ColumnDef('uri', 'varchar',
                                   255, false, 'PRI'),
                     new ColumnDef('profile_id', 'integer',
                                   null, true, 'UNI'),
                     new ColumnDef('group_id', 'integer',
                                   null, true, 'UNI'),
                     new ColumnDef('feeduri', 'varchar',
                                   255, true, 'UNI'),
                     new ColumnDef('salmonuri', 'text',
                                   null, true),
                     new ColumnDef('created', 'datetime',
                                   null, false),
                     new ColumnDef('modified', 'datetime',
                                   null, false));
    }

    /**
     * return key definitions for DB_DataObject
     *
     * DB_DataObject needs to know about keys that the table has; this function
     * defines them.
     *
     * @return array key definitions
     */

    function keys()
    {
        return array_keys($this->keyTypes());
    }

    /**
     * return key definitions for Memcached_DataObject
     *
     * Our caching system uses the same key definitions, but uses a different
     * method to get them.
     *
     * @return array key definitions
     */

    function keyTypes()
    {
        return array('uri' => 'K', 'profile_id' => 'U', 'group_id' => 'U', 'feeduri' => 'U');
    }

    function sequenceKey()
    {
        return array(false, false, false);
    }

    /**
     * Fetch the StatusNet-side profile for this feed
     * @return Profile
     */
    public function localProfile()
    {
        if ($this->profile_id) {
            return Profile::staticGet('id', $this->profile_id);
        }
        return null;
    }

    /**
     * Fetch the StatusNet-side profile for this feed
     * @return Profile
     */
    public function localGroup()
    {
        if ($this->group_id) {
            return User_group::staticGet('id', $this->group_id);
        }
        return null;
    }

    /**
     * Returns an ActivityObject describing this remote user or group profile.
     * Can then be used to generate Atom chunks.
     *
     * @return ActivityObject
     */
    function asActivityObject()
    {
        if ($this->isGroup()) {
            $object = new ActivityObject();
            $object->type = 'http://activitystrea.ms/schema/1.0/group';
            $object->id = $this->uri;
            $self = $this->localGroup();

            // @fixme put a standard getAvatar() interface on groups too
            if ($self->homepage_logo) {
                $object->avatar = $self->homepage_logo;
                $map = array('png' => 'image/png',
                             'jpg' => 'image/jpeg',
                             'jpeg' => 'image/jpeg',
                             'gif' => 'image/gif');
                $extension = pathinfo(parse_url($avatarHref, PHP_URL_PATH), PATHINFO_EXTENSION);
                if (isset($map[$extension])) {
                    // @fixme this ain't used/saved yet
                    $object->avatarType = $map[$extension];
                }
            }

            $object->link = $this->uri; // @fixme accurate?
            return $object;
        } else {
            return ActivityObject::fromProfile($this->localProfile());
        }
    }

    /**
     * Returns an XML string fragment with profile information as an
     * Activity Streams noun object with the given element type.
     *
     * Assumes that 'activity' namespace has been previously defined.
     *
     * @fixme replace with wrappers on asActivityObject when it's got everything.
     *
     * @param string $element one of 'actor', 'subject', 'object', 'target'
     * @return string
     */
    function asActivityNoun($element)
    {
        $xs = new XMLStringer(true);
        $avatarHref = Avatar::defaultImage(AVATAR_PROFILE_SIZE);
        $avatarType = 'image/png';
        if ($this->isGroup()) {
            $type = 'http://activitystrea.ms/schema/1.0/group';
            $self = $this->localGroup();

            // @fixme put a standard getAvatar() interface on groups too
            if ($self->homepage_logo) {
                $avatarHref = $self->homepage_logo;
                $map = array('png' => 'image/png',
                             'jpg' => 'image/jpeg',
                             'jpeg' => 'image/jpeg',
                             'gif' => 'image/gif');
                $extension = pathinfo(parse_url($avatarHref, PHP_URL_PATH), PATHINFO_EXTENSION);
                if (isset($map[$extension])) {
                    $avatarType = $map[$extension];
                }
            }
        } else {
            $type = 'http://activitystrea.ms/schema/1.0/person';
            $self = $this->localProfile();
            $avatar = $self->getAvatar(AVATAR_PROFILE_SIZE);
            if ($avatar) {
                  $avatarHref = $avatar->url;
                  $avatarType = $avatar->mediatype;
            }
        }
        $xs->elementStart('activity:' . $element);
        $xs->element(
            'activity:object-type',
            null,
            $type
        );
        $xs->element(
            'id',
            null,
            $this->uri); // ?
        $xs->element('title', null, $self->getBestName());

        $xs->element(
            'link', array(
                'type' => $avatarType,
                'href' => $avatarHref
            ),
            ''
        );

        $xs->elementEnd('activity:' . $element);

        return $xs->getString();
    }

    /**
     * @return boolean true if this is a remote group
     */
    function isGroup()
    {
        if ($this->profile_id && !$this->group_id) {
            return false;
        } else if ($this->group_id && !$this->profile_id) {
            return true;
        } else if ($this->group_id && $this->profile_id) {
            throw new ServerException("Invalid ostatus_profile state: both group and profile IDs set for $this->uri");
        } else {
            throw new ServerException("Invalid ostatus_profile state: both group and profile IDs empty for $this->uri");
        }
    }

    /**
     * Subscribe a local user to this remote user.
     * PuSH subscription will be started if necessary, and we'll
     * send a Salmon notification to the remote server if available
     * notifying them of the sub.
     *
     * @param User $user
     * @return boolean success
     * @throws FeedException
     */
    public function subscribeLocalToRemote(User $user)
    {
        if ($this->isGroup()) {
            throw new ServerException("Can't subscribe to a remote group");
        }

        if ($this->subscribe()) {
            if ($user->subscribeTo($this->localProfile())) {
                $this->notify($user->getProfile(), ActivityVerb::FOLLOW, $this);
                return true;
            }
        }
        return false;
    }

    /**
     * Mark this remote profile as subscribing to the given local user,
     * and send appropriate notifications to the user.
     *
     * This will generally be in response to a subscription notification
     * from a foreign site to our local Salmon response channel.
     *
     * @param User $user
     * @return boolean success
     */
    public function subscribeRemoteToLocal(User $user)
    {
        if ($this->isGroup()) {
            throw new ServerException("Remote groups can't subscribe to local users");
        }

        // @fixme use regular channels for subbing, once they accept remote profiles
        $sub = new Subscription();
        $sub->subscriber = $this->profile_id;
        $sub->subscribed = $user->id;
        $sub->created = common_sql_now(); // current time

        if ($sub->insert()) {
            // @fixme use subs_notify() if refactored to take profiles?
            mail_subscribe_notify_profile($user, $this->localProfile());
            return true;
        }
        return false;
    }

    /**
     * Send a subscription request to the hub for this feed.
     * The hub will later send us a confirmation POST to /main/push/callback.
     *
     * @return bool true on success, false on failure
     * @throws ServerException if feed state is not valid
     */
    public function subscribe()
    {
        $feedsub = FeedSub::ensureFeed($this->feeduri);
        if ($feedsub->sub_state == 'active' || $feedsub->sub_state == 'subscribe') {
            return true;
        } else if ($feedsub->sub_state == '' || $feedsub->sub_state == 'inactive') {
            return $feedsub->subscribe();
        } else if ('unsubscribe') {
            throw new FeedSubException("Unsub is pending, can't subscribe...");
        }
    }

    /**
     * Send a PuSH unsubscription request to the hub for this feed.
     * The hub will later send us a confirmation POST to /main/push/callback.
     *
     * @return bool true on success, false on failure
     * @throws ServerException if feed state is not valid
     */
    public function unsubscribe() {
        $feedsub = FeedSub::staticGet('uri', $this->feeduri);
        if ($feedsub->sub_state == 'active') {
            return $feedsub->unsubscribe();
        } else if ($feedsub->sub_state == '' || $feedsub->sub_state == 'inactive' || $feedsub->sub_state == 'unsubscribe') {
            return true;
        } else if ($feedsub->sub_state == 'subscribe') {
            throw new FeedSubException("Feed is awaiting subscription, can't unsub...");
        }
    }

    /**
     * Check if this remote profile has any active local subscriptions, and
     * if not drop the PuSH subscription feed.
     *
     * @return boolean
     */
    public function garbageCollect()
    {
        if ($this->isGroup()) {
            $members = $this->localGroup()->getMembers(0, 1);
            $count = $members->N;
        } else {
            $count = $this->localProfile()->subscriberCount();
        }
        if ($count == 0) {
            common_log(LOG_INFO, "Unsubscribing from now-unused remote feed $oprofile->feeduri");
            $this->unsubscribe();
            return true;
        } else {
            return false;
        }
    }

    /**
     * Send an Activity Streams notification to the remote Salmon endpoint,
     * if so configured.
     *
     * @param Profile $actor  Actor who did the activity
     * @param string  $verb   Activity::SUBSCRIBE or Activity::JOIN
     * @param Object  $object object of the action; must define asActivityNoun($tag)
     */
    public function notify($actor, $verb, $object=null)
    {
        if (!($actor instanceof Profile)) {
            $type = gettype($actor);
            if ($type == 'object') {
                $type = get_class($actor);
            }
            throw new ServerException("Invalid actor passed to " . __METHOD__ . ": " . $type);
        }
        if ($object == null) {
            $object = $this;
        }
        if ($this->salmonuri) {

            $text = 'update';
            $id = TagURI::mint('%s:%s:%s',
                               $verb,
                               $actor->getURI(),
                               common_date_iso8601(time()));

            // @fixme consolidate all these NS settings somewhere
            $attributes = array('xmlns' => Activity::ATOM,
                                'xmlns:activity' => 'http://activitystrea.ms/spec/1.0/',
                                'xmlns:thr' => 'http://purl.org/syndication/thread/1.0',
                                'xmlns:georss' => 'http://www.georss.org/georss',
                                'xmlns:ostatus' => 'http://ostatus.org/schema/1.0',
                                'xmlns:poco' => 'http://portablecontacts.net/spec/1.0');

            $entry = new XMLStringer();
            $entry->elementStart('entry', $attributes);
            $entry->element('id', null, $id);
            $entry->element('title', null, $text);
            $entry->element('summary', null, $text);
            $entry->element('published', null, common_date_w3dtf(common_sql_now()));

            $entry->element('activity:verb', null, $verb);
            $entry->raw($actor->asAtomAuthor());
            $entry->raw($actor->asActivityActor());
            $entry->raw($object->asActivityNoun('object'));
            $entry->elementEnd('entry');

            $xml = $entry->getString();
            common_log(LOG_INFO, "Posting to Salmon endpoint $this->salmonuri: $xml");

            $salmon = new Salmon(); // ?
            return $salmon->post($this->salmonuri, $xml);
        }
        return false;
    }

    public function notifyActivity($activity)
    {
        if ($this->salmonuri) {

            $xml = '<?xml version="1.0" encoding="UTF-8" ?' . '>' .
                          $activity->asString(true);

            $salmon = new Salmon(); // ?

            return $salmon->post($this->salmonuri, $xml);
        }

        return false;
    }

    function getBestName()
    {
        if ($this->isGroup()) {
            return $this->localGroup()->getBestName();
        } else {
            return $this->localProfile()->getBestName();
        }
    }

    function atomFeed($actor)
    {
        $feed = new Atom10Feed();
        // @fixme should these be set up somewhere else?
        $feed->addNamespace('activity', 'http://activitystrea.ms/spec/1.0/');
        $feed->addNamespace('thr', 'http://purl.org/syndication/thread/1.0');
        $feed->addNamespace('georss', 'http://www.georss.org/georss');
        $feed->addNamespace('ostatus', 'http://ostatus.org/schema/1.0');

        $taguribase = common_config('integration', 'taguri');
        $feed->setId("tag:{$taguribase}:UserTimeline:{$actor->id}"); // ???

        $feed->setTitle($actor->getBestName() . ' timeline'); // @fixme
        $feed->setUpdated(time());
        $feed->setPublished(time());

        $feed->addLink(common_local_url('ApiTimelineUser',
                                        array('id' => $actor->id,
                                              'type' => 'atom')),
                       array('rel' => 'self',
                             'type' => 'application/atom+xml'));

        $feed->addLink(common_local_url('userbyid',
                                        array('id' => $actor->id)),
                       array('rel' => 'alternate',
                             'type' => 'text/html'));

        return $feed;
    }

    /**
     * Read and post notices for updates from the feed.
     * Currently assumes that all items in the feed are new,
     * coming from a PuSH hub.
     *
     * @param DOMDocument $feed
     */
    public function processFeed($feed)
    {
        $entries = $feed->getElementsByTagNameNS(Activity::ATOM, 'entry');
        if ($entries->length == 0) {
            common_log(LOG_ERR, __METHOD__ . ": no entries in feed update, ignoring");
            return;
        }

        for ($i = 0; $i < $entries->length; $i++) {
            $entry = $entries->item($i);
            $this->processEntry($entry, $feed);
        }
    }

    /**
     * Process a posted entry from this feed source.
     *
     * @param DOMElement $entry
     * @param DOMElement $feed for context
     */
    protected function processEntry($entry, $feed)
    {
        $activity = new Activity($entry, $feed);

        $debug = var_export($activity, true);
        common_log(LOG_DEBUG, $debug);

        if ($activity->verb == ActivityVerb::POST) {
            $this->processPost($activity);
        } else {
            common_log(LOG_INFO, "Ignoring activity with unrecognized verb $activity->verb");
        }
    }

    /**
     * Process an incoming post activity from this remote feed.
     * @param Activity $activity
     * @fixme break up this function, it's getting nasty long
     */
    protected function processPost($activity)
    {
        if ($this->isGroup()) {
            // @fixme validate these profiles in some way!
            $oprofile = self::ensureActorProfile($activity);
        } else {
            $actorUri = self::getActorProfileURI($activity);
            if ($actorUri == $this->uri) {
                // @fixme check if profile info has changed and update it
            } else {
                // @fixme drop or reject the messages once we've got the canonical profile URI recorded sanely
                common_log(LOG_INFO, "OStatus: Warning: non-group post with unexpected author: $actorUri expected $this->uri");
                //return;
            }
            $oprofile = $this;
        }
        $sourceUri = $activity->object->id;

        $dupe = Notice::staticGet('uri', $sourceUri);

        if ($dupe) {
            common_log(LOG_INFO, "OStatus: ignoring duplicate post: $sourceUri");
            return;
        }

        $sourceUrl = null;

        if ($activity->object->link) {
            $sourceUrl = $activity->object->link;
        } else if (preg_match('!^https?://!', $activity->object->id)) {
            $sourceUrl = $activity->object->id;
        }

        // @fixme sanitize and save HTML content if available

        $content = $activity->object->title;

        $params = array('is_local' => Notice::REMOTE_OMB,
                        'url' => $sourceUrl,
                        'uri' => $sourceUri);

        $location = $activity->context->location;

        if ($location) {
            $params['lat'] = $location->lat;
            $params['lon'] = $location->lon;
            if ($location->location_id) {
                $params['location_ns'] = $location->location_ns;
                $params['location_id'] = $location->location_id;
            }
        }

        $profile = $oprofile->localProfile();
        $params['groups'] = array();
        $params['replies'] = array();
        if ($activity->context) {
            foreach ($activity->context->attention as $recipient) {
                $roprofile = Ostatus_profile::staticGet('uri', $recipient);
                if ($roprofile) {
                    if ($roprofile->isGroup()) {
                        // Deliver to local recipients of this remote group.
                        // @fixme sender verification?
                        $params['groups'][] = $roprofile->group_id;
                        continue;
                    } else {
                        // Delivery to remote users is the source service's job.
                        continue;
                    }
                }
    
                $user = User::staticGet('uri', $recipient);
                if ($user) {
                    // An @-reply directed to a local user.
                    // @fixme sender verification, spam etc?
                    $params['replies'][] = $recipient;
                    continue;
                }
    
                // @fixme we need a uri on user_group
                // $group = User_group::staticGet('uri', $recipient);
                $template = common_local_url('groupbyid', array('id' => '31337'));
                $template = preg_quote($template, '/');
                $template = str_replace('31337', '(\d+)', $template);
                common_log(LOG_DEBUG, $template);
                if (preg_match("/$template/", $recipient, $matches)) {
                    $id = $matches[1];
                    $group = User_group::staticGet('id', $id);
                    if ($group) {
                        // Deliver to all members of this local group.
                        // @fixme sender verification?
                        if ($profile->isMember($group)) {
                            common_log(LOG_DEBUG, "delivering to group $id $group->nickname");
                            $params['groups'][] = $group->id;
                        } else {
                            common_log(LOG_DEBUG, "not delivering to group $id $group->nickname because sender $profile->nickname is not a member");
                        }
                        continue;
                    } else {
                        common_log(LOG_DEBUG, "not delivering to missing group $id");
                    }
                } else {
                    common_log(LOG_DEBUG, "not delivering to groups for $recipient");
                }
            }
        }

        try {
            $saved = Notice::saveNew($profile->id,
                                     $content,
                                     'ostatus',
                                     $params);
        } catch (Exception $e) {
            common_log(LOG_ERR, "Failed saving notice entry for $sourceUri: " . $e->getMessage());
            return;
        }

        // Record which feed this came through...
        try {
            Ostatus_source::saveNew($saved, $this, 'push');
        } catch (Exception $e) {
            common_log(LOG_ERR, "Failed saving ostatus_source entry for $saved->notice_id: " . $e->getMessage());
        }
    }

    /**
     * @param string $profile_url
     * @return Ostatus_profile
     * @throws FeedSubException
     */
    public static function ensureProfile($profile_uri, $hints=array())
    {
        // Get the canonical feed URI and check it
        $discover = new FeedDiscovery();
        $feeduri = $discover->discoverFromURL($profile_uri);

        //$feedsub = FeedSub::ensureFeed($feeduri, $discover->feed);
        $huburi = $discover->getAtomLink('hub');
        $salmonuri = $discover->getAtomLink('salmon');

        if (!$huburi) {
            // We can only deal with folks with a PuSH hub
            throw new FeedSubNoHubException();
        }

        // Try to get a profile from the feed activity:subject

        $feedEl = $discover->feed->documentElement;

        $subject = ActivityUtils::child($feedEl, Activity::SUBJECT, Activity::SPEC);

        if (!empty($subject)) {
            $subjObject = new ActivityObject($subject);
            return self::ensureActivityObjectProfile($subjObject, $feeduri, $salmonuri, $hints);
        }

        // Otherwise, try the feed author

        $author = ActivityUtils::child($feedEl, Activity::AUTHOR, Activity::ATOM);

        if (!empty($author)) {
            $authorObject = new ActivityObject($author);
            return self::ensureActivityObjectProfile($authorObject, $feeduri, $salmonuri, $hints);
        }

        // Sheesh. Not a very nice feed! Let's try fingerpoken in the
        // entries.

        $entries = $discover->feed->getElementsByTagNameNS(Activity::ATOM, 'entry');

        if (!empty($entries) && $entries->length > 0) {

            $entry = $entries->item(0);

            $actor = ActivityUtils::child($entry, Activity::ACTOR, Activity::SPEC);

            if (!empty($actor)) {
                $actorObject = new ActivityObject($actor);
                return self::ensureActivityObjectProfile($actorObject, $feeduri, $salmonuri, $hints);

            }

            $author = ActivityUtils::child($entry, Activity::AUTHOR, Activity::ATOM);

            if (!empty($author)) {
                $authorObject = new ActivityObject($author);
                return self::ensureActivityObjectProfile($authorObject, $feeduri, $salmonuri, $hints);
            }
        }

        // XXX: make some educated guesses here

        throw new FeedSubException("Can't find enough profile information to make a feed.");
    }

    /**
     *
     * Download and update given avatar image
     * @param string $url
     * @throws Exception in various failure cases
     */
    protected function updateAvatar($url)
    {
        if ($this->isGroup()) {
            $self = $this->localGroup();
        } else {
            $self = $this->localProfile();
        }
        if (!$self) {
            throw new ServerException(sprintf(
                _m("Tried to update avatar for unsaved remote profile %s"),
                $this->uri));
        }

        // @fixme this should be better encapsulated
        // ripped from oauthstore.php (for old OMB client)
        $temp_filename = tempnam(sys_get_temp_dir(), 'listener_avatar');
        if (!copy($url, $temp_filename)) {
            throw new ServerException(sprintf(_m("Unable to fetch avatar from %s"), $url));
        }

        if ($this->isGroup()) {
            $id = $this->group_id;
        } else {
            $id = $this->profile_id;
        }
        // @fixme should we be using different ids?
        $imagefile = new ImageFile($id, $temp_filename);
        $filename = Avatar::filename($id,
                                     image_type_to_extension($imagefile->type),
                                     null,
                                     common_timestamp());
        rename($temp_filename, Avatar::path($filename));
        $self->setOriginal($filename);
    }

    protected static function getActivityObjectAvatar($object)
    {
        // XXX: go poke around in the feed
        return $object->avatar;
    }

    /**
     * Get an appropriate avatar image source URL, if available.
     *
     * @param ActivityObject $actor
     * @param DOMElement $feed
     * @return string
     */

    protected static function getAvatar($actor, $feed)
    {
        $url = '';
        $icon = '';
        if ($actor->avatar) {
            $url = trim($actor->avatar);
        }
        if (!$url) {
            // Check <atom:logo> and <atom:icon> on the feed
            $els = $feed->childNodes();
            if ($els && $els->length) {
                for ($i = 0; $i < $els->length; $i++) {
                    $el = $els->item($i);
                    if ($el->namespaceURI == Activity::ATOM) {
                        if (empty($url) && $el->localName == 'logo') {
                            $url = trim($el->textContent);
                            break;
                        }
                        if (empty($icon) && $el->localName == 'icon') {
                            // Use as a fallback
                            $icon = trim($el->textContent);
                        }
                    }
                }
            }
            if ($icon && !$url) {
                $url = $icon;
            }
        }
        if ($url) {
            $opts = array('allowed_schemes' => array('http', 'https'));
            if (Validate::uri($url, $opts)) {
                return $url;
            }
        }
        return common_path('plugins/OStatus/images/96px-Feed-icon.svg.png');
    }

    /**
     * Fetch, or build if necessary, an Ostatus_profile for the actor
     * in a given Activity Streams activity.
     *
     * @param Activity $activity
     * @param string $feeduri if we already know the canonical feed URI!
     * @param string $salmonuri if we already know the salmon return channel URI
     * @return Ostatus_profile
     */

    public static function ensureActorProfile($activity, $feeduri=null, $salmonuri=null)
    {
        return self::ensureActivityObjectProfile($activity->actor, $feeduri, $salmonuri);
    }

    public static function ensureActivityObjectProfile($object, $feeduri=null, $salmonuri=null, $hints=array())
    {
        $profile = self::getActivityObjectProfile($object);
        if (!$profile) {
            $profile = self::createActivityObjectProfile($object, $feeduri, $salmonuri, $hints);
        }
        return $profile;
    }

    /**
     * @param Activity $activity
     * @return mixed matching Ostatus_profile or false if none known
     */
    protected static function getActorProfile($activity)
    {
        return self::getActivityObjectProfile($activity->actor);
    }

    protected static function getActivityObjectProfile($object)
    {
        $uri = self::getActivityObjectProfileURI($object);
        return Ostatus_profile::staticGet('uri', $uri);
    }

    protected static function getActorProfileURI($activity)
    {
        return self::getActivityObjectProfileURI($activity->actor);
    }

    /**
     * @param Activity $activity
     * @return string
     * @throws ServerException
     */
    protected static function getActivityObjectProfileURI($object)
    {
        $opts = array('allowed_schemes' => array('http', 'https'));
        if ($object->id && Validate::uri($object->id, $opts)) {
            return $object->id;
        }
        if ($object->link && Validate::uri($object->link, $opts)) {
            return $object->link;
        }
        throw new ServerException("No author ID URI found");
    }

    /**
     * @fixme validate stuff somewhere
     */

    protected static function createActorProfile($activity, $feeduri=null, $salmonuri=null)
    {
        $actor = $activity->actor;

        self::createActivityObjectProfile($actor, $feeduri, $salmonuri);
    }

    /**
     * Create local ostatus_profile and profile/user_group entries for
     * the provided remote user or group.
     *
     * @param ActivityObject $object
     * @param string $feeduri
     * @param string $salmonuri
     * @param array $hints
     *
     * @fixme fold $feeduri/$salmonuri into $hints
     * @return Ostatus_profile
     */
    protected static function createActivityObjectProfile($object, $feeduri=null, $salmonuri=null, $hints=array())
    {
        $homeuri  = $object->id;
        $nickname = self::getActivityObjectNickname($object, $hints);
        $avatar   = self::getActivityObjectAvatar($object);

        if (!$homeuri) {
            common_log(LOG_DEBUG, __METHOD__ . " empty actor profile URI: " . var_export($activity, true));
            throw new ServerException("No profile URI");
        }

        if (empty($feeduri)) {
            if (array_key_exists('feedurl', $hints)) {
                $feeduri = $hints['feedurl'];
            }
        }

        if (empty($salmonuri)) {
            if (array_key_exists('salmon', $hints)) {
                $salmonuri = $hints['salmon'];
            }
        }

        if (!$feeduri || !$salmonuri) {
            // Get the canonical feed URI and check it
            $discover = new FeedDiscovery();
            $feeduri = $discover->discoverFromURL($homeuri);

            $huburi = $discover->getAtomLink('hub');
            $salmonuri = $discover->getAtomLink('salmon');

            if (!$huburi) {
                // We can only deal with folks with a PuSH hub
                throw new FeedSubNoHubException();
            }
        }

        $oprofile = new Ostatus_profile();

        $oprofile->uri        = $homeuri;
        $oprofile->feeduri    = $feeduri;
        $oprofile->salmonuri  = $salmonuri;

        $oprofile->created    = common_sql_now();
        $oprofile->modified   = common_sql_now();

        if ($object->type == ActivityObject::PERSON) {
            $profile = new Profile();
            $profile->nickname   = $nickname;
            $profile->fullname   = $object->title;
            if (!empty($object->link)) {
                $profile->profileurl = $object->link;
            } else if (array_key_exists('profileurl', $hints)) {
                $profile->profileurl = $hints['profileurl'];
            }
            $profile->created    = common_sql_now();
    
            // @fixme bio
            // @fixme tags/categories
            // @fixme location?
            // @todo tags from categories
            // @todo lat/lon/location?
    
            $oprofile->profile_id = $profile->insert();
    
            if (!$oprofile->profile_id) {
                throw new ServerException("Can't save local profile");
            }
        } else {
            $group = new User_group();
            $group->nickname = $nickname;
            $group->fullname = $object->title;
            // @fixme no canonical profileurl; using homepage instead for now
            $group->homepage = $homeuri;
            $group->created = common_sql_now();

            // @fixme homepage
            // @fixme bio
            // @fixme tags/categories
            // @fixme location?
            // @todo tags from categories
            // @todo lat/lon/location?

            $oprofile->group_id = $group->insert();

            if (!$oprofile->group_id) {
                throw new ServerException("Can't save local profile");
            }
        }

        $ok = $oprofile->insert();

        if ($ok) {
            if ($avatar) {
                $oprofile->updateAvatar($avatar);
            }
            return $oprofile;
        } else {
            throw new ServerException("Can't save OStatus profile");
        }
    }

    protected static function getActivityObjectNickname($object, $hints=array())
    {
        if (!empty($object->nickname)) {
            return common_nicknamize($object->nickname);
        }

        // Try the definitive ID

        $nickname = self::nicknameFromURI($object->id);

        // Try a Webfinger if one was passed (way) down

        if (empty($nickname)) {
            if (array_key_exists('webfinger', $hints)) {
                $nickname = self::nicknameFromURI($hints['webfinger']);
            }
        }

        // Try the name

        if (empty($nickname)) {
            $nickname = common_nicknamize($object->title);
        }

        return $nickname;
    }

    protected static function nicknameFromURI($uri)
    {
        preg_match('/(\w+):/', $uri, $matches);

        $protocol = $matches[1];

        switch ($protocol) {
        case 'acct':
        case 'mailto':
            if (preg_match("/^$protocol:(.*)?@.*\$/", $uri, $matches)) {
                return common_canonical_nickname($matches[1]);
            }
            return null;
        case 'http':
            return common_url_to_nickname($uri);
            break;
        default:
            return null;
        }
    }

    public static function ensureWebfinger($addr)
    {
        // First, look it up

        $oprofile = Ostatus_profile::staticGet('uri', 'acct:'.$addr);

        if (!empty($oprofile)) {
            return $oprofile;
        }

        // Now, try some discovery

        $wf = new Webfinger();

        $result = $wf->lookup($addr);

        if (!$result) {
            return null;
        }

        foreach ($result->links as $link) {
            switch ($link['rel']) {
            case Webfinger::PROFILEPAGE:
                $profileUrl = $link['href'];
                break;
            case 'salmon':
                $salmonEndpoint = $link['href'];
                break;
            case Webfinger::UPDATESFROM:
                $feedUrl = $link['href'];
                break;
            default:
                common_log(LOG_NOTICE, "Don't know what to do with rel = '{$link['rel']}'");
                break;
            }
        }

        $hints = array('webfinger' => $addr,
                       'profileurl' => $profileUrl,
                       'feedurl' => $feedUrl,
                       'salmon' => $salmonEndpoint);

        // If we got a feed URL, try that

        if (isset($feedUrl)) {
            try {
                $oprofile = self::ensureProfile($feedUrl, $hints);
                return $oprofile;
            } catch (Exception $e) {
                common_log(LOG_WARNING, "Failed creating profile from feed URL '$feedUrl': " . $e->getMessage());
                // keep looking
            }
        }

        // If we got a profile page, try that!

        if (isset($profileUrl)) {
            try {
                $oprofile = self::ensureProfile($profileUrl, $hints);
                return $oprofile;
            } catch (Exception $e) {
                common_log(LOG_WARNING, "Failed creating profile from profile URL '$profileUrl': " . $e->getMessage());
                // keep looking
            }
        }

        // XXX: try hcard
        // XXX: try FOAF

        if (isset($salmonEndpoint)) {

            // An account URL, a salmon endpoint, and a dream? Not much to go
            // on, but let's give it a try

            $uri = 'acct:'.$addr;

            $profile = new Profile();

            $profile->nickname = self::nicknameFromUri($uri);
            $profile->created  = common_sql_now();

            if (isset($profileUrl)) {
                $profile->profileurl = $profileUrl;
            }

            $profile_id = $profile->insert();

            if (!$profile_id) {
                common_log_db_error($profile, 'INSERT', __FILE__);
                throw new Exception("Couldn't save profile for '$addr'");
            }

            $oprofile = new Ostatus_profile();

            $oprofile->uri        = $uri;
            $oprofile->salmonuri  = $salmonEndpoint;
            $oprofile->profile_id = $profile_id;
            $oprofile->created    = common_sql_now();

            if (isset($feedUrl)) {
                $profile->feeduri = $feedUrl;
            }

            $result = $oprofile->insert();

            if (!$result) {
                common_log_db_error($oprofile, 'INSERT', __FILE__);
                throw new Exception("Couldn't save ostatus_profile for '$addr'");
            }

            return $oprofile;
        }

        return null;
    }
}
