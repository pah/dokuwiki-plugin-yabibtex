/*!
* @file
* @brief    BibTeX formatted bibliography plug-in for Joomla JavaScript functions
* @author   Levente Hunyadi
* @version  1.1.1
* @remarks  Copyright (C) 2009-2011 Levente Hunyadi
* @remarks  Licensed under GNU/GPLv3, see http://www.gnu.org/licenses/gpl-3.0.html
* @see      http://hunyadi.info.hu/projects/bibtex
*/

window.addEvent('domready', function () {
	$$('a.bibtexLink').each(function (obj) {
		// find element that contains raw BibTeX code
		var codeblock;
		for (var parent = obj.getParent(); parent != null && codeblock == null; parent = parent.getParent()) {
			codeblock = parent.getElement('pre.bibtexCode');
		};
		if (codeblock != null) {
			// register click event to show/hide BibTeX code
			obj.addEvent('click', function () {
				// toggle BibTeX code display
				codeblock.setStyle('display', codeblock.getStyle('display') != 'block' ? 'block' : 'none');
				
				// suppress event propagation
				return false;
			});
		}
	});

	$$('pre.bibtexCode').setStyle('display', 'none');
});