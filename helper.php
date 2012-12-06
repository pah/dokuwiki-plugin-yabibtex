<?php
/**
 * DokuWiki Plugin yabibtex (Helper Component)
 *
 * Based on Joomla! BibteX bibliography formatter plugin,
 * written by Levente Hunyadi.
 *
 * @license GPL 3 http://www.gnu.org/licenses/gpl-3.0.html
 * @author  Levente Hunyadi
 * @author  Philipp A. Hartmann <pah@qo.cx>
 * @see     http://hunyadi.info.hu/projects/bibtex
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
if (!defined('DOKU_PLUGIN_YABIBTEX'))
  define('DOKU_PLUGIN_YABIBTEX',DOKU_PLUGIN.'yabibtex/');

require_once DOKU_PLUGIN.'syntax.php';
require_once DOKU_INC.'inc/infoutils.php';

class helper_plugin_yabibtex extends DokuWiki_Plugin
{
    var $namespace  = '';      // namespace tag links point to

    var $sort       = '';      // sort key

    var $data       = array(); // handle to loaded entries

    var $show_raw_bibtex = true;
    var $show_abstract   = false;

    /**
     * Constructor gets default preferences and language strings
     */
    function helper_plugin_yabibtex() {
        global $ID;

        require_once(DOKU_PLUGIN_YABIBTEX.'bibtex/references.php');
        require_once(DOKU_PLUGIN_YABIBTEX.'bibtex/bib.php');
        $this->setupLocale();
        $this->loadConfig();

        BibliographyParser::$lang  =& $this->lang;
        BibliographyParser::$conf  =& $this->conf;
        BibliographyParser::$users =& $this->user_table;

        $this->bibns = $this->getConf('bibns');
        if (!$this->bibns) $this->bibns = getNS($ID);
        $this->sort = $this->getConf('sortkey');
    }

    public function loadFile( $filename )
    {
        $bibtex_entries = BibTexParser::read($filename);
        $entries = BibTexParser::parse($bibtex_entries);
        $this->data = array($bibtex_entries, $entries);
        $this->_loadUsers();
        return $this->data;
    }

    public function loadString( $string )
    {
        $bibtex_entries = BibTexParser::readString($string);
        $entries = BibTexParser::parse($bibtex_entries);
        $this->data = array($bibtex_entries, $entries);
        $this->_loadUsers();
        return $this->data;
    }

    private function _stripTitle( $longname ) {
      // TODO
      return $longname;
    }

    private function _loadUsers() {
      $ns    = $this->getConf('userns');

      if( $this->getConf('autouserlink') ) {
        // use retrieveUsers!
        // TODO
      } else {
        $users = array();
        foreach( $this->data[0] as $entry ) {
          if( !empty($entry['users']) ) {
            $users = array_merge( $users, explode(',', $entry['users']) ); 
          }
        }
        $users = array_unique( $users );
      }

      $user_table = array();
      foreach( $users as $user ) {
        $user = trim($user);
        $page = cleanID( $ns.':'.$user ); 
        if( page_exists($page) ) {
          $title    = p_get_first_heading($page);
          $name     = $this->_stripTitle($title); 
          $user_table[$name] = compact( 'user', 'page', 'name', 'title' );
        }
      }

      $this->user_table = $user_table;
      if( empty($user_table) )
        return false;

      foreach( $this->data[1] as $e ) {
              dbg($e);
        foreach( array( $e->getAuthors(), $e->getEditors() ) as $list ) {
          if( !$list->isEmpty() )
            foreach( $e->creators as $c ) {
            }
        }
      }
    }

    /**
     * Produces a formatted BibTeX list.
     */
    public function renderBibTeX() {

        if( empty($this->data) )
          return '';

        ob_start();

        print '<dl class="bibtexList">'.DOKU_LF;
        foreach ( $this->data[1] as $entry) {
          $custom_text =
            ($this->show_raw_bibtex)
              ? Entry::getRaw(
                  $this->data[0][$entry->citation] )
              : false;

          print '<dd>';
          $entry->printFormatted( $custom_text, $this->show_abstract );
          print '</dd>'.DOKU_LF;
        }
        print '</dl>'.DOKU_LF;
        return ob_get_clean();
    }
}

// vim:ts=4:sw=4:et:
