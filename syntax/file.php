<?php
/**
 * DokuWiki Plugin yabibtex (Syntax Component)
 *
 * @license GPL 3 http://www.gnu.org/licenses/gpl-3.0.html
 * @author  Philipp A. Hartmann <pah@qo.cx>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once DOKU_INC.'inc/infoutils.php';
require_once DOKU_PLUGIN.'syntax.php';

class syntax_plugin_yabibtex_file extends DokuWiki_Syntax_Plugin
{
    var $helper = false;

    public function getType() {
        return 'substition';
    }

    public function getPType() {
        return 'block';
    }

    public function getSort() {
        return 313;
    }

    public function connectTo($mode) {
      $this->Lexer->addSpecialPattern( '\{\{(?i:bibtex)>(?sU:.*)\}\}'
                                     , $mode, 'plugin_yabibtex_file');
    }

    private function _prepareFile( &$file ) {
      $file = trim($file);
      $ns   = $this->getConf('bibns');
      if( substr($file,0,1) != ':' )
        $file = $this->getConf('bibns').':'.$file;
      $file = cleanID($file);
    }

    private function _prepareFiles( $files_str ) {
      $files = explode(',', $files_str);
      array_walk( $files, array($this,'_prepareFile') );
      return array_unique( $files );
    }

    public function handle($match, $state, $pos, &$handler)
    {
      if($this->helper===false)
        $this->helper =& plugin_load('helper','yabibtex');
      if(!$this->helper)
        return false;

      $args = trim(substr( $match, strlen('{{bibtex>'),-2));
      $argv = NULL;
      if( !preg_match( '/^([^&]+)((?:&[^&]+)*)\s*$/', $args, $argv ) ) {
        msg( 'BibTeX error: No bibliography file given! (\''.hsc($args).'\')', -1 );
        return false;
      }

      $data['files'] = $this->_prepareFiles($argv[1]);
      $data['flags'] = $this->helper->parseOptions($argv[2]);

      return $data;
    }

    public function render($mode, &$renderer, $data) {

        if(empty($data)) return false;

        if($mode == 'metadata' ) {
          foreach( $data['files'] as $f )
            // add file dependency for caching
            $renderer->meta['relation']['haspart'][$f]
                = @file_exists(wikiFN($f));
        }

        if($mode != 'xhtml' && $mode != 'code' ) return false;

        if($this->helper===false)
          $this->helper =& plugin_load('helper','yabibtex');
        if(!$this->helper)
          return false;

        if( $mode == 'xhtml' ) {
          foreach( $data['files'] as $i => $f )
            if(!page_exists($f)) {
              msg( 'BibTeX error: Bibliography not found \''.$f.'\'', -1 );
              unset( $data['files'][$i] ); 
            }
        }

        $this->helper->loadFiles($data['files'],$data['flags']['filter']);
        $this->helper->sort( $data['flags']['sort']  );
        $this->helper->renderBibTeX( $data['flags'], $renderer, $mode );

        if( $mode == 'xhtml' ) {
          $renderer->doc.='<div class="bibtexPageSource">';
          foreach( $data['files'] as $f )
            if( auth_quickaclcheck($f) >= ACL_READ ) {
            $renderer->internallink($f);
          }
          $renderer->doc.='</div>';
        }

        return true;
    }
}

// vim:ts=4:sw=4:et:
