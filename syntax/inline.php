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
        $this->Lexer->addEntryPattern('<bibtex[^>]*>',$mode,'plugin_yabibtex_inline');
    }

    public function postConnect() {
        $this->Lexer->addExitPattern('</bibtex>','plugin_yabibtex_inline');
    }

    public function handle($match, $state, $pos, &$handler){
        $data = array();

        if ($state == DOKU_LEXER_ENTER){
            $options = substr($match, strlen('<bibtex'),-1);
            if( !empty($options) )
                return array( 'options' => $options );
        } else if ($state == DOKU_LEXER_UNMATCHED) {
            $bibtex=trim($match);
            if( !empty($bibtex) )
                return array( 'bibtex' => $bibtex );
        }
        return false;
    }

    public function render($mode, &$renderer, $data) {
        if($mode != 'xhtml') return false;

        $bt =& plugin_load('helper','yabibtex');
        if(!$bt) return false;

        $data['parsed'] = $bt->loadString($data['bibtex']);

//  if(!plugin_isdisabled('tag')) {
//            $tag =& plugin_load('helper', 'tag');
//            $entries = $tag->tagRefine($entries, $refine);
//        }

        $body = '<code bibtex>'.$data['bibtex'].'</code>';
        $renderer->doc.=$bt->render( $body );
        return true;
    }
}

// vim:ts=4:sw=4:et:
