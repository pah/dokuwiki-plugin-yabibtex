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
    var $helper = false;
    var $flags  = array();

    public function syntax_plugin_yabibtex_inline() {
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
        $this->Lexer->addEntryPattern('<bibtex[[:space:]&]*[^>]*>'
                                     , $mode, 'plugin_yabibtex_inline' );
    }

    public function postConnect() {
        $this->Lexer->addExitPattern('</bibtex>','plugin_yabibtex_inline');
    }

    public function handle($match, $state, $pos, &$handler)
    {
        if($this->helper===false)
          $this->helper =& plugin_load('helper','yabibtex');
        if(!$this->helper)
          return false;

        $data = false;

        if ($state == DOKU_LEXER_ENTER){
          $flags['sort']    = '-date,citation';
          $flags['abstract']= true;
          $flags['bibtex']  = true;

          $options = substr($match, strlen('<bibtex'),-1);
          if( !empty($options) ) {
          }

          if(!$flags['abstract'])
            $flags['filter_raw'][] = 'abstract';

          $this->flags[ $pos+strlen($match) ] = $flags;

        } else if ($state == DOKU_LEXER_UNMATCHED) {
            $bibtex=trim($match);
            if( !empty($bibtex) )
                $data = array( 'bibtex' => $bibtex
                             , 'flags' => $this->flags[$pos] );
            unset( $this->flags[$pos] );

        }
        return $data;
    }

    public function render($mode, &$renderer, $data)
    {
        if(!$this->helper)
          return false;

        $this->helper->loadString($data['bibtex']);
        $this->helper->sort( $data['flags']['sort'] );
        $this->helper->renderBibTeX( $data['flags'], $renderer, $mode );
        return true;
    }
}

// vim:ts=4:sw=4:et:
