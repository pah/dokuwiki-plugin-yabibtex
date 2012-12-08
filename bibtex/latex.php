<?php
/**
* @file
* @brief    BibTeX bibliography formatter LaTeX and BibTeX utility functions
* @author   Levente Hunyadi
* @version  1.1.1
* @remarks  Copyright (C) 2009-2011 Levente Hunyadi
* @remarks  Licensed under GNU/GPLv3, see http://www.gnu.org/licenses/gpl-3.0.html
* @see      http://hunyadi.info.hu/projects/bibtex
*/

// no direct access
defined( 'DOKU_PLUGIN_YABIBTEX' ) or die( 'Restricted access' );

/**
 * Encapsulates an accented letter and its ANSI LaTeX representation.
 */
class Letter {
	private $upper;
	private $lower;
	private $latin;
	private $accent;
	public static $search;
	public static $replace;

	function __construct($upper, $lower, $latin, $accent) {
		$this->upper = $upper;
		$this->lower = $lower;
		$this->latin = $latin;
		$this->accent = $accent;
	}
	
	function has_compact_latex() {
		return strpos('\'=".`^~', $this->accent) !== false;
	}
	
	function get_upper_latex($compact = false) {
		if ($compact && $this->has_compact_latex()) {
			return '\\'.$this->accent.strtoupper($this->latin);
		} else {
			return '\\'.$this->accent.'{'.strtoupper($this->latin).'}';
		}
	}
	
	function get_lower_latex($compact = false) {
		if ($compact && $this->has_compact_latex()) {
			return '\\'.$this->accent.strtolower($this->latin);
		} else {
			return '\\'.$this->accent.'{'.strtolower($this->latin).'}';
		}
	}

	/** Search table for LaTeX accented letters. */
	private static function get_search_table($list) {
		$table = array();
		foreach ($list as $element) {
			if ($element->has_compact_latex()) {
				$table[] = $element->get_upper_latex(true);
				$table[] = $element->get_lower_latex(true);
			}
			$table[] = $element->get_upper_latex(false);
			$table[] = $element->get_lower_latex(false);
		}
		return $table;
	}
	
	/** Replacement table for LaTeX accented letters. */
	private static function get_replacement_table($list) {
		$table = array();
		foreach ($list as $element) {
			if ($element->has_compact_latex()) {
				$table[] = $element->upper;
				$table[] = $element->lower;
			}
			$table[] = $element->upper;
			$table[] = $element->lower;
		}
		return $table;
	}
	
	public static function set_table($list) {
		Letter::$search = Letter::get_search_table($list);
		Letter::$replace = Letter::get_replacement_table($list);
	}
}

Letter::set_table(array(
	// acute
	new Letter('Á','á','a','\''),
	new Letter('Ǽ','ǽ','\\ae','\''),
	new Letter('É','é','e','\''),
	new Letter('Í','í','i','\''),
	new Letter('Ó','ó','o','\''),
	new Letter('Ú','ú','u','\''),
	new Letter('Ć','ć','c','\''),
	new Letter('Ĺ','ĺ','l','\''),
	new Letter('Ń','ń','n','\''),
	new Letter('Ŕ','ŕ','r','\''),
	new Letter('Ś','ś','s','\''),
	new Letter('Ẃ','ẃ','w','\''),
	new Letter('Ý','ý','y','\''),
	new Letter('Ź','ź','z','\''),
	
	// grave
	new Letter('À','à','a','`'),
	new Letter('È','è','e','`'),
	new Letter('Ì','ì','i','`'),
	new Letter('Ò','ò','o','`'),
	new Letter('Ù','ù','u','`'),
	new Letter('Ẁ','ẁ','w','\''),
	new Letter('Ỳ','ỳ','y','\''),

	// circumflex
	new Letter('Â','â','a','^'),
	new Letter('Ê','ê','e','^'),
	new Letter('Î','î','i','^'),
	new Letter('Ô','ô','o','^'),
	new Letter('Û','û','u','^'),
	new Letter('Ĉ','ĉ','c','^'),
	new Letter('Ĝ','ĝ','g','^'),
	new Letter('Ĥ','ĥ','h','^'),
	new Letter('Ĵ','ĵ','j','^'),
	new Letter('Ŝ','ŝ','s','^'),
	new Letter('Ŵ','ŵ','w','^'),
	new Letter('Ŷ','ŷ','y','^'),
	
	// diaeresis
	new Letter('Ä','ä','a','"'),
	new Letter('Ë','ë','e','"'),
	new Letter('Ï','ï','i','"'),
	new Letter('Ö','ö','o','"'),
	new Letter('Ü','ü','u','"'),
	new Letter('Ẅ','ẅ','w','"'),
	new Letter('Ÿ','ÿ','y','"'),

	// caron
	new Letter('Ǎ','ǎ','a','v'),
	new Letter('Ě','ě','e','v'),
	new Letter('Ǐ','ǐ','i','v'),
	new Letter('Ǒ','ǒ','o','v'),
	new Letter('Ǔ','ǔ','u','v'),
	new Letter('Č','č','c','v'),
	new Letter('Ď','ď','d','v'),
	new Letter('Ľ','ľ','l','v'),
	new Letter('Ň','ň','n','v'),
	new Letter('Ř','ř','r','v'),
	new Letter('Š','š','s','v'),
	new Letter('Ť','ť','t','v'),
	new Letter('Ž','ž','z','v'),

	// tilde
	new Letter('Ã','ã','a','~'),
	new Letter('Ẽ','ẽ','a','~'),
	new Letter('Ĩ','ĩ','i','~'),
	new Letter('Õ','õ','o','~'),
	new Letter('Ũ','ũ','u','~'),
	new Letter('Ñ','ñ','n','~'),
	new Letter('Ỹ','ỹ','y','~'),

	// cedilla
	new Letter('Ç','ç','c','c'),
	new Letter('Ģ','ģ','g','c'),
	new Letter('Ķ','ķ','k','c'),
	new Letter('Ļ','ļ','l','c'),
	new Letter('Ņ','ņ','n','c'),
	new Letter('Ŗ','ŗ','r','c'),
	new Letter('Ş','ş','s','c'),
	new Letter('Ţ','ţ','t','c'),
	
	// ring
	new Letter('Å','å','a','r'),
	new Letter('Ů','ů','u','r'),

	// double acute
	new Letter('Ő','ő','o','H'),
	new Letter('Ű','ű','u','H')
));

class Symbol {
	private $character;
	private $command;
	public static $search;
	public static $replace;

	function __construct($character, $command) {
		$this->character = $character;
		$this->command = $command;
	}
	
	function get_latex() {
		return '\\'.$this->command;
	}
	
	/** Search table for LaTeX accented letters. */
	private static function get_search_table($list) {
		$table = array();
		foreach ($list as $element) {
			$table[] = $element->get_latex();
		}
		return $table;
	}
	
	/** Replacement table for LaTeX accented letters. */
	private static function get_replacement_table($list) {
		$table = array();
		foreach ($list as $element) {
			$table[] = $element->character;
		}
		return $table;
	}
	
	public static function set_table($list) {
		Symbol::$search = Symbol::get_search_table($list);
		Symbol::$replace = Symbol::get_replacement_table($list);
	}
}

Symbol::set_table(array(
	new Symbol('©','copyright'),
	new Symbol('†','dag'),
	new Symbol('‡','ddag'),
	new Symbol('…','dots'),
	new Symbol('£','pounds'),
	new Symbol('å','AA'),
	new Symbol('Å','aa'),
	new Symbol('Æ','AE'),
	new Symbol('æ','ae'),
	new Symbol('Ð','DH'),
	new Symbol('ð','dh'),
	new Symbol('Đ','DJ'),
	new Symbol('đ','dj'),
	new Symbol('Ł','L'),
	new Symbol('ł','l'),
	new Symbol('Ŋ','NG'),
	new Symbol('ŋ','ng'),
	new Symbol('Œ','OE'),
	new Symbol('œ','oe'),
	new Symbol('Ø','O'),  // prefix of OE
	new Symbol('ø','o'),  // prefix of oe
	new Symbol('ß','ss'),
	new Symbol('SS','SS'),
	new Symbol('Þ','TH'),
	new Symbol('þ','th'),
	new Symbol('¶','P'),
	new Symbol('§','S'),  // prefix of SS

	// LaTeX special symbols
	new Symbol('$','$'),
	new Symbol('%','%'),
	new Symbol('&','&'),
	new Symbol('#','#'),
	new Symbol('_','_'),
	new Symbol('{','{'),
	new Symbol('}','}')
));

/**
* Converts a LaTeX text into plain text, replacing special LaTeX markers for accents and punctuation.
* Commands besides accent and punctuation markers are not recognized and are automatically skipped.
* @param text Text in which to replace LaTeX commands.
* @return Plain text without LaTeX commands.
*/
function latex2plain($text) {
	$text = str_replace(Letter::$search, Letter::$replace, $text);
	$text = str_replace(Symbol::$search, Symbol::$replace, $text);
	$search = array('--','``','\'\'',',,','~');
	$replacement = array('–','“','”','„',"\xc2\xa0");
	$text = str_replace($search, $replacement, $text);
	$text = preg_replace('/\{([^{}]*)\}/', '\1', $text);
	return $text;
}

function get_ordinal_standard_name($field) {
	$ordinal_standard_full_names = array(
		1 =>'first',
		2 =>'second',
		3 =>'third',
		4 =>'fourth',
		5 =>'fifth',
		6 =>'sixth',
		7 =>'seventh',
		8 =>'eighth',
		9 =>'ninth',
		10=>'tenth',
		11=>'eleventh',
		12=>'twelfth',
		13=>'thirteenth',
		14=>'fourteenth',
		15=>'fifteenth',
		16=>'sixteenth',
		17=>'seventeenth',
		18=>'eighteenth',
		19=>'nineteenth',
		20=>'twentieth');
	$ordinal_standard_short_names = array(
		1 =>'1st',
		2 =>'2nd',
		3 =>'3rd',
		4 =>'4th',
		5 =>'5th',
		6 =>'6th',
		7 =>'7th',
		8 =>'8th',
		9 =>'9th',
		10=>'10th',
		11=>'11th',
		12=>'12th',
		13=>'13th',
		14=>'14th',
		15=>'15th',
		16=>'16th',
		17=>'17th',
		18=>'18th',
		19=>'19th',
		20=>'20th');
		
	if (ctype_digit($field)) {
		$ordinal = (int) $field;
		if (array_key_exists($ordinal, $ordinal_standard_full_names)) {
			return $ordinal_standard_full_names[$ordinal];
		}
	} else {
		$field = strtolower($field);
		$key = array_search($field, $ordinal_standard_full_names, true);
		if ($key !== false) {
			return $ordinal_standard_full_names[$key];
		}
		$key = array_search($field, $ordinal_standard_short_names, true);
		if ($key !== false) {
			return $ordinal_standard_full_names[$key];
		}
	}
	return false;
}

function get_month_standard_number($field) {
	$month_standard_full_names = array(
		'January'=>1,
		'February'=>2,
		'March'=>3,
		'April'=>4,
		'May'=>5,
		'June'=>6,
		'July'=>7,
		'August'=>8 ,
		'September'=>9,
		'October'=>10,
		'November'=>11,
		'December'=>12
	);
	$month_standard_short_names = array(
		'Jan'=>1 ,
		'Feb'=>2 ,
		'Mar'=>3 ,
		'Apr'=>4 ,
		'May'=>5 ,
		'Jun'=>6 ,
		'Jul'=>7 ,
		'Aug'=>8 ,
		'Sep'=>9 ,
		'Oct'=>10,
		'Nov'=>11,
		'Dec'=>12
	);

	if((is_int($field) || ctype_digit($field)) && $field>0 && $field<13 )
		return (int)$field;

	$field = ucfirst(strtolower($field));

	if( isset( $month_standard_full_names[$field] ) )
		return $month_standard_full_names[$field];

	if( isset( $month_standard_short_names[$field] ) )
		return $month_standard_short_names[$field];

	return false;
}

function get_month_standard_name($field) {
	$month_standard_full_names = array(
		1 =>'January',
		2 =>'February',
		3 =>'March',
		4 =>'April',
		5 =>'May',
		6 =>'June',
		7 =>'July',
		8 =>'August',
		9 =>'September',
		10=>'October',
		11=>'November',
		12=>'December'
	);
	$month_standard_short_names = array(
		1 =>'Jan',
		2 =>'Feb',
		3 =>'Mar',
		4 =>'Apr',
		5 =>'May',
		6 =>'Jun',
		7 =>'Jul',
		8 =>'Aug',
		9 =>'Sep',
		10=>'Oct',
		11=>'Nov',
		12=>'Dec'
	);

	if (ctype_digit((string)$field)) {
		$month_number = (int) $field;
		if (array_key_exists($month_number, $month_standard_full_names)) {
			return $month_standard_full_names[$month_number];
		}
	} else {
		$field = ucfirst(strtolower($field));
		$key = array_search($field, $month_standard_full_names, true);
		if ($key !== false) {
			return $month_standard_full_names[$key];
		}
		$key = array_search($field, $month_standard_short_names, true);
		if ($key !== false) {
			return $month_standard_full_names[$key];
		}
	}
	return false;
}
