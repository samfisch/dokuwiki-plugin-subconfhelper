<?php
// ---------------[ settings for settings ]------------------------------
$config['format']  = 'php';      // format of setting files, supported formats: php
$config['varname'] = 'conf';     // name of the config variable, sans $

// this string is written at the top of the rewritten settings file,
// !! do not include any comment indicators !!
// this value can be overriden when calling save_settings() method

$meta['title']  = array('string');
$meta['tagline']  = array('string');

$meta['ns']  = array('string');
$meta['ns_inherit']  = array('onoff');
$meta['default_startpage']	    = array('string');
$meta['default_groups']		    = array('string');
$meta['template']		    = array('dirchoice','_dir' => DOKU_INC.'lib/tpl/','_pattern' => '/^[\w-]+$/');
$meta['disableactions']		    = array('disableactions',
                                '_choices' => array('backlink','index','recent','revisions','search','subscription','register','resendpwd','profile','edit','wikicode','check'),
                                '_combine' => array('subscription' => array('subscribe','unsubscribe'), 'wikicode' => array('source','export_raw')));
