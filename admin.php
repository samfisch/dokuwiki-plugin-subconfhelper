<?php
if (!defined('DOKU_INC')) die();
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once(DOKU_PLUGIN.'admin.php');

class admin_plugin_subconfhelper extends DokuWiki_Admin_Plugin {


    function getMenuSort() { return 200; }
    function forAdminOnly() { return false; }

    function handle() {/*{{{*/
        global $lang;

	$this->meta = DOKU_PLUGIN.'subconfhelper/conf/subconfhelper_metadata.php';
	$this->default = DOKU_PLUGIN.'subconfhelper/conf/subconfhelper_default.php';
	$this->config_path = DOKU_CONF;
	$this->config_prefix = 'subconfhelper_';

        if (!isset($_REQUEST['vhost']['admin'])) { $admin = null; }
	else { 
	    $admin = (is_array($_REQUEST['vhost']['admin'])) ? key( $_REQUEST['vhost']['admin'] ) 
			    : $_REQUEST['vhost']['admin']; 
	}

	if( isset( $_REQUEST['vhost']['vhost']) && preg_match( '/[a-z0-9_\-]/', $_REQUEST['vhost']['vhost'] )) {
	    $vhost = $_REQUEST['vhost']['vhost'];
	    if( $vhost == '__new__' ) {
		if( isset( $_REQUEST['ns']['ns']) && preg_match( '/[a-z0-9_\-]/', $_REQUEST['ns']['ns'] )) {
		    $vhost = $_REQUEST['ns']['ns'];
		}
	    }
            $file = $this->config_path.$this->config_prefix.$vhost.'.php';
	    $_input = $_REQUEST['config'];
	}

        // handle actions
        switch($admin) {

            case 'vhost_new':
                if( is_file( $file ) || is_dir( $file ) || !touch( $file )) {
                    msg($this->getLang('msg_vhost_error'), -1);
                } else {
                    msg($this->getLang('msg_vhost_delete'), 1);
                }
              break;

            case 'vhost_delete':
                if( !is_file( $file ) || is_dir( $file ) || !unlink( $file )) {
                    msg($this->getLang('msg_vhost_error'), -1);
                } else {
                    msg($this->getLang('msg_vhost_save'), 1);
                }
              break;

            case 'vhost_save':
		if( !$vhost || !is_file( $file )) break;

		$_changed   = false;
		$_error	    = false;

		$_config = &plugin_load( 'helper', 'subconfhelper_config' );
		$_config->configuration( $this->meta,  array( $file ));

		while (list($key) = each($_config->setting)) {
		  $input = isset($_input[$key]) ? $_input[$key] : NULL;
		  if ($_config->setting[$key]->update($input)) {
		    $_changed = true;
		  } 
		  if ($_config->setting[$key]->error()) $_error = true;
		}   
		if( $_changed  && !$_error && $_config->save_settings( $vhost )) {
		    msg($this->getLang('msg_vhost_save'), 1);
		} else {
		    msg($this->getLang('msg_vhost_save_error'), -1);
		}
              break;

            default:
                // do nothing - show dashboard
                break;
        }
    }/*}}}*/

    function html() {/*{{{*/
        ptln('<div  class="act-admin"><div id="subconfhelper">');
        $this->xhtml_vhost_list();
        ptln('</div></div>');
    }/*}}}*/

    function xhtml_vhost_list( ) {/*{{{*/
        global $lang;
        global $ID;

        ptln( '<div class="level2">' );
        ptln( '<p>' . $this->getLang( 'vhost_text' ).'</p>' );
        ptln( '<ul>' );

        $vhost = '__new__';
        ptln( '<li class="vhostitem"><h2>'.$this->getLang( 'vhost' ).$vhost.'</h2></li>' );
        ptln( '<form action="" method="post">' );
	ptln( "<input type='hidden' name='vhost[admin]' value='vhost_new' />" );
        ptln( '<ul><li>' );
        ptln( '<span class="outkey"><label for="vhost-new">'.$this->getLang( 'vhost-new' ).'</label></span>' );
        ptln( '<input id="vhost-new" type="text" class="edit" name="vhost[vhost]" />' );
        ptln( '<li class="submit"><input type="submit" value="Save"></li>' );
	ptln( "</form>" );
        ptln( '</li></ul>' );

        if( $handle = opendir( $this->config_path )) {
            while( false !== ( $file = readdir( $handle ))) {
                if( strpos( $file, $this->config_prefix ) === 0  && !strpos( $file, '.bak' )) {
                    $vhost = str_replace( '.php', '', str_replace( $this->config_prefix, '', $file ));
                    ptln( '<li class="vhostitem">' );
                    ptln( '<h2>'.$this->getLang( 'vhost' ).$vhost.'</h2>' );
                    $this->xhtml_vhost_item( $vhost, $this->config_path.$file );
                    ptln( '</li>' );

                }
            }
            closedir($handle);


        }

        ptln('</ul>');
        ptln('</div>');
    }/*}}}*/

    function xhtml_vhost_item( $vhost, $file ) {/*{{{*/
        global $lang;
        global $ID;

        $meta = $this->meta;
        $default = $this->default;
        $_config = &plugin_load( 'helper', 'subconfhelper_config'); #$_config->configuration( $meta, array( $file ), array( $default )); // TODO defaults
        $_config->configuration( $meta, array( $file ));

        if ($_config->locked)
            ptln('<div class="info">'.$this->getLang('locked').'</div>');
        elseif ($this->_error)
            ptln('<div class="error">'.$this->getLang('error').'</div>');
        elseif ($this->_changed)
            ptln('<div class="success">'.$this->getLang('updated').'</div>');

	ptln( "<form action='' method='post'>" );
	ptln( "<input type='hidden' name='vhost[admin]' value='vhost_save' />" );
	ptln( "<input type='hidden' name='vhost[vhost]' value='$vhost' />" );
        ptln( '<ul>');
        foreach( $_config->setting as $setting ) {

          list($label,$input) = $setting->html($this, $this->_error);
                                                       
          $class = $setting->is_default() ? ' class="default"' : ($setting->is_protected() ? ' class="protected"' : '');
          $error = $setting->error() ? ' class="value error"' : ' class="value"';
          $icon = $setting->caution() ? '<img src="'.DOKU_PLUGIN_IMAGES.$setting->caution().'.png" '
                                .'alt="'.$setting->caution().'" title="'.$this->getLang($setting->caution()).'" />' : '';
                                                       
          ptln( '<li '.$class.'>');                  
          ptln( '<span class="outkey">'.$icon.$label.'</span>');
          ptln( $input );               
          ptln( '</li>');                           
        }
        ptln( "<li class='submit'>" );
        ptln( "<input type='submit' value='".$lang['btn_save']."'>" );                           
        ptln( "<input type='submit' name=vhost[admin][vhost_delete] value='".$this->getLang( 'btn_vhost_del' )."'>" );                           
        ptln( '</li>');                           
        ptln( '</ul>');
	ptln( "</form>" );

    }/*}}}*/

}
