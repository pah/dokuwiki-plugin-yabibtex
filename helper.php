<?php
/**
 * DokuWiki Plugin yabibtex (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Philipp A. Hartmann <pah@qo.cx>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once DOKU_INC.'inc/infoutils.php';
require_once DOKU_PLUGIN.'syntax.php';

class helper_plugin_yabibtex extends DokuWiki_Plugin
{
    var $namespace  = '';      // namespace tag links point to

    var $sort       = '';      // sort key

    /**
     * Constructor gets default preferences and language strings
     */
    function helper_plugin_yabibtex() {
        global $ID;

        $this->bibns = $this->getConf('bibns');
        if (!$this->bibns) $this->bibns = getNS($ID);
        $this->sort = $this->getConf('sortkey');
    }
}

// vim:ts=4:sw=4:et:
