<?php
/**
 *
 */


define('CONF_SELF', DOKU_PLUGIN.'config/');
define('PLUGIN_METADATA',CONF_SELF.'settings/config.metadata.php');
if(!defined('DOKU_PLUGIN_IMAGES')) define('DOKU_PLUGIN_IMAGES',DOKU_BASE.'lib/plugins/config/images/');
  
require_once(CONF_SELF.'settings/config.class.php');  // main configuration class and generic settings classes
require_once(CONF_SELF.'settings/extra.class.php');   // settings classes specific to these settings

  class helper_plugin_subconfhelper_config extends configuration {

    var $_name = 'conf';           // name of the config variable found in the files (overridden by $config['varname'])
    var $_format = 'php';          // format of the config file, supported formats - php (overridden by $config['format'])
    var $_heading = '';            // heading string written at top of config file - don't include comment indicators
    var $_loaded = true;          // set to true after configuration files are loaded
    var $_metadata = array();      // holds metadata describing the settings
    var $setting = array();        // array of setting objects
    var $locked = false;           // configuration is considered locked if it can't be updated

    // configuration filenames
    var $_default_files  = array();
    var $_local_files = array();      // updated configuration is written to the first file
    var $_protected_files = array();

    /**
     *  constructor
     */
    function helper_plugin_subconfhelper_config( ) {

    }

    function isSingleton( ) {
	return false;
    }


    function configuration( $datafile, $local=array( ), $default=array( ), $protected=array( ) ) {
        global $conf, $config_cascade;

	$this->_data_file	= $datafile;
        $this->_local_files	= $local;
        $this->_default_files   = $default;
        $this->_protected_files = $protected;

        if (!@file_exists($datafile)) {
          msg('No configuration metadata found at - '.htmlspecialchars($datafile),-1);
          return;
        }
        include($datafile);

        if (isset($config['varname'])) $this->_name = $config['varname'];
        if (isset($config['format'])) $this->_format = $config['format'];
        if (isset($config['heading'])) $this->_heading = $config['heading'];

        $this->locked = $this->_is_locked();

        #$this->_metadata = array_merge($meta, $this->get_plugintpl_metadata($conf['template']));
        $this->_metadata = $meta;

      // retrieve 
        $no_default_check = array('setting_fieldset', 'setting_undefined', 'setting_no_class');

        #$default = array_merge($this->get_plugintpl_default($conf['template']), $this->_read_config_group($this->_default_files));
        $default = array_merge($this->_read_config_group($this->_default_files));
        $local = $this->_read_config_group($this->_local_files);
        $protected = $this->_read_config_group($this->_protected_files);
              
        $keys = array_merge(array_keys($this->_metadata),array_keys($default), array_keys($local), array_keys($protected));
        $keys = array_unique($keys);

        foreach ($keys as $key) {
          if (isset($this->_metadata[$key])) {
            $class = $this->_metadata[$key][0];
            $class = ($class && class_exists('setting_'.$class)) ? 'setting_'.$class : 'setting';
            if ($class=='setting') {
              $this->setting[] = new setting_no_class($key,$param);
            } 
              
            $param = $this->_metadata[$key];
            array_shift($param);
          } else {
            $class = 'setting_undefined';
            $param = NULL;
          }   
              
          #if (!in_array($class, $no_default_check) && !isset($default[$key])) {
          #  $this->setting[] = new setting_no_default($key,$param);
          #}   
              
          $this->setting[$key] = new $class($key,$param);
          $this->setting[$key]->initialize($default[$key],$local[$key],$protected[$key]);
        }     
              
        $this->_loaded = true;

    }

}
