<?php
/**
 * Copyright 1999-2011 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @package Kronolith
 */

/**
 * The Kronolith:: class provides functionality common to all of Kronolith.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @package Kronolith
 */
class Kronolith
{
    /** Event status */
    const STATUS_NONE      = 0;
    const STATUS_TENTATIVE = 1;
    const STATUS_CONFIRMED = 2;
    const STATUS_CANCELLED = 3;
    const STATUS_FREE      = 4;

    /** Invitation responses */
    const RESPONSE_NONE      = 1;
    const RESPONSE_ACCEPTED  = 2;
    const RESPONSE_DECLINED  = 3;
    const RESPONSE_TENTATIVE = 4;

    /** Attendee status */
    const PART_REQUIRED = 1;
    const PART_OPTIONAL = 2;
    const PART_NONE     = 3;
    const PART_IGNORE   = 4;

    /** iTip requests */
    const ITIP_REQUEST = 1;
    const ITIP_CANCEL  = 2;

    /** The event can be delegated. */
    const PERMS_DELEGATE = 1024;

    /**
     * Driver singleton instances.
     *
     * @var array
     */
    static private $_instances = array();

    /**
     * @var Kronolith_Tagger
     */
    static private $_tagger;

    /**
     * Output everything for the AJAX interface up to but not including the
     * <body> tag.
     */
    static public function header()
    {
        // Need to include script files before we start output
        $datejs = str_replace('_', '-', $GLOBALS['language']) . '.js';
        if (!file_exists($GLOBALS['registry']->get('jsfs', 'horde') . '/date/' . $datejs)) {
            $datejs = 'en-US.js';
        }
        Horde::addScriptFile('effects.js', 'horde');
        Horde::addScriptFile('sound.js', 'horde');
        Horde::addScriptFile('horde.js', 'horde');
        Horde::addScriptFile('dragdrop2.js', 'kronolith');
        Horde::addScriptFile('growler.js', 'horde');
        Horde::addScriptFile('redbox.js', 'horde');
        Horde::addScriptFile('tooltips.js', 'horde');
        Horde::addScriptFile('colorpicker.js', 'horde');
        Horde::addScriptFile('date/' . $datejs, 'horde');
        Horde::addScriptFile('date/date.js', 'horde');
        Horde::addScriptFile('kronolith.js', 'kronolith');
        Horde_Core_Ui_JsCalendar::init(array('short_weekdays' => true));

        if (isset($GLOBALS['language'])) {
            header('Content-type: text/html; charset=UTF-8');
            header('Vary: Accept-Language');
        }

        echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "DTD/xhtml1-transitional.dtd">' . "\n" .
             (!empty($GLOBALS['language']) ? '<html lang="' . strtr($GLOBALS['language'], '_', '-') . '"' : '<html') . ">\n".
             "<head>\n" .
             '<title>' . htmlspecialchars($GLOBALS['registry']->get('name')) . "</title>\n";

        Horde::includeFavicon();
        echo Horde::wrapInlineScript(self::includeJSVars());
        Horde::includeStylesheetFiles();

        echo "</head>\n";

        // Send what we have currently output so the browser can start
        // loading CSS/JS. See:
        // http://developer.yahoo.com/performance/rules.html#flush
        echo Horde::endBuffer();
        flush();
    }

    /**
     * Outputs the javascript code which defines all javascript variables
     * that are dependent on the local user's account.
     *
     * @private
     *
     * @return string
     */
    static public function includeJSVars()
    {
        global $prefs, $registry;

        $kronolith_webroot = $registry->get('webroot');
        $horde_webroot = $registry->get('webroot', 'horde');
        $has_tasks = self::hasApiPermission('tasks');
        $app_urls = array();
        if (isset($GLOBALS['conf']['menu']['apps']) &&
            is_array($GLOBALS['conf']['menu']['apps'])) {
            foreach ($GLOBALS['conf']['menu']['apps'] as $app) {
                $app_urls[$app] = (string)Horde::url($registry->getInitialPage($app), true)->add('ajaxui', 1);
            }
        }

        /* Variables used in core javascript files. */
        $code['conf'] = array(
            'URI_AJAX' => (string)Horde::getServiceLink('ajax', 'kronolith'),
            'URI_SNOOZE' => (string)Horde::url($registry->get('webroot', 'horde') . '/services/snooze.php', true, -1),
            'URI_CALENDAR_EXPORT' => (string)Horde::url('data.php', true)->add(array('actionID' => 'export', 'all_events' => 1, 'exportID' => Horde_Data::EXPORT_ICALENDAR, 'exportCal' => 'internal_')),
            'URI_EVENT_EXPORT' => str_replace(array('%23', '%7B', '%7D'), array('#', '{', '}'), Horde::url('event.php', true)->add(array('view' => 'ExportEvent', 'eventID' => '#{id}', 'calendar' => '#{calendar}', 'type' => '#{type}'))),
            'SESSION_ID' => defined('SID') ? SID : '',
            'images' => array(
                'attendees' => (string)Horde_Themes::img('attendees-fff.png'),
                'alarm'     => (string)Horde_Themes::img('alarm-fff.png'),
                'recur'     => (string)Horde_Themes::img('recur-fff.png'),
                'exception' => (string)Horde_Themes::img('exception-fff.png'),
            ),
            'user' => $GLOBALS['registry']->convertUsername($GLOBALS['registry']->getAuth(), false),
            'prefs_url' => (string)Horde::getServiceLink('prefs', 'kronolith')->setRaw(true)->add('ajaxui', 1),
            'app_urls' => $app_urls,
            'use_iframe' => !empty($GLOBALS['conf']['menu']['apps_iframe']),
            'name' => $registry->get('name'),
            'has_tasks' => (bool)$has_tasks,
            'is_ie6' => ($GLOBALS['browser']->isBrowser('msie') && ($GLOBALS['browser']->getMajor() < 7)),
            'login_view' => $prefs->getValue('defaultview') == 'workweek' ? 'week' : $prefs->getValue('defaultview'),
            'default_calendar' => 'internal|' . self::getDefaultCalendar(Horde_Perms::EDIT),
            'week_start' => (int)$prefs->getValue('week_start_monday'),
            'max_events' => (int)$prefs->getValue('max_events'),
            'date_format' => str_replace(array('%e', '%d', '%a', '%A', '%m', '%h', '%b', '%B', '%y', '%Y'),
                                         array('d', 'dd', 'ddd', 'dddd', 'MM', 'MMM', 'MMM', 'MMMM', 'yy', 'yyyy'),
                                         Horde_Nls::getLangInfo(D_FMT)),
            'time_format' => $prefs->getValue('twentyFour') ? 'HH:mm' : 'hh:mm tt',
            'default_alarm' => (int)$prefs->getValue('default_alarm'),
            'status' => array('tentative' => self::STATUS_TENTATIVE,
                              'confirmed' => self::STATUS_CONFIRMED,
                              'cancelled' => self::STATUS_CANCELLED,
                              'free' => self::STATUS_FREE),
            'recur' => array(Horde_Date_Recurrence::RECUR_NONE => 'None',
                             Horde_Date_Recurrence::RECUR_DAILY => 'Daily',
                             Horde_Date_Recurrence::RECUR_WEEKLY => 'Weekly',
                             Horde_Date_Recurrence::RECUR_MONTHLY_DATE => 'Monthly',
                             Horde_Date_Recurrence::RECUR_MONTHLY_WEEKDAY => 'Monthly',
                             Horde_Date_Recurrence::RECUR_YEARLY_DATE => 'Yearly',
                             Horde_Date_Recurrence::RECUR_YEARLY_DAY => 'Yearly',
                             Horde_Date_Recurrence::RECUR_YEARLY_WEEKDAY => 'Yearly'),
            'perms' => array('all' => Horde_Perms::ALL,
                             'show' => Horde_Perms::SHOW,
                             'read' => Horde_Perms::READ,
                             'edit' => Horde_Perms::EDIT,
                             'delete' => Horde_Perms::DELETE,
                             'delegate' => self::PERMS_DELEGATE),
            'snooze' => array('0' => _("select..."),
                              '5' => _("5 minutes"),
                              '15' => _("15 minutes"),
                              '60' => _("1 hour"),
                              '360' => _("6 hours"),
                              '1440' => _("1 day")),
        );
        if (!empty($GLOBALS['conf']['logo']['link'])) {
            $code['conf']['URI_HOME'] = $GLOBALS['conf']['logo']['link'];
        }

        if ($has_tasks) {
            $code['conf']['tasks'] = $registry->tasks->ajaxDefaults();
        }

        // Calendars. Do some twisting to sort own calendar before shared
        // calendars.
        foreach (array(true, false) as $my) {
            foreach ($GLOBALS['all_calendars'] as $id => $calendar) {
                $owner = $GLOBALS['registry']->getAuth() &&
                    $calendar->owner() == $GLOBALS['registry']->getAuth();
                if (($my && $owner) || (!$my && !$owner)) {
                    $code['conf']['calendars']['internal'][$id] = $calendar->toHash();
                }
            }

            // Tasklists
            if (!$has_tasks) {
                continue;
            }
            foreach ($registry->tasks->listTasklists($my, Horde_Perms::SHOW) as $id => $tasklist) {
                if (!isset($GLOBALS['all_external_calendars']['tasks/' . $id])) {
                    continue;
                }
                $owner = $GLOBALS['registry']->getAuth() &&
                    $tasklist->get('owner') == $GLOBALS['registry']->getAuth();
                if (($my && $owner) || (!$my && !$owner)) {
                    $code['conf']['calendars']['tasklists']['tasks/' . $id] = $GLOBALS['all_external_calendars']['tasks/' . $id]->toHash();
                }
            }
        }

        // Timeobjects
        foreach ($GLOBALS['all_external_calendars'] as $id => $calendar) {
            if ($calendar->api() == 'tasks') {
                continue;
            }
            if (!empty($GLOBALS['conf']['share']['hidden']) &&
                !in_array($id, $GLOBALS['display_external_calendars'])) {
                continue;
            }
            $code['conf']['calendars']['external'][$id] = $calendar->toHash();
        }

        // Remote calendars
        foreach ($GLOBALS['all_remote_calendars'] as $url => $calendar) {
            $code['conf']['calendars']['remote'][$url] = $calendar->toHash();
        }

        // Holidays
        foreach ($GLOBALS['all_holidays'] as $id => $calendar) {
            $code['conf']['calendars']['holiday'][$id] = $calendar->toHash();
        }

        /* Gettext strings used in core javascript files. */
        $code['text'] = array(
            'ajax_error' => _("Error when communicating with the server."),
            'ajax_timeout' => _("There has been no contact with the server for several minutes. The server may be temporarily unavailable or network problems may be interrupting your session. You will not see any updates until the connection is restored."),
            'ajax_recover' => _("The connection to the server has been restored."),
            'alarm' => _("Alarm:"),
            'snooze' => sprintf(_("You can snooze it for %s or %s dismiss %s it entirely"), '#{time}', '#{dismiss_start}', '#{dismiss_end}'),
            'noalerts' => _("No Notifications"),
            'alerts' => sprintf(_("%s notifications"), '#{count}'),
            'hidelog' => _("Hide Notifications"),
            'growlerinfo' => _("This is the notification backlog"),
            'agenda' => _("Agenda"),
            'searching' => sprintf(_("Events matching \"%s\""), '#{term}'),
            'allday' => _("All day"),
            'more' => _("more..."),
            'prefs' => _("Preferences"),
            'shared' => _("Shared"),
            'external_category' => _("Other events"),
            'no_url' => _("You must specify a URL."),
            'no_calendar_title' => _("The calendar title must not be empty."),
            'no_tasklist_title' => _("The task list title must not be empty."),
            'delete_calendar' => _("Are you sure you want to delete this calendar and all the events in it?"),
            'delete_tasklist' => _("Are you sure you want to delete this task list and all the tasks in it?"),
            'wrong_auth' => _("The authentication information you specified wasn't accepted."),
            'geocode_error' => _("Unable to locate requested address"),
            'wrong_date_format' => sprintf(_("You used an unknown date format \"%s\". Please try something like \"%s\"."), '#{wrong}', '#{right}'),
            'wrong_time_format' => sprintf(_("You used an unknown time format \"%s\". Please try something like \"%s\"."), '#{wrong}', '#{right}'),
            'fix_form_values' => _("Please enter correct values in the form first."),
        );
        for ($i = 1; $i <= 12; ++$i) {
            $code['text']['month'][$i - 1] = Horde_Nls::getLangInfo(constant('MON_' . $i));
        }
        for ($i = 1; $i <= 7; ++$i) {
            $code['text']['weekday'][$i] = Horde_Nls::getLangInfo(constant('DAY_' . $i));
        }
        foreach (array(Horde_Date_Recurrence::RECUR_DAILY,
                       Horde_Date_Recurrence::RECUR_WEEKLY,
                       Horde_Date_Recurrence::RECUR_MONTHLY_DATE,
                       Horde_Date_Recurrence::RECUR_MONTHLY_WEEKDAY,
                       Horde_Date_Recurrence::RECUR_YEARLY_DATE,
                       Horde_Date_Recurrence::RECUR_YEARLY_DAY,
                       Horde_Date_Recurrence::RECUR_YEARLY_WEEKDAY) as $recurType) {
            $code['text']['recur'][$recurType] = self::recurToString($recurType);
        }

        $code['text']['recur']['exception'] = _("Exception");

        // Maps
        $code['conf']['maps'] = $GLOBALS['conf']['maps'];

        return Horde::addInlineJsVars(array(
            'var Kronolith' => $code
        ), array('ret_vars' => true));
    }

    /**
     * Converts a permission object to a json object.
     *
     * This methods filters out any permissions for the owner and converts the
     * user name if necessary.
     *
     * @param Horde_Perms_Permission $perm  A permission object.
     *
     * @return array  A hash suitable for json.
     */
    static public function permissionToJson(Horde_Perms_Permission $perm)
    {
        $json = $perm->data;
        if (isset($json['users'])) {
            $users = array();
            foreach ($json['users'] as $user => $value) {
                if ($user == $GLOBALS['registry']->getAuth()) {
                    continue;
                }
                $user = $GLOBALS['registry']->convertUsername($user, false);
                $users[$user] = $value;
            }
            if ($users) {
                $json['users'] = $users;
            } else {
                unset($json['users']);
            }
        }
        return $json;
   }

    /**
     * Returns all the alarms active on a specific date.
     *
     * @param Horde_Date $date    The date to check for alarms.
     * @param array $calendars    The calendars to check for events.
     * @param boolean $fullevent  Whether to return complete alarm objects or
     *                            only alarm IDs.
     *
     * @return array  The alarms active on the date. A hash with calendar names
     *                as keys and arrays of events or event ids as values.
     * @throws Kronolith_Exception
     */
    static public function listAlarms($date, $calendars, $fullevent = false)
    {
        $kronolith_driver = self::getDriver();

        $alarms = array();
        foreach ($calendars as $cal) {
            $kronolith_driver->open($cal);
            $alarms[$cal] = $kronolith_driver->listAlarms($date, $fullevent);
        }

        return $alarms;
    }

    /**
     * Searches for events with the given properties.
     *
     * @param object $query     The search query.
     * @param string $calendar  The calendar to search in the form
     *                          "Driver|calendar_id".
     *
     * @return array  The events.
     * @throws Kronolith_Exception
     */
    static public function search($query, $calendar = null)
    {
        if ($calendar) {
            $driver = explode('|', $calendar, 2);
            $calendars = array($driver[0] => array($driver[1]));
        } elseif (!empty($query->calendars)) {
            $calendars = $query->calendars;
        } else {
            $calendars = array(
                Horde_String::ucfirst($GLOBALS['conf']['calendar']['driver']) => $GLOBALS['display_calendars'],
                'Horde' => $GLOBALS['display_external_calendars'],
                'Ical' => $GLOBALS['display_remote_calendars'],
                'Holidays' => $GLOBALS['display_holidays']);
        }

        $events = array();
        foreach ($calendars as $type => $list) {
            $kronolith_driver = self::getDriver($type);
            foreach ($list as $cal) {
                $kronolith_driver->open($cal);
                $retevents = $kronolith_driver->search($query);
                self::mergeEvents($events, $retevents);
            }
        }

        return $events;
    }

    /**
     * Returns all the events that happen each day within a time period
     *
     * @deprecated
     *
     * @param Horde_Date $startDate    The start of the time range.
     * @param Horde_Date $endDate      The end of the time range.
     * @param array $calendars         The calendars to check for events.
     * @param boolean $showRecurrence  Return every instance of a recurring
     *                                 event? If false, will only return
     *                                 recurring events once inside the
     *                                 $startDate - $endDate range.
     *                                 Defaults to true
     * @param boolean $alarmsOnly      Filter results for events with alarms
     *                                 Defaults to false
     * @param boolean $showRemote      Return events from remote and
     *                                 listTimeObjects as well?
     * @param boolean $hideExceptions  Hide events that represent exceptions to
     *                                 a recurring event?
     * @param boolean $fetchTags       Should we fetch each event's tags from
     *                                 storage?
     *
     * @return array  The events happening in this time period.
     */
    static public function listEvents($startDate, $endDate, $calendars = null,
                                      $showRecurrence = true,
                                      $alarmsOnly = false, $showRemote = true,
                                      $hideExceptions = false,
                                      $coverDates = true, $fetchTags = false)
    {
        $results = array();

        /* Internal calendars. */
        if (!isset($calendars)) {
            $calendars = $GLOBALS['display_calendars'];
        }
        $driver = self::getDriver();
        foreach ($calendars as $calendar) {
            try {
                $driver->open($calendar);
                $events = $driver->listEvents($startDate, $endDate,
                                              $showRecurrence, $alarmsOnly,
                                              false, $coverDates,
                                              $hideExceptions, $fetchTags);
                self::mergeEvents($results, $events);
            } catch (Kronolith_Exception $e) {
                $GLOBALS['notification']->push($e);
            }
        }

        /* Resource calendars (this would only be populated if explicitly
         * requested in the request, so include them if this is set regardless
         * of $calendars value).
         *
         * @TODO: Should we disallow even *viewing* these if the user is not an
         *        admin?
         */
        if (!empty($GLOBALS['display_resource_calendars'])) {
            $driver = self::getDriver('Resource');
            foreach ($GLOBALS['display_resource_calendars'] as $calendar) {
                try {
                    $driver->open($calendar);
                    $events = $driver->listEvents($startDate, $endDate, $showRecurrence);
                    self::mergeEvents($results, $events);
                } catch (Kronolith_Exception $e) {
                    $GLOBALS['notification']->push($e);
                }
            }
        }

        if ($showRemote) {
            /* Horde applications providing listTimeObjects. */
            if (count($GLOBALS['display_external_calendars'])) {
                $driver = self::getDriver('Horde');
                foreach ($GLOBALS['display_external_calendars'] as $external_cal) {
                    try {
                        $driver->open($external_cal);
                        $events = $driver->listEvents($startDate, $endDate, $showRecurrence);
                        self::mergeEvents($results, $events);
                    } catch (Kronolith_Exception $e) {
                        $GLOBALS['notification']->push($e);
                    }
                }
            }

            /* Remote Calendars. */
            foreach ($GLOBALS['display_remote_calendars'] as $url) {
                try {
                    $driver = self::getDriver('Ical', $url);
                    $events = $driver->listEvents($startDate, $endDate, $showRecurrence);
                    self::mergeEvents($results, $events);
                } catch (Kronolith_Exception $e) {
                    $GLOBALS['notification']->push($e);
                }
            }

            /* Holidays. */
            if (count($GLOBALS['display_holidays']) && !empty($GLOBALS['conf']['holidays']['enable'])) {
                $driver = self::getDriver('Holidays');
                foreach ($GLOBALS['display_holidays'] as $holiday) {
                    try {
                        $driver->open($holiday);
                        $events = $driver->listEvents($startDate, $endDate, $showRecurrence);
                        self::mergeEvents($results, $events);
                    } catch (Kronolith_Exception $e) {
                        $GLOBALS['notification']->push($e);
                    }
                }
            }
        }

        /* Sort events. */
        $results = Kronolith::sortEvents($results);

        return $results;
    }

    /**
     * Merges results from two listEvents() result sets.
     *
     * @param array $results  First list of events.
     * @param array $events   List of events to be merged into the first one.
     */
    static public function mergeEvents(&$results, $events)
    {
        foreach ($events as $day => $day_events) {
            if (isset($results[$day])) {
                $results[$day] = array_merge($results[$day], $day_events);
            } else {
                $results[$day] = $day_events;
            }
        }
        ksort($results);
    }

    /**
     * Calculates recurrences of an event during a certain period.
     */
    static public function addEvents(&$results, &$event, $startDate, $endDate,
                                     $showRecurrence, $json, $coverDates = true)
    {
        if ($event->recurs() && $showRecurrence) {
            /* Recurring Event. */

            /* If the event ends at 12am and does not end at the same time
             * that it starts (0 duration), set the end date to the previous
             * day's end date. */
            if ($event->end->hour == 0 &&
                $event->end->min == 0 &&
                $event->end->sec == 0 &&
                $event->start->compareDateTime($event->end) != 0) {
                $event->end = new Horde_Date(
                    array('hour' =>  23,
                          'min' =>   59,
                          'sec' =>   59,
                          'month' => $event->end->month,
                          'mday' =>  $event->end->mday - 1,
                          'year' =>  $event->end->year));
            }

            /* We can't use the event duration here because we might cover a
             * daylight saving time switch. */
            $diff = array($event->end->year - $event->start->year,
                          $event->end->month - $event->start->month,
                          $event->end->mday - $event->start->mday,
                          $event->end->hour - $event->start->hour,
                          $event->end->min - $event->start->min);
            while ($diff[4] < 0) {
                --$diff[3];
                $diff[4] += 60;
            }
            while ($diff[3] < 0) {
                --$diff[2];
                $diff[3] += 24;
            }
            while ($diff[2] < 0) {
                --$diff[1];
                $diff[2] += Horde_Date_Utils::daysInMonth($event->start->month, $event->start->year);
            }
            while ($diff[1] < 0) {
                --$diff[0];
                $diff[1] += 12;
            }

            if ($event->start->compareDateTime($startDate) < 0) {
                /* The first time the event happens was before the period
                 * started. Start searching for recurrences from the start of
                 * the period. */
                $next = array('year' => $startDate->year,
                              'month' => $startDate->month,
                              'mday' => $startDate->mday);
            } else {
                /* The first time the event happens is in the range; unless
                 * there is an exception for this ocurrence, add it. */
                if (!$event->recurrence->hasException($event->start->year,
                                                      $event->start->month,
                                                      $event->start->mday)) {
                    if ($coverDates) {
                        self::addCoverDates($results, $event, $event->start, $event->end, $json);
                    } else {
                        $results[$event->start->dateString()][$event->id] = $json ? $event->toJson() : $event;
                    }
                }

                /* Start searching for recurrences from the day after it
                 * starts. */
                $next = clone $event->start;
                ++$next->mday;
            }

            /* Add all recurrences of the event. */
            $next = $event->recurrence->nextRecurrence($next);
            while ($next !== false && $next->compareDate($endDate) <= 0) {
                if (!$event->recurrence->hasException($next->year, $next->month, $next->mday)) {
                    /* Add the event to all the days it covers. */
                    $nextEnd = clone $next;
                    $nextEnd->year  += $diff[0];
                    $nextEnd->month += $diff[1];
                    $nextEnd->mday  += $diff[2];
                    $nextEnd->hour  += $diff[3];
                    $nextEnd->min   += $diff[4];
                    if ($coverDates) {
                        self::addCoverDates($results, $event, $next, $nextEnd, $json);
                    } else {
                        $addEvent = clone $event;
                        $addEvent->start = $next;
                        $addEvent->end = $nextEnd;
                        $results[$addEvent->start->dateString()][$addEvent->id] = $json ? $addEvent->toJson() : $addEvent;

                    }
                }
                $next = $event->recurrence->nextRecurrence(
                    array('year' => $next->year,
                          'month' => $next->month,
                          'mday' => $next->mday + 1,
                          'hour' => $next->hour,
                          'min' => $next->min,
                          'sec' => $next->sec));
            }
        } else {
            /* Event only occurs once. */
            if (!$coverDates) {
                $results[$event->start->dateString()][$event->id] = $json ? $event->toJson() : $event;
            } else {
                $allDay = $event->isAllDay();

                /* Work out what day it starts on. */
                if ($startDate &&
                    $event->start->compareDateTime($startDate) < 0) {
                    /* It started before the beginning of the period. */
                    $eventStart = clone $startDate;
                } else {
                    $eventStart = clone $event->start;
                }

                /* Work out what day it ends on. */
                if ($endDate &&
                    $event->end->compareDateTime($endDate) > 0) {
                    /* Ends after the end of the period. */
                    if (is_object($endDate)) {
                        $eventEnd = clone $endDate;
                    } else {
                        $eventEnd = $endDate;
                    }
                } else {
                    /* If the event doesn't end at 12am set the end date to
                     * the current end date. If it ends at 12am and does not
                     * end at the same time that it starts (0 duration), set
                     * the end date to the previous day's end date. */
                    if ($event->end->hour != 0 ||
                        $event->end->min != 0 ||
                        $event->end->sec != 0 ||
                        $event->start->compareDateTime($event->end) == 0 ||
                        $allDay) {
                        $eventEnd = clone $event->end;
                    } else {
                        $eventEnd = new Horde_Date(
                            array('hour' =>  23,
                                  'min' =>   59,
                                  'sec' =>   59,
                                  'month' => $event->end->month,
                                  'mday' =>  $event->end->mday - 1,
                                  'year' =>  $event->end->year));
                    }
                }

                /* Add the event to all the days it covers. This is similar to
                 * Kronolith::addCoverDates(), but for days in between the
                 * start and end day, the range is midnight to midnight, and
                 * for the edge days it's start to midnight, and midnight to
                 * end. */
                $i = $eventStart->mday;
                $loopDate = new Horde_Date(array('month' => $eventStart->month,
                                                 'mday' => $i,
                                                 'year' => $eventStart->year));
                while ($loopDate->compareDateTime($eventEnd) <= 0) {
                    if (!$allDay ||
                        $loopDate->compareDateTime($eventEnd) != 0) {
                        $addEvent = clone $event;

                        /* If this is the start day, set the start time to
                         * the real start time, otherwise set it to
                         * 00:00 */
                        if ($loopDate->compareDate($eventStart) == 0) {
                            $addEvent->start = $eventStart;
                        } else {
                            $addEvent->start = new Horde_Date(array(
                                'hour' => 0, 'min' => 0, 'sec' => 0,
                                'month' => $loopDate->month, 'mday' => $loopDate->mday, 'year' => $loopDate->year));
                            $addEvent->first = false;
                        }

                        /* If this is the end day, set the end time to the
                         * real event end, otherwise set it to 23:59. */
                        if ($loopDate->compareDate($eventEnd) == 0) {
                            $addEvent->end = $eventEnd;
                        } else {
                            $addEvent->end = new Horde_Date(array(
                                'hour' => 23, 'min' => 59, 'sec' => 59,
                                'month' => $loopDate->month, 'mday' => $loopDate->mday, 'year' => $loopDate->year));
                            $addEvent->last = false;
                        }

                        $results[$loopDate->dateString()][$addEvent->id] = $json ? $addEvent->toJson($allDay) : $addEvent;
                    }

                    $loopDate = new Horde_Date(
                        array('month' => $eventStart->month,
                              'mday' => ++$i,
                              'year' => $eventStart->year));
                }
            }
        }
        ksort($results);
    }

    /**
     * Adds an event to all the days it covers.
     *
     * @param array $result           The current result list.
     * @param Kronolith_Event $event  An event object.
     * @param Horde_Date $eventStart  The event's start at the actual
     *                                recurrence.
     * @param Horde_Date $eventEnd    The event's end at the actual recurrence.
     * @param boolean $json           Store the results of the events' toJson()
     *                                method?
     */
    static public function addCoverDates(&$results, $event, $eventStart,
                                         $eventEnd, $json)
    {
        $loopDate = new Horde_Date($eventStart->year, $eventStart->month, $eventStart->mday);
        $allDay = $event->isAllDay();
        $first = true;
        while ($loopDate->compareDateTime($eventEnd) <= 0) {
            if (!$allDay ||
                $loopDate->compareDateTime($eventEnd) != 0) {
                $addEvent = clone $event;
                $addEvent->start = $eventStart;
                $addEvent->end = $eventEnd;
                if ($loopDate->compareDate($eventStart) != 0) {
                    $addEvent->first = false;
                }
                if ($loopDate->compareDate($eventEnd) != 0) {
                    $addEvent->last = false;
                }
                $results[$loopDate->dateString()][$addEvent->id] = $json ? $addEvent->toJson($allDay) : $addEvent;
            }
            $loopDate->mday++;
        }
    }

    /**
     * Adds an event to set of search results.
     *
     * @param array $events           The list of events to update with the new
     *                                event.
     * @param Kronolith_Event $event  An event from a search result.
     * @param stdClass $query         A search query.
     * @param boolean $json           Store the results of the events' toJson()
     *                                method?
     */
    static public function addSearchEvents(&$events, $event, $query, $json)
    {
        static $now;
        if (!isset($now)) {
            $now = new Horde_Date($_SERVER['REQUEST_TIME']);
        }
        $showRecurrence = true;
        if ($event->recurs()) {
            if (empty($query->start) && empty($query->end)) {
                $eventStart = $event->start;
                $eventEnd = $event->end;
            } else {
                if (empty($query->end)) {
                    $eventEnd = $event->recurrence->nextRecurrence($now);
                    if (!$eventEnd) {
                        return;
                    }
                } else {
                    $eventEnd = $query->end;
                }
                if (empty($query->start)) {
                    $eventStart = $event->start;
                    $showRecurrence = false;
                } else {
                    $eventStart = $query->start;
                }
            }
        } else {
            $eventStart = $event->start;
            $eventEnd = $event->end;
        }
        self::addEvents($events, $event, $eventStart, $eventEnd, $showRecurrence, $json, false);
    }

    /**
     * Returns the number of events in calendars that the current user owns.
     *
     * @return integer  The number of events.
     */
    static public function countEvents()
    {
        static $count;
        if (isset($count)) {
            return $count;
        }

        $kronolith_driver = self::getDriver();
        $calendars = self::listInternalCalendars(true, Horde_Perms::ALL);
        $current_calendar = $kronolith_driver->calendar;

        $count = 0;
        foreach (array_keys($calendars) as $calendar) {
            $kronolith_driver->open($calendar);
            try {
                $count += $kronolith_driver->countEvents();
            } catch (Exception $e) {
            }
        }

        /* Reopen last calendar. */
        $kronolith_driver->open($current_calendar);

        return $count;
    }

    /**
     * Imports an event parsed from a string.
     *
     * @param string $text      The text to parse into an event
     * @param string $calendar  The calendar into which the event will be
     *                          imported.  If 'null', the user's default
     *                          calendar will be used.
     *
     * @return array  The UID of all events that were added.
     * @throws Kronolith_Exception
     */
    public function quickAdd($text, $calendar = null)
    {
        $text = trim($text);
        if (strpos($text, "\n") !== false) {
            list($title, $description) = explode($text, "\n", 2);
        } else {
            $title = $text;
            $description = '';
        }
        $title = trim($title);
        $description = trim($description);

        $dateParser = Horde_Date_Parser::factory(array('locale' => $GLOBALS['language']));
        $r = $dateParser->parse($title, array('return' => 'result'));
        if (!($d = $r->guess())) {
            throw new Horde_Exception(sprintf(_("Cannot parse event description \"%s\""), Horde_String::truncate($text)));
        }

        $title = $r->untaggedText();
        $start = $d->timestamp();

        $kronolith_driver = self::getDriver(null, $calendar);
        $event = $kronolith_driver->getEvent();
        $event->initialized = true;
        $event->title = $title;
        $event->description = $description;
        $event->start = $d;
        $event->end = $d->add(array('hour' => 1));
        $event->save();

        return $event;
    }

    /**
     * Initial app setup code.
     */
    static public function initialize()
    {
        /* Store the request timestamp if it's not already present. */
        if (!isset($_SERVER['REQUEST_TIME'])) {
            $_SERVER['REQUEST_TIME'] = time();
        }

        /* Fetch display preferences. */
        $GLOBALS['display_calendars'] = @unserialize($GLOBALS['prefs']->getValue('display_cals'));
        $GLOBALS['display_remote_calendars'] = @unserialize($GLOBALS['prefs']->getValue('display_remote_cals'));
        $GLOBALS['display_external_calendars'] = @unserialize($GLOBALS['prefs']->getValue('display_external_cals'));
        $GLOBALS['display_holidays'] = @unserialize($GLOBALS['prefs']->getValue('holiday_drivers'));

        if (!is_array($GLOBALS['display_calendars'])) {
            $GLOBALS['display_calendars'] = array();
        }
        if (!is_array($GLOBALS['display_remote_calendars'])) {
            $GLOBALS['display_remote_calendars'] = array();
        }
        if (!is_array($GLOBALS['display_external_calendars'])) {
            $GLOBALS['display_external_calendars'] = array();
        }
        if (!is_array($GLOBALS['display_holidays']) ||
            empty($GLOBALS['conf']['holidays']['enable'])) {
            $GLOBALS['display_holidays'] = array();
        }

        /* Update preferences for which calendars to display. If the
         * user doesn't have any selected calendars to view then fall
         * back to an available calendar. An empty string passed in this
         * parameter will clear any existing session value.*/
        if (($calendarId = Horde_Util::getFormData('display_cal')) !== null) {
            $GLOBALS['session']->set('kronolith', 'display_cal', $calendarId);
        }

        if (strlen($GLOBALS['session']->get('kronolith', 'display_cal'))) {
            /* Specifying a value for display_cal is always to make sure
             * that only the specified calendars are shown. Use the
             * "toggle_calendar" argument  to toggle the state of a single
             * calendar. */
            $GLOBALS['display_calendars'] = array();
            $GLOBALS['display_remote_calendars'] = array();
            $GLOBALS['display_external_calendars'] = array();
            $GLOBALS['display_resource_calendars'] = array();
            $GLOBALS['display_holidays'] = array();
            $calendars = $GLOBALS['session']->get('kronolith', 'display_cal');
            if (!is_array($calendars)) {
                $calendars = array($calendars);
            }
            foreach ($calendars as $calendarId) {
                if (strncmp($calendarId, 'remote_', 7) === 0) {
                    $calendarId = substr($calendarId, 7);
                    if (!in_array($calendarId, $GLOBALS['display_remote_calendars'])) {
                        $GLOBALS['display_remote_calendars'][] = $calendarId;
                    }
                } elseif (strncmp($calendarId, 'external_', 9) === 0) {
                    $calendarId = substr($calendarId, 9);
                    if (!in_array($calendarId, $GLOBALS['display_external_calendars'])) {
                        $GLOBALS['display_external_calendars'][] = $calendarId;
                    }
                } elseif (strncmp($calendarId, 'resource_', 9) === 0) {
                    if (!in_array($calendarId, $GLOBALS['display_resource_calendars'])) {
                        $GLOBALS['display_resource_calendars'][] = $calendarId;
                    }
                } elseif (strncmp($calendarId, 'holidays_', 9) === 0) {
                    $calendarId = substr($calendarId, 9);
                    if (!in_array($calendarId, $GLOBALS['display_holidays'])) {
                        $GLOBALS['display_holidays'][] = $calendarId;
                    }
                } else {
                    if (!in_array($calendarId, $GLOBALS['display_calendars'])) {
                        $GLOBALS['display_calendars'][] = $calendarId;
                    }
                }
            }
        }

        /* Check for single "toggle" calendars. */
        if (($calendarId = Horde_Util::getFormData('toggle_calendar')) !== null) {
            if (strncmp($calendarId, 'remote_', 7) === 0) {
                $calendarId = substr($calendarId, 7);
                if (in_array($calendarId, $GLOBALS['display_remote_calendars'])) {
                    $key = array_search($calendarId, $GLOBALS['display_remote_calendars']);
                    unset($GLOBALS['display_remote_calendars'][$key]);
                } else {
                    $GLOBALS['display_remote_calendars'][] = $calendarId;
                }
            } elseif ((strncmp($calendarId, 'external_', 9) === 0 &&
                       $calendarId = substr($calendarId, 9)) ||
                      (strncmp($calendarId, 'tasklists_', 10) === 0 &&
                       $calendarId = substr($calendarId, 10))) {
                if (in_array($calendarId, $GLOBALS['display_external_calendars'])) {
                    $key = array_search($calendarId, $GLOBALS['display_external_calendars']);
                    unset($GLOBALS['display_external_calendars'][$key]);
                } else {
                    $GLOBALS['display_external_calendars'][] = $calendarId;
                }
                if (strpos($calendarId, 'tasks/') === 0) {
                    $tasklists = array();
                    foreach ($GLOBALS['display_external_calendars'] as $id) {
                        if (strpos($id, 'tasks/') === 0) {
                            $tasklists[] = substr($id, 6);
                        }
                    }
                    try {
                        $GLOBALS['registry']->tasks->setDisplayedTasklists($tasklists);
                    } catch (Horde_Exception $e) {
                    }
                }
            } elseif (strncmp($calendarId, 'holiday_', 8) === 0) {
                $calendarId = substr($calendarId, 8);
                if (in_array($calendarId, $GLOBALS['display_holidays'])) {
                    $key = array_search($calendarId, $GLOBALS['display_holidays']);
                    unset($GLOBALS['display_holidays'][$key]);
                } else {
                    $GLOBALS['display_holidays'][] = $calendarId;
                }
            } else {
                if (in_array($calendarId, $GLOBALS['display_calendars'])) {
                    $key = array_search($calendarId, $GLOBALS['display_calendars']);
                    unset($GLOBALS['display_calendars'][$key]);
                } else {
                    $GLOBALS['display_calendars'][] = $calendarId;
                }
            }

            $GLOBALS['prefs']->setValue('display_cals', serialize($GLOBALS['display_calendars']));
        }

        /* Make sure all shares exists now to save on checking later. */
        $GLOBALS['all_calendars'] = array();
        foreach (self::listInternalCalendars() as $id => $calendar) {
            $GLOBALS['all_calendars'][$id] = new Kronolith_Calendar_Internal(array('share' => $calendar));
        }
        $calendar_keys = array_values($GLOBALS['display_calendars']);
        $GLOBALS['display_calendars'] = array();
        foreach ($calendar_keys as $id) {
            if (isset($GLOBALS['all_calendars'][$id])) {
                $GLOBALS['display_calendars'][] = $id;
            }
        }

        /* Make sure all the remote calendars still exist. */
        $_temp = $GLOBALS['display_remote_calendars'];
        $GLOBALS['display_remote_calendars'] = array();
        $GLOBALS['all_remote_calendars'] = array();
        $calendars = @unserialize($GLOBALS['prefs']->getValue('remote_cals'));
        if (!is_array($calendars)) {
            $calendars = array();
        }
        foreach ($calendars as $calendar) {
            $GLOBALS['all_remote_calendars'][$calendar['url']] = new Kronolith_Calendar_Remote($calendar);
            if (in_array($calendar['url'], $_temp)) {
                $GLOBALS['display_remote_calendars'][] = $calendar['url'];
            }
        }
        $GLOBALS['prefs']->setValue('display_remote_cals', serialize($GLOBALS['display_remote_calendars']));

        /* Make sure all the holiday drivers still exist. */
        $GLOBALS['all_holidays'] = array();
        if (!empty($GLOBALS['conf']['holidays']['enable'])) {
            if (class_exists('Date_Holidays')) {
                foreach (Date_Holidays::getInstalledDrivers() as $driver) {
                    if ($driver['id'] == 'Composite') {
                        continue;
                    }
                    $GLOBALS['all_holidays'][$driver['id']] = new Kronolith_Calendar_Holiday(array('driver' => $driver));
                    ksort($GLOBALS['all_holidays']);
                }
            }
        }
        $_temp = $GLOBALS['display_holidays'];
        $GLOBALS['display_holidays'] = array();
        foreach (array_keys($GLOBALS['all_holidays']) as $id) {
            if (in_array($id, $_temp)) {
                $GLOBALS['display_holidays'][] = $id;
            }
        }
        $GLOBALS['prefs']->setValue('holiday_drivers', serialize($GLOBALS['display_holidays']));

        /* Get a list of external calendars. */
        $GLOBALS['all_external_calendars'] = array();

        /* Make sure all task lists exist. */
        if (self::hasApiPermission('tasks') &&
            $GLOBALS['registry']->hasMethod('tasks/listTimeObjects')) {
            try {
                $tasklists = $GLOBALS['registry']->tasks->listTasklists();
                $categories = $GLOBALS['registry']->call('tasks/listTimeObjectCategories');
                foreach ($categories as $name => $description) {
                    if (!isset($tasklists[$name])) {
                        continue;
                    }
                    $GLOBALS['all_external_calendars']['tasks/' . $name] = new Kronolith_Calendar_External_Tasks(array('api' => 'tasks', 'name' => $description, 'share' => $tasklists[$name]));
                }
            } catch (Horde_Exception $e) {
                Horde::logMessage($e, 'DEBUG');
            }
        }

        if ($GLOBALS['session']->exists('kronolith', 'all_external_calendars')) {
            foreach ($GLOBALS['session']->get('kronolith', 'all_external_calendars') as $calendar) {
                if (!self::hasApiPermission($calendar['a']) ||
                    $calendar['a'] == 'tasks') {
                    continue;
                }
                $GLOBALS['all_external_calendars'][$calendar['a'] . '/' . $calendar['n']] = new Kronolith_Calendar_External(array('api' => $calendar['a'], 'name' => $calendar['d'], 'id' => $calendar['n']));
            }
        } else {
            $apis = array_unique($GLOBALS['registry']->listAPIs());
            $ext_cals = array();

            foreach ($apis as $api) {
                if ($api == 'tasks' ||
                    !self::hasApiPermission($api) ||
                    !$GLOBALS['registry']->hasMethod($api . '/listTimeObjects')) {
                    continue;
                }
                try {
                    $categories = $GLOBALS['registry']->call($api . '/listTimeObjectCategories');
                } catch (Horde_Exception $e) {
                    Horde::logMessage($e, 'DEBUG');
                    continue;
                }

                foreach ($categories as $name => $description) {
                    $GLOBALS['all_external_calendars'][$api . '/' . $name] = new Kronolith_Calendar_External(array('api' => $api, 'name' => $description, 'id' => $name));
                    $ext_cals[] = array(
                        'a' => $api,
                        'n' => $name,
                        'd' => $description
                    );
                }
            }

            $GLOBALS['session']->set('kronolith', 'all_external_calendars', $ext_cals);
        }

        /* Make sure all the external calendars still exist. */
        $_tasklists = $_temp = $GLOBALS['display_external_calendars'];
        if (self::hasApiPermission('tasks')) {
            try {
                $_tasklists = $GLOBALS['registry']->tasks->getDisplayedTasklists();
            } catch (Horde_Exception $e) {
            }
        }
        $GLOBALS['display_external_calendars'] = array();
        foreach ($GLOBALS['all_external_calendars'] as $id => $calendar) {
            if ((substr($id, 0, 6) == 'tasks/' &&
                 in_array(substr($id, 6), $_tasklists)) ||
                in_array($id, $_temp)) {
                $GLOBALS['display_external_calendars'][] = $id;
            } else {
                /* Convert Kronolith 2 preferences.
                 * @todo: remove in Kronolith 3.1. */
                list(,$oldid,) = explode('/', $id);
                if (in_array($calendar->api() . '/' . $oldid, $_temp)) {
                    $GLOBALS['display_external_calendars'][] = $calendarId;
                }
            }
        }
        $GLOBALS['prefs']->setValue('display_external_cals', serialize($GLOBALS['display_external_calendars']));

        /* If an authenticated doesn't own a calendar, create it. */
        if (!empty($GLOBALS['conf']['share']['auto_create']) &&
            $GLOBALS['registry']->getAuth() &&
            !count(self::listInternalCalendars(true))) {
            $identity = $GLOBALS['injector']->getInstance('Horde_Core_Factory_Identity')->create();
            $share = $GLOBALS['kronolith_shares']->newShare(
                $GLOBALS['registry']->getAuth(),
                strval(new Horde_Support_Randomid()),
                sprintf(_("Calendar of %s"), $identity->getName())
            );
            $GLOBALS['kronolith_shares']->addShare($share);
            $GLOBALS['all_calendars'][$share->getName()] = new Kronolith_Calendar_Internal(array('share' => $share));
            $GLOBALS['display_calendars'][] = $share->getName();

            /* Calendar auto-sharing with the user's groups */
            if ($GLOBALS['conf']['autoshare']['shareperms'] != 'none') {
                $perm_value = 0;
                switch ($GLOBALS['conf']['autoshare']['shareperms']) {
                case 'read':
                    $perm_value = Horde_Perms::READ | Horde_Perms::SHOW;
                    break;
                case 'edit':
                    $perm_value = Horde_Perms::READ | Horde_Perms::SHOW | Horde_Perms::EDIT;
                    break;
                case 'full':
                    $perm_value = Horde_Perms::READ | Horde_Perms::SHOW | Horde_Perms::EDIT | Horde_Perms::DELETE;
                    break;
                }

                try {
                    $group_list = $GLOBALS['injector']
                        ->getInstance('Horde_Group')
                        ->listGroups($GLOBALS['registry']->getAuth());
                    if (count($group_list)) {
                        $perm = $share->getPermission();
                        // Add the default perm, not added otherwise
                        foreach ($group_list as $group_id => $group_name) {
                            $perm->addGroupPermission($group_id, $perm_value, false);
                        }
                        $share->setPermission($perm);
                        $GLOBALS['notification']->push(sprintf(_("New calendar created and automatically shared with the following group(s): %s."), implode(', ', $group_list)), 'horde.success');
                    }
                } catch (Horde_Group_Exception $e) {}
            }

            $GLOBALS['prefs']->setValue('display_cals', serialize($GLOBALS['display_calendars']));
        }
    }

    /**
     * Initialize the event map.
     *
     * @param array $params Parameters to pass the the map
     *
     * @return void
     */
    static public function initEventMap($params)
    {
        // Add the apikeys
        if (!empty($params['providers'])) {
            /* It is safe to put configuration specific to horde driver inside
            this block since horde driver *must* contain a provider array */

            // Language specific file needed?
            //$language = str_replace('_', '-', $GLOBALS['language']);
            $language = $GLOBALS['language'];
            if (!file_exists($GLOBALS['registry']->get('jsfs', 'horde') . '/map/' . $language . '.js')) {
                $language = 'en-US';
            }
            $params['conf'] = array(
                'markerImage' => (string)Horde_Themes::img('map/marker.png'),
                'markerBackground' => (string)Horde_Themes::img('map/marker-shadow.png'),
                'useMarkerLayer' => true,
                'language' => $language,
            );

            foreach ($params['providers'] as $layer) {
                switch ($layer) {
                case 'Google':
                    $params['conf']['apikeys']['google'] = $GLOBALS['conf']['api']['googlemaps'];
                    break;
                case 'Yahoo':
                    $params['conf']['apikeys']['yahoo'] = $GLOBALS['conf']['api']['yahoomaps'];
                    break;
                case 'Cloudmade':
                    $params['conf']['apikeys']['cloudmade'] = $GLOBALS['conf']['api']['cloudmade'];
                    break;
                }
            }
        }

        if (!empty($params['geocoder'])) {
            switch ($params['geocoder']) {
            case 'Google':
                $params['conf']['apikeys']['google'] = $GLOBALS['conf']['api']['googlemaps'];
                break;
            case 'Yahoo':
                $params['conf']['apikeys']['yahoo'] = $GLOBALS['conf']['api']['yahoomaps'];
                break;
            case 'Cloudmade':
                $params['conf']['apikeys']['cloudmade'] = $GLOBALS['conf']['api']['cloudmade'];
                break;
            }
        }
        $params['jsuri'] = $GLOBALS['registry']->get('jsuri', 'horde') . '/map/';
        Horde::addScriptFile('map/map.js', 'horde');
        $js = 'HordeMap.initialize(' . Horde_Serialize::serialize($params, HORDE_SERIALIZE::JSON) . ');';
        Horde::addinlineScript($js);
    }

    /**
     * Returns the real name, if available, of a user.
     */
    static public function getUserName($uid)
    {
        static $names = array();

        if (!isset($names[$uid])) {
            $ident = $GLOBALS['injector']->getInstance('Horde_Core_Factory_Identity')->create($uid);
            $ident->setDefault($ident->getDefault());
            $names[$uid] = $ident->getValue('fullname');
            if (empty($names[$uid])) {
                $names[$uid] = $uid;
            }
        }

        return $names[$uid];
    }

    /**
     * Returns the email address, if available, of a user.
     */
    static public function getUserEmail($uid)
    {
        static $emails = array();

        if (!isset($emails[$uid])) {
            $ident = $GLOBALS['injector']->getInstance('Horde_Core_Factory_Identity')->create($uid);
            $emails[$uid] = $ident->getValue('from_addr');
            if (empty($emails[$uid])) {
                $emails[$uid] = $uid;
            }
        }

        return $emails[$uid];
    }

    /**
     * Checks if an email address belongs to a user.
     */
    static public function isUserEmail($uid, $email)
    {
        static $emails = array();

        if (!isset($emails[$uid])) {
            $ident = $GLOBALS['injector']->getInstance('Horde_Core_Factory_Identity')->create($uid);

            $addrs = $ident->getAll('from_addr');
            $addrs[] = $uid;

            $emails[$uid] = $addrs;
        }

        return in_array($email, $emails[$uid]);
    }

    /**
     * Maps a Kronolith recurrence value to a translated string suitable for
     * display.
     *
     * @param integer $type  The recurrence value; one of the
     *                       Horde_Date_Recurrence::RECUR_XXX constants.
     *
     * @return string  The translated displayable recurrence value string.
     */
    static public function recurToString($type)
    {
        switch ($type) {
        case Horde_Date_Recurrence::RECUR_NONE:
            return _("Does not recur");

        case Horde_Date_Recurrence::RECUR_DAILY:
            return _("Recurs daily");

        case Horde_Date_Recurrence::RECUR_WEEKLY:
            return _("Recurs weekly");

        case Horde_Date_Recurrence::RECUR_MONTHLY_DATE:
        case Horde_Date_Recurrence::RECUR_MONTHLY_WEEKDAY:
            return _("Recurs monthly");

        case Horde_Date_Recurrence::RECUR_YEARLY_DATE:
        case Horde_Date_Recurrence::RECUR_YEARLY_DAY:
        case Horde_Date_Recurrence::RECUR_YEARLY_WEEKDAY:
            return _("Recurs yearly");
        }
    }

    /**
     * Maps a Kronolith meeting status string to a translated string suitable
     * for display.
     *
     * @param integer $status  The meeting status; one of the
     *                         Kronolith::STATUS_XXX constants.
     *
     * @return string  The translated displayable meeting status string.
     */
    static public function statusToString($status)
    {
        switch ($status) {
        case self::STATUS_CONFIRMED:
            return _("Confirmed");

        case self::STATUS_CANCELLED:
            return _("Cancelled");

        case self::STATUS_FREE:
            return _("Free");

        case self::STATUS_TENTATIVE:
        default:
            return _("Tentative");
        }
    }

    /**
     * Maps a Kronolith attendee response string to a translated string
     * suitable for display.
     *
     * @param integer $response  The attendee response; one of the
     *                           Kronolith::RESPONSE_XXX constants.
     *
     * @return string  The translated displayable attendee response string.
     */
    static public function responseToString($response)
    {
        switch ($response) {
        case self::RESPONSE_ACCEPTED:
            return _("Accepted");

        case self::RESPONSE_DECLINED:
            return _("Declined");

        case self::RESPONSE_TENTATIVE:
            return _("Tentative");

        case self::RESPONSE_NONE:
        default:
            return _("None");
        }
    }

    /**
     * Maps a Kronolith attendee participation string to a translated string
     * suitable for display.
     *
     * @param integer $part  The attendee participation; one of the
     *                       Kronolith::PART_XXX constants.
     *
     * @return string  The translated displayable attendee participation
     *                 string.
     */
    static public function partToString($part)
    {
        switch ($part) {
        case self::PART_OPTIONAL:
            return _("Optional");

        case self::PART_NONE:
            return _("None");

        case self::PART_REQUIRED:
        default:
            return _("Required");
        }
    }

    /**
     * Maps an iCalendar attendee response string to the corresponding
     * Kronolith value.
     *
     * @param string $response  The attendee response.
     *
     * @return string  The Kronolith response value.
     */
    static public function responseFromICal($response)
    {
        switch (Horde_String::upper($response)) {
        case 'ACCEPTED':
            return self::RESPONSE_ACCEPTED;

        case 'DECLINED':
            return self::RESPONSE_DECLINED;

        case 'TENTATIVE':
            return self::RESPONSE_TENTATIVE;

        case 'NEEDS-ACTION':
        default:
            return self::RESPONSE_NONE;
        }
    }

    /**
     * Builds the HTML for an event status widget.
     *
     * @param string $name     The name of the widget.
     * @param string $current  The selected status value.
     * @param string $any      Whether an 'any' item should be added
     *
     * @return string  The HTML <select> widget.
     */
    static public function buildStatusWidget($name,
                                             $current = self::STATUS_CONFIRMED,
                                             $any = false)
    {
        $html = "<select id=\"$name\" name=\"$name\">";

        $statii = array(
            self::STATUS_FREE,
            self::STATUS_TENTATIVE,
            self::STATUS_CONFIRMED,
            self::STATUS_CANCELLED
        );

        if (!isset($current)) {
            $current = self::STATUS_NONE;
        }

        if ($any) {
            $html .= "<option value=\"" . self::STATUS_NONE . "\"";
            $html .= ($current == self::STATUS_NONE) ? ' selected="selected">' : '>';
            $html .= _("Any") . "</option>";
        }

        foreach ($statii as $status) {
            $html .= "<option value=\"$status\"";
            $html .= ($status == $current) ? ' selected="selected">' : '>';
            $html .= self::statusToString($status) . "</option>";
        }
        $html .= '</select>';

        return $html;
    }

    /**
     * Returns all internal calendars a user has access to, according
     * to several parameters/permission levels.
     *
     * This method takes the $conf['share']['hidden'] setting into account. If
     * this setting is enabled, even if requesting permissions different than
     * SHOW, it will only return calendars that the user owns or has SHOW
     * permissions for. For checking individual calendar's permissions, use
     * hasPermission() instead.
     *
     * @param boolean $owneronly   Only return calenders that this user owns?
     *                             Defaults to false.
     * @param integer $permission  The permission to filter calendars by.
     *
     * @return array  The calendar list.
     */
    static public function listInternalCalendars($owneronly = false, $permission = Horde_Perms::SHOW)
    {
        if ($owneronly && !$GLOBALS['registry']->getAuth()) {
            return array();
        }

        if ($owneronly || empty($GLOBALS['conf']['share']['hidden'])) {
            try {
                $calendars = $GLOBALS['kronolith_shares']->listShares(
                    $GLOBALS['registry']->getAuth(),
                    array('perm' => $permission,
                          'attributes' => $owneronly ? $GLOBALS['registry']->getAuth() : null,
                          'sort_by' => 'name'));
            } catch (Horde_Share_Exception $e) {
                Horde::logMessage($e);
                return array();
            }
        } else {
            try {
                $calendars = $GLOBALS['kronolith_shares']->listShares(
                    $GLOBALS['registry']->getAuth(),
                    array('perm' => $permission,
                          'attributes' => $GLOBALS['registry']->getAuth(),
                          'sort_by' => 'name'));
            } catch (Horde_Share_Exception $e) {
                Horde::logMessage($e);
                return array();
            }
            $display_calendars = @unserialize($GLOBALS['prefs']->getValue('display_cals'));
            if (is_array($display_calendars)) {
                foreach ($display_calendars as $id) {
                    try {
                        $calendar = $GLOBALS['kronolith_shares']->getShare($id);
                        if ($calendar->hasPermission($GLOBALS['registry']->getAuth(), $permission)) {
                            $calendars[$id] = $calendar;
                        }
                    } catch (Horde_Exception_NotFound $e) {
                    } catch (Horde_Share_Exception $e) {
                        Horde::logMessage($e);
                        return array();
                    }
                }
            }
        }

        $default_share = $GLOBALS['prefs']->getValue('default_share');
        if (isset($calendars[$default_share])) {
            $calendar = $calendars[$default_share];
            unset($calendars[$default_share]);
            $calendars = array($default_share => $calendar) + $calendars;
        }

        return $calendars;
    }

    /**
     * Returns all calendars a user has access to, according to several
     * parameters/permission levels.
     *
     * @param boolean $owneronly   Only return calenders that this user owns?
     *                             Defaults to false.
     * @param boolean $display     Only return calendars that are supposed to
     *                             be displayed per configuration and user
     *                             preference.
     * @param integer $permission  The permission to filter calendars by.
     *
     * @return array  The calendar list.
     */
    static public function listCalendars($permission = Horde_Perms::SHOW,
                                         $display = false,
                                         $flat = true)
    {
        $calendars = array();
        foreach ($GLOBALS['all_calendars'] as $id => $calendar) {
            if ($calendar->hasPermission($permission) &&
                (!$display || $calendar->display())) {
                if ($flat) {
                    $calendars['internal_' . $id] = $calendar;
                }
            }
        }

        foreach ($GLOBALS['all_remote_calendars'] as $id => $calendar) {
            try {
                if ($calendar->hasPermission($permission) &&
                    (!$display || $calendar->display())) {
                    if ($flat) {
                        $calendars['remote_' . $id] = $calendar;
                    }
                }
            } catch (Kronolith_Exception $e) {
                $GLOBALS['notification']->push(sprintf(_("The calendar %s returned the error: %s"), $calendar->name(), $e->getMessage()), 'horde.error');
            }
        }

        foreach ($GLOBALS['all_external_calendars'] as $id => $calendar) {
            if ($calendar->hasPermission($permission) &&
                (!$display || $calendar->display())) {
                if ($flat) {
                    $calendars['external_' . $id] = $calendar;
                }
            }
        }

        foreach ($GLOBALS['all_holidays'] as $id => $calendar) {
            if ($calendar->hasPermission($permission) &&
                (!$display || $calendar->display())) {
                if ($flat) {
                    $calendars['holiday_' . $id] = $calendar;
                }
            }
        }

        return $calendars;
    }

    /**
     * Returns the default calendar for the current user at the specified
     * permissions level.
     *
     * @param integer $permission  Horde_Perms constant for permission level required.
     * @param boolean $owner_only  Only consider owner-owned calendars.
     *
     * @return mixed  The calendar id, or false if none found.
     */
    static public function getDefaultCalendar($permission = Horde_Perms::SHOW, $owner_only = false)
    {
        global $prefs;

        $default_share = $prefs->getValue('default_share');
        $calendars = self::listInternalCalendars($owner_only, $permission);

        if (isset($calendars[$default_share]) ||
            $prefs->isLocked('default_share')) {
            return $default_share;
        } elseif (isset($GLOBALS['all_calendars'][$GLOBALS['registry']->getAuth()]) &&
                  $GLOBALS['all_calendars'][$GLOBALS['registry']->getAuth()]->hasPermission($permission)) {
            // This is for older, existing default shares. New default shares
            // are not named as the username.
            return $GLOBALS['registry']->getAuth();
        } elseif (count($calendars)) {
            return key($calendars);
        }

        return false;
    }

    /**
     * Returns the calendars that should be used for syncing.
     *
     * @return array  An array of calendar ids
     */
    static public function getSyncCalendars()
    {
        $cs = unserialize($GLOBALS['prefs']->getValue('sync_calendars'));
        if (!empty($cs)) {
            // Have a pref, make sure it's still available
            $calendars = self::listInternalCalendars(true, Horde_Perms::EDIT);
            $cscopy = array_flip($cs);
            foreach ($cs as $c) {
                if (empty($calendars[$c])) {
                    unset($cscopy[$c]);
                }
            }

            // Have at least one
            if (count($cscopy)) {
                return array_flip($cscopy);
            }
        }

        if ($cs = self::getDefaultCalendar(Horde_Perms::EDIT, true)) {
            return array($cs);
        }

        return array();
    }

    /**
     * Returns whether the current user has certain permissions on a calendar.
     *
     * @since Kronolith 3.0.6
     *
     * @param string $calendar  A calendar id.
     * @param integer $perm     A Horde_Perms permission mask.
     *
     * @return boolean  True if the current user has the requested permissions.
     */
    static public function hasPermission($calendar, $perm)
    {
        try {
            $share = $GLOBALS['kronolith_shares']->getShare($calendar);
            if (!$share->hasPermission($GLOBALS['registry']->getAuth(), $perm)) {
                throw new Horde_Exception_NotFound();
            }
        } catch (Horde_Exception_NotFound $e) {
            return false;
        }
        return true;
    }

    /**
     * Creates a new share.
     *
     * @param array $info  Hash with calendar information.
     *
     * @return Horde_Share  The new share.
     * @throws Kronolith_Exception
     */
    static public function addShare($info)
    {
        try {
            $calendar = $GLOBALS['kronolith_shares']->newShare($GLOBALS['registry']->getAuth(), strval(new Horde_Support_Randomid()), $info['name']);
        } catch (Horde_Share_Exception $e) {
            throw new Kronolith_Exception($e);
        }

        $calendar->set('color', $info['color']);
        $calendar->set('desc', $info['description']);
        if (!empty($info['system'])) {
            $calendar->set('owner', null);
        }
        $tagger = self::getTagger();
        $tagger->tag($calendar->getName(), $info['tags'], $calendar->get('owner'), 'calendar');

        try {
            $GLOBALS['kronolith_shares']->addShare($calendar);
        } catch (Horde_Share_Exception $e) {
            throw new Kronolith_Exception($e);
        }

        $GLOBALS['display_calendars'][] = $calendar->getName();
        $GLOBALS['prefs']->setValue('display_cals', serialize($GLOBALS['display_calendars']));

        return $calendar;
    }

    /**
     * Updates an existing share.
     *
     * @param Horde_Share $share  The share to update.
     * @param array $info         Hash with calendar information.
     *
     * @throws Kronolith_Exception
     */
    static public function updateShare(&$calendar, $info)
    {
        if (!$GLOBALS['registry']->getAuth() ||
            ($calendar->get('owner') != $GLOBALS['registry']->getAuth() &&
             (!is_null($calendar->get('owner')) || !$GLOBALS['registry']->isAdmin()))) {
            throw new Kronolith_Exception(_("You are not allowed to change this calendar."));
        }

        $original_name = $calendar->get('name');
        $calendar->set('name', $info['name']);
        $calendar->set('color', $info['color']);
        $calendar->set('desc', $info['description']);
        $calendar->set('owner', empty($info['system']) ? $GLOBALS['registry']->getAuth() : null);

        try {
            $result = $calendar->save();
        } catch (Horde_Share_Exception $e) {
            throw new Kronolith_Exception(sprintf(_("Unable to save calendar \"%s\": %s"), $info['name'], $e->getMessage()));
        }

        $tagger = self::getTagger();
        $tagger->replaceTags($calendar->getName(), $info['tags'], $calendar->get('owner'), 'calendar');
    }

    /**
     * Deletes a share.
     *
     * @param Horde_Share $calendar  The share to delete.
     *
     * @throws Kronolith_Exception
     */
    static public function deleteShare($calendar)
    {
        if (!$GLOBALS['registry']->getAuth() ||
            ($calendar->get('owner') != $GLOBALS['registry']->getAuth() &&
             (!is_null($calendar->get('owner')) || !$GLOBALS['registry']->isAdmin()))) {
            throw new Kronolith_Exception(_("You are not allowed to delete this calendar."));
        }

        // Delete the calendar.
        try {
            self::getDriver()->delete($calendar->getName());
        } catch (Exception $e) {
            throw new Kronolith_Exception(sprintf(_("Unable to delete \"%s\": %s"), $calendar->get('name'), $ed->getMessage()));
        }

        // Remove share and all groups/permissions.
        try {
            $result = $GLOBALS['kronolith_shares']->removeShare($calendar);
        } catch (Horde_Share_Exception $e) {
            throw new Kronolith_Exception($e);
        }
    }

    /**
     * Reads a submitted permissions form and updates the share permissions.
     *
     * @param Horde_Share_Object $share  The share to update.
     *
     * @return array  A list of error messages.
     * @throws Kronolith_Exception
     */
    static public function readPermsForm($share)
    {
        $auth = $GLOBALS['injector']->getInstance('Horde_Core_Factory_Auth')->create();
        $perm = $share->getPermission();
        $errors = array();

        if ($GLOBALS['conf']['share']['notify']) {
            $identity = $GLOBALS['injector']
                ->getInstance('Horde_Core_Factory_Identity')
                ->create();
            $mail = new Horde_Mime_Mail(array(
                'From' => $identity->getDefaultFromAddress(true),
                'User-Agent' => 'Kronolith ' . $GLOBALS['registry']->getVersion()));
            $image = self::getImagePart('big_share.png');
            $view = new Horde_View(array('templatePath' => KRONOLITH_TEMPLATES . '/share'));
            new Horde_View_Helper_Text($view);
            $view->identity = $identity;
            $view->calendar = $share->get('name');
            $view->imageId = $image->getContentId();
        }

        // Process owner and owner permissions.
        $old_owner = $share->get('owner');
        $new_owner_backend = Horde_Util::getFormData('owner_select', Horde_Util::getFormData('owner_input', $old_owner));
        $new_owner = $GLOBALS['registry']->convertUsername($new_owner_backend, true);
        if ($old_owner !== $new_owner && !empty($new_owner)) {
            if ($old_owner != $GLOBALS['registry']->getAuth() && !$GLOBALS['registry']->isAdmin()) {
                $errors[] = _("Only the owner or system administrator may change ownership or owner permissions for a share");
            } elseif ($auth->hasCapability('list') && !$auth->exists($new_owner_backend)) {
                $errors[] = sprintf(_("The user \"%s\" does not exist."), $new_owner_backend);
            } else {
                $share->set('owner', $new_owner);
                $share->save();
                if ($GLOBALS['conf']['share']['notify']) {
                    $view->ownerChange = true;
                    $multipart = self::buildMimeMessage($view, 'notification', $image);
                    $to = $GLOBALS['injector']
                        ->getInstance('Horde_Core_Factory_Identity')
                        ->create($new_owner)
                        ->getDefaultFromAddress(true);
                    $mail->addHeader('Subject', _("Ownership assignment"));
                    $mail->addHeader('To', $to);
                    $mail->setBasePart($multipart);
                    $mail->send($GLOBALS['injector']->getInstance('Horde_Mail'));
                    $view->ownerChange = false;
                }
            }
        }

        if ($GLOBALS['conf']['share']['notify']) {
            if ($GLOBALS['conf']['share']['hidden']) {
                $view->subscribe = Horde::url('calendars/subscribe.php', true)->add('calendar', $share->getName());
            }
            $multipart = self::buildMimeMessage($view, 'notification', $image);
        }

        if ($GLOBALS['registry']->isAdmin() ||
            !empty($GLOBALS['conf']['share']['world'])) {
            // Process default permissions.
            if (Horde_Util::getFormData('default_show')) {
                $perm->addDefaultPermission(Horde_Perms::SHOW, false);
            } else {
                $perm->removeDefaultPermission(Horde_Perms::SHOW, false);
            }
            if (Horde_Util::getFormData('default_read')) {
                $perm->addDefaultPermission(Horde_Perms::READ, false);
            } else {
                $perm->removeDefaultPermission(Horde_Perms::READ, false);
            }
            if (Horde_Util::getFormData('default_edit')) {
                $perm->addDefaultPermission(Horde_Perms::EDIT, false);
            } else {
                $perm->removeDefaultPermission(Horde_Perms::EDIT, false);
            }
            if (Horde_Util::getFormData('default_delete')) {
                $perm->addDefaultPermission(Horde_Perms::DELETE, false);
            } else {
                $perm->removeDefaultPermission(Horde_Perms::DELETE, false);
            }
            if (Horde_Util::getFormData('default_delegate')) {
                $perm->addDefaultPermission(self::PERMS_DELEGATE, false);
            } else {
                $perm->removeDefaultPermission(self::PERMS_DELEGATE, false);
            }

            // Process guest permissions.
            if (Horde_Util::getFormData('guest_show')) {
                $perm->addGuestPermission(Horde_Perms::SHOW, false);
            } else {
                $perm->removeGuestPermission(Horde_Perms::SHOW, false);
            }
            if (Horde_Util::getFormData('guest_read')) {
                $perm->addGuestPermission(Horde_Perms::READ, false);
            } else {
                $perm->removeGuestPermission(Horde_Perms::READ, false);
            }
            if (Horde_Util::getFormData('guest_edit')) {
                $perm->addGuestPermission(Horde_Perms::EDIT, false);
            } else {
                $perm->removeGuestPermission(Horde_Perms::EDIT, false);
            }
            if (Horde_Util::getFormData('guest_delete')) {
                $perm->addGuestPermission(Horde_Perms::DELETE, false);
            } else {
                $perm->removeGuestPermission(Horde_Perms::DELETE, false);
            }
            if (Horde_Util::getFormData('guest_delegate')) {
                $perm->addGuestPermission(self::PERMS_DELEGATE, false);
            } else {
                $perm->removeGuestPermission(self::PERMS_DELEGATE, false);
            }
        }

        // Process creator permissions.
        if (Horde_Util::getFormData('creator_show')) {
            $perm->addCreatorPermission(Horde_Perms::SHOW, false);
        } else {
            $perm->removeCreatorPermission(Horde_Perms::SHOW, false);
        }
        if (Horde_Util::getFormData('creator_read')) {
            $perm->addCreatorPermission(Horde_Perms::READ, false);
        } else {
            $perm->removeCreatorPermission(Horde_Perms::READ, false);
        }
        if (Horde_Util::getFormData('creator_edit')) {
            $perm->addCreatorPermission(Horde_Perms::EDIT, false);
        } else {
            $perm->removeCreatorPermission(Horde_Perms::EDIT, false);
        }
        if (Horde_Util::getFormData('creator_delete')) {
            $perm->addCreatorPermission(Horde_Perms::DELETE, false);
        } else {
            $perm->removeCreatorPermission(Horde_Perms::DELETE, false);
        }
        if (Horde_Util::getFormData('creator_delegate')) {
            $perm->addCreatorPermission(self::PERMS_DELEGATE, false);
        } else {
            $perm->removeCreatorPermission(self::PERMS_DELEGATE, false);
        }

        // Process user permissions.
        $u_names = Horde_Util::getFormData('u_names');
        $u_show = Horde_Util::getFormData('u_show');
        $u_read = Horde_Util::getFormData('u_read');
        $u_edit = Horde_Util::getFormData('u_edit');
        $u_delete = Horde_Util::getFormData('u_delete');
        $u_delegate = Horde_Util::getFormData('u_delegate');

        $current = $perm->getUserPermissions();
        if ($GLOBALS['conf']['share']['notify']) {
            $mail->addHeader('Subject', _("Access permissions"));
        }

        $perm->removeUserPermission(null, null, false);
        foreach ($u_names as $key => $user_backend) {
            // Apply backend hooks
            $user = $GLOBALS['registry']->convertUsername($user_backend, true);
            // If the user is empty, or we've already set permissions
            // via the owner_ options, don't do anything here.
            if (empty($user) || $user == $new_owner) {
                continue;
            }
            if ($auth->hasCapability('list') && !$auth->exists($user_backend)) {
                $errors[] = sprintf(_("The user \"%s\" does not exist."), $user_backend);
                continue;
            }

            $has_perms = false;
            if (!empty($u_show[$key])) {
                $perm->addUserPermission($user, Horde_Perms::SHOW, false);
                $has_perms = true;
            }
            if (!empty($u_read[$key])) {
                $perm->addUserPermission($user, Horde_Perms::READ, false);
                $has_perms = true;
            }
            if (!empty($u_edit[$key])) {
                $perm->addUserPermission($user, Horde_Perms::EDIT, false);
                $has_perms = true;
            }
            if (!empty($u_delete[$key])) {
                $perm->addUserPermission($user, Horde_Perms::DELETE, false);
                $has_perms = true;
            }
            if (!empty($u_delegate[$key])) {
                $perm->addUserPermission($user, self::PERMS_DELEGATE, false);
                $has_perms = true;
            }

            // Notify users that have been added.
            if ($GLOBALS['conf']['share']['notify'] &&
                !isset($current[$user]) && $has_perms) {
                $to = $GLOBALS['injector']
                    ->getInstance('Horde_Core_Factory_Identity')
                    ->create($user)
                    ->getDefaultFromAddress(true);
                $mail->addHeader('To', $to);
                $mail->setBasePart($multipart);
                $mail->send($GLOBALS['injector']->getInstance('Horde_Mail'));
            }
        }

        // Process group permissions.
        $g_names = Horde_Util::getFormData('g_names');
        $g_show = Horde_Util::getFormData('g_show');
        $g_read = Horde_Util::getFormData('g_read');
        $g_edit = Horde_Util::getFormData('g_edit');
        $g_delete = Horde_Util::getFormData('g_delete');
        $g_delegate = Horde_Util::getFormData('g_delegate');

        $current = $perm->getGroupPermissions();
        $perm->removeGroupPermission(null, null, false);
        foreach ($g_names as $key => $group) {
            if (empty($group)) {
                continue;
            }

            $has_perms = false;
            if (!empty($g_show[$key])) {
                $perm->addGroupPermission($group, Horde_Perms::SHOW, false);
                $has_perms = true;
            }
            if (!empty($g_read[$key])) {
                $perm->addGroupPermission($group, Horde_Perms::READ, false);
                $has_perms = true;
            }
            if (!empty($g_edit[$key])) {
                $perm->addGroupPermission($group, Horde_Perms::EDIT, false);
                $has_perms = true;
            }
            if (!empty($g_delete[$key])) {
                $perm->addGroupPermission($group, Horde_Perms::DELETE, false);
                $has_perms = true;
            }
            if (!empty($g_delegate[$key])) {
                $perm->addGroupPermission($group, self::PERMS_DELEGATE, false);
                $has_perms = true;
            }

            // Notify users that have been added.
            if ($GLOBALS['conf']['share']['notify'] &&
                !isset($current[$group]) && $has_perms) {
                $groupOb = $GLOBALS['injector']
                    ->getInstance('Horde_Group')
                    ->getData($group);
                if (!empty($groupOb['email'])) {
                    $mail->addHeader('To', $groupOb['name'] . ' <' . $groupOb['email'] . '>');
                    $mail->setBasePart($multipart);
                    $mail->send($GLOBALS['injector']->getInstance('Horde_Mail'));
                }
            }
        }
        try {
            $share->setPermission($perm);
        } catch (Horde_Share_Exception $e) {
            throw new Kronolith_Exception($e);
        }

        return $errors;
    }

    /**
     * Subscribes to or updates a remote calendar.
     *
     * @param array $info     Hash with calendar information.
     * @param string $update  If present, the original URL of the calendar to
     *                        update.
     *
     * @throws Kronolith_Exception
     */
    static public function subscribeRemoteCalendar(&$info, $update = false)
    {
        if (!(strlen($info['name']) && strlen($info['url']))) {
            throw new Kronolith_Exception(_("You must specify a name and a URL."));
        }

        if (!empty($info['user']) || !empty($info['password'])) {
            $key = $GLOBALS['registry']->getAuthCredential('password');
            if ($key) {
                $secret = $GLOBALS['injector']->getInstance('Horde_Secret');
                $info['user'] = base64_encode($secret->write($key, $info['user']));
                $info['password'] = base64_encode($secret->write($key, $info['password']));
            }
        }

        $remote_calendars = unserialize($GLOBALS['prefs']->getValue('remote_cals'));
        if ($update) {
            foreach ($remote_calendars as $key => $calendar) {
                if ($calendar['url'] == $update) {
                    $remote_calendars[$key] = $info;
                    break;
                }
            }
        } else {
            $remote_calendars[] = $info;
            $GLOBALS['display_remote_calendars'][] = $info['url'];
            $GLOBALS['prefs']->setValue('display_remote_cals', serialize($GLOBALS['display_remote_calendars']));
        }

        $GLOBALS['prefs']->setValue('remote_cals', serialize($remote_calendars));
    }

    /**
     * Unsubscribes from a remote calendar.
     *
     * @param string $url  The calendar URL.
     *
     * @return array  Hash with the deleted calendar's information.
     * @throws Kronolith_Exception
     */
    static public function unsubscribeRemoteCalendar($url)
    {
        $url = trim($url);
        if (!strlen($url)) {
            return false;
        }

        $remote_calendars = unserialize($GLOBALS['prefs']->getValue('remote_cals'));
        $remote_calendar = null;
        foreach ($remote_calendars as $key => $calendar) {
            if ($calendar['url'] == $url) {
                $remote_calendar = $calendar;
                unset($remote_calendars[$key]);
                break;
            }
        }
        if (!$remote_calendar) {
            throw new Kronolith_Exception(_("The remote calendar was not found."));
        }

        $GLOBALS['prefs']->setValue('remote_cals', serialize($remote_calendars));

        return $remote_calendar;
    }

    /**
     * Returns the feed URL for a calendar.
     *
     * @param string $calendar  A calendar name.
     *
     * @return string  The calendar's feed URL.
     */
    static public function feedUrl($calendar)
    {
        if (isset($GLOBALS['conf']['urls']['pretty']) &&
            $GLOBALS['conf']['urls']['pretty'] == 'rewrite') {
            return Horde::url('feed/' . $calendar, true, -1);
        }
        return Horde::url('feed/index.php', true, -1)
            ->add('c', $calendar);
    }

    /**
     * Returs the HTML/javascript snippit needed to embed a calendar in an
     * external website.
     *
     * @param string $calendar  A calendar name.
     *
     * @return string  The calendar's embed snippit.
     */
    static public function embedCode($calendar)
    {
        /* Get the base url */
        $imple = $GLOBALS['injector']->getInstance('Horde_Core_Factory_Imple')->create(array('kronolith', 'Embed'), array(
            'calendar' => 'internal_' . $calendar,
            'container' => 'kronolithCal',
            'view' => 'month'
        ), true);

        $html = '<div id="kronolithCal"></div><script src="' . $imple->getUrl()
            . '" type="text/javascript"></script>';

        return $html;
    }

    /**
     * Parses a comma separated list of names and e-mail addresses into a list
     * of attendee hashes.
     *
     * @param string $newAttendees  A comma separated attendee list.
     *
     * @return array  The attendee list with e-mail addresses as keys and
     *                attendee information as values.
     */
    static public function parseAttendees($newAttendees)
    {
        global $notification;

        if (empty($newAttendees)) {
            return;
        }

        $parser = new Horde_Mail_Rfc822();
        $attendees = array();

        foreach (Horde_Mime_Address::explode($newAttendees) as $newAttendee) {
            // Parse the address without validation to see what we can get out
            // of it. We allow email addresses (john@example.com), email
            // address with user information (John Doe <john@example.com>),
            // and plain names (John Doe).
            try {
                $newAttendeeParsed = $parser->parseAddressList($newAttendee, array(
                    'default_domain' => null,
                    'nest_groups' => false,
                    'validate' => false
                ));
                $error = (!isset($newAttendeeParsed[0]) || !isset($newAttendeeParsed[0]->mailbox));
            } catch (Horde_Mail_Exception $e) {
                $error = true;
            }

            // If we can't even get a mailbox out of the address, then it is
            // likely unuseable. Reject it entirely.
            if ($error) {
                $notification->push(
                    sprintf(_("Unable to recognize \"%s\" as an email address."), $newAttendee),
                    'horde.error');
                continue;
            }

            // Loop through any addresses we found.
            foreach ($newAttendeeParsed as $newAttendeeParsedPart) {
                // If there is only a mailbox part, then it is just a local
                // name.
                if (empty($newAttendeeParsedPart->host)) {
                    $attendees[] = array(
                        'attendance' => self::PART_REQUIRED,
                        'response'   => self::RESPONSE_NONE,
                        'name'       => $newAttendee,
                    );
                    continue;
                }

                // Build a full email address again and validate it.
                $name = empty($newAttendeeParsedPart->personal)
                    ? ''
                    : $newAttendeeParsedPart->personal;

                try {
                    $newAttendeeParsedPartNew = Horde_Mime::encodeAddress(Horde_Mime_Address::writeAddress($newAttendeeParsedPart->mailbox, $newAttendeeParsedPart->host, $name), 'UTF-8');
                    $newAttendeeParsedPartValidated = $parser->parseAddressList($newAttendeeParsedPartNew, array(
                        'default_domain' => null
                    ));

                    $email = $newAttendeeParsedPart->mailbox . '@'
                        . $newAttendeeParsedPart->host;
                    // Avoid overwriting existing attendees with the default
                    // values.
                    $attendees[Horde_String::lower($email)] = array(
                        'attendance' => self::PART_REQUIRED,
                        'response'   => self::RESPONSE_NONE,
                        'name'       => $name);
                } catch (Horde_Mime_Exception $e) {
                    $notification->push($e, 'horde.error');
                }
            }
        }

        return $attendees;
    }

    /**
     * Returns a comma separated list of attendees and resources
     *
     * @return string  Attendee/Resource list.
     */
    static public function attendeeList()
    {
        /* Attendees */
        $attendees = array();
        foreach ($GLOBALS['session']->get('kronolith', 'attendees', Horde_Session::TYPE_ARRAY) as $email => $attendee) {
            $attendees[] = empty($attendee['name']) ? $email : Horde_Mime_Address::trimAddress($attendee['name'] . (strpos($email, '@') === false ? '' : ' <' . $email . '>'));
        }

        /* Resources */
        foreach ($GLOBALS['session']->get('kronolith', 'resources', Horde_Session::TYPE_ARRAY) as $resource) {
            $attendees[] = $resource['name'];
        }

        return implode(', ', $attendees);
    }

    /**
     * Sends out iTip event notifications to all attendees of a specific
     * event.
     *
     * Can be used to send event invitations, event updates as well as event
     * cancellations.
     *
     * @param Kronolith_Event $event
     *        The event in question.
     * @param Horde_Notification_Handler $notification
     *        A notification object used to show result status.
     * @param integer $action
     *        The type of notification to send. One of the Kronolith::ITIP_*
     *        values.
     * @param Horde_Date $instance
     *        If cancelling a single instance of a recurring event, the date of
     *        this intance.
     */
    static public function sendITipNotifications($event, $notification,
                                                 $action, $instance = null)
    {
        global $conf, $registry;

        if (!$event->attendees) {
            return;
        }

        $ident = $GLOBALS['injector']->getInstance('Horde_Core_Factory_Identity')->create($event->creator);
        if (!$ident->getValue('from_addr')) {
            $notification->push(sprintf(_("You do not have an email address configured in your Personal Information Preferences. You must set one %shere%s before event notifications can be sent."), Horde::getServiceLink('prefs', 'kronolith')->add(array('app' => 'horde', 'group' => 'identities'))->link(), '</a>'), 'horde.error', array('content.raw'));
            return;
        }

        // Generate image mime part first and only once, because we
        // need the Content-ID.
        $image = self::getImagePart('big_invitation.png');

        $share = $GLOBALS['kronolith_shares']->getShare($event->calendar);
        $view = new Horde_View(array('templatePath' => KRONOLITH_TEMPLATES . '/itip'));
        new Horde_View_Helper_Text($view);
        $view->identity = $ident;
        $view->event = $event;
        $view->imageId = $image->getContentId();

        foreach ($event->attendees as $email => $status) {
            /* Don't bother sending an invitation/update if the recipient does
             * not need to participate, or has declined participating, or
             * doesn't have an email address. */
            if (strpos($email, '@') === false ||
                $status['attendance'] == self::PART_NONE ||
                $status['response'] == self::RESPONSE_DECLINED) {
                continue;
            }

            /* Determine all notification-specific strings. */
            switch ($action) {
            case self::ITIP_CANCEL:
                /* Cancellation. */
                $method = 'CANCEL';
                $filename = 'event-cancellation.ics';
                $view->subject = sprintf(_("Cancelled: %s"), $event->getTitle());
                if (empty($instance)) {
                    $view->header = sprintf(_("%s has cancelled \"%s\"."), $ident->getName(), $event->getTitle());
                } else {
                    $view->header = sprintf(_("%s has cancelled an instance of the recurring \"%s\"."), $ident->getName(), $event->getTitle());
                }
                break;

            case self::ITIP_REQUEST:
            default:
                $method = 'REQUEST';
                if ($status['response'] == self::RESPONSE_NONE) {
                    /* Invitation. */
                    $filename = 'event-invitation.ics';
                    $view->subject = $event->getTitle();
                    $view->header = sprintf(_("%s wishes to make you aware of \"%s\"."), $ident->getName(), $event->getTitle());
                } else {
                    /* Update. */
                    $filename = 'event-update.ics';
                    $view->subject = sprintf(_("Updated: %s."), $event->getTitle());
                    $view->header = sprintf(_("%s wants to notify you about changes of \"%s\"."), $ident->getName(), $event->getTitle());
                }
                break;
            }

            if ($event->attendees) {
                $attendees = array();
                foreach ($event->attendees as $mail => $attendee) {
                    $attendees[] = empty($attendee['name']) ? $mail : Horde_Mime_Address::trimAddress($attendee['name'] . (strpos($mail, '@') === false ? '' : ' <' . $mail . '>'));
                }
                $view->organizer = $GLOBALS['registry']->convertUserName($event->creator, false);
                $view->attendees = $attendees;
            }

            if ($action == self::ITIP_REQUEST) {
                $attend_link = Horde::url('attend.php', true, -1)
                    ->add(array('c' => $event->calendar,
                                'e' => $event->id,
                                'u' => $email));
                $view->linkAccept    = (string)$attend_link->add('a', 'accept');
                $view->linkTentative = (string)$attend_link->add('a', 'tentative');
                $view->linkDecline   = (string)$attend_link->add('a', 'decline');
            }

            /* Build the iCalendar data */
            $iCal = new Horde_Icalendar();
            $iCal->setAttribute('METHOD', $method);
            $iCal->setAttribute('X-WR-CALNAME', $share->get('name'));
            $vevent = $event->toiCalendar($iCal);
            if ($action == self::ITIP_CANCEL && !empty($instance)) {
                // Recurring event instance deletion, need to specify the
                // RECURRENCE-ID but NOT the EXDATE.
                $vevent = array_pop($vevent);
                $vevent->setAttribute('RECURRENCE-ID', $instance, array('VALUE' => 'DATE'));
                $vevent->removeAttribute('EXDATE');
            }
            $iCal->addComponent($vevent);

            /* text/calendar part */
            $ics = new Horde_Mime_Part();
            $ics->setType('text/calendar');
            $ics->setContents($iCal->exportvCalendar());
            $ics->setName($filename);
            $ics->setContentTypeParameter('METHOD', $method);
            $ics->setCharset('UTF-8');
            $ics->setEOL("\r\n");

            $multipart = self::buildMimeMessage($view, 'notification', $image);
            $multipart->addPart($ics);
            $recipient = empty($status['name']) ? $email : Horde_Mime_Address::trimAddress($status['name'] . ' <' . $email . '>');
            $mail = new Horde_Mime_Mail(
                array('Subject' => $view->subject,
                      'To' => $recipient,
                      'From' => $ident->getDefaultFromAddress(true),
                      'User-Agent' => 'Kronolith ' . $GLOBALS['registry']->getVersion()));
            $mail->setBasePart($multipart);

            try {
                $mail->send($GLOBALS['injector']->getInstance('Horde_Mail'));
                $notification->push(
                    sprintf(_("The event notification to %s was successfully sent."), $recipient),
                    'horde.success'
                );
            } catch (Horde_Mime_Exception $e) {
                $notification->push(
                    sprintf(_("There was an error sending an event notification to %s: %s"), $recipient, $e->getMessage(), $e->getCode()),
                    'horde.error'
                );
            }
        }
    }

    /**
     * Sends email notifications that a event has been added, edited, or
     * deleted to users that want such notifications.
     *
     * @param Kronolith_Event $event  An event.
     * @param string $action          The event action. One of "add", "edit",
     *                                or "delete".
     *
     * @throws Horde_Mime_Exception
     * @throws Kronolith_Exception
     */
    static public function sendNotification($event, $action)
    {
        global $conf;

        if (!in_array($action, array('add', 'edit', 'delete'))) {
            throw new Kronolith_Exception('Unknown event action: ' . $action);
        }

        $groups = $GLOBALS['injector']->getInstance('Horde_Group');
        $calendar = $event->calendar;
        $recipients = array();
        try {
            $share = $GLOBALS['kronolith_shares']->getShare($calendar);
        } catch (Horde_Share_Exception $e) {
            throw new Kronolith_Exception($e);
        }

        $identity = $GLOBALS['injector']->getInstance('Horde_Core_Factory_Identity')->create();

        $owner = $share->get('owner');
        if ($owner) {
            $recipients[$owner] = self::_notificationPref($owner, 'owner');
        }

        foreach ($share->listUsers(Horde_Perms::READ) as $user) {
            if (!isset($recipients[$user])) {
                $recipients[$user] = self::_notificationPref($user, 'read', $calendar);
            }
        }

        foreach ($share->listGroups(Horde_Perms::READ) as $group) {
            try {
                $group_users = $groups->listUsers($group);
            } catch (Horde_Group_Exception $e) {
                Horde::logMessage($e, 'ERR');
                continue;
            }

            foreach ($group_users as $user) {
                if (!isset($recipients[$user])) {
                    $recipients[$user] = self::_notificationPref($user, 'read', $calendar);
                }
            }
        }

        $addresses = array();
        foreach ($recipients as $user => $vals) {
            if (!$vals) {
                continue;
            }
            $identity = $GLOBALS['injector']->getInstance('Horde_Core_Factory_Identity')->create($user);
            $email = $identity->getValue('from_addr');
            if (strpos($email, '@') === false) {
                continue;
            }
            list($mailbox, $host) = explode('@', $email);
            if (!isset($addresses[$vals['lang']][$vals['tf']][$vals['df']])) {
                $addresses[$vals['lang']][$vals['tf']][$vals['df']] = array();
            }
            $addresses[$vals['lang']][$vals['tf']][$vals['df']][] = Horde_Mime_Address::writeAddress($mailbox, $host, $identity->getValue('fullname'));
        }

        if (!$addresses) {
            return;
        }

        foreach ($addresses as $lang => $twentyFour) {
            $GLOBALS['registry']->setLanguageEnvironment($lang);

            switch ($action) {
            case 'add':
                $subject = _("Event added:");
                $notification_message = _("You requested to be notified when events are added to your calendars.") . "\n\n" . _("The event \"%s\" has been added to \"%s\" calendar, which is on %s at %s.");
                break;

            case 'edit':
                $subject = _("Event edited:");
                $notification_message = _("You requested to be notified when events are edited in your calendars.") . "\n\n" . _("The event \"%s\" has been edited on \"%s\" calendar, which is on %s at %s.");
                break;

            case 'delete':
                $subject = _("Event deleted:");
                $notification_message = _("You requested to be notified when events are deleted from your calendars.") . "\n\n" . _("The event \"%s\" has been deleted from \"%s\" calendar, which was on %s at %s.");
                break;
            }

            foreach ($twentyFour as $tf => $dateFormat) {
                foreach ($dateFormat as $df => $df_recipients) {
                    $message = "\n"
                        . sprintf($notification_message,
                                  $event->title,
                                  $share->get('name'),
                                  $event->start->strftime($df),
                                  $event->start->strftime($tf ? '%R' : '%I:%M%p'))
                        . "\n\n" . $event->description;

                    $mime_mail = new Horde_Mime_Mail(array(
                        'Subject' => $subject . ' ' . $event->title,
                        'To' => implode(',', $df_recipients),
                        'From' => $identity->getDefaultFromAddress(true),
                        'User-Agent' => 'Kronolith ' . $GLOBALS['registry']->getVersion(),
                        'body' => $message));
                    Horde::logMessage(sprintf('Sending event notifications for %s to %s', $event->title, implode(', ', $df_recipients)), 'DEBUG');
                    $mime_mail->send($GLOBALS['injector']->getInstance('Horde_Mail'));
                }
            }
        }
    }

    /**
     * Check for resource declines and push notice to stack if found.
     *
     * @param Kronolith_Event $event
     *
     * @throws Kronolith_Exception
     */
    static public function notifyOfResourceRejection($event)
    {
        $declined = array();
        $accepted = array();
        foreach ($event->getResources() as $id => $resource) {
            if ($resource['response'] == self::RESPONSE_DECLINED) {
                $r = self::getDriver('Resource')->getResource($id);
                $declined[] = $r->get('name');
            } elseif ($resource['response'] == self::RESPONSE_ACCEPTED) {
                $r = self::getDriver('Resource')->getResource($id);
                $accepted[] = $r->get('name');
            }


        }
        if (count($declined)) {
            $GLOBALS['notification']->push(sprintf(ngettext("The following resource has declined your request: %s",
                                                            "The following resources have declined your request: %s",
                                                            count($declined)),
                                                    implode(", ", $declined)),
                                           'horde.error');
        }
        if (count($accepted)) {
             $GLOBALS['notification']->push(sprintf(ngettext("The following resource has accepted your request: %s",
                                                            "The following resources have accepted your request: %s",
                                                            count($accepted)),
                                                    implode(", ", $accepted)),
                                           'horde.success');
        }
    }

    /**
     * Returns whether a user wants email notifications for a calendar.
     *
     * @access private
     *
     * @todo This method is causing a memory leak somewhere, noticeable if
     *       importing a large amount of events.
     *
     * @param string $user      A user name.
     * @param string $mode      The check "mode". If "owner", the method checks
     *                          if the user wants notifications only for
     *                          calendars he owns. If "read", the method checks
     *                          if the user wants notifications for all
     *                          calendars he has read access to, or only for
     *                          shown calendars and the specified calendar is
     *                          currently shown.
     * @param string $calendar  The name of the calendar if mode is "read".
     *
     * @return mixed  The user's email, time, and language preferences if they
     *                want a notification for this calendar.
     */
    static public function _notificationPref($user, $mode, $calendar = null)
    {
        $prefs = $GLOBALS['injector']->getInstance('Horde_Core_Factory_Prefs')->create('kronolith', array(
            'cache' => false,
            'user' => $user
        ));
        $vals = array('lang' => $prefs->getValue('language'),
                      'tf' => $prefs->getValue('twentyFour'),
                      'df' => $prefs->getValue('date_format'));

        if ($prefs->getValue('event_notification_exclude_self') &&
            $user == $GLOBALS['registry']->getAuth()) {
            return false;
        }

        switch ($prefs->getValue('event_notification')) {
        case 'owner':
            return $mode == 'owner' ? $vals : false;

        case 'read':
            return $mode == 'read' ? $vals : false;

        case 'show':
            if ($mode == 'read') {
                $display_calendars = unserialize($prefs->getValue('display_cals'));
                return in_array($calendar, $display_calendars) ? $vals : false;
            }
        }

        return false;
    }

    /**
     * Builds the body MIME part of a multipart message.
     *
     * @param Horde_View $view        A view to render the HTML and plain text
     *                                templates for the messate.
     * @param string $template        The template base name for the view.
     * @param Horde_Mime_Part $image  The MIME part of a related image.
     *
     * @return Horde_Mime_Part  A multipart/alternative MIME part.
     */
    static public function buildMimeMessage(Horde_View $view, $template,
                                            Horde_Mime_Part $image)
    {
        $multipart = new Horde_Mime_Part();
        $multipart->setType('multipart/alternative');
        $bodyText = new Horde_Mime_Part();
        $bodyText->setType('text/plain');
        $bodyText->setCharset('UTF-8');
        $bodyText->setContents($view->render($template . '.plain.php'));
        $bodyText->setDisposition('inline');
        $multipart->addPart($bodyText);
        $bodyHtml = new Horde_Mime_Part();
        $bodyHtml->setType('text/html');
        $bodyHtml->setCharset('UTF-8');
        $bodyHtml->setContents($view->render($template . '.html.php'));
        $bodyHtml->setDisposition('inline');
        $related = new Horde_Mime_Part();
        $related->setType('multipart/related');
        $related->setContentTypeParameter('start', $bodyHtml->setContentId());
        $related->addPart($bodyHtml);
        $related->addPart($image);
        $multipart->addPart($related);
        return $multipart;
    }

    /**
     * Returns a MIME part for an image to be embedded into a HTML document.
     *
     * @param string $file  An image file name.
     *
     * @return Horde_Mime_Part  A MIME part representing the image.
     */
    static public function getImagePart($file)
    {
        $background = Horde_Themes::img($file);
        $image = new Horde_Mime_Part();
        $image->setType('image/png');
        $image->setContents(file_get_contents($background->fs));
        $image->setContentId();
        $image->setDisposition('attachment');
        return $image;
    }

    /**
     * @return Horde_Date
     */
    static public function currentDate()
    {
        if ($date = Horde_Util::getFormData('date')) {
            return new Horde_Date($date . '000000');
        }
        if ($date = Horde_Util::getFormData('datetime')) {
            return new Horde_Date($date);
        }

        return new Horde_Date($_SERVER['REQUEST_TIME']);
    }

    /**
     * Parses a complete date-time string into a Horde_Date object.
     *
     * @param string $date       The date-time string to parse.
     * @param boolean $withtime  Whether time is included in the string.
     *
     * @return Horde_Date  The parsed date.
     * @throws Horde_Date_Exception
     */
    static public function parseDate($date, $withtime = true)
    {
        // strptime() is not available on Windows.
        if (!function_exists('strptime')) {
            return new Horde_Date($date);
        }

        // strptime() is locale dependent, i.e. %p is not always matching
        // AM/PM. Set the locale to C to workaround this, but grab the
        // locale's D_FMT before that.
        $format = Horde_Nls::getLangInfo(D_FMT);
        if ($withtime) {
            $format .= ' '
                . ($GLOBALS['prefs']->getValue('twentyFour') ? '%H:%M' : '%I:%M %p');
        }
        $old_locale = setlocale(LC_TIME, 0);
        setlocale(LC_TIME, 'C');

        // Try exact format match first.
        $date_arr = strptime($date, $format);
        setlocale(LC_TIME, $old_locale);

        if (!$date_arr) {
            // Try with locale dependent parsing next.
            $date_arr = strptime($date, $format);
            if (!$date_arr) {
                // Try throwing at Horde_Date finally.
                return new Horde_Date($date);
            }
        }

        return new Horde_Date(
            array('year'  => $date_arr['tm_year'] + 1900,
                  'month' => $date_arr['tm_mon'] + 1,
                  'mday'  => $date_arr['tm_mday'],
                  'hour'  => $date_arr['tm_hour'],
                  'min'   => $date_arr['tm_min'],
                  'sec'   => $date_arr['tm_sec']));
    }

    /**
     * @param string $tabname
     */
    static public function tabs($tabname = null)
    {
        $date = self::currentDate();
        $date_stamp = $date->dateString();

        $tabs = new Horde_Core_Ui_Tabs('view', Horde_Variables::getDefaultVariables());
        $tabs->preserve('date', $date_stamp);

        $tabs->addTab(_("Day"), Horde::url('day.php'),
                      array('tabname' => 'day', 'id' => 'tabday', 'onclick' => 'return ShowView(\'Day\', \'' . $date_stamp . '\');'));
        $tabs->addTab(_("Work Week"), Horde::url('workweek.php'),
                      array('tabname' => 'workweek', 'id' => 'tabworkweek', 'onclick' => 'return ShowView(\'WorkWeek\', \'' . $date_stamp . '\');'));
        $tabs->addTab(_("Week"), Horde::url('week.php'),
                      array('tabname' => 'week', 'id' => 'tabweek', 'onclick' => 'return ShowView(\'Week\', \'' . $date_stamp . '\');'));
        $tabs->addTab(_("Month"), Horde::url('month.php'),
                      array('tabname' => 'month', 'id' => 'tabmonth', 'onclick' => 'return ShowView(\'Month\', \'' . $date_stamp . '\');'));
        $tabs->addTab(_("Year"), Horde::url('year.php'),
                      array('tabname' => 'year', 'id' => 'tabyear', 'onclick' => 'return ShowView(\'Year\', \'' . $date_stamp . '\');'));

        if ($tabname === null) {
            $tabname = basename($_SERVER['PHP_SELF']) == 'index.php' ? $GLOBALS['prefs']->getValue('defaultview') : str_replace('.php', '', basename($_SERVER['PHP_SELF']));
        }
        echo $tabs->render($tabname);
    }

    /**
     * @param string $tabname
     * @param Kronolith_Event $event
     */
    static public function eventTabs($tabname, $event)
    {
        if (!$event->initialized) {
            return;
        }

        $tabs = new Horde_Core_Ui_Tabs('event', Horde_Variables::getDefaultVariables());

        $date = self::currentDate();
        $tabs->preserve('datetime', $date->dateString());

        $tabs->addTab(
            htmlspecialchars($event->getTitle()),
            $event->getViewUrl(),
            array('tabname' => 'Event',
                  'id' => 'tabEvent',
                  'onclick' => 'return ShowTab(\'Event\');'));
        /* We check for read permissions, because we can always save a copy if
         * we can read the event. */
        if ((!$event->private ||
             $event->creator == $GLOBALS['registry']->getAuth()) &&
            $event->hasPermission(Horde_Perms::READ) &&
            self::getDefaultCalendar(Horde_Perms::EDIT)) {
            $tabs->addTab(
                $event->hasPermission(Horde_Perms::EDIT) ? _("_Edit") : _("Save As New"),
                $event->getEditUrl(),
                array('tabname' => 'EditEvent',
                      'id' => 'tabEditEvent',
                      'onclick' => 'return ShowTab(\'EditEvent\');'));
        }
        if ($event->hasPermission(Horde_Perms::DELETE)) {
            $tabs->addTab(
                _("De_lete"),
                $event->getDeleteUrl(array('confirm' => 1)),
                array('tabname' => 'DeleteEvent',
                      'id' => 'tabDeleteEvent',
                      'onclick' => 'return ShowTab(\'DeleteEvent\');'));
        }
        $tabs->addTab(
            _("Export"),
            $event->getExportUrl(),
            array('tabname' => 'ExportEvent',
                  'id' => 'tabExportEvent'));

        echo $tabs->render($tabname);
    }

    /**
     * Attempts to return a single, concrete Kronolith_Driver instance based
     * on a driver name.
     *
     * This singleton method automatically retrieves all parameters required
     * for the specified driver.
     *
     * @param string $driver    The type of concrete Kronolith_Driver subclass
     *                          to return.
     * @param string $calendar  The calendar name. The format depends on the
     *                          driver being used.
     *
     * @return Kronolith_Driver  The newly created concrete Kronolith_Driver
     *                           instance.
     * @throws Kronolith_Exception
     */
    static public function getDriver($driver = null, $calendar = null)
    {
        switch ($driver) {
        case 'internal':
            $driver = '';
            break;
        case 'external':
        case 'tasklists':
            $driver = 'Horde';
            break;
        case 'remote':
            $driver = 'Ical';
            break;
        case 'holiday':
            $driver = 'Holidays';
            break;
        case 'resource':
            $driver = 'Resource';
            break;
        }

        if (empty($driver)) {
            $driver = Horde_String::ucfirst($GLOBALS['conf']['calendar']['driver']);
        }

        if (!isset(self::$_instances[$driver])) {
            switch ($driver) {
            case 'Sql':
            case 'Resource':
                $params = Horde::getDriverConfig('calendar', 'sql');
                if ($params['driverconfig'] != 'Horde') {
                    $customParams = $params;
                    unset($customParams['driverconfig'], $customParams['table'], $customParams['utc']);
                    $params['db'] = $GLOBALS['injector']->getInstance('Horde_Core_Factory_Db')->create('kronolith', $customParams);
               } else {
                    $params['db'] = $GLOBALS['injector']->getInstance('Horde_Db_Adapter');
                }
                break;

            case 'Kolab':
                $params['storage'] = $GLOBALS['injector']->getInstance('Horde_Kolab_Storage');
                break;

            case 'Ical':
            case 'Mock':
                $params = array();
                break;

            case 'Horde':
                $params['registry'] = $GLOBALS['registry'];
                break;

            case 'Holidays':
                if (empty($GLOBALS['conf']['holidays']['enable'])) {
                    throw new Kronolith_Exception(_("Holidays are disabled"));
                }
                $params['language'] = $GLOBALS['language'];
                break;

            default:
                throw new Kronolith_Exception('No calendar driver specified');
                break;
            }

            self::$_instances[$driver] = $GLOBALS['injector']->getInstance('Kronolith_Factory_Driver')->create($driver, $params);
        }

        if (!is_null($calendar)) {
            self::$_instances[$driver]->open($calendar);
            /* Remote calendar parameters are per calendar. */
            if ($driver == 'Ical') {
                self::$_instances[$driver]->setParams(self::getRemoteParams($calendar));
            }
        }

        return self::$_instances[$driver];
    }

    /**
     * Check for HTTP authentication credentials
     */
    static public function getRemoteParams($calendar)
    {
        if (empty($calendar)) {
            return array();
        }

        $cals = unserialize($GLOBALS['prefs']->getValue('remote_cals'));
        foreach ($cals as $cal) {
            if ($cal['url'] == $calendar) {
                $user = isset($cal['user']) ? $cal['user'] : '';
                $password = isset($cal['password']) ? $cal['password'] : '';
                $key = $GLOBALS['registry']->getAuthCredential('password');
                if ($key && $password) {
                    $secret = $GLOBALS['injector']->getInstance('Horde_Secret');
                    $user = $secret->read($key, base64_decode($user));
                    $password = $secret->read($key, base64_decode($password));
                }
                if (!empty($user)) {
                    return array('user' => $user, 'password' => $password);
                }
                return array();
            }
        }

        return array();
    }

    /**
     * Get a named Kronolith_View_* object and load it with the
     * appropriate date parameters.
     *
     * @param string $view The name of the view.
     */
    static public function getView($view)
    {
        switch ($view) {
        case 'Day':
        case 'Month':
        case 'Week':
        case 'WorkWeek':
        case 'Year':
            $class = 'Kronolith_View_' . $view;
            return new $class(self::currentDate());

        case 'Event':
        case 'EditEvent':
        case 'DeleteEvent':
        case 'ExportEvent':
            try {
                if ($uid = Horde_Util::getFormData('uid')) {
                    $event = self::getDriver()->getByUID($uid);
                } else {
                    $event = self::getDriver(Horde_Util::getFormData('type'),
                                             Horde_Util::getFormData('calendar'))
                        ->getEvent(Horde_Util::getFormData('eventID'),
                                   Horde_Util::getFormData('datetime'));
                }
            } catch (Horde_Exception $e) {
                $event = $e->getMessage();
            }
            switch ($view) {
            case 'Event':
                if (!is_string($event) &&
                    !$event->hasPermission(Horde_Perms::READ)) {
                    $event = _("Permission Denied");
                }
                return new Kronolith_View_Event($event);
            case 'EditEvent':
                /* We check for read permissions, because we can always save a
                 * copy if we can read the event. */
                if (!is_string($event) &&
                    !$event->hasPermission(Horde_Perms::READ)) {
                    $event = _("Permission Denied");
                }
                return new Kronolith_View_EditEvent($event);
            case 'DeleteEvent':
                if (!is_string($event) &&
                    !$event->hasPermission(Horde_Perms::DELETE)) {
                    $event = _("Permission Denied");
                }
                return new Kronolith_View_DeleteEvent($event);
            case 'ExportEvent':
                if (!is_string($event) &&
                    !$event->hasPermission(Horde_Perms::READ)) {
                    $event = _("Permission Denied");
                }
                return new Kronolith_View_ExportEvent($event);
            }
        }
    }

    /**
     * Should we show event location, based on the show_location pref?
     */
    static public function viewShowLocation()
    {
        $show = @unserialize($GLOBALS['prefs']->getValue('show_location'));
        return @in_array('screen', $show);
    }

    /**
     * Should we show event time, based on the show_time preference?
     */
    static public function viewShowTime()
    {
        $show = @unserialize($GLOBALS['prefs']->getValue('show_time'));
        return @in_array('screen', $show);
    }

    /**
     * Returns the background color for a calendar.
     *
     * @param array|Horde_Share_Object $calendar  A calendar share or a hash
     *                                            from a remote calender
     *                                            definition.
     *
     * @return string  A HTML color code.
     */
    static public function backgroundColor($calendar)
    {
        $color = '';
        if (!is_array($calendar)) {
            $color = $calendar->get('color');
        } elseif (isset($calendar['color'])) {
            $color = $calendar['color'];
        }
        return empty($color) ? '#dddddd' : $color;
    }

    /**
     * Returns the foreground color for a calendar or a background color.
     *
     * @param array|Horde_Share_Object|string $calendar  A color string, a
     *                                                   calendar share or a
     *                                                   hash from a remote
     *                                                   calender definition.
     *
     * @return string  A HTML color code.
     */
    static public function foregroundColor($calendar)
    {
        return Horde_Image::brightness(is_string($calendar) ? $calendar : self::backgroundColor($calendar)) < 128 ? '#fff' : '#000';
    }

    /**
     * Returns the CSS color definition for a calendar.
     *
     * @param array|Horde_Share_Object $calendar  A calendar share or a hash
     *                                            from a remote calender
     *                                            definition.
     * @param boolean $with_attribute             Whether to wrap the colors
     *                                            inside a "style" attribute.
     *
     * @return string  A CSS string with color definitions.
     */
    static public function getCSSColors($calendar, $with_attribute = true)
    {
        $css = 'background-color:' . self::backgroundColor($calendar) . ';color:' . self::foregroundColor($calendar);
        if ($with_attribute) {
            $css = ' style="' . $css . '"';
        }
        return $css;
    }

    /**
     * Returns whether to display the ajax view.
     *
     * return boolean  True if the ajax view should be displayed.
     */
    static public function showAjaxView()
    {
        global $prefs, $session;

        $mode = $session->get('horde', 'mode');
        return ($mode == 'dynamic' || ($prefs->getValue('dynamic_view') && $mode == 'auto')) && Horde::ajaxAvailable();
    }

    /**
     * Sorts an event list.
     *
     * @since Kronolith 3.0.5
     *
     * @param array $days  A list of days with events.
     *
     * @return array  The sorted day list.
     */
    static public function sortEvents($days)
    {
        foreach ($days as $day => $devents) {
            if (count($devents)) {
                uasort($devents, array('Kronolith', '_sortEventStartTime'));
                $days[$day] = $devents;
            }
        }
        return $days;
    }

    /**
     * Used with usort() to sort events based on their start times.
     */
    static protected function _sortEventStartTime($a, $b)
    {
        $diff = $a->start->compareDateTime($b->start);
        if ($diff == 0) {
            return strcoll($a->title, $b->title);
        } else {
            return $diff;
        }
    }

    /**
     * Obtain a Kronolith_Tagger instance
     *
     * @return Kronolith_Tagger
     */
    static public function getTagger()
    {
        if (empty(self::$_tagger)) {
            self::$_tagger = new Kronolith_Tagger();
        }
        return self::$_tagger;
    }

    /**
     * Obtain an internal calendar. Use this where we don't know if we will
     * have a Horde_Share or a Kronolith_Resource based calendar.
     *
     * @param string $target  The calendar id to retrieve.
     *
     * @return Kronolith_Resource|Horde_Share_Object
     * @throws Kronolith_Exception
     */
    static public function getInternalCalendar($target)
    {
        if (Kronolith_Resource::isResourceCalendar($target)) {
            $driver = self::getDriver('Resource');
            $id = $driver->getResourceIdByCalendar($target);
            return $driver->getResource($id);
        } else {
            return $GLOBALS['kronolith_shares']->getShare($target);
        }
    }

    /**
     * Determines parameters needed to do an address search
     *
     * @return array  An array with two keys: 'fields' and 'sources'.
     */
    static public function getAddressbookSearchParams()
    {
        $src = json_decode($GLOBALS['prefs']->getValue('search_sources'));
        if (empty($src)) {
            $src = array();
        }

        $fields = json_decode($GLOBALS['prefs']->getValue('search_fields'), true);
        if (empty($fields)) {
            $fields = array();
        }

        return array(
            'fields' => $fields,
            'sources' => $src
        );
    }

    /**
     * Checks whether an API (application) exists and the user has permission
     * to access it.
     *
     * @param string $api    The API (application) to check.
     * @param integer $perm  The permission to check.
     *
     * @return boolean  True if the API can be accessed.
     */
    static public function hasApiPermission($api, $perm = Horde_Perms::READ)
    {
        $app = $GLOBALS['registry']->hasInterface($api);
        return ($app && $GLOBALS['registry']->hasPermission($app, $perm));
    }

}
