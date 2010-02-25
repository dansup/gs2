<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

/**
 * Base class for queue handlers.
 *
 * As extensions of the Daemon class, each queue handler has the ability
 * to launch itself in the background, at which point it'll pass control
 * to the configured QueueManager class to poll for updates.
 *
 * Subclasses must override at least the following methods:
 * - transport
 * - handle_notice
 */

class DistribQueueHandler
{
    /**
     * Return transport keyword which identifies items this queue handler
     * services; must be defined for all subclasses.
     *
     * Must be 8 characters or less to fit in the queue_item database.
     * ex "email", "jabber", "sms", "irc", ...
     *
     * @return string
     */

    function transport()
    {
        return 'distrib';
    }

    /**
     * Here's the meat of your queue handler -- you're handed a Notice
     * object, which you may do as you will with.
     *
     * If this function indicates failure, a warning will be logged
     * and the item is placed back in the queue to be re-run.
     *
     * @param Notice $notice
     * @return boolean true on success, false on failure
     */
    function handle($notice)
    {
        // XXX: do we need to change this for remote users?

        try {
            $notice->addToInboxes();
        } catch (Exception $e) {
            $this->logit($notice, $e);
        }

        try {
            Event::handle('EndNoticeSave', array($notice));
            // Enqueue for other handlers
        } catch (Exception $e) {
            $this->logit($notice, $e);
        }

        try {
            common_enqueue_notice($notice);
        } catch (Exception $e) {
            $this->logit($notice, $e);
        }

        return true;
    }

    protected function logit($notice, $e)
    {
        common_log(LOG_ERR, "Distrib queue exception saving notice $notice->id: " .
            $e->getMessage() . ' ' .
            str_replace("\n", " ", $e->getTraceAsString()));

        // We'll still return true so we don't get stuck in a loop
        // trying to run a bad insert over and over...
    }
}

