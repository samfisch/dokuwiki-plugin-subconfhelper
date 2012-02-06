<?php
/** vhostconfig
 * changes config based on vhost 
 *
 * requires: templateconfhelper, manual changes to config file
 *
 */

if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'action.php');
class action_plugin_subconfhelper extends DokuWiki_Action_Plugin {

  var $sconf = array( );

  function getInfo(){
    return array(
        'author' => 'ai',
        'email'  => 'ai',
        'date'   => '2010-01-07',
        'name'   => 'override',
        'desc'   => 'override groups for a user based on registration from domain',
        'url'    => 'muc.ccc.de',
    );
  }

    function register(&$controller) {/*{{{*/
      $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE',  $this, 'check_vhost' );
      $controller->register_hook('TPL_ACT_RENDER', 'BEFORE',  $this, 'check_act' );
    }/*}}}*/

    function read_config( $domain ) {/*{{{*/
      if( !$domain ) { return false; }

      if( isset( $this->sconf[$domain] )) { 
          return $this->sconf[$domain];
      } else {
          $this->sconf[$domain] = array( );
          $conf = &$this->sconf[$domain];
      }

      $this->config_path = DOKU_CONF;
      $this->config_prefix = 'subconfhelper_';

      while( $domain && !$subdomain ) {
          $conf_file = $this->config_path.$this->config_prefix.$domain.'.php';
          if( !file_exists( $conf_file )) {
              if( strpos( $domain, '.' )) {
                  $domain = substr( $domain, 0, strrpos( $domain, '.' ));
              } else {
                  $subdomain = $domain;
              }
          } else {
              $subdomain = $domain;
          }
      }

      if( file_exists( $conf_file )) {
          require_once( $conf_file ); // filling $conf
      }

      return $conf;
    }/*}}}*/

    function check_vhost(&$event, $param) {/*{{{*/
      global $ACT, $INFO;
      $domain	    = $_SERVER['HTTP_HOST'];

      $sconf = $this->read_config( $domain );
      if( is_array( $sconf )) {
        // register
          if($ACT == 'register' && $_POST['save'] ) {
              if( $this->override_register( $sconf)) {
		$ACT = 'login';
              } else {
		$_POST['save'] = false;
              }
          }
	// default_page
	  $this->override_defaultpage( $sconf );  // 
	  $this->override_template( $sconf );  // 
	  $this->override_namespace( $sconf );  // 

	  $this->override_conf( $sconf );  // 
	// template
      }
    }/*}}}*/

    function check_act(&$event, $param) {/*{{{*/
      global $conf, $ACT, $INFO;
      $domain	    = $_SERVER['HTTP_HOST'];

      $sconf = $this->read_config( $domain );
      if( $ACT == 'index' && $sconf['ns'] ) {
        
        $dir = $conf['datadir'];                                           
        $ns  = cleanID($ns);                                               
        $ns  = utf8_encodeFN(str_replace(':','/',$ns));                    
                                                                       
        echo p_locale_xhtml('index');                                      
        echo '<div id="index__tree">';                                     
                                                                       
        $data = array();                                                   
        search($data,$conf['datadir'],'search_index',array('ns' => $ns), $sconf['ns'] );
        echo html_buildlist($data,'idx','html_list_index','html_li_index');
                                                                       
        echo '</div>';                                                     
        $event->preventDefault();

      }
    }/*}}}*/

    function override_conf( $conf_override ) {/*{{{*/
	global $conf;
	foreach( array( 'disableactions' ) as $key ) {
	    if( isset( $conf_override[$key] )) {
		$conf[$key] = $conf_override[$key]; 
	    }
	}
    }/*}}}*/

    function override_register( $conf_override ) {/*{{{*/
      global $lang;
      global $conf;
      global $auth;
      $default_groups = isset( $conf_override['default_groups'] ) ? $conf_override['default_groups'] : $conf['default_groups'];
      if(!$auth) return false;
      if(!$_POST['save']) return false;
      if(!$auth->canDo('addUser')) return false;

      //clean username
      $_POST['login'] = trim($auth->cleanUser($_POST['login']));

      //clean fullname and email
      $_POST['fullname'] = trim(preg_replace('/[\x00-\x1f:<>&%,;]+/','',$_POST['fullname']));
      $_POST['email']    = trim(preg_replace('/[\x00-\x1f:<>&%,;]+/','',$_POST['email']));

      if( empty($_POST['login']) ||
        empty($_POST['fullname']) ||
        empty($_POST['email']) ){
        msg($lang['regmissing'],-1);
        return false;
      }
      if ($conf['autopasswd']) {
        $pass = auth_pwgen();                // automatically generate password
      } elseif (empty($_POST['pass']) ||
            empty($_POST['passchk'])) {
        msg($lang['regmissing'], -1);        // complain about missing passwords
        return false;
      } elseif ($_POST['pass'] != $_POST['passchk']) {
        msg($lang['regbadpass'], -1);      // complain about misspelled passwords
        return false;
      } else {
        $pass = $_POST['pass'];              // accept checked and valid password
      }

      //check mail
      if(!mail_isvalid($_POST['email'])){
        msg($lang['regbadmail'],-1);
        return false;
      }

    //okay try to create the user
      if(!$auth->triggerUserMod('create', array($_POST['login'],$pass,$_POST['fullname'],$_POST['email'], $default_groups ))){
        msg($lang['reguexists'],-1);
        return false;
      }

      $substitutions = array(
        'NEWUSER' => $_POST['login'],
        'NEWNAME' => $_POST['fullname'],
        'NEWEMAIL' => $_POST['email'],
        );

      if (!$conf['autopasswd']) {
        msg($lang['regsuccess2'],1);
        notify('', 'register', '', $_POST['login'], false, $substitutions);
        return true;
      }
   
    // autogenerated password? then send him the password
      if (auth_sendPassword($_POST['login'],$pass)){
        msg($lang['regsuccess'],1);
        notify('', 'register', '', $_POST['login'], false, $substitutions);
        return true;
      }else{
        msg($lang['regmailfail'],-1);
        return false;
      }

      return true;
  }/*}}}*/

  function override_template(  $conf_override ) {/*{{{*/

    $t = plugin_load( 'action', 'templateconfhelper_templateaction' );
    if( $t ) {
      $t->tpl_switch( $conf_override['template'] );
    }


  }/*}}}*/

  function override_defaultpage(  $conf_override ) {/*{{{*/
    global $ID,$INFO,$conf;

    if( $defaultpage = $conf_override['default_startpage'] ) {
	if( $ID == $conf['start'] ) {
	    $ID	    = $defaultpage;
	    $NS     = getNS($ID);
	    $INFO = pageinfo();
	    $JSINFO['id']        = $ID;
	    $INFO['namespace'] = (string) $INFO['namespace'];
	    if ($conf['breadcrumbs']) breadcrumbs();
	}
    }
  }/*}}}*/

  function override_namespace(  $conf_override ) {/*{{{*/
    global $ID,$INFO,$conf;
    $path = explode( ':', $ID );
    if( !$conf_override['ns'] ) { return ''; }
    if( count( $path ) > 1 && $path[0] == $conf_override['ns'] ) { return ''; }

    $file = wikiFN($ID);
    #if( file_exists( $file )) { return ''; }       // why was this here?

    $ID	    = $conf_override['ns'].':'.$ID;;
    $NS     = getNS($ID);
    $INFO = pageinfo();
    $JSINFO['id']        = $ID;
    $INFO['namespace'] = (string) $INFO['namespace'];
    if ($conf['breadcrumbs']) breadcrumbs();

  }/*}}}*/

  function override_index(  $conf_override ) {/*{{{*/
    global $ID,$INFO,$conf;
    $path = explode( ':', $ID );
    if( !$conf_override['ns'] ) { return ''; }
    if( count( $path ) > 1 && $path[0] == $conf_override['ns'] ) { return ''; }

    $file = wikiFN($ID);
    #if( file_exists( $file )) { return ''; }       // why was this here?

    $dir = $conf['datadir'];                                           
    $ns  = cleanID($ns);                                               

#    #fixme use appropriate function                                    
#    if(empty($ns)){                                                    
#        $ns = dirname(str_replace(':','/',$ID));                       
#        if($ns == '.') $ns ='';                                        
#    }                                                                  
    $dir = $conf['datadir'];                                           
    $ns  = cleanID($ns);                                               
    $ns  = utf8_encodeFN(str_replace(':','/',$ns));                    
                                                                       
    echo p_locale_xhtml('index');                                      
    echo '<div id="index__tree">';                                     
                                                                       
    $data = array();                                                   
    search($data,$conf['datadir'],'search_index',array('ns' => $ns),'dw');
echo "<pre>";                                                                                                                                    
print_r( $data );                                                      
echo "</pre>";                                                         
    echo html_buildlist($data,'idx','html_list_index','html_li_index');
                                                                       
    echo '</div>';                                                     

exit;

#    $ID	    = $conf_override['ns'].':'.$ID;;
#    $NS     = getNS($ID);
#    $INFO = pageinfo();
#    $JSINFO['id']        = $ID;
#    $INFO['namespace'] = (string) $INFO['namespace'];
#    if ($conf['breadcrumbs']) breadcrumbs();

  }/*}}}*/

}
