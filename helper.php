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

function yabibtex_field_sorter($keys)
{
  $keyarray = explode(',',$keys);
  if( count($keyarray) > 1) {
    foreach( $keyarray as $key ) {
      $cmparray[]=yabibtex_field_sorter($key);
    }
    return function( $a, $b ) use($cmparray) {
      foreach( $cmparray as $cmp ) {
        $result=call_user_func( $cmp, $a, $b );
        if( $result!=0 )
          return $result;
      }
      return 0;
    };
  }

  $asc=true;
  $key = trim($keyarray[0]);
  if( substr($key,0,1) == '-' ) {
    $asc = false;
    $key = substr($key,1);
  }

  if( $key=='date' ) {
    $asc = $asc ? '' : '-';
    $y_cmp = yabibtex_field_sorter($asc.'year');
    $m_cmp = yabibtex_field_sorter($asc.'month');
    return function( $a, $b) use ($y_cmp,$m_cmp) {
      $y = call_user_func($y_cmp,$a,$b);
      if($y==0)
        return call_user_func($m_cmp,$a,$b);
      return $y;
    };
  }

  return function( $a, $b) use ($key,$asc) {
    $before = $asc ? -1 :  1;
    $after  = $asc ?  1 : -1;
    $f1 = $a->$key;
    $f2 = $b->$key;
    if ($f1 == $f2 ) return 0;
    return ($f1 < $f2) ? $before : $after;
  };
}

class helper_plugin_yabibtex extends DokuWiki_Plugin
{
    var $namespace  = '';      // namespace tag links point to

    var $sort       = '';      // sort key

    var $data       = array(); // handle to loaded entries

    var $show_raw_bibtex = true;
    var $show_abstract   = true;

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
        $this->sortkey = $this->getConf('sort');
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

    function sort( $keys ) {
       if( empty($this->entries) )
         return false;
       if(empty($keys))
         $keys = $this->sortkey;
       if(empty($keys))
        return true;

       return usort( $this->entries, yabibtex_field_sorter($keys) );
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
          $bibfilename = preg_replace( '/[^A-Za-z0-9_-]/', '_'
                                     , trim($entry->citation) ).'.bib';
          $custom_text = ($this->show_raw_bibtex)
              ? $this->render( '<code bibtex>' // '.$bibfilename.'>'
                .Entry::getRaw(
                   $this->data[0][$entry->citation] )
                .'</code>' )
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
