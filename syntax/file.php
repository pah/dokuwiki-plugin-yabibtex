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

      $data['file']  = cleanID($this->getConf('bibns').':'.trim($argv[1]));
      $data['flags'] = $this->helper->parseOptions($argv[2]);

      return $data;
    }

    public function render($mode, &$renderer, $data) {

        if(empty($data)) return false;

        if($mode == 'metadata' ) {
          // add file dependency for caching
          $renderer->meta['relation']['haspart'][$data['file']]
            = @file_exists(wikiFN($data['file']));
        }

        if($mode != 'xhtml' && $mode != 'code' ) return false;

        $bt =& plugin_load('helper','yabibtex');
        if(!$bt) return false;

        if( $mode == 'xhtml' ) {
          if(!page_exists($data['file'])) {
            msg( 'BibTeX error: Bibliography not found \''
               .$data['file'].'\'', -1 );
            return true;
          }
        }

        $bt->loadFile(wikiFN($data['file'],$data['flags']['filter']));
        $bt->sort( $data['flags']['sort']  );
        $bt->renderBibTeX( $data['flags'], $renderer, $mode );

        if( $mode == 'xhtml' ) {
          if( auth_quickaclcheck($data['file']) >= ACL_READ ) {
            $renderer->doc.='<div class="bibtexPageSource">';
            $renderer->internallink( $data['file'], "BibTeX source" );
            $renderer->doc.='</div>';
          }
        }

        return true;
    }
}

// vim:ts=4:sw=4:et:
