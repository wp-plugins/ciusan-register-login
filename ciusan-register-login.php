<?php 
/*
Plugin Name: Ciusan Register Login
Plugin URI: http://plugin.ciusan.com/
Description: Showing login, register or lost password form modal popup with ajax.
Author: Dannie Herdyawan
Version: 1.0
Author URI: http://www.ciusan.com/
*/

/*
   _____                                                 ___  ___
  /\  __'\                           __                 /\  \/\  \
  \ \ \/\ \     __      ___     ___ /\_\     __         \ \  \_\  \
   \ \ \ \ \  /'__`\  /' _ `\ /` _ `\/\ \  /'__'\        \ \   __  \
    \ \ \_\ \/\ \L\.\_/\ \/\ \/\ \/\ \ \ \/\  __/    ___  \ \  \ \  \
     \ \____/\ \__/.\_\ \_\ \_\ \_\ \_\ \_\ \____\  /\__\  \ \__\/\__\
      \/___/  \/__/\/_/\/_/\/_/\/_/\/_/\/_/\/____/  \/__/   \/__/\/__/

*/

function ajax_auth_init(){
wp_register_style( 'ajax-auth-style', plugin_dir_url( __FILE__ ).'/ajax-auth-style.css');
wp_enqueue_style('ajax-auth-style');
wp_register_script('validate-script', plugin_dir_url( __FILE__ ).'/jquery.validate.js', array('jquery'));
    wp_enqueue_script('validate-script');
 
    wp_register_script('ajax-auth-script', plugin_dir_url( __FILE__ ).'/ajax-auth-script.js', array('jquery'));
    wp_enqueue_script('ajax-auth-script');
 
    wp_localize_script( 'ajax-auth-script', 'ajax_auth_object', array(
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
        'redirecturl' => home_url(),
        'loadingmessage' => __('Sending user info, please wait...')
    ));
 
    // Enable the user with no privileges to run ajax_login() in AJAX
    add_action( 'wp_ajax_nopriv_ajaxlogin', 'ajax_login' );
// Enable the user with no privileges to run ajax_register() in AJAX
add_action( 'wp_ajax_nopriv_ajaxregister', 'ajax_register' );
}
 
// Execute the action only if the user isn't logged in
    add_action('init', 'ajax_auth_init');
  
// Execute the action only if the user isn't logged in
//if (!is_user_logged_in()) {
    add_action('init', 'ajax_auth_init');
//}
  
function ajax_login(){

    // First check the nonce, if it fails the function will break
    check_ajax_referer( 'ajax-login-nonce', 'security' );

    // Nonce is checked, get the POST data and sign user on
  	// Call auth_user_login
	auth_user_login($_POST['username'], $_POST['password'], 'Login'); 
	
    die();
}

function ajax_register(){

    // First check the nonce, if it fails the function will break
    check_ajax_referer( 'ajax-register-nonce', 'security' );
		
    // Nonce is checked, get the POST data and sign user on
    $info = array();
  	$info['user_nicename'] = $info['nickname'] = $info['display_name'] = $info['first_name'] = $info['user_login'] = sanitize_user($_POST['username']) ;
    $info['user_pass'] = sanitize_text_field($_POST['password']);
	$info['user_email'] = sanitize_email( $_POST['email']);
	
	// Register the user
    $user_register = wp_insert_user( $info );
 	if ( is_wp_error($user_register) ){	
		$error  = $user_register->get_error_codes()	;
		
		if(in_array('empty_user_login', $error))
			echo json_encode(array('loggedin'=>false, 'message'=>__($user_register->get_error_message('empty_user_login'))));
		elseif(in_array('existing_user_login',$error))
			echo json_encode(array('loggedin'=>false, 'message'=>__('This username is already registered.')));
		elseif(in_array('existing_user_email',$error))
        echo json_encode(array('loggedin'=>false, 'message'=>__('This email address is already registered.')));
    } else {
	  auth_user_login($info['nickname'], $info['user_pass'], 'Registration');       
    }

    die();
}

function auth_user_login($user_login, $password, $login)
{
	$info = array();
    $info['user_login'] = $user_login;
    $info['user_password'] = $password;
    $info['remember'] = true;
	
	$user_signon = wp_signon( $info, false );
    if ( is_wp_error($user_signon) ){
		echo json_encode(array('loggedin'=>false, 'message'=>__('Wrong username or password.')));
    } else {
		wp_set_current_user($user_signon->ID); 
        echo json_encode(array('loggedin'=>true, 'message'=>__($login.' successful, redirecting...')));
    }
	
	die();
}

function ajax_forgotPassword(){
	 
	// First check the nonce, if it fails the function will break
    check_ajax_referer( 'ajax-forgot-nonce', 'security' );
	
	global $wpdb;
	
	$account = $_POST['user_login'];
	
	if( empty( $account ) ) {
		$error = 'Enter an username or e-mail address.';
	} else {
		if(is_email( $account )) {
			if( email_exists($account) ) 
				$get_by = 'email';
			else	
				$error = 'There is no user registered with that email address.';			
		}
		else if (validate_username( $account )) {
			if( username_exists($account) ) 
				$get_by = 'login';
			else	
				$error = 'There is no user registered with that username.';				
		}
		else
			$error = 'Invalid username or e-mail address.';		
	}	
	
	if(empty ($error)) {
		// lets generate our new password
		//$random_password = wp_generate_password( 12, false );
		$random_password = wp_generate_password();

			
		// Get user data by field and data, fields are id, slug, email and login
		$user = get_user_by( $get_by, $account );
			
		$update_user = wp_update_user( array ( 'ID' => $user->ID, 'user_pass' => $random_password ) );
			
		// if  update user return true then lets send user an email containing the new password
		if( $update_user ) {
			
			$from = get_option('admin_email'); // Set whatever you want like mail@yourdomain.com
			
			if(!(isset($from) && is_email($from))) {		
				$sitename = strtolower( $_SERVER['SERVER_NAME'] );
				if ( substr( $sitename, 0, 4 ) == 'www.' ) {
					$sitename = substr( $sitename, 4 );					
				}
				$from = 'do-not-reply@'.$sitename; 
			}
			
			$to = $user->user_email;
			$subject = 'Your new password';
			$sender = 'From: '.get_option('name').' <'.$from.'>' . "\r\n";
			
			$message = 'Your new password is: '.$random_password;
				
			$headers[] = 'MIME-Version: 1.0' . "\r\n";
			$headers[] = 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
			$headers[] = "X-Mailer: PHP \r\n";
			$headers[] = $sender;
				
			$mail = wp_mail( $to, $subject, $message, $headers );
			if( $mail ) 
				$success = 'Check your email address for you new password.';
			else
				$error = 'System is unable to send you mail containg your new password.';						
		} else {
			$error = 'Oops! Something went wrong while updating your account.';
		}
	}
	
	if( ! empty( $error ) )
		//echo '<div class="error_login"><strong>ERROR:</strong> '. $error .'</div>';
		echo json_encode(array('loggedin'=>false, 'message'=>__($error)));
			
	if( ! empty( $success ) )
		//echo '<div class="updated"> '. $success .'</div>';
		echo json_encode(array('loggedin'=>false, 'message'=>__($success)));
				
	die();
}

function ciusan_login_form() { ?>
<form id="login" class="ajax-auth" action="login" method="post">
	<h1>Login</h1>
	<hr />
	<p class="status"></p>  
	<?php wp_nonce_field('ajax-login-nonce', 'security'); ?>  
	<span for="username">Username</span>    
	<span style="float:right;"><a id="pop_signup" style="cursor:pointer;color:#B4B2B2;">Create an Account!</a></span>
	<input id="username" type="text" class="required" name="username" placeholder="Insert your username">
	<span for="password">Password</span>
	<input id="password" type="password" class="required" name="password" placeholder="Insert your password">
	<input class="button" type="submit" value="LOGIN">
	<a id="pop_forgot" class="text-link"  href="<?php echo wp_lostpassword_url(); ?>">Forgot Password?</a>
	<a class="close" href="">(close)</a>    
</form>

<form id="register" class="ajax-auth"  action="register" method="post">
    <h1>Create an Account</h1>
    <hr />
    <p class="status"></p>
    <?php wp_nonce_field('ajax-register-nonce', 'signonsecurity'); ?>         
    <span for="signonname">Username</span>
    <input id="signonname" type="text" name="signonname" class="required" placeholder="Your unique username">
    <span for="email">Email</span>
    <input id="email" type="text" class="required email" name="email" placeholder="Your valid email">
    <span for="signonpassword">Password</span>
    <input id="signonpassword" type="password" class="required" name="signonpassword" placeholder="Create secure password">
    <span for="password2">Confirm Password</span>
    <input type="password" id="password2" class="required" name="password2" placeholder="Confirm your secure password">
    <input class="button" type="submit" value="SIGNUP">
	<a id="pop_login" class="text-link" style="cursor:pointer">Want to Login?</a>
    <a class="close" href="">(close)</a>    
</form>

<form id="forgot_password" class="ajax-auth" action="forgot_password" method="post">    
    <h1>Forgot Password?</h1>
    <hr />
    <p class="status"></p>  
    <?php wp_nonce_field('ajax-forgot-nonce', 'forgotsecurity'); ?>  
    <span for="user_login">Username or Email</span>
    <input id="user_login" type="text" class="required" name="user_login" placeholder="Insert your username or email">
	<input class="button" type="submit" value="SUBMIT">
	<a class="close" style="cursor:pointer">(close)</a>    
</form>
<?php } 
add_action("wp_footer", "ciusan_login_form");


function ciusan_login() {
	if (!is_user_logged_in()){
		return '<a id="show_login" style="cursor:pointer">Login</a>';
	}
} add_shortcode('ciusan_login', 'ciusan_login');

function ciusan_register() {
	if (!is_user_logged_in()){
		return '<a id="show_signup" style="cursor:pointer">Create an Account?</a>';
	}
} add_shortcode('ciusan_register', 'ciusan_register');

function ciusan_logout($atts, $content = null) {
	if (is_user_logged_in()){
		extract( shortcode_atts( array(
			'redirect' => 'default'
		), $atts ) );
		switch ($redirect) {
			case 'default':
			$output = wp_logout_url();
			break;
			case 'current':
			$output = wp_logout_url(get_permalink());
			break;
			case 'home':
			$output = wp_logout_url(home_url());
			break;
		}
		return $output;
	}
} add_shortcode('ciusan_logout', 'ciusan_logout');
?>