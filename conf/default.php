<?php
/**
 * Default settings for the yabibtex plugin
 *
 * @author Philipp A. Hartmann <pah@qo.cx>
 */

$conf['sort']          = '^date';
$conf['rowmarkers']    = 1;
$conf['show_key']      = 1;
$conf['show_type']     = 1;
$conf['show_links']    = 'auto';
$conf['show_abstract'] = 1;
$conf['show_bibtex']   = 1;
$conf['filter_raw']    = 'file,users,tags';

$conf['userlink']   = 'auto';
$conf['userfind']   = 'users';

$conf['bibns']   = 'bib';
$conf['medians'] = $conf['bibns'];
$conf['userns']  = 'people';
$conf['tagns']   = 'tags';

