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
	
	function __construct($family_name, $given_name = false) {
		$this->family_name = $family_name;
		$this->given_name = $given_name;
	}

	/**
	* Produces the full name of the creator.
	* @return A language-specific full name.
	*/
	function __toString() {
		if ($this->given_name) {
			$full_name = JText::sprintf('BIBTEX_NAME_FORMAT', $this->family_name, $this->given_name);
			if (!$full_name) {  /* language string missing, default to Western name order */
				$full_name = $this->given_name.' '.$this->family_name;
			}
			return $full_name;
		} else {
			return $this->family_name;
		}
	}
}

/**
* A list of creators (i.e. authors or editors) of a piece in a bibliography entry.
*/
class EntryCreatorList {
	protected $creators = array();
	
	public function add(EntryCreator $creator) {
		$this->creators[] = $creator;
	}
	
	public function addList(EntryCreatorList $creators) {
		$this->creators = array_merge($this->creators, $creators->creators);
	}
	
	public function isEmpty() {
		return count($this->creators) == 0;
	}
}

class EntryAuthorList extends EntryCreatorList {
	/** 
	* Produces a formatted author field.
	* @return A properly delimited language-specific author list text.
	*/
	function __toString() {
		switch (count($this->creators)) {
			case 0:
				return '';
			case 1:
				return (string) $this->creators[0];
			default:
				$s = (string) $this->creators[0];
				for ($k = 1; $k < count($this->creators) - 1; $k++) {
					$s .= ', '.$this->creators[$k];
				}
				$s .= ' '.JText::_('BIBTEX_AND').' '.end($this->creators);
				return $s;
		}
	}
}

class EntryEditorList extends EntryCreatorList {
	/**
	* Produces a formatted editor field.
	* @return A properly delimited language-specific editor list text.
	*/
	function __toString() {
		switch (count($this->creators)) {
			case 0:
				return '';
			case 1:
				return $this->creators[0].' ('.JText::_('BIBTEX_EDITOR').')';
			default:
				$s = (string) $this->creators[0];
				for ($k = 1; $k < count($this->creators) - 1; $k++) {
					$s .= ', '.$this->creators[$k];
				}
				$s .= ' '.JText::_('BIBTEX_AND').' '.end($this->creators).' ('.JText::_('BIBTEX_EDITORS').')';
				return $s;
		}
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

	protected $authors;
	protected $editors;

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
	public function printFormatted($raw_bibtex = false, $raw_caption = false) {
		$id = get_class($this).'-'.preg_replace('/[^A-Za-z0-9_-]/', '_', $this->citation);
		print '<div class="bibtexEntry" id="'.$id.'">';
		print '<p>';
		$this->printEntry();
		if ($raw_bibtex) {
			print ' [<a href="#" class="bibtexLink">'.($raw_caption ? $raw_caption : 'bib').'</a>]';
		}
		print '</p>';
		if ($raw_bibtex) {
			print '<pre class="bibtexCode">';
			print $raw_bibtex;
			print '</pre>';
		}
		print '</div>';
	}
	
	private static function translateOrdinal(&$entry, $field) {
		if (isset($entry[$field])) {
			if (($value = get_ordinal_standard_name($entry[$field])) !== false) {
				$entry[$field] = JText::_($value);
			}
		}
	}

	protected static function printLink($entry,$field,&$usecomma) {
		if (isset($entry[$field])) {
			if( $field == 'doi' ) {
				$entry[$field] = 'http://dx.doi.org/'.$entry[$field];
			}
			if ($usecomma) {
				print ', ';
			}
			print ' [<a class="bibtexLink" target="_blank" href="'
			      .$entry[$field].'">'.$field.'</a>]';
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
	
	private static function printFormattedField($entry, $field, $formatkey, $stringkey, &$usecomma) {
		if (isset($entry[$field])) {
			if ($usecomma) {
				print ', ';
				JText::printf($formatkey, $entry[$field], JText::_($stringkey));
			} else {
				print ' ';
				print ucfirst(JText::sprintf($formatkey, $entry[$field], JText::_($stringkey)));
			}
			$usecomma = true;
		}
	}

	protected static function printSeries($entry, &$usecomma) {
		Entry::translateOrdinal($entry, 'series');
		Entry::printFormattedField($entry, 'series', 'BIBTEX_SERIES_FORMAT', 'BIBTEX_SERIES', $usecomma);
	}

	protected static function printEdition($entry, &$usecomma) {
		Entry::translateOrdinal($entry, 'edition');
		Entry::printFormattedField($entry, 'edition', 'BIBTEX_EDITION_FORMAT', 'BIBTEX_EDITION', $usecomma);
	}

	protected static function printVolume($entry, &$usecomma) {
		Entry::printFormattedField($entry, 'volume', 'BIBTEX_VOLUME_FORMAT', 'BIBTEX_VOLUME', $usecomma);
	}

	protected static function printNumber($entry, &$usecomma) {
		Entry::printFormattedField($entry, 'number', 'BIBTEX_NUMBER_FORMAT', 'BIBTEX_NUMBER', $usecomma);
	}

	protected static function printChapter($entry, &$usecomma) {
		Entry::printFormattedField($entry, 'chapter', 'BIBTEX_CHAPTER_FORMAT', 'BIBTEX_CHAPTER', $usecomma);
	}

	protected static function printPages($entry, &$usecomma) {
		if (isset($entry['pages'])) {
			print $usecomma ? ', ' : ' ';
			JText::printf('BIBTEX_PAGERANGE_FORMAT', $entry['pages'], ctype_digit($entry['pages']) ? JText::_('BIBTEX_PAGE') : JText::_('BIBTEX_PAGES'));
			$usecomma = true;
		}
	}
	
	protected static function printDate($entry, &$usecomma) {
		if (isset($entry['year'])) {
			print $usecomma ? ', ' : ' ';
			if (isset($entry['month']) && ($month = get_month_standard_name($entry['month'])) !== false) {
				JText::printf('BIBTEX_DATE_FORMAT_YEARMONTH', $entry['year'], JText::_($month));
			} else {
				JText::printf('BIBTEX_DATE_FORMAT_YEAR', $entry['year']);
			}
			$usecomma = true;
		}
	}
	
	protected static function printUrl($entry, &$usecomma) {
		if (isset($entry['url'])) {
			$urlpattern =
				'(?:(?:ht|f)tps?://|~\/|\/)?'.  // protocol
				'(?:\w+:\w+\x40)?'.  // username and password, \x40 = @
				'(?:(?:[-\w]+\.)+(?:com|org|net|gov|mil|biz|info|mobi|name|aero|jobs|museum|travel|[a-z]{2}))'.  // domain
				'(?::[\d]{1,5})?'.  // port
				'(?:(?:(?:\/(?:[-\w~!$+|.,=]|%[a-f\d]{2})+)+|\/)+|\?|#)?'.  // path
				'(?:(?:\?(?:[-\w~!$+|.,*:]|%[a-f\d{2}])+=?(?:[-\w~!$+|.,*:=]|%[a-f\d]{2})*)(?:&(?:[-\w~!$+|.,*:]|%[a-f\d{2}])+=?(?:[-\w~!$+|.,*:=]|%[a-f\d]{2})*)*)*'.  // query
				'(?:#(?:[-\w~!$+|.,*:=]|%[a-f\d]{2})*)?';  // anchor
			if (preg_match('@'.$urlpattern.'@', $entry['url'])) {
				print $usecomma ? ', ' : ' ';
				print '<a href="'.$entry['url'].'">URL</a>';
				$usecomma = true;
			}
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
		if (!$this->authors->isEmpty()) {
			print '<span class="bibtexAuthors">'.$this->authors.'.</span> ';
		}
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
		if (!$this->editors->isEmpty()) {
			print '<span class="bibtexEditors">'.$this->editors.'.</span> ';
		} elseif (!$this->authors->isEmpty()) {
			print '<span class="bibtexAuthors">'.$this->authors.'.</span> ';
		}
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
		if (!$this->authors->isEmpty()) {
			print '<span class="bibtexAuthors">'.$this->authors.'.</span> ';
		}
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
		if (!$this->editors->isEmpty()) {
			print '<span class="bibtexEditors">'.$this->editors.'.</span> ';
		} elseif (!$this->authors->isEmpty()) {
			print '<span class="bibtexAuthors">'.$this->authors.'.</span> ';
		}
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
		if (!$this->authors->isEmpty()) {
			print '<span class="bibtexAuthors">'.$this->authors.'.</span> ';
		}
		print '<span class="bibtexTitle">'.$e['title'].'.</span> ';
		$usecomma = false;
		if (isset($e['booktitle'])) {
			print ' In ';
			if (!$this->editors->isEmpty()) {
				print '<span class="bibtexEditors">'.$this->editors.'.</span> ';
			}
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
		if (!$this->authors->isEmpty()) {
			print '<span class="bibtexAuthors">'.$this->authors.'.</span> ';
		}
		print '<span class="bibtexTitle">'.$e['title'].'.</span> ';
		$usecomma = false;
		if (isset($e['booktitle'])) {
			print ' In ';
			if (!$this->editors->isEmpty()) {
				print '<span class="bibtexEditors">'.$this->editors.'.</span> ';
			}
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
		if (!$this->authors->isEmpty()) {
			print '<span class="bibtexAuthors">'.$this->authors.'.</span> ';
		}
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
		if (!$this->authors->isEmpty()) {
			print '<span class="bibtexAuthors">'.$this->authors.'.</span> ';
		}
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
		if (!$this->authors->isEmpty()) {
			print '<span class="bibtexAuthors">'.$this->authors.'.</span> ';
		}
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
		if (!$this->editors->isEmpty()) {
			print '<span class="bibtexEditors">'.$this->editors.'.</span> ';
		}
		print '<span class="bibtexTitle">'.$e['title'].'.</span> ';
		if (isset($e['volume']) && isset($e['number'])) {
			print ' '.$e['volume'].'('.$e['number'].')';
		} elseif (isset($e['volume'])) {
			print ' '.$e['volume'];
		} elseif (isset($e['number'])) {
			print ' ('.$e['number'].')';
		}
		print '.';
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
		if (!$this->authors->isEmpty()) {
			print '<span class="bibtexAuthors">'.$this->authors.'.</span> ';
		}
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
		if (!$this->authors->isEmpty()) {
			print '<span class="bibtexAuthors">'.$this->authors.'.</span> ';
		}
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
*/
class BibliographyParser {
}
