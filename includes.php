<?php
/*
 * Tests includes
 */
namespace wpldp;

class wpldp_includes

{

    public function __construct()
    {
		
		// test function for debugging purpose
		function wpldp_debug($text)
		{
			$command = 'echo `date` : \''. $text . '\'>> /tmp/wpldp_debug.log';
			exec($command);
		}
		
		// sets default headers
		function wpldp_default_headers()
		{
			header('access-control-allow-credentials', false);
			header('Content-Type:application/ld+json', true);
			header('Access-Control-Allow-Methods:POST,GET,OPTIONS', true);
			header('Access-Control-Allow-Origin:*', true);
		}
		
		// returns context to be set for posts/post_details/view_comments/post_comment etc.
		// see : http://json-ld.org/spec/latest/json-ld/#the-context
		function wpldp_get_context()
		{
			return array("dcterms" => "http://purl.org/dc/terms",
			"foaf" => "http://xmlns.com/foaf/0.1",
			"owl" => "http://www.w3.org/2002/07/owl#",
			"rdf" =>"http://www.w3.org/1999/02/22-rdf-syntax-ns#",
			"rdfs" => "http://www.w3.org/2000/01/rdf-schema#",
			"sioc" => "http://rdfs.org/sioc/ns#",
			"vs" => "http://www.w3.org/2003/06/sw-vocab-status/ns#",
			"wot" => "http://xmlns.com/wot/0.1",
			"xsd" => "http://www.w3.org/2001/XMLSchema#");
		}
		
		// returns an array containing @graph, parameter $content being the data
		function wpldp_get_container_graph($content)
		{
			$url = $_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];
			return array("@id" => $url,
			"@type" => "http://www.w3.org/ns/ldp#BasicContainer",
			"http://www.w3.org/ns/ldp#contains" => $content);
		}
		
		function wpldp_map_comment($graph)
		{
			
			// gets @graph number 0 entrie, stores in array
			$graph_0 = $graph[0];
			
			// gets post_id from slug
			$comment_post_id = wpldp_get_postid_by_slug($graph_0->{'http://www.w3.org/2000/01/rdf-schema#label'});
			// probleme : le JS traduit 'rdfs:label' par son URI
			// écrire une fonction qui récupère les bons URI ? ou inclure ces derniers dans la présente fonction ?
			wpldp_debug('id article : ' . $comment_post_id);

			// gets poster id
			// TODO : envisager une creation de user "à la volée" selon sioc:user ou compte invité
			// Toute une réflexion à faire sur la gestion des utilisateurs, pour les posts/comments "externes"
			$comment_user_id = 2;
			$tabUser = get_user_by('id', $comment_user_id);
					
			// gets user infos from id
			$comment_author = $tabUser->display_name;
			$comment_author_email = $tabUser->user_email;
			$comment_author_url = $tabUser->user_url;
			wpldp_debug('auteur : ' . $comment_author);
			
			// gets content of the comment
			// TODO : ATTENTION à la validation des données ici (balises!)
			$comment_content = $graph_0->{'dcterms:text'};		
			wpldp_debug('contenu : ' . $comment_content);
			
			// sets various properties
			// TODO : a définir
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
		
		}
		
		// returns post_id (or null if it doesn't exist) by slug
		function wpldp_get_postid_by_slug($slug)
		{
			
			$post = get_page_by_path($slug, OBJECT, 'post');
			
			if ($post)
			{
				return $post->ID;
			}
			
			else
			{
				return null;
			}
			
}
    }

}

?>
