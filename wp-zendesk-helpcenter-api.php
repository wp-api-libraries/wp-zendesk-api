<?php
/**
 * WP ZenDesk HelpCenter API (https://developer.zendesk.com/rest_api/docs/help_center/introduction)
 *
 * @package WP-ZD-HelpCenter-API
 */

/* Exit if accessed directly */
if ( ! defined( 'ABSPATH' ) ) { exit; }


if ( ! class_exists( 'ZendeskHelpCenterAPI' ) ) {

	/**
	 * Seny API Class.
	 */
	class ZendeskHelpCenterAPI {

		/**
		 * Install Name.
		 *
		 * @var string
		 */
		static private $install_name;

		/**
		 * API Key.
		 *
		 * @var string
		 */
		static private $api_key;

		/**
		 * URL to the API.
		 *
		 * @var string
		 */
		private $base_uri = 'https://' . static::$install_name . '.zendesk.com';


		/**
		 * __construct function.
		 *
		 * @access public
		 * @param mixed $install_name Install Name.
		 * @param mixed $api_key API Key.
		 * @return void
		 */
		public function __construct( $install_name, $api_key ) {

			static::$install_name = $install_name;
			static::$api_key = $api_key;

		}

		/**
		 * Fetch the request from the API.
		 *
		 * @access private
		 * @param mixed $request Request URL.
		 * @return $body Body.
		 */
		private function fetch( $request ) {

			$response = wp_remote_get( $request );
			$code = wp_remote_retrieve_response_code( $response );

			if ( 200 !== $code ) {
				return new WP_Error( 'response-error', sprintf( __( 'Server response code: %d', 'text-domain' ), $code ) );
			}

			$body = wp_remote_retrieve_body( $response );

			return json_decode( $body );
		}

		/* CATEGORIES. */

		/**
		 * list_categories function.
		 *
		 * @access public
		 * @param string $locale (default: '')
		 * @param string $sort_by (default: '')
		 * @param string $sort_order (default: '')
		 * @return void
		 */
		public function list_categories( $locale = '', $sort_by = '', $sort_order = '' ) {

			$request = $this->base_uri . '/api/v2/help_center/' . $locale . '/categories.json';

			return $this->fetch( $request );

		}

		public function get_category( $category_id ) {

		}

		public function add_category( $category, $locale = 'en-us' ) {

		}

		public function update_category( $category_id, $locale = 'en-us' ) {

		}

		public function update_category_locale( $category_id ) {

		}

		public function delete_category( $category_id ) {

		}

		/* SECTIONS. */

		public function list_sections( $section_id, $locale = 'en-us' ) {

		}

		public function get_section( $section_id, $locale = 'en-us' ) {

		}

		public function create_section( $section_id, $locale = 'en-us' ) {

		}

		public function update_section( $section_id, $locale = 'en-us' ) {

		}

		public function delete_section( $section_id ) {

		}

		/* ARTICLES. */

		public function get_articles( $locale = 'en-us' ) {

		}

		public function get_category_articles( $category_id, $locale = 'en-us', $sort_by = '', $sort_order = '', $label_names = '' ) {

		}

		public function get_section_articles( $section_id, $locale = 'en-us', $sort_by = '', $sort_order = '', $label_names = '' ) {

		}

		public function get_user_articles( $user_id, $sort_by = '', $sort_order = '', $label_names = '' ) {

		}

		public function get_incremental_articles( $start_time, $sort_by = '', $sort_order = '', $label_names = '' ) {

		}

		public function add_article( $article_id, $locale = 'en-us' ) {

		}

		public function update_article( $article_id, $locale = 'en-us' ) {

		}

		public function archive_article( $article_id ) {

		}

		public function update_article_source_locale( $article_id ) {

		}

		public function associate_attachments_to_article( $article_id ) {

		}

		/* ARTICLE COMMENTS. */

		public function get_comments( $article_id, $locale = 'en-us' ) {

		}

		public function show_comment( $comment_id, $article_id, $locale = 'en-us' ) {

		}

		public function add_comment( $article_id ) {

		}

		public function update_comment( $comment_id, $article_id ) {

		}

		public function delete_comment( $comment_id, $article_id ) {

		}


		/* ARTICLE LABELS. */

		public function get_article_labels( $locale = 'en-us' ) {

		}

		public function get_label_details( $label_id ) {

		}

		public function list_article_labels( $label_id ) {

		}

		public function create_label( $label_id ) {

		}

		public function delete_label( $label_id ) {

		}

		public function get_articles_by_label( $label_names ) {

			// GET /api/v2/help_center/articles.json?label_names=photos,camera

		}

		/* ARTICLE ATTACHMENTS. */

		/* TRANSLATIONS. */

		/* SEARCH. */

		public function search_articles( $search_string, $created_before = '', $created_after = '', $created_at = '', $updated_before = '', $updated_after = '', $updated_at = '', $label_names = '', $category = '', $section = '' ) {

		}

		/* TOPICS. */

		public function get_topics() {

		}

		public function show_topics( $topic_id ) {

		}

		public function add_topic() {

		}

		public function update_topic( $topic_id ) {

		}

		public function delete_topic( $topic_id ) {

		}

		/* POSTS. */

		public function get_posts( $filter_by = '', $sort_by = '', $include = '' ) {

		}

		public function get_topic_posts( $topic_id, $filter_by = '', $sort_by = '', $include = '' ) {

		}

		public function get_topic_posts( $user_id, $filter_by = '', $sort_by = '', $include = '' ) {

		}

		public function get_post( $post_id ) {

		}

		public function add_post() {

		}

		public function update_post( $post_id ) {

		}

		public function delete_post( $post_id ) {

		}

		/* POST COMMENTS. */

		public function get_post_comments( $post_id, $include = '' ) {

		}

		public function get_user_comments( $user_id, $include = '' ) {

		}

		public function get_post_comment( $post_id, $comment_id, $include = '' ) {

		}

		public function add_comment( $post_id ) {

		}

		public function update_comment( $post_id, $comment_id ) {

		}

		public function delete_comment( $post_id, $comment_id ) {

		}

		/* SUBSCRIPTIONS. */

		/* VOTES. */

		/* ACCESS POLICIES. */

	}
}
