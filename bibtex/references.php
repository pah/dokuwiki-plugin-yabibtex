<?php
/**
* @file
* @brief    BibTeX bibliography formatter Joomla plug-in
* @author   Levente Hunyadi
* @version  1.1.1
* @remarks  Copyright (C) 2009-2011 Levente Hunyadi
* @remarks  Licensed under GNU/GPLv3, see http://www.gnu.org/licenses/gpl-3.0.html
* @see      http://hunyadi.info.hu/projects/bibtex
*/

// no direct access
defined( 'DOKU_PLUGIN_YABIBTEX' ) or die( 'Restricted access' );

require_once DOKU_PLUGIN_YABIBTEX.'bibtex/latex.php';

/**
* Creator (i.e. author or editor) of a piece in a bibliography entry.
*/
class EntryCreator {
	protected $given_name;
	protected $family_name;
	protected $userinfo;
	
	function __construct($family_name, $given_name = false) {
		$this->family_name = $family_name;
		$this->given_name = $given_name;
		$this->userinfo = NULL;
	}

	/**
	* Produces the full name of the creator.
	* @return A language-specific full name.
	*/
	function __toString() {
		return $this->formatted();
	}

	function addInfo( $info ) {
		$this->userinfo = $info;
	}

	function xhtml() {
		return $this->formatted('xhtml');
	}

	function formatted( $mode = 'plain' ) {
		if ($this->given_name) {
			$full_name = BibliographyParser::sprintf
			             ('name_format', $this->family_name, $this->given_name);
			if (!$full_name) {
				/* language string missing, default to Western name order */
				$full_name = $this->given_name.' '.$this->family_name;
			}
		} else {
			$full_name = $this->family_name;
		}

		if ($mode == 'plain')
			return $full_name;

		return BibliographyParser::formatUser( $this->userinfo, $full_name );
	}
}

/**
* A list of creators (i.e. authors or editors) of a piece in a bibliography entry.
*/
class EntryCreatorList {
	public $creators = array();
	
	public function add(EntryCreator $creator) {
		$this->creators[] = $creator;
	}
	
	public function addList(EntryCreatorList $creators) {
		$this->creators = array_merge($this->creators, $creators->creators);
	}
	
	public function isEmpty() {
		return count($this->creators) == 0;
	}

	public function formatted( $mode='plain', $suffix_l10n='' )
	{
		$suffix_s = '';
		$suffix_p = '';
		if(!empty($suffix_l10n)) {
			$suffix_s = ' ('.BibliographyParser::_($suffix_l10n).')';
			$suffix_p = ' ('.BibliographyParser::_($suffix_l10n.'s').')';
		}

		switch (count($this->creators)) {
			case 0:
				return '';
			case 1:
				return $this->creators[0]->formatted($mode).$suffix_s;
			default:
				$s = $this->creators[0]->formatted($mode);
				for ($k = 1; $k < count($this->creators) - 1; $k++) {
					$s .= ', '.$this->creators[$k]->formatted($mode);
				}
				$s .= ' '.BibliographyParser::_('and')
				     .' '.end($this->creators)->formatted($mode).$suffix_p;
				return $s;
		}
	}
}

class EntryAuthorList extends EntryCreatorList {
	/** 
	* Produces a formatted author field.
	* @return A properly delimited language-specific author list text.
	*/
	function __toString() {
		return $this->formatted( 'plain' );
	}

	function xhtml() {
		return $this->formatted( 'xhtml' );
	}
}

class EntryEditorList extends EntryCreatorList {
	/**
	* Produces a formatted editor field.
	* @return A properly delimited language-specific editor list text.
	*/
	function __toString() {
		return $this->formatted( 'plain', 'editor' );
	}

	function xhtml() {
		return $this->formatted( 'xhtml', 'editor' );
	}
}

class EntryPageRange {
	private $start = false;
	private $end = false;

	function __construct($start_page, $end_page = false) {
		$this->start = $start_page;
		$this->end = $end_page;
	}
}

class Entry {
	/**
	* Citation key. A unique identifier used to tag the article.
	*/
	public $citation = false;
	
	/**
	* A list of (unrecognized) fields attached to the bibliography entry.
	*/
	protected $fields = array();

	public $authors;
	public $editors;

	function __construct() {
		$this->authors = new EntryAuthorList();
		$this->editors = new EntryEditorList();
	}
	
	function __set($key, $value) {
		$this->fields[$key] = $value;
	}
	
	function __get($key) {
		if (isset($this->fields[$key])) {
			return $this->fields[$key];
		} else {
			return null;
		}
	}
	
	public function addAuthor(EntryCreator $author) {
		$this->authors->add($author);
	}
	
	public function addAuthors(EntryAuthorList $authors) {
		$this->authors->addList($authors);
	}

	public function addEditor(EntryCreator $editor) {
		$this->editors->add($editor);
	}
	
	public function addEditors(EntryEditorList $editors) {
		$this->editors->addList($editors);
	}
	
	/**
	* Produces a formatted bibliography entry for HTML output.
	* @return A human-readable bibliography reference.
	*/
	public function printFormatted( $flags = array() )
	{
		$id = get_class($this).'-'
		     .preg_replace('/[^A-Za-z0-9_-]/', '_', $this->citation);
		$this->printString('<div class="bibtexEntry '.$flags['class'].'" id="'.$id.'">' );
		$this->printString('<p>');
		$this->printEntry();
		if ($flags['abstract'] && !empty($this->fields['abstract'])) {
			$this->printString('<a href="#bibtexAbstract_'.$id.'" title="Show abstract" class="bibtexLink folder">Abstract</a>');
		}
		if ($flags['bibtex']) {
			$this->printString('<a href="#bibtexCode_'.$id.'" title="Show BibTeX source" class="bibtexLink folder">BibTeX</a>');
		}
		$this->printString('</p>');

		if ($flags['abstract'])
			$this->printAbstract($id, $this->fields);

		if ($flags['bibtex']) {
			$this->printString('<div class="bibtexCode folded hidden" id="bibtexCode_'.$id.'">');
			$bibfilename = preg_replace( '/[^A-Za-z0-9_-]/', '_'
                                 , trim($this->citation) ).'.bib';
			BibliographyParser::printCode( $this->getRaw($flags['filter_raw']), $bibfilename );
			$this->printString('</div>');
		}
		$this->printString('</div>');
	}
	
	private static function translateOrdinal(&$entry, $field) {
		if (isset($entry[$field])) {
			if (($value = get_ordinal_standard_name($entry[$field])) !== false) {
				$entry[$field] = BibliographyParser::_($value);
			}
		}
	}

	protected static function printString( $str ) {
		BibliographyParser::printString( $str );
	}

	protected static function printCreators($list, $class) {
		if( $list->isEmpty() )
			return false;
		Entry::printString('<span class="'.$class.'">'
		                      .$list->xhtml().'.</span> ');
		return true;
	}

	protected static function printLink($entry,$field,&$usecomma) {
		if (isset($entry[$field])) {
			if( $field == 'doi' ) {
				$entry[$field] = 'http://dx.doi.org/'.$entry[$field];
			}
			if ($usecomma) {
				Entry::printString(', ');
			}
			Entry::printString('<a class="bibtexLink urlextern" target="_blank" href="'
			      .$entry[$field].'">'.$field.'</a>');
			$usecomma = false;
		}
	}

	protected static function printDot(&$usecomma) {
		if ($usecomma) {
			Entry::printString('.');
		}
		$usecomma = false;
	}

	protected static function printField($entry, $field, &$usecomma) {
		if (isset($entry[$field])) {
			if ($usecomma) {
				Entry::printString(',');
			}
			Entry::printString(' '.$entry[$field]);
			$usecomma = true;
		}
	}
	
	private static function printFormattedField($entry, $field, &$usecomma) {
		if (isset($entry[$field])) {
			$stringkey = $field;
			$formatkey = $field.'_format';
			if ($usecomma) {
				Entry::printString(', ');
				BibliographyParser::printf($formatkey, $entry[$field], BibliographyParser::_($stringkey));
			} else {
				Entry::printString(' ');
				Entry::printString(ucfirst(BibliographyParser::sprintf($formatkey, $entry[$field], BibliographyParser::_($stringkey))));
			}
			$usecomma = true;
		}
	}

	protected static function printSeries($entry, &$usecomma) {
		Entry::translateOrdinal($entry, 'series');
		Entry::printFormattedField($entry, 'series',  $usecomma);
	}

	protected static function printEdition($entry, &$usecomma) {
		Entry::translateOrdinal($entry, 'edition');
		Entry::printFormattedField($entry, 'edition', $usecomma);
	}

	protected static function printVolume($entry, &$usecomma) {
		Entry::printFormattedField($entry, 'volume',  $usecomma);
	}

	protected static function printNumber($entry, &$usecomma) {
		Entry::printFormattedField($entry, 'number',  $usecomma);
	}

	protected static function printChapter($entry, &$usecomma) {
		Entry::printFormattedField($entry, 'chapter', $usecomma);
	}

	protected static function printPages($entry, &$usecomma) {
		if (isset($entry['pages'])) {
			Entry::printString($usecomma ? ', ' : ' ');
			BibliographyParser::printf('pagerange_format', $entry['pages'], ctype_digit($entry['pages']) ? BibliographyParser::_('page') : BibliographyParser::_('pages'));
			$usecomma = true;
		}
	}
	
	protected static function printDate($entry, &$usecomma) {

		if (isset($entry['year'])) {
			Entry::printString($usecomma ? ', ' : ' ');
			if (isset($entry['month']) &&
			   ($month = get_month_standard_name($entry['month'])) !== false)
			{
				BibliographyParser::printf('date_format_yearmonth', $entry['year']
				                          , BibliographyParser::_($month) );
			} else {
				BibliographyParser::printf('date_format_year', $entry['year']);
			}
			$usecomma = true;
		}
	}

	protected static function printAbstract( $id, $entry ) {
		if (isset($entry['abstract'])) {
			Entry::printString('<div class="bibtexAbstract folded hidden" '.
			           'id="bibtexAbstract_'.$id.'">'
			     .'<h1>'.BibliographyParser::_('abstract').'</h1>'
			     .'<p>'.latex2plain($entry['abstract']).'</p>'
			     .'</div>');
		}
	}
	
	/**
	* returns a single raw BibTeX entry.
	*/
	public function getRaw( $filter=array() ) {
		$entry_type = $this->entry_type;
		$entry_citation = $this->citation;
		
		$raw = $this->raw_fields;
		BibliographyParser::filterRaw( $raw, $filter );

		ob_start();
		print "@{$entry_type}{{$entry_citation}" ;
		foreach ($raw as $key => $value) {
			print ",\n\t".$key.' = ' ;
			if (ctype_digit($value)) {
				print $value ;  // no need to escape integers
			} elseif (strpos($value, '@') !== false) {
				print '"'.str_replace('"', '{"}', $value).'"' ;
			} elseif (strpos($value, '"') !== false) {
				print '{'.$value.'}' ;
			} else {
				print '"'.$value.'"' ;
			}
		}
		print "\n}\n" ;
		return ob_get_clean();
	}
}

class ArticleEntry extends Entry {
	public function printEntry() {
	
		$e =& $this->fields;
		$this->printCreators($this->authors, 'bibtexAuthors');
		$this->printString('<span class="bibtexTitle">'.$e['title'].'.</span> ');
		$this->printString('<span class="bibtexJournal">'.$e['journal'].'</span>');
		if (isset($e['volume']) && isset($e['number'])) {
			$this->printString(' '.$e['volume'].'('.$e['number'].')');
		} elseif (isset($e['volume'])) {
			$this->printString(' '.$e['volume']);
		} elseif (isset($e['number'])) {
			$this->printString(' ('.$e['number'].')');
		}
		$usecomma = true;
		if (isset($e['pages'])) {
			if (isset($e['volume']) || isset($e['number'])) {
				$this->printString(':'.$e['pages']);
			} else {
				$this->printPages($e, $usecomma);
			}
		}
		$this->printDate($e, $usecomma);
		$this->printDot($usecomma);
		$this->printField($e, 'note', $usecomma);
		$this->printDot($usecomma);
		$this->printLink($e,'file',$usecomma);
		$this->printLink($e,'url',$usecomma);
		$this->printLink($e,'doi',$usecomma);
	}
}

class BookEntry extends Entry {
	public function printEntry() {
		$e =& $this->fields;
		if (!$this->printCreators($this->editors, 'bibtexEditors') )
			$this->printCreators($this->authors, 'bibtexAuthors');
		$this->printString('<span class="bibtexTitle">'.$e['title'].'.</span> ');
		$usecomma = false;
		$this->printEdition($e, $usecomma);
		$this->printSeries($e, $usecomma);
		$this->printVolume($e, $usecomma);
		$this->printNumber($e, $usecomma);
		$this->printField($e, 'publisher', $usecomma);
		$this->printField($e, 'address', $usecomma);
		$this->printDate($e, $usecomma);
		$this->printDot($usecomma);
		$this->printField($e, 'note', $usecomma);
		$this->printDot($usecomma);
		$this->printLink($e,'file',$usecomma);
		$this->printLink($e,'url',$usecomma);
		$this->printLink($e,'doi',$usecomma);
	}
}

class BookletEntry extends Entry {
	public function printEntry() {
		$e =& $this->fields;
		$this->printCreators($this->authors, 'bibtexAuthors');
		$this->printString('<span class="bibtexTitle">'.$e['title'].'.</span> ');
		$usecomma = false;
		$this->printField($e, 'howpublished', $usecomma);
		$this->printField($e, 'address', $usecomma);
		$this->printDate($e, $usecomma);
		$this->printDot($usecomma);
		$this->printField($e, 'note', $usecomma);
		$this->printDot($usecomma);
		$this->printLink($e,'file',$usecomma);
		$this->printLink($e,'url',$usecomma);
		$this->printLink($e,'doi',$usecomma);
	}
}

class InBookEntry extends Entry {
	public function printEntry() {
		$e =& $this->fields;
		if (!$this->printCreators($this->editors, 'bibtexEditors') )
			$this->printCreators($this->authors, 'bibtexAuthors');
		$this->printString('<span class="bibtexTitle">'.$e['title'].'.</span> ');
		$usecomma = false;
		$this->printField($e, 'type', $usecomma);
		$this->printSeries($e, $usecomma);
		$this->printVolume($e, $usecomma);
		$this->printNumber($e, $usecomma);
		$this->printChapter($e, $usecomma);
		$this->printPages($e, $usecomma);
		$this->printField($e, 'publisher', $usecomma);
		$this->printField($e, 'address', $usecomma);
		$this->printDate($e, $usecomma);
		$this->printDot($usecomma);
		$this->printField($e, 'note', $usecomma);
		$this->printDot($usecomma);
		$this->printLink($e,'file',$usecomma);
		$this->printLink($e,'url',$usecomma);
		$this->printLink($e,'doi',$usecomma);
	}
}

class InCollectionEntry extends Entry {
	public function printEntry() {
		$e =& $this->fields;
		$this->printCreators($this->authors, 'bibtexAuthors');
		$this->printString('<span class="bibtexTitle">'.$e['title'].'.</span> ');
		$usecomma = false;
		if (isset($e['booktitle'])) {
			$this->printString(' In ');
			$this->printCreators($this->editors, 'bibtexEditors');
			$this->printString('<span class="bibtexBooktitle">'.$e['booktitle'].'</span>.');
		}
		$this->printSeries($e, $usecomma);
		$this->printVolume($e, $usecomma);
		$this->printNumber($e, $usecomma);
		$this->printField($e, 'publisher', $usecomma);
		$this->printDate($e, $usecomma);
		$this->printPages($e, $usecomma);
		$this->printDot($usecomma);
		$this->printField($e, 'note', $usecomma);
		$this->printDot($usecomma);
		$this->printLink($e,'file',$usecomma);
		$this->printLink($e,'url',$usecomma);
		$this->printLink($e,'doi',$usecomma);
	}
}

class ProceedingsPaperEntry extends Entry {
	public function printEntry() {
		$e =& $this->fields;
		$this->printCreators($this->authors, 'bibtexAuthors');
		$this->printString('<span class="bibtexTitle">'.$e['title'].'.</span> ');
		$usecomma = false;
		if (isset($e['booktitle'])) {
			$this->printString(' In ');
			$this->printCreators($this->editors, 'bibtexEditors');
			$this->printString('<span class="bibtexBooktitle">'.$e['booktitle']);
			if (isset($e['series'])) {
				$this->printString(' ('.$e['series'].')');
			}
			$this->printString('</span>');
			if (isset($e['volume']) && isset($e['number'])) {
				$this->printString(' '.$e['volume'].'('.$e['number'].')');
			} elseif (isset($e['volume'])) {
				$this->printString(' '.$e['volume']);
			} elseif (isset($e['number'])) {
				$this->printString(' ('.$e['number'].')');
			}
			$this->printString('.');
		}
		$this->printField($e, 'location', $usecomma);
		$this->printDate($e, $usecomma);
		$this->printPages($e, $usecomma);
		$this->printDot($usecomma);
		$this->printField($e, 'note', $usecomma);
		$this->printDot($usecomma);
		$this->printLink($e,'file',$usecomma);
		$this->printLink($e,'url',$usecomma);
		$this->printLink($e,'doi',$usecomma);
	}
}

class ManualEntry extends Entry {
	public function printEntry() {
		$e =& $this->fields;
		$this->printCreators($this->authors, 'bibtexAuthors');
		$this->printString('<span class="bibtexTitle">'.$e['title'].'.</span> ');
		$usecomma = false;
		$this->printField($e, 'organization', $usecomma);
		$this->printField($e, 'address', $usecomma);
		$this->printEdition($e, $usecomma);
		$this->printDate($e, $usecomma);
		$this->printDot($usecomma);
		$this->printField($e, 'note', $usecomma);
		$this->printDot($usecomma);
		$this->printLink($e,'file',$usecomma);
		$this->printLink($e,'url',$usecomma);
		$this->printLink($e,'doi',$usecomma);
	}
}

class ThesisEntry extends Entry {
	public function printEntry() {
		$e =& $this->fields;
		$this->printCreators($this->authors, 'bibtexAuthors');
		$this->printString('<span class="bibtexTitle">'.$e['title'].'.</span> ');
		$usecomma = false;
		$this->printField($e, 'type', $usecomma);
		$this->printField($e, 'school', $usecomma);
		$this->printField($e, 'address', $usecomma);
		$this->printDate($e, $usecomma);
		$this->printDot($usecomma);
		$this->printField($e, 'note', $usecomma);
		$this->printDot($usecomma);
		$this->printLink($e,'file',$usecomma);
		$this->printLink($e,'url',$usecomma);
		$this->printLink($e,'doi',$usecomma);
	}
}

class MastersThesisEntry extends ThesisEntry {}

class PhdThesisEntry extends ThesisEntry {}

class MiscellaneousEntry extends Entry {
	public function printEntry() {
		$e =& $this->fields;
		$this->printCreators($this->authors, 'bibtexAuthors');
		$this->printString('<span class="bibtexTitle">'.$e['title'].'.</span> ');
		$usecomma = false;
		$this->printField($e, 'howpublished', $usecomma);
		$this->printDate($e, $usecomma);
		$this->printDot($usecomma);
		$this->printField($e, 'note', $usecomma);
		$this->printDot($usecomma);
		$this->printLink($e,'file',$usecomma);
		$this->printLink($e,'url',$usecomma);
		$this->printLink($e,'doi',$usecomma);
	}
}

class ProceedingsEntry extends Entry {
	public function printEntry() {
		$e =& $this->fields;
		$this->printCreators($this->editors, 'bibtexEditors');
		$this->printString('<span class="bibtexTitle">'.$e['title'].'.</span> ');
		if (isset($e['volume']) && isset($e['number'])) {
			$this->printString(' '.$e['volume'].'('.$e['number'].').');
		} elseif (isset($e['volume'])) {
			$this->printString(' '.$e['volume']);
			$this->printString('.');
		} elseif (isset($e['number'])) {
			$this->printString(' ('.$e['number'].').');
		}
		$usecomma = false;
		$this->printField($e, 'organization', $usecomma);
		$this->printField($e, 'publisher', $usecomma);
		$this->printField($e, 'address', $usecomma);
		$this->printDate($e, $usecomma);
		$this->printDot($usecomma);
		$this->printField($e, 'note', $usecomma);
		$this->printDot($usecomma);
		$this->printLink($e,'file',$usecomma);
		$this->printLink($e,'url',$usecomma);
		$this->printLink($e,'doi',$usecomma);
	}
}

class TechnicalReportEntry extends Entry {
	public function printEntry() {
		$e =& $this->fields;
		$this->printCreators($this->authors, 'bibtexAuthors');
		$this->printString('<span class="bibtexTitle">'.$e['title'].'.</span> ');
		$usecomma = false;
		if (isset($e['type'])) {
			$this->printString($usecomma ? ', ' : ' ');
			if (isset($e['number'])) {
				$this->printString($e['type'].' '.$e['number']);
			} else {
				$this->printString($e['type']);
			}
			$usecomma = true;
		} elseif (isset($e['number'])) {
			$this->printNumber($e, $usecomma);
		}
		$this->printField($e, 'institution', $usecomma);
		$this->printDate($e, $usecomma);
		$this->printDot($usecomma);
		$this->printField($e, 'note', $usecomma);
		$this->printDot($usecomma);
		$this->printLink($e,'file',$usecomma);
		$this->printLink($e,'url',$usecomma);
		$this->printLink($e,'doi',$usecomma);
	}
}

class UnpublishedEntry extends Entry {
	public function printEntry() {
		$e =& $this->fields;
		$this->printCreators($this->authors, 'bibtexAuthors');
		$this->printString('<span class="bibtexTitle">'.$e['title'].'.</span> ');
		$usecomma = false;
		$this->printField($e, 'note', $usecomma);
		$this->printDate($e, $usecomma);
		$this->printDot($usecomma);
		$this->printLink($e,'file',$usecomma);
		$this->printLink($e,'url',$usecomma);
		$this->printLink($e,'doi',$usecomma);
	}
}

/**
 * Base class for bibliography parsers.
 *
 * 
 */
class BibliographyParser {

	/** localization array -- initialized from helper plugin */
	public static $lang = NULL;
	/** configuration array -- initialized from helper plugin */
	public static $conf = NULL;
	/** user array -- initialized from helper plugin */
	public static $users = NULL;
	/** DokuWiki helper plugin reference */
	public static $plugin = NULL;
	/** DokuWiki current renderer reference */
	public static $renderer = NULL;


	public static function filterRaw( &$bibtex_entry, $filter=NULL ) {
		unset($bibtex_entry['bibtexCitation']);
		unset($bibtex_entry['bibtexEntryType']);

		if( !is_array($filter) )
			return false;

		foreach( $filter as $f )
			unset( $bibtex_entry[$f] );
		return true;
	}

	public static function _( $str ) {
		if( !empty(BibliographyParser::$lang[$str]) )
			$str=BibliographyParser::$lang[$str];
		return $str;
	}

	public static function sprintf( $format ) {
		$args = func_get_args();
		if (count($args) > 0) {
			$args[0] = BibliographyParser::$lang[$args[0]];
			call_user_func_array('sprintf', $args);
		}
		return '';
	}

	public static function printf( $format ) {
		$args = func_get_args();
		if (count($args) > 0) {
			$args[0] = BibliographyParser::$lang[$args[0]];
			BibliographyParser::printString(call_user_func_array('sprintf', $args));
		}
	}

	public static function printString( $string ) {
		if( !is_null(BibliographyParser::$renderer) )
			BibliographyParser::$renderer->doc .= $string;
	}

	public static function printCode( $raw, $filename='', $lang='bibtex' ) {
		if( !is_null(BibliographyParser::$renderer) )
			BibliographyParser::$renderer->code( $raw, $lang, $filename );
	}

	public static function printFile() {}

	public static function formatUser( $userinfo, $full_name ) {

		if( !empty($userinfo)  )
			$local = 'bibtexKnown';

		$result='<span class="bibtexAuthor '.$local.'">';
		if( !empty($userinfo['page']) ) {
			$class = 'wikilink1';
			$result .='<a href="'.wl($userinfo['page'])
			         .'" class="'.$class.'" title="'.$userinfo['title'].'">'
			         .$full_name.'</a>';
		} else {
			$result .= $full_name;
		}
		$result.='</span>';

		return $result;
	}
}
