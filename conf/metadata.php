<?php
/**
 * Options for the yabibtex plugin
 *
 * @author Philipp A. Hartmann <pah@qo.cx>
 */

$meta['_basic'] = array('fieldset');
$meta['sort'] = array('string'
                     ,'_pattern' => '/^\\^?[A-Za-z_-]+(,\s*\\^?[A-Za-z_-]+)*$/');


$meta['rowmarkers']    = array('onoff');
$meta['show_abstract'] = array('onoff');
$meta['show_bibtex']   = array('onoff');
$meta['filter_raw']    = array( 'multicheckbox'
                             , '_choices' => array('file','users','tags','abstract')
                             , '_pattern' =>'/^[A-Za-z_-]+(,\s*[A-Za-z_-]+\s*)*$/');

$meta['userlink'] = array( 'multichoice'
                         , '_choices' => array('off','explicit','auto') );
$meta['userfind'] = array( 'multichoice'
                         , '_choices' => array('users','pages','both') );

$meta['_ns']     = array('fieldset');
$meta['bibns']   = array('string');
$meta['medians'] = array('string');
$meta['userns']  = array('string');
$meta['tagns']   = array('string');


