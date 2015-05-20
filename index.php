<?php
class Uber {
	var $token;
	var $config = array(
		'api_version'	=> 'v1',
		'redirect_uri'	=> '',
		'client_id'		=> '',
		'client_secret'	=> '',
		'server_token'	=> ''
	);

	function __construct() {
		if ( ! session_id() )
			session_start();

		if ( isset( $_GET['code'] ) ) {
			$code = $_GET['code'];
			$this->fetch_token( $code );
		}
		
		$this->token = $this->get_token();

		if ( isset( $_GET['add'] ) ) {
			$this->add_highscore();
		}

		if ( isset( $_GET['logout'] ) ) {
			unset( $_SESSION['uber_token'] );
			
			$this->redirect( '?logged_out=1' );
			exit;
		}
	}

	function get_highscore() {
		$pdo = $this->db();

		$sth = $pdo->query( 'SELECT * FROM uber_highscore ORDER BY score DESC' );
		$highscore = $sth->fetchAll(PDO::FETCH_CLASS);

		return $highscore;
	}

	function add_highscore() {
		$history = $this->post('history');
		$profile = $this->post('me');

		$pdo = $this->db();

		$name = $profile->first_name . ' ' . $profile->last_name;

		$exists_sth = $pdo->prepare( 'SELECT * FROM uber_highscore WHERE name = :name', array( PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY ) );
		$exists_sth->execute( array(
			':name'		=> $name
		) );
		$exists = $exists_sth->fetch();

		if ( ! empty( $exists ) ) {
			$this->redirect( '?added=0' );
			exit;
		}
		
		$sth = $pdo->prepare( 'INSERT INTO uber_highscore(name,picture,score) VALUES(:name, :picture, :score)', array( PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY ) );
		$sth->execute( array(
			':name'		=> $name,
			':picture'	=> $profile->picture,
			':score'	=> $history->count
		) );

		$this->redirect( '?added=1' );
		exit;
	}

	function get_db_config() {
		return array(
			'db_host'		=> 'localhost',
			'db_user'		=> 'root',
			'db_password'	=> '',
			'db_name'		=> 'uber'
		);
	}

	function db() {
		$db = $this->get_db_config();

		$pdo = new PDO( 'mysql:dbname=' . $db['db_name'] . ';host=' . $db['db_host'], $db['db_user'], $db['db_password'] );

		return $pdo;
	}

	function get_login_url() {
		$args = array(
			'response_type'	=> 'code',
			'client_id'		=> $this->config['client_id'],
			'redirect_uri'	=> $this->config['redirect_uri']
		);

		$url = 'https://login.uber.com/oauth/authorize?' . http_build_query( $args );

		return $url;
	}

	function get_token() {
		if ( isset( $_SESSION['uber_token'] ) ) {
			return $_SESSION['uber_token'];
		}

		return '';
	}

	function fetch_token( $code ) {
		$auth_url = 'https://login.uber.com/oauth/token';
		
		$args = array(
			'client_secret'	=> $this->config['client_secret'],
			'client_id'		=> $this->config['client_id'],
			'grant_type'	=> 'authorization_code',
			'redirect_uri'	=> $this->config['redirect_uri'],
			'code'			=> $code
		);
		$data = http_build_query( $args );

		$ch = curl_init();

		curl_setopt( $ch, CURLOPT_URL, $auth_url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 15 );
		curl_setopt( $ch, CURLOPT_POST, 1 );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );

		$result = curl_exec( $ch );
		$result = json_decode( $result );

		if ( isset( $result->access_token ) ) {
			$_SESSION['uber_token'] = $result->access_token;

			$this->redirect( '?add=1' );
			exit;
		} else {
			$this->redirect( '?error=auth' );
			exit;
		}
	}

	function post( $service, $parameters = array() ) {
		$api_url = 'https://api.uber.com/' . $this->config['api_version'] . '/';
		$request_url = $api_url . $service;

		$ch = curl_init();

		curl_setopt( $ch, CURLOPT_URL, $request_url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 15 );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'Authorization: Bearer ' . $this->token ) );

		$result = curl_exec( $ch );

		$result = json_decode( $result );

		return $result;
	}

	function redirect( $uri ) {
		header( 'Location: ' . $this->config['redirect_uri'] . $uri );
	}
}
$uber = new Uber();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Uber</title>
<link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap.min.css">
<link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap-theme.min.css">
<style>
body {
	max-width: 600px;
	margin: 0 auto;
}
</style>
</head>
<body>
	<h1>Uber Highscore</h1>
	<p><b>Awesome!</b> Be the most Uber person on the Internet by adding your score. The amount of trips you've done with Uber will count as your score. Let's hope you've spent a lot of money!</p>

	<?php if ( isset( $_GET['added'] ) ) :?>
		<div class="alert alert-success" role="alert"><b>Hooray!</b> Your score was added to the highscore.</div>
	<?php endif; ?>
	
	<?php if ( empty( $uber->token ) ) : ?>
		<h2>Add your score!</h2>
		<p><a href="<?php echo $uber->get_login_url(); ?>" class="btn btn-primary">Login with Uber</a></p>
	<?php endif; ?>
	
	<?php $highscore = $uber->get_highscore(); ?>
	<table class="table table-striped">
		<thead>
			<th></th>
			<th>Name</th>
			<th>Score</th>
		</thead>
		<tbody>
			<?php $index = 0; ?>
			<?php foreach( $highscore as $row ) : $index++; ?>
				<tr>
					<td><?php echo $index; ?></td>
					<td><img src="<?php echo $row->picture; ?>" width="30"> <?php echo $row->name; ?></td>
					<td><?php echo $row->score; ?></td>
				</tr>
			<?php endforeach; ?>
	</table>
	
	<p>
		<?php if ( ! empty( $uber->token ) ) : ?>
			<a href="<?php echo $uber->config['redirect_uri'] . '?logout=1'; ?>" class="btn btn-primary">Logout</a>
		<?php endif; ?>
	</p>
</body>
</html>