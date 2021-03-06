<?php
/* vim:set softtabstop=4 shiftwidth=4 expandtab: */
/**
 *
 * LICENSE: GNU Affero General Public License, version 3 (AGPLv3)
 * Copyright 2001 - 2017 Ampache.org
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
 *
 */

/* Because this is accessed via Ajax we are going to allow the session_id
 * as part of the get request
 */

// Set that this is an ajax include
define('AJAX_INCLUDE', '1');
require_once '../lib/init.php';

xoutput_headers();

$page = isset($_REQUEST['page']) ? $_REQUEST['page'] : null;

debug_event('ajax.server.php', 'Called for page: {' . $page . '}', '5');

switch ($page) {
    case 'stats':
        require_once AmpConfig::get('prefix') . '/server/stats.ajax.php';
        exit;
    case 'browse':
        require_once AmpConfig::get('prefix') . '/server/browse.ajax.php';
        exit;
    case 'random':
        require_once AmpConfig::get('prefix') . '/server/random.ajax.php';
        exit;
    case 'playlist':
        require_once AmpConfig::get('prefix') . '/server/playlist.ajax.php';
        exit;
    case 'localplay':
        require_once AmpConfig::get('prefix') . '/server/localplay.ajax.php';
        exit;
    case 'tag':
        require_once AmpConfig::get('prefix') . '/server/tag.ajax.php';
        exit;
    case 'stream':
        require_once AmpConfig::get('prefix') . '/server/stream.ajax.php';
        exit;
    case 'song':
        require_once AmpConfig::get('prefix') . '/server/song.ajax.php';
        exit;
    case 'democratic':
        require_once AmpConfig::get('prefix') . '/server/democratic.ajax.php';
        exit;
    case 'index':
        require_once AmpConfig::get('prefix') . '/server/index.ajax.php';
        exit;
    case 'catalog':
        require_once AmpConfig::get('prefix') . '/server/catalog.ajax.php';
        exit;
    case 'search':
        require_once AmpConfig::get('prefix') . '/server/search.ajax.php';
        exit;
    case 'player':
        require_once AmpConfig::get('prefix') . '/server/player.ajax.php';
        exit;
    case 'user':
        require_once AmpConfig::get('prefix') . '/server/user.ajax.php';
        exit;
    case 'podcast':
        require_once AmpConfig::get('prefix') . '/server/podcast.ajax.php';
        exit;
    default:
        // A taste of compatibility
    break;
} // end switch on page

switch ($_REQUEST['action']) {
    case 'refresh_rightbar':
        $results['rightbar'] = UI::ajax_include('rightbar.inc.php');
    break;
    case 'current_playlist':
        switch ($_REQUEST['type']) {
            case 'delete':
                $GLOBALS['user']->playlist->delete_track($_REQUEST['id']);
            break;
        } // end switch

        $results['rightbar'] = UI::ajax_include('rightbar.inc.php');
    break;
    // Handle the users basketcases...
    case 'basket':
        $object_type = $_REQUEST['type'] ?: $_REQUEST['object_type'];
        $object_id   = $_REQUEST['id'] ?: $_REQUEST['object_id'];

        if (Core::is_playable_item($object_type)) {
            if (!is_array($object_id)) {
                $object_id = array($object_id);
            }
            foreach ($object_id as $id) {
                $item   = new $object_type($id);
                $medias = $item->get_medias();
                $GLOBALS['user']->playlist->add_medias($medias);
            }
        } else {
            switch ($_REQUEST['type']) {
                case 'browse_set':
                    $browse  = new Browse($_REQUEST['browse_id']);
                    $objects = $browse->get_saved();
                    foreach ($objects as $object_id) {
                        $GLOBALS['user']->playlist->add_object($object_id, 'song');
                    }
                break;
                case 'album_random':
                    $data = explode('_', $_REQUEST['type']);
                    $type = $data['0'];
                    foreach ($_REQUEST['id'] as $i) {
                        $object = new $type($i);
                        $songs  = $object->get_random_songs();
                        foreach ($songs as $song_id) {
                            $GLOBALS['user']->playlist->add_object($song_id, 'song');
                        }
                    }
                break;
                case 'artist_random':
                case 'tag_random':
                    $data   = explode('_', $_REQUEST['type']);
                    $type   = $data['0'];
                    $object = new $type($_REQUEST['id']);
                    $songs  = $object->get_random_songs();
                    foreach ($songs as $song_id) {
                        $GLOBALS['user']->playlist->add_object($song_id, 'song');
                    }
                break;
                case 'playlist_random':
                    $playlist = new Playlist($_REQUEST['id']);
                    $items    = $playlist->get_random_items();
                    foreach ($items as $item) {
                        $GLOBALS['user']->playlist->add_object($item['object_id'], $item['object_type']);
                    }
                break;
                case 'clear_all':
                    $GLOBALS['user']->playlist->clear();
                break;
            }
        }

        $results['rightbar'] = UI::ajax_include('rightbar.inc.php');
    break;
    /* Setting ratings */
    case 'set_rating':
        if (User::is_registered()) {
            ob_start();
            $rating = new Rating($_GET['object_id'], $_GET['rating_type']);
            $rating->set_rating($_GET['rating']);
            // If song is rated, write it down to ID3
            if (AmpConfig::get('write_id3')) {
                if ($_GET['rating_type'] === 'song') {
                    $song = new Song($_GET['object_id']);
                    $song->format();
                    $id3 = new vainfo($song->file);
                    $data = $id3->read_id3();
                    if (isset($data['tags']['id3v2'])) {
                        // Get user mail for rating
                        $ratingMail = trim($GLOBALS['user']->email);
                        // If user has no email in record, use user@example.net
                        if ($ratingMail === '') {
                            $ratingMail = 'user@example.net';
                        }
                        // Convert rating
                        switch ((int)$_GET['rating']) {
                            case (5):
                                $ratingNumber = 255;
                                break;
                            case (4):
                                $ratingNumber = 196;
                                break;
                            case (3):
                                $ratingNumber = 128;
                                break;
                            case (2):
                                $ratingNumber = 64;
                                break;
                            case (1):
                                $ratingNumber = 1;
                                break;
                            default:
                                $ratingNumber = 0;
                                break;
                        }
                        $ndata['popularimeter'] = [
                            'email' => $ratingMail,
                            'rating' => $ratingNumber,
                            'data' => 0
                        ];
                        $ndata = array_merge($ndata, $song->get_metadata());
                        $id3->write_id3($ndata);
                    }
                }
            }
            Rating::show($_GET['object_id'], $_GET['rating_type']);
            $key           = "rating_" . $_GET['object_id'] . "_" . $_GET['rating_type'];
            $results[$key] = ob_get_contents();
            ob_end_clean();
        } else {
            $results['rfc3514'] = '0x1';
        }
    break;
    /* Setting userflags */
    case 'set_userflag':
        if (User::is_registered()) {
            ob_start();
            $userflag = new Userflag($_GET['object_id'], $_GET['userflag_type']);
            $userflag->set_flag($_GET['userflag']);
            Userflag::show($_GET['object_id'], $_GET['userflag_type']);
            $key           = "userflag_" . $_GET['object_id'] . "_" . $_GET['userflag_type'];
            $results[$key] = ob_get_contents();
            ob_end_clean();
        } else {
            $results['rfc3514'] = '0x1';
        }
    break;
    case 'action_buttons':
        ob_start();
        if (AmpConfig::get('ratings')) {
            echo " <div id='rating_" . $_GET['object_id'] . "_" . $_GET['object_type'] . "'>";
            Rating::show($_GET['object_id'], $_GET['object_type']);
            echo "</div> |";
        }
        if (AmpConfig::get('userflags')) {
            echo " <div id='userflag_" . $_GET['object_id'] . "_" . $_GET['object_type'] . "'>";
            Userflag::show($_GET['object_id'], $_GET['object_type']);
            echo "</div>";
        }
        $results['action_buttons'] = ob_get_contents();
        ob_end_clean();
    break;
    default:
        $results['rfc3514'] = '0x1';
    break;
} // end switch action

// Go ahead and do the echo
echo xoutput_from_array($results);
