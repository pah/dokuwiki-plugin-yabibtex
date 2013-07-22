<?php
/**
 * english language file for yabibtex plugin
 *
 * @author Philipp A. Hartmann <pah@qo.cx>
 */

// keys need to match the config setting name

$lang['sort']   = 'Default sort key(s) '
                 .'(comma-separated field specifiers, '
                 .'preprended with \'^\' for reversed ordering)';

$lang['rowmarkers'] = 'Add alternating \'even\'/\'odd\' CSS classes '
                     .'to BibTeX list entries for row highlighting '
                     .'(can be overridden)';

$lang['show_key']   = 'Show citation key';
$lang['show_type']  = 'Show citation entry type';

$lang['show_links']   = 'Show links (can be overridden)';


$lang['show_abstract'] = 'Add box with Abstract, if present; '
                        .'foldable, when Folding plugin is available '
                        .'(can be overridden)';

$lang['show_bibtex']   = 'Add code box with downloadable BibTeX source; '
                        .'foldable, when Folding plugin is available '
                        .'(can be overridden)';

$lang['filter_raw'] = 'Suppress fields in downloadable BibTeX export';

$lang['userlink']   = 'Link author names to user pages';
$lang['userlink_o_off']      = 'No author links';
$lang['userlink_o_auto']     = 'Link all authors with known full name';
$lang['userlink_o_explicit'] = 'Only link authors listed in \'users\' field of BibTeX entry';

$lang['userfind']   = 'Determine user names for author links';
$lang['userfind_o_users']= 'Retrieve full names from Auth backend';
$lang['userfind_o_pages']= 'Retrieve full names from pages in user namespace';
$lang['userfind_o_both'] = 'Merge Auth backend and pages list';

$lang['bibns']  = '(Default) namespace for BibTeX files '
                 .'(can be overridden)';
$lang['medians']= '(Default) namespace for linked media files '
                 .'(can be overridden)';
$lang['userns'] = 'Namespace for author pages';

//Setup VIM: ex: et ts=4 :
