<?php
/**
 * Trean interface to the Horde_Content tagger
 *
 * Copyright 2009-2011 The Horde Project (http://www.horde.org)
 *
 * @author Michael J. Rubinsky <mrubinsk@horde.org>
 *
 * @package Trean
 */
class Trean_Tagger
{
    /**
     * Local cache of the type name => ids from Content, so we don't have to
     * query for them each time.
     *
     * @var array
     */
    protected $_type_ids = array();

    /**
     * Constructor.
     *
     * @return Trean_Tagger
     */
    public function __construct()
    {
        // Remember the types to avoid having Content query them again.
        $key = 'trean.tagger.type_ids';
        $ids = $GLOBALS['injector']->getInstance('Horde_Cache')->get($key, 360);
        if ($ids) {
            $this->_type_ids = unserialize($ids);
        } else {
            $type_mgr = $GLOBALS['injector']->getInstance('Content_Types_Manager');
            $types = $type_mgr->ensureTypes(array('calendar', 'event'));
            $this->_type_ids = array('calendar' => (int)$types[0],
                                     'event' => (int)$types[1]);
            $GLOBALS['injector']->getInstance('Horde_Cache')->set($key, serialize($this->_type_ids));
        }
    }

    /**
     * Tags a trean object with any number of tags.
     *
     * @param string $localId       The identifier of the trean object.
     * @param mixed $tags           Either a single tag string or an array of
     *                              tags.
     * @param string $owner         The tag owner (should normally be the owner
     *                              of the resource).
     * @param string $content_type  The type of object we are tagging
     *                              (event/calendar).
     *
     * @return void
     */
    public function tag($localId, $tags, $owner, $content_type = 'event')
    {
        // If we don't have an array - split the string.
        if (!is_array($tags)) {
            $tags = $GLOBALS['injector']->getInstance('Content_Tagger')->splitTags($tags);
        }

        $GLOBALS['injector']->getInstance('Content_Tagger')->tag(
                $owner,
                array('object' => $localId,
                      'type' => $this->_type_ids[$content_type]),
                $tags);
    }

    /**
     * Retrieves the tags on given object(s).
     *
     * @param mixed  $localId  Either the identifier of the trean object or
     *                         an array of identifiers.
     * @param string $type     The type of object $localId represents.
     *
     * @return array A tag_id => tag_name hash, possibly wrapped in a localid hash.
     */
    public function getTags($localId, $type = 'event')
    {
        if (is_array($localId)) {
            return $GLOBALS['injector']->getInstance('Content_Tagger')->getTagsByObjects($localId, $type);
        }

        return $GLOBALS['injector']->getInstance('Content_Tagger')->getTags(array('objectId' => array('object' => $localId, 'type' => $this->_type_ids[$type])));
    }

    /**
     * Removes a tag from a trean object.
     *
     * Removes *all* tags - regardless of the user that added the tag.
     *
     * @param string $localId       The trean object identifier.
     * @param mixed $tags           Either a tag_id, tag_name or an array of
     *                              ids or names to remove.
     * @param string $content_type  The type of object that $localId represents.
     */
    public function untag($localId, $tags, $content_type = 'event')
    {
        $GLOBALS['injector']->getInstance('Content_Tagger')->removeTagFromObject(
            array('object' => $localId, 'type' => $this->_type_ids[$content_type]), $tags);
    }

    /**
     * Tags the given resource with *only* the tags provided, removing any
     * tags that are already present but not in the list.
     *
     * @param string $localId  The identifier for the trean object.
     * @param mixed $tags      Either a tag_id, tag_name, or array of tag_ids.
     * @param string $owner    The tag owner - should normally be the resource
     *                         owner.
     * @param $content_type    The type of object that $localId represents.
     */
    public function replaceTags($localId, $tags, $owner, $content_type = 'event')
    {
        // First get a list of existing tags.
        $existing_tags = $this->getTags($localId, $content_type);

        // If we don't have an array - split the string.
        if (!is_array($tags)) {
            $tags = $GLOBALS['injector']->getInstance('Content_Tagger')->splitTags($tags);
        }
        $remove = array();
        foreach ($existing_tags as $tag_id => $existing_tag) {
            $found = false;
            foreach ($tags as $tag_text) {
                if ($existing_tag == $tag_text) {
                    $found = true;
                    break;
                }
            }
            // Remove any tags that were not found in the passed in list.
            if (!$found) {
                $remove[] = $tag_id;
            }
        }

        $this->untag($localId, $remove, $content_type);
        $add = array();
        foreach ($tags as $tag_text) {
            $found = false;
            foreach ($existing_tags as $existing_tag) {
                if ($tag_text == $existing_tag) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $add[] = $tag_text;
            }
        }

        $this->tag($localId, $add, $owner, $content_type);
    }

    /**
     * Searches for resources that are tagged with all of the requested tags.
     *
     * @param array $tags    Either a tag_id, tag_name or an array.
     * @param array $filter  Array of filter parameters.
     *                       - user (array) - only include objects owned by
     *                         these users.
     *
     * @return  A hash of 'calendars' and 'events' that each contain an array
     *          of calendar_ids and event_uids respectively.
     */
    public function search($tags, $filter = array())
    {
        $args = array();

        /* Add the tags to the search */
        $args['tagId'] = $GLOBALS['injector']->getInstance('Content_Tagger')->getTagIds($tags);

        $args['typeId'] = $this->_type_ids['bookmarks'];
        $results = $GLOBALS['injector']->getInstance('Content_Tagger')->getObjects($args);

        $results = array('bookmarks' => array_values($results));
        return $results;
    }

    /**
     * Returns tags belonging to the current user beginning with $token.
     *
     * Used for autocomplete code.
     *
     * @param string $token  The token to match the start of the tag with.
     *
     * @return A tag_id => tag_name hash
     */
    public function listTags($token)
    {
        return $GLOBALS['injector']->getInstance('Content_Tagger')->getTags(
                array('q' => $token, 'userId' => $GLOBALS['registry']->getAuth()));
    }

    /**
     * Returns the data needed to build a tag cloud based on the passed in
     * user's tag data set.
     *
     * @param string $user    The user whose tags should be included.
     * @param integer $limit  The maximum number of tags to include.
     *
     * @return An array of hashes, each containing tag_id, tag_name, and count.
     */
    public function getCloud($user, $limit = 5)
    {
        return $GLOBALS['injector']->getInstance('Content_Tagger')->getTagCloud(
                array('userId' => $user, 'limit' => $limit));
    }
}