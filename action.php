<?php
/**
 * Dokuwiki Action Plugin Subconfhelper
 *
 * @license   GPLv3(http://www.gnu.org/licenses/gpl.html)
 * @author    sf@notomorrow.de
 */

if( !defined( 'DOKU_INC' )) die( );
if( !defined( 'DOKU_PLUGIN' )) define( 'DOKU_PLUGIN',DOKU_INC.'lib/plugins/' );
require_once( DOKU_PLUGIN.'action.php' );

class action_plugin_subconfhelper extends DokuWiki_Action_Plugin {

  var $sconf = array( );

  function getInfo( ){
    return array(
        'author' => 'ai',
        'email'  => 'ai',
        'date'   => '2013-09-30',
        'name'   => 'subconfhelper',
        'desc'   => 'override various configuration settings per subdomain',
        'url'    => 'https://www.dokuwiki.org/plugin:subconfhelper' );
  }
  function register( &$controller) {
    $controller->register_hook('FEED_OPTS_POSTPROCESS', 'BEFORE',  $this, 'check_vhost' );
    $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE',  $this, 'check_vhost' );
    $controller->register_hook('TPL_ACT_RENDER', 'BEFORE',  $this, 'check_act' );
  }
  function read_config( $domain ) {
    if( !$domain ) { return false; }
    if( isset( $this->sconf[$domain] )) {
      return $this->sconf[$domain]; }
    else {
      $this->sconf[$domain] = array( );
      $conf = &$this->sconf[$domain]; }
    $this->config_path = DOKU_CONF;
    $this->config_prefix = 'subconfhelper_';
    while( $domain && !$subdomain ) {

        $conf_file = $this->config_path.$this->config_prefix.$domain.'.php';
        $conf_file_ssl = $this->config_path.$this->config_prefix.$domain.'_ssl.php';

        if( $_SERVER['HTTPS'] == 'on' && file_exists( $conf_file_ssl )) {
          $conf_file = $conf_file_ssl; }
        if( !file_exists( $conf_file )) {
            if( strpos( $domain, '.' )) {
                $domain = substr( $domain, 0, strrpos( $domain, '.' )); }
            else {
                $subdomain = $domain; }}
        else {
            $subdomain = $domain; }}
    if( file_exists( $conf_file )) {
        require_once( $conf_file ); }
    return $conf;
  }
  function check_vhost( &$event, $param ) {
    global $ACT, $INFO;
    $domain = $_SERVER['HTTP_HOST'];
    $sconf  = $this->read_config( $domain );
    if( is_array( $sconf )) {
        $this->override_defaultpage( $sconf );
        $this->override_template( $sconf );
        $this->override_namespace( $sconf );
        $this->override_conf( $sconf );
    }
  }
  function check_act( &$event, $param ) {
    global $conf, $ACT, $INFO;
    $domain = $_SERVER['HTTP_HOST'];
    $sconf = $this->read_config( $domain );
    if( $ACT == 'index' && $sconf['ns'] ) {
      $dir = $conf['datadir'];
      $ns  = cleanID($ns);
      $ns  = utf8_encodeFN( str_replace( ':', '/', $ns));
      echo p_locale_xhtml('index');
      echo '<div id="index__tree">';
      $data = array();
      search($data,$conf['datadir'],'search_index',array( 'ns' => $ns ), $sconf['ns'] );
      echo html_buildlist( $data,'idx','html_list_index','html_li_index' );
      echo '</div>';
      $event->preventDefault();
    }
  }
  function override_conf( $conf_override ) {
      global $conf;
      foreach( array( 'disableactions', 'title', 'tagline' ) as $key ) {
          if( isset( $conf_override[$key] )) {
      	    $conf[$key] = $conf_override[$key]; }}
  }
  function override_template( $conf_override ) {
    $t = plugin_load( 'action', 'templateconfhelper_templateaction' );
    if( $t ) {
      $t->tpl_switch( $conf_override['template'] ); }
  }
  function override_defaultpage( $conf_override ) {
    global $ID,$INFO,$conf;
    if( $defaultpage = $conf_override['default_startpage'] ) {
	if( $ID == $conf['start'] ) {
	    $ID	  = $defaultpage;
	    $NS   = getNS( $ID );
	    $INFO = pageinfo( );
	    $JSINFO['id'] = $ID;
	    $INFO['namespace'] = (string) $INFO['namespace'];
	    if( $conf['breadcrumbs'] ) breadcrumbs( ); }}
  }
  function override_namespace( $conf_override ) {
    global $ID,$INFO,$conf;
    if( !$conf_override['ns'] ) { return ''; }
    $path = explode( ':', $ID );
    if( strpos( $ID, $conf_override['ns'] ) === 0 ) { return ''; }
    if( strpos( ':'.$ID, str_replace( '/', ':',
                    $conf_override['ns'] )) === 0 ) { return ''; }
    if( $conf_override['ns_inherit'] ) {
      $newfile = wikiFN( $conf_override['ns'].':'.$ID );
      if( !file_exists( wikiFN( $conf_override['ns'].':'.$ID ))
                     && file_exists( wikiFN($ID))) { return ''; }}
    $ID = $conf_override['ns'].':'.$ID;
    if( strpos( $ID, ':' ) === 0 ) {
	$ID = substr( $ID, 1 ); }
    $NS = getNS( $ID );
    $INFO = pageinfo( );
    $JSINFO['id'] = $ID;
    $INFO['namespace'] = (string) $INFO['namespace'];
    if( $conf['breadcrumbs'] ) breadcrumbs();
  }
}
