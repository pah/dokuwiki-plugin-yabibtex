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

class syntax_plugin_yabibtex_inline extends DokuWiki_Syntax_Plugin
{
    var $options_pattern;

    public function syntax_plugin_yabibtex_inline() {
      $this->options_pattern='';
    }

    public function getType() {
        return 'substition';
    }

    public function getPType() {
        return 'block';
    }

    public function getSort() {
        return 818;
    }

    public function connectTo($mode) {
        $this->Lexer->addEntryPattern('<bibtex[[:space:]&]*[^>]*>',$mode,'plugin_yabibtex_inline');
    }

    public function postConnect() {
        $this->Lexer->addExitPattern('</bibtex>','plugin_yabibtex_inline');
    }

    public function handle($match, $state, $pos, &$handler){
        $data = array();

        if ($state == DOKU_LEXER_ENTER){
          $data['flags']['sort']    = '-date,citation';
          $data['flags']['abstract']= true;
          $data['flags']['bibtex']  = false;

          $flags = substr($match, strlen('<bibtex'),-1);
          if( !empty($flags) ) {
          }

          if(!$data['flags']['abstract'])
            $data['flags']['filter_raw'][] = 'abstract';

          return $data;
        } else if ($state == DOKU_LEXER_UNMATCHED) {
            $bibtex=trim($match);
            if( !empty($bibtex) )
                return array( 'bibtex' => $bibtex );
        }
        return false;
    }

    public function render($mode, &$renderer, $data) {
        $bt =& plugin_load('helper','yabibtex');
        if(!$bt) return false;

        $bt->loadString($data['bibtex']);
        $bt->sort( $data['flags']['sort'] );
        $bt->renderBibTeX( $data['flags'], $renderer, $mode );
        return true;
    }
}

// vim:ts=4:sw=4:et:
