<?php
/**
 * DokuWiki Plugin yabibtex (Action Component)
 *
 * Action plugin component, for cache validity determination
 * (adopted from Include plugin, written by Christopher Smith)
 * 
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Christopher Smith <chris@jalakai.co.uk>  
 * @author  Philipp A. Hartmann <pah@qo.cx>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once DOKU_PLUGIN.'action.php';

class action_plugin_yabibtex_cache extends DokuWiki_Action_Plugin
{

    var $supportedModes = array('xhtml');

    public function register(Doku_Event_Handler &$controller) {
      $controller->register_hook('PARSER_CACHE_USE','BEFORE', $this, '_cache_prepare');
    }

    /**
     * prepare the cache object for default _useCache action
     */
    function _cache_prepare(&$event, $param) {
      $cache =& $event->data;

      // we're only interested in wiki pages and supported render modes
      if (!isset($cache->page)) return;
      if (!isset($cache->mode) || !in_array($cache->mode, $this->supportedModes)) return;

      $key = '';
      $depends = array();
      $expire = $this->_bibtex_check($cache->page, $key, $depends);

      // empty $key implies no BibTeX includes, so nothing to do
      if (empty($key)) return;

      // mark the cache as being modified by the BibTeX plugin
      $cache->bibtex = true;
 
      // set new cache key & cache name - now also dependent on
      // included BibTeX ids
      $cache->key .= $key;
      $cache->cache = getCacheName($cache->key, $cache->ext);
 
      // BibTeX inclusion check was able to determine the cache must be invalid
      if ($expire) {
        $event->preventDefault();
        $event->stopPropagation();
        $event->result = false;
        return;
      }
 
      // update depends['files'] array to include all dependent BibTeX files
      $cache->depends['files'] =
        !empty($cache->depends['files'])
          ? array_merge($cache->depends['files'], $depends)
          : $depends;
    }
 
    /**
     * carry out dependent BibTeX page checks:
     * - to establish proper cache name
     * - to establish file dependencies, the raw BibTeX wiki pages
     *
     * @param   string    $id         wiki page name
     * @param   string    $key        (reference) cache key
     * @param   array     $depends    array of include file dependencies
     *
     * @return  bool                  expire the cache
     */
    function _bibtex_check($id, &$key, &$depends) {
      $hasPart = p_get_metadata($id, 'relation haspart');
      if (empty($hasPart)) return false;
 
      $expire = false;
      foreach ($hasPart as $page => $exists) {
        // ensure its a wiki page
        if (strpos($page,'/') ||  cleanID($page) != $page) continue;

        // recursive includes aren't allowed and there is no need to do the same page twice
        $file = wikiFN($page);
        if (in_array($file, $depends)) continue;

        // file existence state is different from state recorded in metadata
        if (@file_exists($file) != $exists) {

          // if (($acl = $this->_acl_read_check($page)) != 'NONE') { $expire = true;  }
          $expire = true;

        } else if ($exists) {

          // carry out an inclusion check on the included page, that will update $key & $depends
          if ($this->_bibtex_check($page, $key, $depends)) { $expire = true; }
          // if (($acl = $this->_acl_read_check($page)) != 'NONE') { $depends[] = $file;  }
          $depends[] = $file;
//
//        } else {
//          $acl = 'NONE';
        }

        // add this page and acl status to the key
        $key .= '#'.$page; // .'|'.$acl;
      }

      return $expire;
    }

//    function _acl_read_check($id) {
//      return (AUTH_READ <= auth_quickaclcheck($id)) ? 'READ' : 'NONE';
//    }

}

// vim:ts=4:sw=4:et:
