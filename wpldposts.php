<?php
/*
 * Plugin Name: WpLdp
 * Plugin URI: https://wpldp.wares.fr/
 * Description: This is a plugin which aims to emulate the default caracteristics of a Linked Data Platform compatible server
 * Version: 0.1
 * License: GPL2
 */
 
// TODO : repartir sur plusieurs fichiers ? => includes.php
// TODO : crÃ©er fonction get_context();

namespace wpldp;
 
// If the file is accessed outside of index.php (ie. directly), we just deny the access
defined('ABSPATH') or die("No script kiddies please!");

require_once('includes.php');

class wpldp
{

	/* default constructor */
    public function __construct()
    {
	
		/* loads additionnal wpldp functions */
		include_once plugin_dir_path( __FILE__ ).'/includes.php';
		new wpldp_includes();
		
		/* calls a function to register routes at the Rest API initialisation */
        add_action('rest_api_init', array($this, 'wpldp_register_routes')) ;
        
        /* calls a function to set special header when receiving OPTIONS request (wpldp_post_comments) */
        add_filter('rest_post_dispatch', array($this, 'wpldp_ac_allow_headers'));
        
    }

	// sets special header for function wpldp_post_comments
	public function wpldp_ac_allow_headers(\WP_REST_Response $result)
	{
		if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS')
		{
			$result->header('Access-Control-Allow-Headers', 'Authorization, Content-Type', true);
		}
		return $result;
	}
	

	/* Registers custom routes (comments for each route are listed above)
	 * 
	 * E.g : "yoursite.com/wordpress/wp-json/ldp/custom/" where
	 * 
	 * "yoursite.com/wordpress" is the main url
	 * "/wp-json/ is the default route for requests to the embedded WP rest api
	 * "/ldp" is the first URL segment after core prefix. Must be unique to our plugin
	 * "/custom" is the route to some function */
	 
	public function wpldp_register_routes()
    {

		wpldp_debug('wpldp_register_routes');
		
		/* Registers a route for listing posts */
		register_rest_route( 'ldp', '/posts/', array(
		'methods' => 'GET',
		'callback' => array($this, 'wpldp_list_posts') ));
		
		/* Registers a route for fonction post_details */
		register_rest_route( 'ldp', '/posts/(?P<slug>[a-zA-Z0-9-]+)', array(
		'methods' => 'GET',
		'callback' => array($this, 'wpldp_detail_post') ));
		
		/* Registers a route for fonction */
		register_rest_route( 'ldp', '/posts/(?P<slug>[a-zA-Z0-9-]+)/comments/', array(
		'methods' => 'GET',
		'callback' => array($this, 'wpldp_get_comments') ));
		
		/* Registers a route for fonction */
		register_rest_route( 'ldp', '/posts/(?P<slug>[a-zA-Z0-9-]+)/comments/', array(
		'methods' => 'POST',
		'callback' => array($this, 'wpldp_post_comments') ));
		
		/* Registers a route for fonction */
		register_rest_route( 'ldp', '/test/(?P<slug>[a-zA-Z0-9-]+)/comments/', array(
		'methods' => 'POST',
		'callback' => array($this, 'wpldp_test_comments') ));
		
		/* Registers a route for fonction */
		//register_rest_route( 'ldp', '/tests/(?P<slug>[a-zA-Z0-9-]+)/comments/', array(
		//'methods' => 'PUT',
		//'callback' => array($this, 'wpldp_put_comments') ));

	}
	
	/*
	 *  Returns all posts (in jdson-ld format ?)
	 * 	method : GET
	 * 	url : http://www.yoursite.com/wp-json/ldp/posts/
	 */
	 
	public function wpldp_list_posts()
	{	 
		
		wpldp_debug('wpldp_list_posts');
		
		// sets headers
		wpldp_default_headers();
		
		// lists all posts in array
		$tabPosts = get_posts();
		
		for ($cpt = 0; $cpt < count($tabPosts) ; $cpt++)
			{
				$posts[$cpt] = array(
				'rdfs:label'=>$tabPosts[$cpt]-> post_name,
				'dcterms:title'=>$tabPosts[$cpt]-> post_title,
				'dcterms:created'=>$tabPosts[$cpt]-> post_date,
				'sioc:User'=>$tabPosts[$cpt]-> post_author) ;
			}
		
		// initializes the "context" in array
		// see : http://json-ld.org/spec/latest/json-ld/#the-context
		$context = wpldp_get_context();
		
		// stores posts in array
		$graph = wpldp_get_container_graph($posts);
		
		// formats response
		$retour = array('@context' => $context, '@graph' => array($graph));
		
		// checks response then returns
		return rest_ensure_response($retour);
		
	}

	/* 
	 * Returns selected details of specified post (from postname)
	 * method : GET
	 * url : http://www.yoursite.com/wp-json/ldp/posts/some-post-slug/
	 */

	public function wpldp_detail_post($data)
	{
		wpldp_debug('wpldp_detail_post');
		
		// sets headers
		wpldp_default_headers();
		
		// gets slug from args
		$slug = $data['slug'];
	
		// gets post from its slug
		$post = get_page_by_path($data['slug'],OBJECT,'post');
		
		/* Autre solution :
		 * 
		 * $args = array(
		 * 'name'        => $slug,
		 * 'post_type'   => 'post',
		 * 'post_status' => 'publish',
		 * 'numberposts' => 1);
		 * 
		 * $post = get_posts($args)[0]; */
	
		// keeps only useful properties, link them to rdf <properties>, stores them in array
		$filteredPost = array(
		'sioc:User' => $post -> post_author,
		'dcterms:created' => $post -> post_date,
		'dcterms:text' => $post -> post_content,
		'dcterms:title' => $post -> post_title,
		'undefined:1' => $post -> post_status,
		'undefined:2' => $post -> comment_status,
		'rdfs:label' => $post -> post_name,
		'dcterms:modified' => $post -> post_modified,
		'undefined:3' => $post -> post_type);
	
		// initializes the "context" in array
		// see : http://json-ld.org/spec/latest/json-ld/#the-context
		$context = wpldp_get_context();
		
		// formats data
		$retour = array('@context' => $context, '@graph' => array($filteredPost));
		
		// returns json-ld formatted post
		return rest_ensure_response($retour);

	}

	/*
	 * returns comment(s) for a given post
	 * method : GET
	 * url : http://www.yoursite.com/wp-json/ldp/posts/some-post-slug/comments/
	 */
	 
	public function wpldp_get_comments($data)
	{
		
		wpldp_debug('wpldp_get_comments');
		
		// sets headers
		wpldp_default_headers();
		
		// gets slug from args
		$slug = $data['slug'];
		
		// initialises $filteredComments to null
		$filteredComments[0] = array();		
				
		$comments = get_comments('post_name='.$slug);

		// keeps only useful properties, link them to rdf <properties>, stores them in array
		$cpt = -1;
		foreach($comments as $comment)
		{
			$cpt = $cpt + 1;
			
			$filteredComments[$cpt] = array(
				'undefined:commentid' => $comment->comment_ID,
				// TODO : choisir entre author et ID pour sioc:user
				//'sioc:User' => $comment->comment_author,
				'sioc:User' => $comment->user_id,
				'dcterms:created' => $comment->comment_date,
				'dcterms:text' => $comment -> comment_content);
		}
		
		// initializes the "context" in array
		// see : http://json-ld.org/spec/latest/json-ld/#the-context
		$context = wpldp_get_context();
			
		$retour = array('@context' => $context, '@graph' => $filteredComments);
		
		// returns json-ld formatted post
		return rest_ensure_response($retour);
		
	}
	
	/*
	 * allows people to write comment for a given post
	 * method : POST
	 * url : http://www.yoursite.com/wp-json/ldp/posts/some-post-slug/comments/
	 */

	// TODO : ajouter validation des donnÃ©es (isset ?)
	// TODO : revoir la structure (if ? while ?)
	
	public function wpldp_orig_post_comments($data)
	{

		wpldp_debug('wpldp_post_comments');

		/*
		 * parameters :
		 * 
		 * 'rdfs:label' (slug)
		 * 'sioc:user' (author)
		 * 'dcterms:text' (content)
		 */
		
		// declarations
		$retour = null;
		$missingData = false;

		// gets post_id from slug contained in POST request
		if (isset($data['rdfs:label']))
		{
			$comment_post_id = wpldp_get_postid_by_slug($data['rdfs:label']);
		}
		else {$missingData = true; echo 'error : missing slug';}
		
		// gets poster id
		// TODO : envisager une creation de user "Ã  la volÃ©e" selon sioc:user ou compte invitÃ©
		$comment_user_id = 2;
		$tabUser = get_user_by('id', $comment_user_id);
		
		// gets user infos from id
		// TODO : envisager une creation de user "Ã  la volÃ©e" selon sioc:user ou compte invitÃ©
		$comment_author = $tabUser->display_name;
		$comment_author_email = $tabUser->user_email;
		$comment_author_url = $tabUser->user_url;
		
		// gets content of the comment
		// TODO : ATTENTION Ã  la validation des donnÃ©es ici (balises!)
		if (isset($data['dcterms:text']))
		{
			$comment_content = $data['dcterms:text'];
		}
		else {$missingData = true; echo 'error : missing content';}

		// sets various properties
		// TODO : a dÃ©finir
		$comment_type = '';
		$comment_parent = 0;
		
		// gets poster IP and HTTP_USER_AGENT
		$comment_author_IP = $_SERVER['REMOTE_ADDR'];
		$comment_agent = $_SERVER['HTTP_USER_AGENT'];
		
		// gets current time
		$time = current_time('mysql');

		// formats comment data
		$tabComment = array(
		'comment_post_ID' => $comment_post_id,
		'comment_author' => $comment_author,
		'comment_author_email' => $comment_author_email,
		'comment_author_url' => $comment_author_url,
		'comment_content' => $comment_content,
		'comment_type' => $comment_type,
		'comment_parent' => $comment_parent,
		'user_id' => $comment_user_id,
		'comment_author_IP' => $comment_author_IP,
		'comment_agent' => $comment_agent,
		'comment_date' => $time,
		'comment_approved' => 1,
		);
		
		// final validation test(s)
		if ($missingData)
		
		{
			$retour = 'Missing data !';
			return null;
		}
		
		else
		
		{
			// inserts comment if validation tests passed, then displays data for debugging purpose
			wp_insert_comment($tabComment);
			return $tabComment;
		}
	
	}
	
	/*
	 * allows people to write comment for a given post
	 * methods : POST, OPTIONS
	 * url : http://www.yoursite.com/wp-json/ldp/posts/some-post-slug/comments/
	 */
	
	public function wpldp_post_comments($data)
	{
		
		/*
		 * parameters :
		 * 
		 * 'rdfs:label' (slug)
		 * 'sioc:user' (author)
		 * 'dcterms:text' (content)
		 */

		// declarations
		$missingData = false;

		// sets headers
		wpldp_default_headers();
		header('Access-Control-Allow-Origin:*', true);
		
		// gets objects
		$body = json_decode($data->get_body());
		$context = $body->{'@context'};
		$graph = $body->{'@graph'};
		
		// gets @graph number 0 entrie, stores in array
		$graph_0 = $graph[0];
		
		// gets post_id from slug
		$comment_post_id = wpldp_get_postid_by_slug($graph_0->{'http://www.w3.org/2000/01/rdf-schema#label'});
		// probleme : le JS traduit 'rdfs:label' par son URI
		// Ã©crire une fonction qui rÃ©cupÃ¨re les bons URI ? ou inclure ces derniers dans la prÃ©sente fonction ?
		wpldp_debug('id article : ' . $comment_post_id);

		// gets poster id
		// TODO : envisager une creation de user "Ã  la volÃ©e" selon sioc:user ou compte invitÃ©
		// Toute une rÃ©flexion Ã  faire sur la gestion des utilisateurs, pour les posts/comments "externes"
		$comment_user_id = 2;
		$tabUser = get_user_by('id', $comment_user_id);
				
		// gets user infos from id
		$comment_author = $tabUser->display_name;
		$comment_author_email = $tabUser->user_email;
		$comment_author_url = $tabUser->user_url;
		wpldp_debug('auteur : ' . $comment_author);
		
		// gets content of the comment
		// TODO : ATTENTION Ã  la validation des donnÃ©es ici (balises!)
		$comment_content = $graph_0->{'dcterms:text'};		
		wpldp_debug('contenu : ' . $comment_content);
		
		// sets various properties
		// TODO : a dÃ©finir
		$comment_type = '';
		$comment_parent = 0;
		
		// gets poster IP and HTTP_USER_AGENT
		$comment_author_IP = $_SERVER['REMOTE_ADDR'];
		$comment_agent = $_SERVER['HTTP_USER_AGENT'];
		
		// gets current time
		$time = current_time('mysql');
		
		// formats comment data
		$tabComment = array(
		'comment_post_ID' => $comment_post_id,
		'comment_author' => $comment_author,
		'comment_author_email' => $comment_author_email,
		'comment_author_url' => $comment_author_url,
		'comment_content' => $comment_content,
		'comment_type' => $comment_type,
		'comment_parent' => $comment_parent,
		'user_id' => $comment_user_id,
		'comment_author_IP' => $comment_author_IP,
		'comment_agent' => $comment_agent,
		'comment_date' => $time,
		'comment_approved' => 1,
		);
		
		// creates comment
		// TODO: validation des donnÃ©es etc.
		wp_insert_comment($tabComment);
	
		// ALTERNATE ENDING : print_r($data->get_body()); exit(0);
		return($data);

	}
	
	public function wpldp_test_comments($data)
	{
		
		/*
		 * parameters :
		 * 
		 * 'rdfs:label' (slug)
		 * 'sioc:user' (author)
		 * 'dcterms:text' (content)
		 */

		// declarations
		$missingData = false;

		// sets headers
		wpldp_default_headers();
		header('Access-Control-Allow-Origin:*', true);
		
		// gets objects
		$body = json_decode($data->get_body());
		$context = $body->{'@context'};
		$graph = $body->{'@graph'};
		
		// converts data from @graph to Wordpress
		$comment = wpldp_map_comment($graph);
		
		// creates comment
		// TODO: validation des donnÃ©es etc.
		wp_insert_comment($comment);
	
		// ALTERNATE ENDING : print_r($data->get_body()); exit(0);
		return($data);

	}
	
}

new wpldp();

?>
