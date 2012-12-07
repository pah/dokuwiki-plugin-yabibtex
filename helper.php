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
require_once DOKU_INC.'inc/search.php';

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

  if($key=='date') {
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

  return function($a, $b) use ($key,$asc) {
    $before = $asc ? -1 :  1;
    $after  = $asc ?  1 : -1;
    $f1 = $a->$key;
    $f2 = $b->$key;
    if ($f1 == $f2 ) return 0;
    return ($f1 < $f2) ? $before : $after;
  };
}

function yabibtex_field_match_author( $e ) {
  
}

function yabibtex_field_match_pattern($pattern = array()) {
  return function( $e ) use ($pattern) {
    foreach( $pattern as $k => $va ) {
      if( !is_array( $va ) ) {
        $va = array($va);
      }
      $result = false;
      foreach( $va as $v ) {
        if( function_exists( 'yabibtex_field_match_'.$k ) ) {
          if( call_user_func( 'yabibtex_field_match_'.$k, $v ) === FALSE )
            continue;
        } else if( stripos( $v, $e->$k ) === FALSE ) {
          continue;
        }
        $result = true;
      }
      if(!$result)
        return false;
    }
    return true;
  };
}


// call_user_func_array($func, array(&$data,$base,$file,'f',$lvl,$opts));
function yabibtex_init_user_page( &$data, $base, $file, $type, $opts )
{
  global $conf;

  // ignore directories
  if( $type == 'd' ) {
    if(!$opts['depth']) return true; // recurse forever
    $parts = explode('/',ltrim($file,'/'));
    if(count($parts) == $opts['depth']) return false; // depth reached
    return true;
  }

  //only search txt files
  if(substr($file,-4) != '.txt') return true;

  $item['page'] = pathID($file);

  if(!$opts['skipacl'] && auth_quickaclcheck($item['page']) < AUTH_READ)
    return false;

  $user = noNS($item['page']);

  if( $user == $conf['start'] || isHiddenPage($item['page']) )
    return false;

  $item['name'] = $item['title'] = p_get_first_heading($item['page']);

  $data[$user] = $item;
  return true;
}

class helper_plugin_yabibtex extends DokuWiki_Plugin
{
    var $filter_raw = NULL;
    var $sortkey    = '';      // sort key
    var $user_table   = NULL;
    var $author_table = array();

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

    private function _initUsers() {
        $user_table =& $this->user_table;

        // preload known users (done)
        if( is_array( $user_table ) )
          return;

        $user_pages = array(); 
        $user_auth  = array(); 
        $userfind   = $this->getConf('userfind');
        $userns     = $this->getConf('userns');

        if( $userfind != 'users' ) { // look for pages
          global $conf;

          // search(&$data,$base,$func,$opts,$dir='',$lvl=1,$sort=true)
          search( $users_page, $conf['datadir']
                , 'yabibtex_init_user_page'
                , array() /* opts */
                , $userns
                , 1, false );
        }

        if( $userfind != 'pages' ) { // look for users
          global $auth;
          $users_auth = $auth->retrieveUsers();
 
          // cleanup unneeded fields
          foreach( $users_auth as $k => $u ) {
            $users_auth[$k] = array( 'name' => $u['name']
                                   , 'page' => cleanID( $userns.':'.$k) );
            if( isset($users_page[$k]['title']) )
              $users_auth[$k]['title'] = $users_page[$k]['title'];
          }
        }
        $user_table = array_merge( $users_page, $users_auth );
    }

    private function _findUserInfo( $user ) {
      global $auth;
      $users  =& $this->user_table;
      $userns =  $this->getConf('userns');

      if( isset( $users[$user] ) )
        return $users[$user];

      $item = $auth->getUserData( $user );
      $page = cleanID( $userns.':'.$user );

      // user found
      if( $item !== false ) {
        $item = array( 'name' => $item['name']
                     , 'page' => $page );
      } else {
        $item = array( 'page' => $page );
      }

      if( page_exists($page) )
        $item['title'] = p_get_first_heading($page);

      if( empty($item['name']) )
        $item['name'] = $item['title'];

      // fallback
      if( empty($item['name']) )
        $item['name'] = $user;

      // return cached value
      return $users[$user] = $item;
    }

    private function _findAuthorInfo( &$creator, $user=NULL )
    {
      $user_table   =& $this->user_table;

      $name = (string)$creator;

      // search automatic author<->user matching
      if( $user === NULL ) {
        $author_table =& $this->author_table;
        if( isset( $author_table[$name] ) ) {
          $creator->addInfo( $author_table[$name] );
          return true;
        }
        foreach( $user_table as $k => $u ) {
          if( $u !== false && $name == $u['name'] ) {
            $author_table[$name] = $u;
            $creator->addInfo( $u );
            return true;
          }
        }
        return $author_table[$name] = false;
      }

      $item = false;
      // explicit lookup of user info with manual assignment
      if( !isset($user_table[$user]) )
        $item = $this->_findUserInfo($user);

      // fallback entry
      if( $item === false ) {
        $item = array('page'=>cleanID($this->getConf('userns').':'.$user));
      }
      $creator->addInfo($item);
      return isset($item['name']);
    }

    private function _findAuthors( $userlink, &$entry ) {

      if( $userlink == 'off' )
        return false;

      if( $userlink == 'explicit' && $entry->users === NULL )
        return false;

      $referenced = array();
      $overridden = array(); // explicit IDs for authors

      if( $entry->users !== NULL ) {
        $unames = explode(',', $entry->users );
        foreach( $unames as $u ) {
          if( preg_match('/^\s*(?:(?:([ae]):)?([0-9]+):)?([a-z0-9_-]+)\s*$/i'
                        , $u, $m ) )
          {
            $referenced[] = cleanID($m[3]);
            if( !empty($m[2]) ) {
              if( empty($m[1]) ) $m[1] = 'a';
              $overridden[strtolower($m[1])][(int)($m[2])]
                = end( $referenced );
            }
          }
        }
      }

      if( $userlink == 'explicit' ) {
        foreach( $referenced as $u ) { // force update user cache
          $this->_findUserInfo($u);
        }
      }

      $l = 'a';
      foreach( array( $entry->authors, $entry->editors ) as $list ) {
        if( !$list->isEmpty() ) {
          $i = 1; 
          foreach( $list->creators as $c ) {
            $name =  (string) $c;
            $this->_findAuthorInfo( $c, $overridden[$l][$i] );
            $i++;
          }
        }
        $l = 'e';
      }
    }

    private function _loadUsers() {
      global $auth;

      if( $this->getConf('userlink') == "auto" ) {
        $this->_initUsers();
      }
    }

    function sort( $keys ) {
       if( empty($this->entries) )
         return true;
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

        if( !isset( $flags['rowmarkers'] ) )
          $flags['rowmarkers'] = $this->getConf( 'rowmarkers' );
        if( !isset( $flags['bibtex'] ) )
          $flags['bibtex'] = $this->getConf( 'show_bibtex' );
        if( !isset( $flags['abstract'] ) )
          $flags['abstract'] = $this->getConf( 'show_abstract' );
        if( !isset( $flags['userlink'] ) )
          $flags['userlink'] = $this->getConf( 'userlink' );

        BibliographyParser::$renderer =& $renderer;

        if( $mode == 'code' && $flags['bibtex'] )
        {
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
          if ($flags['rowmarkers'] )
            $flags['class']=$oldclass.' even';
          foreach ( $this->entries as $entry) {
            $renderer->doc.= '<dd class="'.$even.'">';
            $this->_findAuthors( $flags['userlink'], $entry );
            $entry->printFormatted( $flags );
            $renderer->doc.= '</dd>'.DOKU_LF;
            if ($flags['rowmarkers'] )
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
