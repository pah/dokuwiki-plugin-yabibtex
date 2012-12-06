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
	protected $username;
	
	function __construct($family_name, $given_name = false) {
		$this->family_name = $family_name;
		$this->given_name = $given_name;
		$this->username = NULL;
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
	public function printFormatted( $raw_bibtex = false
	                              , $with_abstract = false)
	{
		$id = get_class($this).'-'
		     .preg_replace('/[^A-Za-z0-9_-]/', '_', $this->citation);
		print '<div class="bibtexEntry" id="'.$id.'">';
		print '<p>';
		$this->printEntry();
		if ($with_abstract && !empty($this->fields['abstract'])) {
			print '<a href="#bibtexAbstract_'.$id.'" title="Show abstract" class="bibtexLink folder">Abstract</a>';
		}
		if ($raw_bibtex) {
			print '<a href="#bibtexCode_'.$id.'" title="Show BibTeX source" class="bibtexLink folder">BibTeX</a>';
		}
		print '</p>';

		if ($with_abstract)
			$this->printAbstract($id, $this->fields);

		if ($raw_bibtex) {
			print '<div class="bibtexCode folded hidden" id="bibtexCode_'.$id.'">';
			print $raw_bibtex;
			print '</div>';
		}
		print '</div>';
	}
	
	private static function translateOrdinal(&$entry, $field) {
		if (isset($entry[$field])) {
			if (($value = get_ordinal_standard_name($entry[$field])) !== false) {
				$entry[$field] = BibliographyParser::_($value);
			}
		}
	}

	protected static function printCreators($list, $class) {
		if( $list->isEmpty() )
			return false;
		print '<span class="'.$class.'">'.$list.'.</span> ';
		return true;
	}

	protected static function printLink($entry,$field,&$usecomma) {
		if (isset($entry[$field])) {
			if( $field == 'doi' ) {
				$entry[$field] = 'http://dx.doi.org/'.$entry[$field];
			}
			if ($usecomma) {
				print ', ';
			}
			print '<a class="bibtexLink urlextern" target="_blank" href="'
			      .$entry[$field].'">'.$field.'</a>';
			$usecomma = false;
		}
	}

	protected static function printDot(&$usecomma) {
		if ($usecomma) {
			print '.';
		}
		$usecomma = false;
	}

	protected static function printField($entry, $field, &$usecomma) {
		if (isset($entry[$field])) {
			if ($usecomma) {
				print ',';
			}
			print ' '.$entry[$field];
			$usecomma = true;
		}
	}
	
	private static function printFormattedField($entry, $field, &$usecomma) {
		if (isset($entry[$field])) {
			$stringkey = $field;
			$formatkey = $field.'_format';
			if ($usecomma) {
				print ', ';
				BibliographyParser::printf($formatkey, $entry[$field], BibliographyParser::_($stringkey));
			} else {
				print ' ';
				print ucfirst(BibliographyParser::sprintf($formatkey, $entry[$field], BibliographyParser::_($stringkey)));
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
			print $usecomma ? ', ' : ' ';
			BibliographyParser::printf('pagerange_format', $entry['pages'], ctype_digit($entry['pages']) ? BibliographyParser::_('page') : BibliographyParser::_('pages'));
			$usecomma = true;
		}
	}
	
	protected static function printDate($entry, &$usecomma) {
		if (isset($entry['year'])) {
			print $usecomma ? ', ' : ' ';
			if (isset($entry['month']) && ($month = get_month_standard_name($entry['month'])) !== false) {
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
			print '<div class="bibtexAbstract folded hidden" '.
			           'id="bibtexAbstract_'.$id.'">'
			     .'<h1>'.BibliographyParser::_('abstract').'</h1>'
			     .'<p>'.latex2plain($entry['abstract']).'</p>'
			     .'</div>';
		}
	}
	
	/**
	* Outputs a single raw BibTeX entry.
	* @param entry An associative array representing a parsed BibTeX entry returned by BibTexParser.
	*/
	public static function getRaw($entry) {
		$entry_type = $entry['bibtexEntryType'];
		$entry_citation = $entry['bibtexCitation'];
		unset($entry['bibtexEntryType']);
		unset($entry['bibtexCitation']);
		$entry_type = $this->entry_type;
		$entry_citation = $this->citation;
		
		ob_start();
		print "@{$entry_type}{{$entry_citation}";
		foreach ($entry as $key => $value) {
			print ",\n\t".$key.' = ';
			if (ctype_digit($value)) {
				print $value;  // no need to escape integers
			} elseif (strpos($value, '@') !== false) {
				print '"'.str_replace('"', '{"}', $value).'"';
			} elseif (strpos($value, '"') !== false) {
				print '{'.$value.'}';
			} else {
				print '"'.$value.'"';
			}
		}
		print "\n}\n";
		return ob_get_clean();
	}
}

class ArticleEntry extends Entry {
	public function printEntry() {
                // TODO: add more styles (PAH)
		$e =& $this->fields;
		$this->printCreators($this->authors, 'bibtexAuthors');
		print '<span class="bibtexTitle">'.$e['title'].'.</span> ';
    print '<span class="bibtexJournal">'.$e['journal'].'</span>';
		if (isset($e['volume']) && isset($e['number'])) {
			print ' '.$e['volume'].'('.$e['number'].')';
		} elseif (isset($e['volume'])) {
			print ' '.$e['volume'];
		} elseif (isset($e['number'])) {
			print ' ('.$e['number'].')';
		}
		$usecomma = true;
		if (isset($e['pages'])) {
			if (isset($e['volume']) || isset($e['number'])) {
				print ':'.$e['pages'];
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
		print '<span class="bibtexTitle">'.$e['title'].'.</span> ';
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
		print '<span class="bibtexTitle">'.$e['title'].'.</span> ';
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
		print '<span class="bibtexTitle">'.$e['title'].'.</span> ';
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
		print '<span class="bibtexTitle">'.$e['title'].'.</span> ';
		$usecomma = false;
		if (isset($e['booktitle'])) {
			print ' In ';
			$this->printCreators($this->editors, 'bibtexEditors');
			print '<span class="bibtexBooktitle">'.$e['booktitle'].'</span>.';
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
		print '<span class="bibtexTitle">'.$e['title'].'.</span> ';
		$usecomma = false;
		if (isset($e['booktitle'])) {
			print ' In ';
			$this->printCreators($this->editors, 'bibtexEditors');
			print '<span class="bibtexBooktitle">'.$e['booktitle'];
			if (isset($e['series'])) {
				print ' ('.$e['series'].')';
			}
			print '</span>';
			if (isset($e['volume']) && isset($e['number'])) {
				print ' '.$e['volume'].'('.$e['number'].')';
			} elseif (isset($e['volume'])) {
				print ' '.$e['volume'];
			} elseif (isset($e['number'])) {
				print ' ('.$e['number'].')';
			}
			print '.';
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
		print '<span class="bibtexTitle">'.$e['title'].'.</span> ';
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
		print '<span class="bibtexTitle">'.$e['title'].'.</span> ';
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
		print '<span class="bibtexTitle">'.$e['title'].'.</span> ';
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
		print '<span class="bibtexTitle">'.$e['title'].'.</span> ';
		if (isset($e['volume']) && isset($e['number'])) {
			print ' '.$e['volume'].'('.$e['number'].').';
		} elseif (isset($e['volume'])) {
			print ' '.$e['volume'];
			print '.';
		} elseif (isset($e['number'])) {
			print ' ('.$e['number'].').';
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
		print '<span class="bibtexTitle">'.$e['title'].'.</span> ';
		$usecomma = false;
		if (isset($e['type'])) {
			print $usecomma ? ', ' : ' ';
			if (isset($e['number'])) {
				print $e['type'].' '.$e['number'];
			} else {
				print $e['type'];
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
		print '<span class="bibtexTitle">'.$e['title'].'.</span> ';
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
	public static function filterRaw( &$bibtex_entry ) {
		unset($bibtex_entry['bibtexCitation']);
		unset($bibtex_entry['bibtexEntryType']);

		$filter = BibliographyParser::$plugin->filter_raw;
		foreach( $filter as $f )
			unset( $bibtex_entry[$f] );
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
			call_user_func_array('printf', $args);
		}
	}

	public static function renderUser( $username, $full_name ) {
		list( $page_id, $title ) = BibliographyParser::$user[$username];
		$class = 'wikilink1';

		if( !empty($page_id) )
			return '<a href="'.wl($page_id)
			        .'" class="'.$class.'" title="'.$title.'">'
			        .$full_name.'</a>';
		return $full_name;
	}
}
