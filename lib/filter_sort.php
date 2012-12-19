<?php
/**
 * DokuWiki Plugin yabibtex (filter&sort function creators)
 *
 * @note This file requires PHP 5.3.0 or higher, due to
 *       the use of anonymous functions.
 * @see  http://php.net/manual/en/functions.anonymous.php
 *
 * @license GPL 3 http://www.gnu.org/licenses/gpl-3.0.html
 * @author  Philipp A. Hartmann <pah@qo.cx>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
if (!defined('DOKU_PLUGIN_YABIBTEX'))
  define('DOKU_PLUGIN_YABIBTEX',DOKU_PLUGIN.'yabibtex/');


function yabibtex_create_field_match_user( &$this_, $pattern ){
  $userlink = $this_->getConf('userlink');
  $mae = NULL;
  if( $userlink != 'off' ) {
    $uinfo = $this_->_findUserInfo( $pattern, false );
    if($uinfo!==false)
      $mae = yabibtex_create_field_match_creator( $this_, $uinfo['name'] );
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

function yabibtex_create_field_match_users( &$this_, $pattern )
{
  return yabibtex_create_field_match_user( $this_, $pattern );
}

function yabibtex_create_field_match_creator( &$this_, $pattern, $kind='any' )
{
  if( $kind == 'any' ) {
    $mauthor=yabibtex_create_field_match_creator($this_,$pattern,'authors');
    $meditor=yabibtex_create_field_match_creator($this_,$pattern,'editors');
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

function yabibtex_create_field_match_author( &$this_, $pattern )
{
  return yabibtex_create_field_match_creator( $this_, $pattern, 'authors' );
}

function yabibtex_create_field_match_authors( &$this_, $pattern )
{
  return yabibtex_create_field_match_creator( $this_, $pattern, 'authors' );
}

function yabibtex_create_field_match_editors( &$this_, $pattern )
{
  return yabibtex_create_field_match_creator( $this_, $pattern, 'editors' );
}

function yabibtex_create_field_match_editor( &$this_, $pattern )
{
  return yabibtex_create_field_match_creator( $this_, $pattern, 'editors' );
}

function yabibtex_create_field_match_key( &$this_, $pattern )
{
  return _yabibtex_create_field_match_generic( 'citation', $pattern );
}

function yabibtex_create_field_match_type( &$this_, $pattern )
{
  return _yabibtex_create_field_match_generic( 'entry_type', $pattern );
}

function _yabibtex_create_field_match_generic( $key, $pattern )
{
  return function( $e ) use($key, $pattern) {
    if( is_null($e->$key) ) return false;
    return stripos( $e->$key, $pattern ) !== false;
  };
}

function yabibtex_create_field_filter( &$this_, $key, $pattern )
{
  if( empty($key) )     return NULL;
  if( empty($pattern) ) return NULL;

  if( !is_array($pattern) ) {
    return function_exists( 'yabibtex_create_field_match_'.$key )
              ? call_user_func( 'yabibtex_create_field_match_'.$key
                              , $this_, $pattern )
              : _yabibtex_create_field_match_generic( $key, $pattern );
  } else {
    $marray = array();
    foreach( $pattern as $p ) {
      $mf = yabibtex_create_field_filter($this_,$key,$p);
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

function yabibtex_create_filter( &$this_, $filter )
{
  if( !is_array($filter) || count($filter) == 0 )
    return NULL;

  $marray = array();
  foreach( $filter as $f ) {
    if( strpos($f['pattern'],'|') ) {
      $f['pattern'] = array_map('trim', explode('|',$f['pattern']));
    }
    $mf = yabibtex_create_field_filter( $this_, $f['key'], $f['pattern'] );
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

/* -------------------------------------------------------------------- */

function yabibtex_create_field_sorter($keys)
{
  $keyarray = explode(',',$keys);
  if( count($keyarray) > 1) {
    foreach( $keyarray as $key ) {
      $cmparray[]=yabibtex_create_field_sorter($key);
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
    return yabibtex_create_field_sorter(($asc ? '':'^').'citation');

  if($key=='date') {
    $asc = $asc ? '' : '^';
    $y_cmp = yabibtex_create_field_sorter($asc.'year');
    $m_cmp = yabibtex_create_field_sorter($asc.'month');
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
