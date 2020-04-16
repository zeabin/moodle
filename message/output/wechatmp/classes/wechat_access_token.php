<?php
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Wechat access token fetcher.
 *
 * @package    message_wechatmp
 * @category   cache
 * @copyright  2020 Zheng Zhibin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since Moodle 3.8
 */

 defined('MOODLE_INTERNAL') || die();

 /**
 * Cache data source for the Wechat access token.
 *
 * @package    message_wechatmp
 * @category   cache
 * @copyright  2020 Zheng Zhibin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 class wechat_access_token implements \cache_data_source {

    protected static $instance = null;

     /**
     * Returns an instance of the data source class that the cache can use for loading data using the other methods
     * specified by this interface.
     *
     * @param \cache_definition $definition
     * @return object
     */
    public static function get_instance_for_cache(\cache_definition $definition) {
        if (is_null(self::$instance)) {
            self::$instance = new wechat_access_token();
        }
        return self::$instance;
    }

    /**
     * Loads the data for the key provided ready formatted for caching.
     *
     * @param string|int $key The key to load.
     * @return mixed What ever data should be returned, or false if it can't be loaded.
     */
    public function load_for_cache($key) {
        if ($key != 'access_token') {
            return null;
        }

        global $CFG;

        require_once($CFG->libdir . '/filelib.php');

        // Sending the request access token request to Wechat.
        //     'appid' => 'wx5e34e980b0706455',
        //     'secret' => '244313e158dccc397b62d37243d75d90'
        $serverurl = 'https://api.weixin.qq.com/cgi-bin/token';
        $curl = new \curl();
        $params = array(
            'grant_type' => 'client_credential',
            'appid' => $CFG->wechatappid,
            'secret' => $CFG->wechatappsecret,
            );
        $resp = $curl->get($serverurl, $params);

        if ($key = json_decode($resp, true)) {
            if (!empty($key['access_token'])) {
                return $key['access_token'];
            }
        }
        debugging("Unexpected response from the Wechat server: $resp");
        return null;
    }

    /**
     * Loads several keys for the cache.
     *
     * @param array $keys An array of keys each of which will be string|int.
     * @return array An array of matching data items.
     */
    public function load_many_for_cache(array $keys) {
        $results = [];

        foreach ($keys as $key) {
            $results[] = $this->load_for_cache($key);
        }

        return $results;
    }
 }
