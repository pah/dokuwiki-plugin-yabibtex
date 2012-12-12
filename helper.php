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


class helper_plugin_yabibtex extends DokuWiki_Plugin
{
    var $filter_raw = NULL;
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
        $this->filter_raw = explode(',', $this->getConf('filter_raw') );
    }

    public function loadFiles( $filenames, $filter=NULL )
    {
        if( empty($filenames) ) {
          $this->entries = array();
          return false;
        }
        if( !is_array($filenames) )
          $filenames = array( $filenames );

        $bibtex_entries = array();
        foreach( $filenames as $f ) {
          $bibtex_local   = BibTexParser::read(wikiFN($f));
          $bibtex_entries = array_merge( $bibtex_entries, $bibtex_local );
        }

        $filter_func = $this->_createFilter($filter);
        $this->entries  = BibTexParser::parse($bibtex_entries, $filter_func);
        return $this->entries;
    }

    public function loadString( $string, $filter = NULL )
    {
        $filter_func = $this->_createFilter($filter);
        $bibtex_entries = BibTexParser::readString($string);
        $this->entries  = BibTexParser::parse($bibtex_entries, $filter_func);
        return $this->entries;
    }

    private function _create_field_match_user( $pattern ){
      $userlink = $this->getConf('userlink');
      if( $userlink != 'off' ) {
        $uinfo = $this->_findUserInfo( $pattern, false );
        $mae = ($uinfo!==false)
             ? $this->_create_field_match_creator( $uinfo['name'] )
             : NULL;
      }
      return function($e) use ($pattern,$mae) {
        if( $e->users !== NULL ) {
          $unames = explode(' ', $e->users );
          foreach( $unames as $u ) {
            if( preg_match('/^\s*(?:(?:([ae]):)?([0-9]+):)?([a-z0-9_-]+)\s*$/i'
                          , $u, $m ) && ( $pattern == $m[3] ) )
              return true;
          }
        }
        if( $mae !== NULL )
          return call_user_func( $mae, $e );

        return false;
      };
    }

    private function _create_field_match_users( $pattern ) {
      return $this->_create_field_match_user( $pattern );
    }

    private function _create_field_match_creator( $pattern, $kind='any' ) {
      if( $kind == 'any' ) {
        $mauthor = $this->_create_field_match_creator( $pattern, 'authors' );
        $meditor = $this->_create_field_match_creator( $pattern, 'editors' );
        return function($e) use($mauthor,$meditor) {
          return ( call_user_func( $mauthor, $e ) !== false )
              || ( call_user_func( $meditor, $e ) !== false );
        };
      }

      return function($e) use ($kind,$pattern) {
        $list = $e->$kind;
        if( !$list->isEmpty() ) {
          foreach( $list->creators as $c ) {
            if( stripos( (string) $c, $pattern ) !== false )
              return true;
          }
        }
        return false;
      };
    }

    private function _create_field_match_author( $pattern ) {
      return $this->_create_field_match_creator( $pattern, 'authors' );
    }

    private function _create_field_match_authors( $pattern ) {
      return $this->_create_field_match_creator( $pattern, 'authors' );
    }

    private function _create_field_match_editors( $pattern ) {
      return $this->_create_field_match_creator( $pattern, 'editors' );
    }

    private function _create_field_match_editor( $pattern ) {
      return $this->_create_field_match_creator( $pattern, 'editors' );
    }

    private function _create_field_match_key( $pattern ) {
      return $this->_create_field_match_generic( 'citation', $pattern );
    }

    private function _create_field_match_type( $pattern ) {
      return $this->_create_field_match_generic( 'entry_type', $pattern );
    }

    private function _create_field_match_generic( $key, $pattern ) {
      return function( $e ) use($key, $pattern) {
        if( is_null($e->$key) ) return false;
        return stripos( $e->$key, $pattern ) !== false;
      };
    }

    private function _createFieldFilter( $key, $pattern ) {
      if( empty($key) )     return NULL;
      if( empty($pattern) ) return NULL;

      if( !is_array($pattern) ) {
        return method_exists( $this, '_create_field_match_'.$key )
                  ? call_user_func( array($this, '_create_field_match_'.$key )
                                  , $pattern )
                  : $this->_create_field_match_generic( $key, $pattern );
      } else {
        $marray = array();
        foreach( $pattern as $p ) {
          $mf = $this->_createFieldFilter($key,$p);
          if( $mf!== NULL ) $marray[] = $mf;
        }
        // disjunction of all applied sub-filters
        return function( $e ) use($marray) {
          foreach( $marray as $m ) {
            if( call_user_func( $m, $e ) !== false )
              return true;
          }
          return false;
        };
      }
    }

    private function _createFilter( $filter ) {

      if( !is_array($filter) || count($filter) == 0 )
        return NULL;

      $marray = array();
      foreach( $filter as $f ) {
        if( strpos($f['pattern'],'|') ) {
          $f['pattern'] = array_map('trim', explode('|',$f['pattern']));
        }
        $mf = $this->_createFieldFilter( $f['key'], $f['pattern'] );
        if( $mf!== NULL ) $marray[] = $mf;
      }

      // conjunction of all applied filters
      return function( $e ) use($marray) {
          foreach( $marray as $m ) {
            if( call_user_func( $m, $e ) === false )
              return false;
          }
          return true;
        };
    }

    public function _version_check(){
      if( $this->has_closures ) return true;
      $php_version = explode( '.', PHP_VERSION );
      $this->has_closures = ($php_version[0]*10000
                             + $php_version[1]*100) >= 50300;
      if( !$this->has_closures ) 
        msg('No PHP5 closure support (PHP >= 5.3.0 needed). '
           .'Sorting+filtering disabled.' -1 );
      return $this->has_closures;
    }

    public function parseOptions( $opts ) {
      $flags  = array();
      $filter = array();
      $opts   = explode( '&', $opts );
      foreach( $opts as $o )
      {
        $o = trim($o);
        if( empty($o) ) continue;
        if( preg_match( '/^(?i:((no)?([a-z0-9_-]*)))(?:\s*=\s*(.*))?$/',$o,$m )  )
        {
          if( isset($m[4]) ) // assignment option, store key/value pair
          {
            switch( $m[1] )
            {
            case 'sort':            // sort option
              if( !$this->_version_check() ) {
                $m[4] = false;
              }
            // 
            case 'class':           // set CSS class
            case 'userlink':        // (off|auto|explicit)
            case 'links':           // (off|keyinline)
              $flags[$m[1]]=trim($m[4]); 
              break;
            default:
              if( $this->_version_check() ) {
                $filter[] = array( 'key'     => $m[1] // value filter
                                 , 'pattern' => trim($m[4]) );
              }
            }
          }
          else // flag option
          {
            $flags[$m[3]]=($m[2]=='no')?false:true;
          }
        } else {
          msg('BibTeX: Invalid option syntax ignored: "'.hsc($o).'"',-1);
        }
        // else ignore invalid option
      }
      $flags['filter'] = $filter;
      return $flags;
    }

    // call_user_func_array($func, array(&$data,$base,$file,'f',$lvl,$opts));
    function _initUserPage( &$data, $base, $file, $type, $opts )
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

    private function _initUsers() {
        global $auth;

        $user_table =& $this->user_table;

        // preload known users (done)
        if( is_array( $user_table ) )
          return;

        $users_page = array(); 
        $user_auth  = array(); 
        $userfind   = $this->getConf('userfind');
        $userns     = $this->getConf('userns');

        if( $userfind != 'users' ) { // look for pages
          global $conf;

          // search(&$data,$base,$func,$opts,$dir='',$lvl=1,$sort=true)
          search( $users_page, $conf['datadir']
                , array($this,'_initUserPage')
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

    private function _findUserInfo( $user, $allowempty=true ) {
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
      if( empty($item['name']) ) {
        if( $allowempty )
          $item['name'] = $user;
        else
          $item = false;
      }

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

      $this->_initUsers();

      $referenced = array();
      $overridden = array(); // explicit IDs for authors

      if( $entry->users !== NULL ) {
        $unames = explode(' ', $entry->users );
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
            $this->_findAuthorInfo( $c, $overridden[$l][$i] );
            $i++;
          }
        }
        $l = 'e';
      }
    }

    function _create_field_sorter($keys)
    {
      $keyarray = explode(',',$keys);
      if( count($keyarray) > 1) {
        foreach( $keyarray as $key ) {
          $cmparray[]=$this->_create_field_sorter($key);
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

      if($key=='key')
        return $this->_create_field_sorter(($asc ? '':'^').'citation');

      if($key=='date') {
        $asc = $asc ? '' : '^';
        $y_cmp = $this->_create_field_sorter($asc.'year');
        $m_cmp = $this->_create_field_sorter($asc.'month');
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
        $cmp = strcasecmp($f1,$f2);
        return ( $cmp == 0 )  
               ? 0 : ($cmp < 0 ? $before : $after);
      };
    }

    function sort( $keys ) {
       if( empty($this->entries) )
         return true;
       if(empty($keys))
        return true;

       return usort( $this->entries, $this->_create_field_sorter($keys) );
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

        if( !isset( $flags['sort']) && $this->_version_check() )
          $flags['sort'] =  $this->getConf( 'sort' );
        if( !isset( $flags['rowmarkers'] ) )
          $flags['rowmarkers'] = $this->getConf( 'rowmarkers' );
        if( !isset( $flags['showkey'] ) )
          $flags['showkey'] = $this->getConf( 'show_key' );
        if( !isset( $flags['showtype'] ) )
          $flags['showtype'] = $this->getConf( 'show_type' );
        if( !isset( $flags['links'] ) )
          $flags['links'] = $this->getConf( 'show_links' );
        if( !isset( $flags['bibtex'] ) )
          $flags['bibtex'] = $this->getConf( 'show_bibtex' );
        if( !isset( $flags['abstract'] ) )
          $flags['abstract'] = $this->getConf( 'show_abstract' );
        if( !isset( $flags['userlink'] ) )
          $flags['userlink'] = $this->getConf( 'userlink' );

        if( $flags['abstract']===0 )
          $flags['filter_raw'][] = 'abstract';

        if( $flags['links'] == 'auto' ) {
          $flags['links'] = ( $flags['showkey'] || $flags['showtype'] )
                            ? 'key' : 'inline';
        }

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
          $oldclass = $flags['class'];
          foreach ( $this->entries as $entry) {
            if ($flags['rowmarkers'] ) {
              $even = (($even=='odd') ? 'even' : 'odd');
              $flags['class']=$oldclass.' '.$even;
            }
            if( $flags['showkey'] || $flags['showtype'] ) {
              $renderer->doc.= '<dt class="bibtexKey '.$even.'"><ul>';
              if($flags['showkey']) {
                $renderer->doc.='<li class="bibtexKey">'
                               .hsc($entry->citation)
                               .'</li>';
              }
              if($flags['showtype']) {
                $renderer->doc.='<li class="bibtexType">'
                               .hsc($entry->entry_type)
                               .'</li>';
              }
              $renderer->doc.= '</ul>'.DOKU_LF;

              if( $flags['links'] == 'key' ) {
                $entry->printLinks( $flags );
              }
              $renderer->doc.= '</dt>'.DOKU_LF;
            }
            $renderer->doc.= '<dd class="bibtexEntry '.$even.'">';
            $this->_findAuthors( $flags['userlink'], $entry );
            $entry->printFormatted( $flags );
            $renderer->doc.= '</dd>'.DOKU_LF;
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
