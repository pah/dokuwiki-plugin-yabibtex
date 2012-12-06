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
  if( substr($key,0,1) == '^' ) {
    $asc = false;
    $key = substr($key,1);
  }

  if( $key=='date' ) {
    $asc = $asc ? '' : '^';
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

function yabibtex_field_match_user( $e ) {
  
}

function yabibtex_field_match($pattern = array()) {
  return function( $e ) use ($pattern) {
    

  };
}

class helper_plugin_yabibtex extends DokuWiki_Plugin
{
    var $namespace  = '';      // namespace tag links point to

    var $sortkey    = '';      // sort key

    var $data       = array(); // handle to loaded entries

    var $show_raw_bibtex = true;
    var $show_abstract   = true;

    static $user_table = array();

    /**
     * Constructor gets default preferences and language strings
     */
    function helper_plugin_yabibtex() {
        global $ID;

        require_once(DOKU_PLUGIN_YABIBTEX.'bibtex/references.php');
        require_once(DOKU_PLUGIN_YABIBTEX.'bibtex/bib.php');
        $this->setupLocale();
        $this->loadConfig();

        BibliographyParser::$plugin=& $this;
        BibliographyParser::$lang  =& $this->lang;
        BibliographyParser::$conf  =& $this->conf;
        BibliographyParser::$users =& $this->user_table;

        $this->bibns = $this->getConf('bibns');
        if (!$this->bibns) $this->bibns = getNS($ID);
        $this->sortkey = $this->getConf('sort');
        $this->filter_raw = explode(',', $this->getConf('filter_raw') );
    }

    public function loadFile( $filename )
    {
        $bibtex_entries = BibTexParser::read($filename);
        $this->entries  = BibTexParser::parse($bibtex_entries);
        $this->_loadUsers();
        return $this->entries;
    }

    public function loadString( $string )
    {
        $bibtex_entries = BibTexParser::readString($string);
        $this->entries = BibTexParser::parse($bibtex_entries);
        $this->_loadUsers();
        return $this->entries;
    }

    private function _loadUsers() {
      global $auth;

      $ns    = $this->getConf('userns');

      if( $this->getConf('userlinkauto') ) {
        $users = $auth->retrieveUsers();
      } else {
        $users = array();
        foreach( $this->entries as $entry ) {
          if( !empty($entry->users) ) {
            $unames = explode(',', $entry->users);
            foreach( $unames as $u ) {
              $u = trim($u);
              if (!isset($users[$u]) ) {
                $users[$u] = $auth->getUserData($u);
              }
            }
          }
        }
      }
      $user_table =& $this->user_table;
      foreach( $users as $user => $info ) {
        $name = $info['name'];
        $page = cleanID( $ns.':'.$user ); 
        $title = $name;
        if( page_exists($page) ) {
          $title    = p_get_first_heading($page);
        }
        $user_table[$name] = compact( 'user', 'page', 'name', 'title' );
      }

      if( empty($user_table) )
        return false;

      foreach( $this->entries as $e ) {
        foreach( array( $e->authors, $e->editors ) as $list ) {
          if( !$list->isEmpty() )
            foreach( $list->creators as $c ) {
              $name =  (string) $c;
              if( isset($user_table[$name]) ) {
                $c->addInfo( $user_table[$name] );
              }
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
     * Produces a formatted BibTeX list of the current entry array.
     */
    public function renderBibTeX( $flags=array() 
                                , &$renderer = NULL
                                , $mode='xhtml' )
    {
        if( empty($this->entries) )
          return NULL;

        $temp_render = false;
        if( $renderer === NULL ) {
          $renderer =& p_get_renderer($mode);
          $temp_render = true;
          $renderer->reset();
        }

        if( !isset( $flags['rowcolors'] ) )
          $flags['rowcolors'] = $this->getConf( 'rowcolors' );
        if( !isset( $flags['bibtex'] ) )
          $flags['bibtex'] = $this->getConf( 'show_bibtex' );
        if( !isset( $flags['abstract'] ) )
          $flags['abstract'] = $this->getConf( 'show_abstract' );
        if( !isset( $flags['userlink'] ) )
          $flags['userlink'] = $this->getConf( 'userlink' );

        BibliographyParser::$renderer =& $renderer;

        if( $mode == 'code')
        {
          if( $flags['bibtex'] )
            foreach ( $this->entries as $entry) {
              $bibfilename = preg_replace( '/[^A-Za-z0-9_-]/', '_'
                                         , trim($entry->citation) ).'.bib';
              BibliographyParser::printCode(
                $entry->getRaw($flags['filter_raw']),$bibfilename
              );
            }
        }
        else if ($mode == 'xhtml' )
        {
          $renderer->doc.= '<dl class="bibtexList">'.DOKU_LF;
          $even=0; $oldclass = $flags['class'];
          if ($flags['rowcolors'] )
            $flags['class']=$oldclass.' even';
          foreach ( $this->entries as $entry) {
            $renderer->doc.= '<dd class="'.$even.'">';
            $entry->printFormatted( $flags );
            $renderer->doc.= '</dd>'.DOKU_LF;
            if ($flags['rowcolors'] )
              $flags['class'] = $oldclass
                . (($even=($even+1)%2) ? ' odd' : ' even');
          }
          $renderer->doc.= '</dl>'.DOKU_LF;
        }

        if( $temp_render == true ) {
          // Post process and return the output
          $data = array($mode,& $renderer->doc);
          trigger_event('RENDERER_CONTENT_POSTPROCESS',$data);
          $result = $renderer->doc;
          BibliographyParser::$renderer = NULL;
          return $result;
        }

        return true;
    }
}

// vim:ts=4:sw=4:et:
