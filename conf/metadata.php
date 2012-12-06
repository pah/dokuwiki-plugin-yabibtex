<?php
/**
 * Options for the yabibtex plugin
 *
 * @author Philipp A. Hartmann <pah@qo.cx>
 */

$meta['sort'] = array('string'
                     ,'_pattern' => '/^\\^?[A-Za-z_-]+(,\s*\\^?[A-Za-z_-]+)*$/');

$meta['bibns']  = array('string');
$meta['userns'] = array('string');
$meta['tagns']  = array('string');

$meta['filter_raw']   = array('string','_pattern'=>'/^(\w+(,\s*\w+)*)?$)/');
