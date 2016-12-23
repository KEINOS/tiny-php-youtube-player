<?php

/* The Class is defined at the end of the page */
$my_ytp = new Tiny_YTP_MPSYT();

/* Set basic settings */
$my_ytp->api_key      = '';     // your Youtube Data API key
$my_ytp->player       = 'mpv';  // omxplayer or mpv
$my_ytp->searh_mode   = 'song'; // song or list
$my_ytp->path_mpsyt   = '/usr/local/bin/mpsyt'; // use `whereis mpsyt` to find

/* Set GET query value or default settings */
$my_ytp->search_keyword = ( isset( $_GET['search']     ) ) ? $_GET['search']     : "shortest song";
$my_ytp->debug_mode     = ( isset( $_GET['debug_mode'] ) ) ? $_GET['debug_mode'] : "false";
$my_ytp->play_mode      = ( isset( $_GET['play_mode']  ) ) ? $_GET['play_mode']  : 'song';
$my_ytp->status         = ( isset( $_GET['status']     ) ) ? $_GET['status']     : 'stop';

/* Pre prossess like sanitizing or setting flags */
$my_ytp->ready();

/* Creating checked attribute for HTML form */
$att_is_debug_mode = ( $my_ytp->flag_is_debug_mode ) ? " checked='checked'" : '';
$att_is_song_mode  = ( $my_ytp->flag_is_song_mode  ) ? " checked='checked'" : '';
$att_is_list_mode  = ( $my_ytp->flag_is_list_mode  ) ? " checked='checked'" : '';

?>
<html>
<head>
	<meta charset='UTF-8'>
	<title>Tiny PHP Youtube Player for Mpsyt</title>
</head>
<body>

<h1>Tiny PHP Youtube Player for Mpsyt</h1>
<h2>Search</h2>
<form action='./index2.php' method='get'>
	<div>
		Search keyword :
		<input type='text' name='search' value='<?php echo $my_ytp->search_keyword; ?>' >
	</div>
	<div>
		Search as a :
		<label><input type='radio' name='play_mode' value='song' <?php echo $att_is_song_mode;?> >Song name</label>
		<label><input type='radio' name='play_mode' value='list' <?php echo $att_is_list_mode;?> >Play List name</label>
	</div>
	<div>
		<label>
			Debug mode : <input type='checkbox' name="debug_mode" value="true" <?php echo $att_is_debug_mode; ?> >
			(Show output log after play)
		</label>
	</div>
	<div>
		<button type='submit' name="status" value='play'>Play the 1st hit</button>
		<button type='submit' name="status" value='stop'>Stop the music</button>
	</div>
</form>

<h2>Status</h2>
<?php $my_ytp->play(); ?>

<hr>
<p><small>Powered by <a href='https://github.com/mps-youtube/mps-youtube'>Mps-yutube</a></small></p>
</body></html>

<?php
/* =====================================================
	Tiny PHP Youtube Player for Mpsyt w/RaspberryPi

	GitHub
	https://github.com/KEINOS/tiny-php-youtube-player

	This software was tested only on RaspberryPi3 ModelB
	with Raspbian Jessie + Mps-youtube.

	About Mps-youtube, see:
	https://github.com/mps-youtube/mps-youtube
   ===================================================== */
class Tiny_YTP_MPSYT {
	/* Properties */
	public $api_key        = "";
	public $debug_mode     = "false";
	public $player         = "mpv";
	public $play_mode      = "song";
	public $path_mpsyt     = "/usr/local/bin/mpsyt";
	public $search_keyword = "";
	public $song_default   = "shortest song";
	public $status         = "play";
	public $shuffle_list   = "true";

	public $flag_has_api_key   = false;
	public $flag_is_sanitized  = false;
	public $flag_is_debug_mode = false;
	public $flag_is_list_mode  = false;
	public $flag_is_song_mode  = true;
	public $flag_stop_play     = false;

	public $array_output_log     = array();
	public $array_returned_value = array();

	/* Constants */
	const DO_NOT_SHOW_HISTORY = true;
	
	function __construct( ){}


	function log_output( $array ){
		$this->array_output_log += array_filter( $array );		
	}

	function log_returned_value( $value ){
		$this->array_returned_value[] = $value;
	}

	/* Kill current plaing process */
	function pkill_all( $flag_force_unshow_history = false ){

		exec( 'sudo pkill -f mpsyt', $output, $return_var );
		$this->log_output( $output );
		$this->log_returned_value( $return_var );

		exec( "sudo pkill -f {$this->player}", $output, $return_var );
		$this->log_output( $output );
		$this->log_returned_value( $return_var );

		// Get song history
		if( $this->flag_is_debug_mode && ! $flag_force_unshow_history ){
			exec( "sudo {$this->path_mpsyt} history,q", $output, $return_var );
			$this->log_output( $output );
			$this->log_returned_value( $return_var );			
		}

	}

	/* Escaping input strings to sanitize */
	function sanitize(){
		$this->search_keyword = trim( htmlspecialchars( $this->search_keyword ) );
		$this->api_key        = trim( htmlspecialchars( $this->api_key ) );
		$this->song_default   = trim( htmlspecialchars( $this->song_default ) );

		$this->search_keyword = ! empty( $this->search_keyword ) ? $this->search_keyword : $this->song_default;

		$this->flag_is_sanitized = true;
	}

	function set_flags(){
		$this->flag_has_api_key   = ( ! empty( $this->api_key ) ) ? true : false;
		$this->flag_is_debug_mode = ( 'true' === mb_strtolower( $this->debug_mode ) ) ? true : false;
		$this->flag_is_song_mode  = ( 'song' === mb_strtolower( $this->play_mode  ) ) ? true : false;
		$this->flag_is_list_mode  = ( 'list' === mb_strtolower( $this->play_mode  ) ) ? true : false;
		$this->flag_stop_play     = ( 'stop' === mb_strtolower( $this->status     ) ) ? true : false;
	}

	function ready(){

		if( ! $this->flag_is_sanitized ){
			$this->sanitize();
		}

		$this->set_flags();

	}

	/* Create command line for `exec` func. */
	function create_command() {

		if( ! $this->flag_is_sanitized ){
			$this->sanitize();
		}
		
		$this->set_flags();

		$path_mpsyt         = $this->path_mpsyt;
		$flag_has_api_key   = $this->flag_has_api_key;
		$api_key            = $this->api_key;
		$player             = $this->player;
		$flag_is_song_mode  = $this->flag_is_song_mode;
		$search_keyword     = $this->search_keyword;
		$flag_is_debug_mode = $this->flag_is_debug_mode;

		/* Creating command */
		$command  = "sudo {$path_mpsyt}";
		$command .= ( $flag_has_api_key ) ? " set api_key {$api_key}," : "";
		$command .= " set player {$player},";
		$command .= ( $flag_is_song_mode ) ? ".{$search_keyword}," : "//{$search_keyword},";
		$command .= ( $flag_is_song_mode ) ? "1," : "1,shuffle,all,";
		$command .= "q";
		$command .= ( $flag_is_debug_mode ) ? "" : ' > /dev/null &';
		$command  = trim( $command );

		return $command;

	}

	/* Main function to play Mpsyt */
	function play(){
		
		$this->set_flags();

		if ( $this->flag_stop_play ){

		    echo( '<p>Now Stopping all the music...</p>');

		    @ob_flush();
		    @flush();
			$this->pkill_all();

			if ( $this->flag_is_debug_mode){
				echo '<h2>Output</h2><pre>';
				print_r( array_filter( $this->array_output_log ) );
				echo '</pre>';
				echo '<h2>Returned Value</h2><pre>';
				print_r( $this->array_returned_value );
				echo '</pre>';
			}

		} else {

			$command = $this->create_command();

		    echo( "<p>Command sent : {$command}</p>" );
		    if ( $this->flag_is_song_mode ){
			    echo( '<p>Now playing the first song found...</p>');
		    }else{
			    echo( '<p>Now playing the first play list found...<br>(All the songs on the list has been shuffled)</p>');
		    }

		    @ob_flush();
		    @flush();
			$this->pkill_all( self::DO_NOT_SHOW_HISTORY );

			// here is the actual command to play Mpsyt via PHP.
			exec( $command, $output, $return_var );

			$this->log_output( $output );
			$this->log_returned_value( $return_var );

			if ( $this->flag_is_debug_mode){
				echo '<h2>Output</h2><pre>';
				print_r( array_filter( $this->array_output_log ) );
				echo '</pre>';
				echo '<h2>Returned Value</h2><pre>';
				print_r( $this->array_returned_value );
				echo '</pre>';
			}
		}
	}


	function set_search_keyword( $search_keyword ){
		$default = trim( $this->song_default );

		$keyword = trim( $search_keyword );
		$keyword = mb_convert_encoding( $keyword, 'UTF-8', 'auto' );
		$keyword = ( Empty( $keyword ) ) ? $default : $keyword;
		$keyword = htmlspecialchars( $keyword );
		// currently commented below to check multibite code(Japanese) input. Uncomment it for more security.
		//$search_keyword = urlencode( $search_keyword );

		$this->search_keyword = $keyword;
	}
}

