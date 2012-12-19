<?php
/**
* @file
* @brief    BibTeX bibliography formatter Joomla plug-in BIB parser (adopted for DokuWiki)
* @author   Levente Hunyadi
* @author   Philipp A. Hartmann <pah@qo.cx>
* @remarks  Copyright (C) 2009-2011 Levente Hunyadi
* @remarks  Copyright (C) 2012      Philipp A. Hartmann
* @remarks  Licensed under GNU/GPLv3, see http://www.gnu.org/licenses/gpl-3.0.html
* @see      http://hunyadi.info.hu/projects/bibtex
*/

// no direct access
defined( 'DOKU_PLUGIN_YABIBTEX' ) or die( 'Restricted access' );

require_once DOKU_PLUGIN_YABIBTEX.'lib/bib/parseentries.php';
require_once DOKU_PLUGIN_YABIBTEX.'lib/bib/parsecreators.php';

/**
* Parses a bibliography in BibTeX format.
*/
class BibTexParser extends BibliographyParser {
	/**
	* Maps BibTeX entry types to Entry object types.
	*/
	private static $mapping = array(
		'ARTICLE' => 'ArticleEntry',
		'BOOK' => 'BookEntry',
		'BOOKLET' => 'BookletEntry',
		'INBOOK' => 'InBookEntry',
		'INCOLLECTION' => 'InCollectionEntry',
		'INPROCEEDINGS' => 'ProceedingsPaperEntry',
		'MANUAL' => 'ManualEntry',
		'MASTERSTHESIS' => 'MastersThesisEntry',
		'MISC' => 'MiscellaneousEntry',
		'PHDTHESIS' => 'PhdThesisEntry',
		'PROCEEDINGS' => 'ProceedingsEntry',
		'TECHREPORT' => 'TechnicalReportEntry',
		'UNPUBLISHED' => 'UnpublishedEntry'
	);

	/**
	* Reads BibTeX entries in a file into an array.
	* Each BibTeX entry is parsed into an associative array with the key being the BibTeX field name and the value being the field data.
	* The citation key and the entry type are mapped to special array keys 'bibtexCitation' and 'bibtexEntryType'.
	* @return An array whose keys are citation keys and values are BibTeX entries.
	*/
	public static function read($filename) {
		$entry_parser = new ParseEntries();
		$entry_parser->openBib($filename);
		$entry_parser->extractEntries();
		$entry_parser->closeBib();
		list($bibtex_preamble, $bibtex_strings, $bibtex_entries, $bibtex_undefined_strings) = $entry_parser->returnArrays();
		$entries = array();
		foreach ($bibtex_entries as $bibtex_entry) {
			$entries[$bibtex_entry['bibtexCitation']] = $bibtex_entry;
		}
		return $entries;
	}

	public static function readString($string) {
		$entry_parser = new ParseEntries();
		$entry_parser->loadBibtexString($string);
		$entry_parser->extractEntries();
		list($bibtex_preamble, $bibtex_strings, $bibtex_entries, $bibtex_undefined_strings) = $entry_parser->returnArrays();
		$entries = array();
		foreach ($bibtex_entries as $bibtex_entry) {
			$entries[$bibtex_entry['bibtexCitation']] = $bibtex_entry;
		}
		return $entries;
	}


	/**
	* Parses raw BibTeX entries into Entry class objects.
	*/
	public static function parse($bibtex_entries, $filter=NULL) {
		$entries = array();

		foreach ($bibtex_entries as $bibtex_entry) {
			// instantiate proper entry class based on entry type
			$bibtex_entry_type = strtoupper($bibtex_entry['bibtexEntryType']);
			if (!isset(BibTexParser::$mapping[$bibtex_entry_type])) {  // unrecognized entry type
				continue;
			}
			$entry_type = BibTexParser::$mapping[$bibtex_entry_type];
			$entry = new $entry_type();
			$entry->citation   = trim($bibtex_entry['bibtexCitation']);
			$entry->entry_type = $bibtex_entry['bibtexEntryType'];

			// unescape special LaTeX characters
			foreach ($bibtex_entry as $key => $value) {
				if (ctype_alpha($key[0]) && ctype_alnum($key)) {  // ensure valid PHP property name
					$entry->$key = latex2plain($value);
				}
			}

			// convert author field into list of authors
			if (isset($bibtex_entry['author'])) {
				$entry->addAuthors(BibTexParser::getAuthors(latex2plain($bibtex_entry['author'])));
			}

			// convert editor field into list of editors
			if (isset($bibtex_entry['editor'])) {
				$entry->addEditors(BibTexParser::getEditors(latex2plain($bibtex_entry['editor'])));
			}
			
			if(isset($bibtex_entry['month'])) {
				$month = get_month_standard_number( $bibtex_entry['month'] );
				if( $month !== false ) {
					$entry->month=$month;
					$bibtex_entry['month'] = $month;
				}
			}

			// store raw fields for export
			BibTexParser::filterRaw( $bibtex_entry );
			$entry->raw_fields = $bibtex_entry;

			// skip, if filter does not match
			if( is_callable($filter) && call_user_func( $filter, $entry ) === false )
				continue;

			// add newly created entry to list of entries
			$entries[] = $entry;
		}
		return $entries;
	}
	
	/**
	* Parses a BibTeX author (or editor) field.
	* @param creator_field A BibTeX author (or editor) field value to parse.
	* @param creator_list The container to populate with parsed authors (or editors).
	*/
	private static function getCreators($creator_field, &$creator_list) {
		$creator_parser = new ParseCreators();
		$parsed_list = $creator_parser->parse($creator_field);
		foreach ($parsed_list as $parsed_creator) {
			list ($given_name, $initials, $family_name) = $parsed_creator;
			$family_name = trim($family_name);
			$given_name = trim($given_name.' '.$initials);
			$creator_list->add(new EntryCreator($family_name, $given_name));
		}
	}
	
	/**
	* Parses a BibTeX author field.
	* @param author_field A BibTeX author field value to parse.
	* @return An EntryAuthorList object.
	*/
	private static function getAuthors($author_field) {
		$authors = new EntryAuthorList();
		BibTexParser::getCreators($author_field, $authors);
		return $authors;
	}

	/**
	* Parses a BibTeX editor field.
	* @param editor_field A BibTeX editor field value to parse.
	* @return An EntryEditorList object.
	*/
	private static function getEditors($editor_field) {
		$editors = new EntryEditorList();
		BibTexParser::getCreators($editor_field, $editors);
		return $editors;
	}
}
