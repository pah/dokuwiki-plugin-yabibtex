<?php
/**
 * english language file for yabibtex plugin
 *
 * @author Philipp A. Hartmann <pah@qo.cx>
 */

// keys need to match the config setting name
$lang['bibns']  = 'default namespace for BibTeX files';
$lang['userns'] = 'namespace for author pages';
$lang['tagns']  = 'namespace for tag links';

$lang['sort']   = 'default sort key(s) '
                 .'(comma-separated field specifiers, '
                 .'preprended with \'^\' for reversed ordering)';

$lang['userlink']   = 'Link author names to user pages';
$lang['userlink_o_off']      = 'No author links';
$lang['userlink_o_auto']     = 'Link all authors with known full name';
$lang['userlink_o_explicit'] = 'Only link authors listed in \'users\' field of BibTeX entry';

$lang['userfind']   = 'Determine user names for author links';
$lang['userfind_o_users']= 'Retrieve full names from Auth backend';
$lang['userfind_o_pages']= 'Retrieve full names from pages in \'userns\' namespace';
$lang['userfind_o_both'] = 'Merge Auth backend and pages lists';

$lang['filter_raw'] = 'Suppress fields in BibTeX export';
//Setup VIM: ex: et ts=4 :
