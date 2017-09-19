<?php
/**
 * WP Zendesk API class, for interacting with the Zendesk API.
 */

/* If access directly, exit. */
if( !defined( 'ABSPATH'  ) ){ exit; }

/* Confirm that not being included elsewhere. */
if( ! class_exists( 'WpZendeskAPI' ) ){

	/**
	 * WP Zendesk API class.
	 *
	 * Extended off the WP API Libraries Base class.
	 * @link https://github.com/wp-api-libraries/wp-api-base
	 */
  class WpZendeskAPI extends WpLibrariesBase {

		/**
		 * The username through which to make all calls.
		 *
		 * @var string
		 */
    private $username;

		/**
		 * The alternate username (should not be accessed frequently). Used for calls
		 * where you want to act as a different user.
		 *
		 * @var string
		 */
		private $backup_username = '';
		private $fast_rest = true;
		private $no_auth = false;

		/**
		 * The API key used for authentication.
		 *
		 * @var string
		 */
    private $api_key;

		/**
		 * The extended URI to which requests are made.
		 *
		 * @var string
		 */
    protected $base_uri = '';

		/**
		 * Arguments to be built upon.
		 *
		 * Contains header and body information.
		 *
		 * @var string
		 */
    protected $args;

		protected $is_debug;

		/**
		 * Constructorinatorino 9000
		 *
		 * @param string $domain   The domain extension of zendesk (basically org name).
		 * @param string $username The username through which requests will be made
		 *                         under.
		 * @param string $api_key  The API key used for authentication.
		 */
    public function __construct( $domain, $username, $api_key, $debug = false ){
      $this->base_uri = "https://$domain.zendesk.com/api/v2/";
      $this->username = $username;
      $this->api_key = $api_key;
			$this->is_debug = $debug;
    }

		/**
		 * Get the current username.
		 *
		 * @return string The username.
		 */
    public function get_username(){
      return $this->username;
    }

		/**
		 * Get the current API key.
		 *
		 * @return string The API key.
		 */
    public function get_api_key(){
      return $this->api_key;
    }

		/**
		 * Set authentication.
		 *
		 * Used for changing the authentication methods.
		 * Note: the domain cannot be changed.
		 *
		 * @param string $username The new username.
		 * @param string $api_key  The new API key.
		 */
    public function set_auth( $username, $api_key ){
      $this->username = $username;
      $this->api_key = $api_key;
    }

		/**
		 * Set username for a single call.
		 *
		 * Useful for, as an example, fetching requests that an end user is authorized
		 * to view, by setting the username for the next call to be their email.
		 *
		 * After fetch() is run, the username is reset to the original (or most recently
		 * updated) username.
		 *
		 * @param string $username The temporary single call username.
		 */
		public function set_temporary_username( $username, $fast_reset = true ){
			$this->backup_username = $this->username;
			$this->username = $username;
			$this->fast_reset = $fast_reset;
		}

		public function set_temporary_noauth( $fast_reset = true ){
			$this->no_auth = true;
			$this->fast_reset = $fast_reset;
		}

		/**
		 * Resets the username to its original status.
		 *
		 * Designed to be used with set_temporary_username('<username>', false).
		 *
		 * @return WpZendeskAPI Self.
		 */
		public function reset_username(){
			if( $this->backup_username !== '' ){
				$this->username = $this->backup_username;
				$this->backup_username = '';
			}

			return $this;
		}

		/**
		 * Perform the request, normally after build_request.
		 *
		 * @return mixed The body of the call.
		 */
		protected function fetch(){
			$result = parent::fetch();

			if( $this->backup_username !== '' && $this->fast_reset ){
				$this->username = $this->backup_username;
				$this->backup_username = '';
			}

			return $result;
		}

		/**
		 * Abstract extended function that is used to set authorization before each
		 * call. $this->args['headers'] are wiped after every fetch call, hence this
		 * function is necessary.
		 *
		 * @return void
		 */
    protected function set_headers(){
      $this->args['headers'] = array(
        'Content-Type' => 'application/json',
      );

			if( !$this->no_auth ){
				$this->args['headers']['Authorization'] = 'Basic ' . base64_encode( $this->username . '/token:' . $this->api_key );
			}
    }

		/**
		 * Handle the build request and fetch methods, along with (optionally, but by
		 * default) adding the data type extension to the route.
		 *
		 * @param  string $route         The route to access.
		 * @param  array  $args          (Default: array()) Optional arguments. If the request
		 *                               method is 'GET', then the arguments are appended to
		 *                               the route as query args. Otherwise, they are stored
		 *                               in the body of the request.
		 * @param  string $method        (Default: 'GET') The type of request to make.
		 * @param  bool   $add_data_type (Default: true) Whether to add the data type
		 *                               extension to the route or not.
		 * @return [type]                [description]
		 */
    protected function run( $route, $args = array(), $method = 'GET', $add_data_type = true ){
			// Caching happens here. ONLY if the request is a get, serialize the route + args.
			if( 'GET' === $method && ! $this->is_debug ){
        // I was thinking about building the request first then serializing it, but
  			// that build should be identical for identical inputs. Therefore:
  			//
  			// Right here, serialize the route and args, make a hash of each. Store to a
  			// custom table, search for the hash, along with a timeout (say, 60 seconds?).

				$key = 'hostops_zendeskapi_' . $route . ($add_data_type?'.json':'') . serialize( $args );

				$result = get_transient( $key );

				if( false === $result ){
					$result = $this->build_request( $route . ($add_data_type?'.json':''), $args, $method )->fetch();

					$expiration = 60; // Seconds.

					// Possible TODO: set longer expiration, depending on the route.
					//
					// Ie: more expensive or frequent calls could be cached longer, such as
					// pinging the search API.
					//
					// OK lets think about this.
					//
					// Things that WON'T change very often unless modified (which again, not often).
					// 	 get_user
					//	 list_groups
					//	 memberships
					//
					// Other stuff I'm sure.
					//
					// But, eh. I guess 60 seconds is good enough for now.
					//
					// A big thing todo though, would be on certain functions clearing a transient.
					//
					// Heck I could get clever with this. Perhaps when updating a ticket delete
					// its transient, and this would allow me to have a longer expiration date.

					set_transient( $key, $result, $expiration );
				}

				return $result;
      }

      return $this->build_request( $route . ($add_data_type?'.json':''), $args, $method )->fetch();
    }

		/**
		 * Deletes all stored transients.
		 *
		 * More a helper function, should not be often routinely called.
		 *
		 * @return integer The number rows affected.
		 */
		public function clear_cache(){
			global $wpdb;

			$count = $wpdb->query( $wpdb->prepare(
				"DELETE FROM $wpdb->options
				WHERE `option_name` LIKE '%s'",
				'%hostops_zendeskapi_%'
			));

			// Divided by 2 because there's a row for both the value itself and its expiration.
			return $count / 2;
		}

		/**
		 * Clear arguments.
		 *
		 * Extended just in case you don't want to wipe everything.
		 *
		 * Recommended at least clearing body.
		 *
		 * @return void
		 */
    protected function clear(){
      $this->args = array();
    }

		/**
		 * Function for building zendesk pagination.
		 *
		 * @param  integer $per_page   [description]
		 * @param  integer $page       [description]
		 * @param  string  $sort_by    [description]
		 * @param  string  $sort_order [description]
		 * @return [type]              [description]
		 */
		public function build_zendesk_pagination( $per_page = 100, $page = 1, $sort_by = '', $sort_order = 'desc' ){
			$args = array(
				'per_page' => $per_page,
				'page' => $page,
			);

			if( $sort_by !== '' ){
				$args['sort_by'] = $sort_by;
				$args['sort_order'] = $sort_order;
			}

			return $args;
		}

		/**
		 * Query the Zendesk search route.
		 *
		 * @link https://developer.zendesk.com/rest_api/docs/core/search
		 *
		 * @param  string  $search_string The search query.
		 * @param  integer $per_page      (Default: 100) The number of results to return
		 *                                per page. Maxes out at 100.
		 * @param  integer $page          (Default: 1) The page off of results to start at.
		 * @return object                 A stdClass of the body from the response.
		 */
    public function search( $search_string, $per_page = 100, $page = 1, $sort_by = '', $sort_order = 'desc' ){
			$args = array(
				 'query'      => $search_string,
				 'per_page'   => $per_page,
				 'page'       => $page,
				 'sort_order' => $sort_order
			);

			if( $sort_by !== '' ){
				$args['sort_by'] = $sort_by;
			}
      return $this->run( 'search', $args );
    }

		/* Useful search functions */

    /**
     * Get tickets associated with an email.
     *
     * @param  string $email The email to look for.
     * @return object        The results of the search (Zendesk search results).
     */
		public function get_tickets_by_email( $email ){
			return $this->run( 'search', array( 'query' => urlencode( 'type:ticket requester:'. $email ) ) );
		}

		public function get_user_by_email( $email ){ // or is it get user?
			return $this->run( 'users/search', array( 'query' => $email ) );
		}

		public function get_requests_by_email( $email ) {
			return $this->run( 'search', array( 'query' => urlencode( 'type:request requester:' . $email . ' status:all' ) ) );
		}

		public function get_organizations_by_name( $organization_name ){
			return $this->run( 'search', array( 'query' => urlencode( 'type:organization ' . $organization_name ) ) );
		}

    /* Tickets */

    public function list_tickets( $per_page = 100, $page = 1, $sort_by = '', $sort_order = 'desc' ){
			$args = array(
				'per_page' => $per_page,
				'page' => $page,
			);

			if( $sort_by !== '' ){
				$args['sort_by'] = $sort_by;
				$args['sort_order'] = $sort_order;
			}

      return $this->run( 'tickets', $args );
    }

    public function list_tickets_by_user_id_requested( $user_id ){
      return $this->run( "users/$user_id/tickets/requested" );
    }

    public function show_ticket( $ticket_id ){
			return $this->run( "tickets/$ticket_id" );
    }

		// Ids -> Comma separated list or array of ticket IDs to return
    public function show_tickets( $ids ){
			if( is_array( $ids ) ){
				$ids = implode( $ids, ',' );
			}
			return $this->run( 'tickets/show_many', array( 'ids' => $ids ) );
    }

		public function build_zendesk_ticket( $subject = '', $description = '', $comment = '', $requester_id = '', $tags = '', $other = array(), $raw = false ){
			$ticket = array();

			if( $subject !== '' ){
				$ticket['subject'] = $subject;
			}

			if( $description !== '' ){
				$ticket['description'] = $description;
			}

			if( $comment !== '' ){
				$ticket['comment'] = $comment;
			}

			if( $requester_id != '' ){
				$ticket['requester_id'] = $requester_id;
			}

			if( $tags != '' ){
				if( gettype( $tags ) == 'array' ){
					$ticket['tags'] = implode(',', $tags);
				}else{
					$tickets['tags'] = $tags;
				}
			}

			if( !empty( $other ) ){
				foreach( $other as $key => $val ){
					$ticket[$key] = $val;
				}
			}

			if( $raw ){
				return $ticket;
			}

			return array('ticket' => $ticket);
		}

		// Ticket could be a ticket, or it could be the subject. If it's the subject, a ticket will be built off of it.
    public function create_ticket( $ticket, $description = '', $requester_id = '', $tags = '', $other = array() ){

			if( gettype( $ticket ) !== 'object' && gettype( $ticket ) !== 'array' ){
				$ticket = $this->build_zendesk_ticket( $ticket, $description, '', $requester_id, $tags, $other );
			}

			return $this->run( 'tickets', $ticket, 'POST' );
    }

		// Array of ticket objects.
    public function create_many_tickets( $ticket_objs ){
			return $this->run( 'tickets/create_many', array( 'tickets' => $ticket_objs ), 'POST' );
    }

		// All properties are optional
    public function update_ticket( $ticket_id, $ticket_obj ){
			return $this->run( 'tickets/' . $ticket_id, $ticket_obj, 'PUT' );
    }

		public function get_requests_by_user( $user_id ){
			return $this->run( "users/$user_id/tickets/requested" );
		}

		public function get_ccd_by_user( $user_id ){
			return $this->run( "users/$user_id/tickets/ccd" );
		}

		public function get_assigned_by_user( $user_id, $per_page = 100, $page = 1 ){
			$args = array(
				'per_page' => $per_page,
				'page' => $page,
			);
			return $this->run( "users/$user_id/tickets/assigned", $args );
		}

		/**
		 * @link https://developer.zendesk.com/rest_api/docs/core/tickets#update-many-tickets
		 *
		 * @param  array  $ticket_objs Accepts an array of up to 100 ticket objects.
		 *                             If ticket is set, then will require ids to be set.
		 *                             Otherwise, tickets should be set, and ids is not necessary
		 *                             to be set.
		 * @param  array  $ids         A comma-separated list of up to 100 ticket ids.
		 *                             Use this for modifying many tickets with the same
		 *                             change.
		 * @return [type]              [description]
		 */
    public function update_many_tickets( $ticket_obj, $ids = array() ){
      if( empty( $ids ) ){
        return $this->run( 'tickets/update_many', $ticket_obj, 'PUT' );
      }else{
        return $this->run( 'tickets/update_many.json?ids=' . implode( ',', $ids ), $ticket_obj, 'PUT', false );
      }
    }

    public function protect_ticket_update_collisions(){

    }

    public function mark_ticket_spam_and_block_requester( $ticket_id ){
      return $this->run( "tickets/$ticket_id/mark_as_spam", array(), 'PUT' );
    }

    public function mark_many_tickets_as_spam( $ids ){
      return $this->run( 'tickets/mark_many_as_spam.json?ids=' . implode( ',', $ids ), array(), 'PUT', false );
    }

    public function merge_tickets_into_target(){

    }

    public function get_ticket_related_info( $ticket_id ){
			return $this->run( "tickets/$ticket_id/related" );
    }


    public function create_ticket_new_requester(){

    }

    public function set_ticket_fields(){

    }

    public function delete_ticket( $ticket_id ){
			return $this->run( "tickets/$ticket_id", array(), 'DELETE' );
    }

    public function bulk_delete_tickets( $ticket_ids = array() ){
			return $this->run( 'tickets/destroy_many.json?ids=' . implode( ',', $ticket_ids ), array(), 'DELETE', false );
    }

    public function show_delete_tickets(){
			return $this->run( 'deleted_tickets' );
    }

    public function restore_deleted_ticket( $ticket_id ){
			return $this->run( "deleted_tickets/$ticket_id/restore", array(), 'PUT' );
    }

    public function restore_bulk_deleted_tickets( $ticket_ids = array() ){
			return $this->run( 'deleted_tickets/restore_many?ids=' . implode( ',', $ticket_ids ), array(), 'PUT', false );
    }

    public function delete_tickets_permanently(){

    }

		/**
		 * List collaborators for a ticket.
		 *
		 * @param  string $ticket_id The ID of the ticket (can also be numeric).
		 * @return array             A list of collaborators on a ticket.
		 */
    public function list_collaborators_ticket( $ticket_id ){
			return $this->run( "tickets/$ticket_id/collaborators" );
    }

		/**
		 * List incidents for a ticket.
		 *
		 * @param  [type] $ticket_id [description]
		 * @return array             A list of incidents from a ticket.
		 */
    public function list_ticket_incidents( $ticket_id ){
			return $this->run( "tickets/$ticket_id/incidents" );
    }

		/**
		 * List ticket problems.
		 *
		 * The response is always ordered by updated_at, in desc order.
		 *
		 * @return [type] [description]
		 */
    public function list_ticket_problems(){
			return $this->run( 'problems' );
    }

		/**
		 * Returns tickets whose type is "Problem" and whose subject contains the string
		 * specified in the <code>text</code> parameter.
		 *
		 * @return array A list of tickets that have been autocompleted.
		 */
    public function autocomplete_problems( $text ){
			return $this->run( 'autocomplete', array( 'text' => $text ), 'POST' );
    }

    /* Ticket import */

		/**
		 * The endpoint takes a ticket object describing the ticket.
		 *
		 * @link https://developer.zendesk.com/rest_api/docs/core/ticket_import#ticket-import
		 * @param  array $ticket A ZendeskAPI ticket object (see $this->build_zendesk_ticket()).
		 * @return object        The successfully created ticket (hopefully).
		 */
    public function ticket_import( $ticket ){
			return $this->run( 'imports/tickets', $ticket, 'POST' );
    }

		/**
		 * The endpoint takes a tickets array of up to 100 ticket objects.
		 *
		 * Similar to single tickets, except they're single tickets.
		 *
		 * @link https://developer.zendesk.com/rest_api/docs/core/ticket_import#ticket-bulk-import
		 * @param  [type] $tickets [description]
		 * @return [type]          [description]
		 */
    public function bulk_ticket_import( $tickets ){
			return $this->run( 'imports/tickets/create_many', $tickets, 'POST' );
    }

    /* Requests */

		/**
		 * List general requests.
		 *
		 * @param  integer $per_page   [description]
		 * @param  integer $page       [description]
		 * @param  string  $sort_by    [description]
		 * @param  string  $sort_order [description]
		 * @return [type]              [description]
		 */
    public function list_requests($per_page = 100, $page = 1, $sort_by = '', $sort_order = 'desc' ){
			$args = array(
				'per_page' => $per_page,
				'page' => $page,
			);

			if( $sort_by !== '' ){
				$args['sort_by'] = $sort_by;
				$args['sort_order'] = $sort_order;
			}
			return $this->run( 'requests', $args );
    }

		public function list_open_requests( $per_page = 100, $page = 1, $sort_by = '', $sort_order = 'desc' ){
			$args = array(
				'per_page' => $per_page,
				'page' => $page,
			);

			if( $sort_by !== '' ){
				$args['sort_by'] = $sort_by;
				$args['sort_order'] = $sort_order;
			}
			return $this->run( 'requests/open', $args );
		}

		public function list_hold_requests( $per_page = 100, $page = 1, $sort_by = '', $sort_order = 'desc' ){
			$args = array(
				'per_page' => $per_page,
				'page' => $page,
			);

			if( $sort_by !== '' ){
				$args['sort_by'] = $sort_by;
				$args['sort_order'] = $sort_order;
			}

			return $this->run( 'requests/hold', $args );
		}

		/**
		 * Format for statuses: comma separated list (string) of statuses to browse through.
		 *
		 * @param  [type] $statuses
		 * @param  array  $zendesk_pagination Zendesk pagination tool.
		 * @return [type]              [description]
		 */
		public function list_requests_by_status( $statuses, $zendesk_pagination = null ){
			if( null === $zendesk_pagination ){
				$zendesk_pagination = $this->build_zendesk_pagination();
			}

			$args = array_merge( $zendesk_pagination, array(
				'status' => $statuses,
			));

			return $this->run( 'requests', $args );
		}

		public function list_requests_by_user( $user_id, $zendesk_pagination = null ){
			if( null === $zendesk_pagination ){
				$zendesk_pagination = $this->build_zendesk_pagination();
			}

			return $this->run( "users/$user_id/requests", $zendesk_pagination );
		}

		public function list_requests_by_organization( $zendesk_pagination = null ){
			if( null === $zendesk_pagination ){
				$zendesk_pagination = $this->build_zendesk_pagination();
			}

			return $this->run( "organizations/$org_id/requests", $zendesk_pagination );
		}

		/**
		 * Search requests.
		 *
		 * GET /api/v2/requests/search.json?query=camera
		 * GET /api/v2/requests/search.json?query=camera&organization_id=1
		 * GET /api/v2/requests/search.json?query=camera&cc_id=true
		 * GET /api/v2/requests/search.json?query=camera&status=hold,open
		 *
		 * @param  [type] $query              [description]
		 * @param  [type] $zendesk_pagination
		 * @return [type]                     [description]
		 */
    public function search_requests( $query, $zendesk_pagination = null ){
			if( null === $zendesk_pagination ){
				$zendesk_pagination = $this->build_zendesk_pagination();
			}

			$args = array_merge( $zendesk_pagination, array(
				'query' => $query
			));

			return $this->run( 'requests/search', $args );
    }

		/**
		 * Show a single request.
		 *
		 * @link https://developer.zendesk.com/rest_api/docs/core/requests#show-request
		 * @param  [type] $request_id [description]
		 * @return [type]             [description]
		 */
    public function show_request( $request_id ){
			return $this->run( 'requests/' . $request_id );
    }

		/**
		 * Build a request (following the zendesk API structure).
		 *
		 * @param  string $subject     [description]
		 * @param  string $description [description]
		 * @param  string $comment     [description]
		 * @param  array  $other       [description]
		 * @param  bool   $raw
		 * @return [type]              [description]
		 */
		public function build_zendesk_request( $subject = '', $description = '', $comment = '', $other = array(), $raw = false ){
			$request = array();

			if( $subject != '' ){
				$request['subject'] = $subject;
			}
			if( $description != '' ){
				$request['description'] = $description;
			}
			if( $comment != '' ){
				$request['comment']['body'] = $comment;
			}

			if( !empty( $other ) ){
				foreach( $other as $key => $val ){
					$request[$key] = $val;
				}
			}

			if( $raw ){
				return $request;
			}

			return array( 'request' => $request );
		}

		/**
		 * Call build request, must fill out subject and description, should fill out requester.
		 *
		 * If not defined and not admin, then will be set to whoever is authenticated.
		 *
		 * @link https://developer.zendesk.com/rest_api/docs/core/requests#create-request
		 * @param  [type] $request [description]
		 * @return [type]          [description]
		 */
    public function create_request( $request ){
			return $this->run( 'requests', $request, 'POST' );
    }

		/**
		 * Call build_request, recommended fill out comment, can fill out status
		 * This function is mostly used for adding a comment.
		 *
		 * @link https://developer.zendesk.com/rest_api/docs/core/requests#update-request
		 * @param  [type] $request_id [description]
		 * @param  [type] $request    [description]
		 * @return [type]             [description]
		 */
    public function update_request( $request_id, $request ){
			return $this->run( 'requests/' . $request_id, $request, 'PUT' );
    }

		/**
		 * Lists comments from a request.
		 *
		 * I BELIEVE it will not list private comments.
		 *
		 * Not totally sure.
		 *
		 * Please test.
		 *
		 * @link https://developer.zendesk.com/rest_api/docs/core/requests#listing-comments
		 * @param  [type] $request_id [description]
		 * @return [type]             [description]
		 */
    public function list_comments_request( $request_id, $zendesk_pagination = null ){
			if( null === $zendesk_pagination ){
				$zendesk_pagination = $this->build_zendesk_pagination();
			}

			return $this->run( "requests/$request_id/comments", $zendesk_pagination );
    }

		/**
		 * Get a specific comment.
		 *
		 * @link https://developer.zendesk.com/rest_api/docs/core/requests#getting-comments
		 * @param  [type] $request_id [description]
		 * @param  [type] $comment_id [description]
		 * @return [type]             [description]
		 */
    public function get_comment_request( $request_id, $comment_id ){
			return $this->run( "requests/$request_id/comments/$comment_id" );
    }

    /* Attachments */

    public function show_attachment( $attachment_id ){
      return $this->run( "api/v2/attachments/$attachment_id" );
    }

    public function delete_attachment(){
      return $this->run( "api/v2/attachments/$attachment_id", array(), 'DELETE' );
    }

    public function upload_files( $filename, $file, $token = null ){
      $route = "api/v2/uploads.json?filename=$filename";
      if( $token !== null ){
        $route .= "&token=$token";
      }

      // Okaaaaaaay... how the heck do I handle uploads?

      return $this->run( $route, $body, 'POST', false );
    }

		/**
		 * Delete an upload.
		 *
		 * @link https://developer.zendesk.com/rest_api/docs/core/attachments#delete-upload
		 * @param  [type] $token [description]
		 * @return [type]        [description]
		 */
    public function delete_upload( $token ){
			return $this->run( "uploads/$token", array(), 'DELETE' );
    }

		/**
		 * Redaction allows you to permanently remove attachments from an existing
		 * comment on a ticket. Once removed from a comment, the attachment is replaced
		 * with a placeholder "redacted.txt" file.
		 *
		 * Note that redaction is permanent. It is not possible to undo redaction or
		 * see what was removed. Once a ticket is closed, redacting its attachments
		 * is no longer possible.
		 *
		 * @link https://developer.zendesk.com/rest_api/docs/core/attachments#redact-comment-attachment
		 * @return [type] [description]
		 */
    public function redact_comment_attachment( $ticket_id, $comment_id, $attachment_id ){
			return $this->run( "tickets/$ticket_id/comments/$comment_id/attachments/$attachment_id/redact", array(), 'PUT' );
    }

    /* Satisfaction Ratings */

		/**
		 * List satisfcation ratings
		 *
		 * @link https://developer.zendesk.com/rest_api/docs/core/satisfaction_ratings#list-satisfaction-ratings
		 * @param  string $score              received, received_with_comment, received_without_comment,
		 *                                    good, good_with_comment, good_without_comment,
		 *                                    bad, bad_with_comment, bad_without_comment
		 * @param  string $start_time         Time of the oldest satisfaction rating, as
		 *                                    a Unix epoch time
		 * @param  string $end_time           Time of the most recent satisfaction rating,
		 *                                    as a Unix epoch time
		 * @param  [type] $zendesk_pagination [description]
		 * @return [type]                     [description]
		 */
    public function list_satisfaction_ratings( $score = '', $start_time = '', $end_time = '', $zendesk_pagination = null ){
			if( null === $zendesk_pagination ){
				$zendesk_pagination = $this->build_zendesk_pagination();
			}

			$args = $zendesk_pagination;

			if( '' !== $score ){
				$args['score'] = $score;
			}

			if( '' !== $start_time ){
				$args['start_time'] = $start_time;
			}

			if( '' !== $end_time ){
				$args['end_time'] = $end_time;
			}

			return $this->run( 'satisfaction_ratings', $args );
    }

		/**
		 * Show satisfaction rating.
		 *
		 * Returns a specific satisfaction rating. You can get the id from the List
		 * Satisfaction Ratings endpoint.
		 *
		 * @link https://developer.zendesk.com/rest_api/docs/core/satisfaction_ratings#show-satisfaction-rating
		 * @param  string $rating_id [description]
		 * @return [type] [description]
		 */
    public function show_satisfaction_rating( $rating_id ){
			return $this->run( "satisfaction_ratings/$rating_id" );
    }

		/**
		 * Create a satisfaction rating.
		 *
		 * Creates a CSAT rating for solved tickets, or for tickets that were
		 * previously solved and then reopened.
		 *
		 * The end user must be a verified user, and the person who requested the ticket.
		 *
		 * @link https://developer.zendesk.com/rest_api/docs/core/satisfaction_ratings#create-a-satisfaction-rating
		 * @param  [type] $ticket_id  [description]
		 * @param  [type] $score      [description]
		 * @param  string $comment    [description]
		 * @param  string $sort_order [description]
		 * @return [type]             [description]
		 */
    public function create_satisfaction_rating( $ticket_id, $score, $comment = '', $sort_order = 'asc' ){
			$args = array(
				'score' => $score,
				'sort_order' => $sort_order,
			);

			if( '' !== $comment ){
				$args['comment'] = $comment;
			}

			return $this->run( "tickets/$ticket_id/satisfaction_rating", $args, 'POST' );
    }

    /* Satisfaction Reasons */

    public function list_reasons_for_satisfaction_rating(){

    }

    public function show_reasons_for_satisfaction_rating(){

    }

    /* Suspended Tickets */

    public function list_suspended_tickets(){

    }

    public function show_suspended_tickets(){

    }

    public function recover_suspended_ticket(){

    }

    public function recover_suspended_tickets(){

    }

    public function delete_suspended_ticket(){

    }

    public function delete_suspended_tickets(){

    }

    /* Ticket Audits */

    public function list_audits_for_ticket(){

    }

    public function show_audit(){

    }

    public function change_comment_to_private(){

    }

    public function get_audit_events(){

    }

    public function the_via_object(){

    }

    /* Ticket Comments */

    public function create_ticket_comment( $ticket_id, $text, $public = true ){
			$ticket = $this->build_zendesk_ticket();

			$ticket['comment'] = array(
				'public' => $public,
				'body' => $text,
			);

			return $this->run( 'tickets/' . $ticket_id, $ticket, 'PUT' );
    }

		public function create_request_comment( $request_id, $text ){
			$request = $this->build_zendesk_request( '', '', $text );

			return $this->run( 'requests/' . $request_id, $request, 'PUT' );
		}

    public function list_comments( $ticket_id, $sort_order = 'asc' ){
			return $this->run( "tickets/$ticket_id/comments", array( 'sort_order' => $sort_order ) ); // might need to do a json_decode? TODO: look into
    }

    public function redact_string_in_comment(){

    }

    public function make_comment_private(){

    }

    /* Ticket skips */

    public function record_skip_for_user(){

    }

    public function list_skips_for_account(){

    }

    /* Ticket metrics */

    /* Ticket metric events */

    /* Users */

		public function list_users( $id = '', $is_group = true, $page = '' ){
			$options = array();

			if ( $page != '' ) {
				$options = array( 'page' => $page );
			}

			if( $id != '' ){
				if( $is_group ){
					return $this->run( "groups/$id/users", $options );
				}else{
					return $this->run( "organizations/$id/users", $options );
				}
			}

			return $this->run( "users", $options );
		}

		public function show_user( $user_id ){
			return $this->run( "users/$user_id" );
		}

		// Either a comma separated list, or an array of IDs.
		public function show_users( $user_ids ){
			if( is_array( $user_ids ) ){
				$user_ids = implode( $user_ids, ',' );
			}

			return $this->run( "users/show_many", array( 'ids' => $user_ids ) );
		}

		public function get_user_info( $user_id ){
			return $this->run( "users/$user_id/related" );
		}

		/**
		 * Build zendesk user function. Used for creating a zendesk user.
		 * Ie, creating a user could be done by:
		 * <code>return $zenapi->create_user( $zenapi->build_zendesk_user( $name, $email,
		 * $role, array( 'active' => true ) ) );</code>
		 * All parameters are optional, an empty user object will be returned if they
		 * are all empty.
		 *
		 * @param  string $name  (Default: '') Name of the user.
		 * @param  string $email (Default: '') Email of the user.
		 * @param  string $role  (Default: '') Role of the user. Must be either 'end-user',
		 *                       'agent', or 'admin'
		 * @param  array  $other (Default: array()) An associative array of whatever
		 *                       else you want to put in. Each key will have its value
		 *                       placed in under the key.
		 * @return array         User object (really an array) up to specs with the Zendesk
		 *                       API style.
		 */
		public function build_zendesk_user( $name = '', $email = '', $role = '', $other = array() ){
			$user = array( 'user' => array() );

			if( $name != '' ){
				$user['user']['name'] = $name;
			}
			if( $email != '' ){
				$user['user']['email'] = $email;
			}
			if( $role != '' ){
				$user['user']['role'] = $role;
			}

			if( !empty( $other ) ){
				foreach( $other as $key => $val ){
					$user['user'][$key] = $val;
				}
			}

			return $user;
		}

		// Use the build_zendesk_user function
		public function create_user( $user ){
			return $this->run( 'users', $user, 'POST' );
		}

    public function search_users( $query, $external_id = false ){
      if( $external_id !== false ){
        return $this->run( 'users/search', array( 'external_id' => $external_id ) );
      }
      return $this->run( 'users/search', array('query' => $query ) );
    }

		public function delete_user( $user_id ){
			return $this->run( "users/$user_id", array(), 'DELETE' );
		}

		public function bulk_delete_users( $user_ids ){
			if( gettype( $user_ids ) === 'string' ){
				return $this->run( "users/destroy_many.json?ids=$user_ids", array(), 'DELETE', false );
			}else if( gettype( $user_ids ) === 'array' ){
				return $this->run( "users/destroy_many.json?ids=" . implode(',', $user_ids ), array(), 'DELETE', false );
			}else{
				return "Error: invalid data type.";
			}
		}

		public function set_user_password( $user_id, $pass ){
			return $this->run( "users/$user_id/password", array( 'password' => $pass ), 'POST' );
		}

		public function get_user_groups( $user_id ){
			return $this->run( "users/$user_id/groups" );
		}

    /* User identities */

		public function list_identities( $user_id ){
			return $this->run( "users/$user_id/identities" );
		}

    /* Custom agent roles */

    /* End users */

    /* Groups */
		public function list_groups(){
			return $this->run( 'groups' );
		}

		public function show_group( $group_id ){
			return $this->run( "groups/$group_id" );
		}

    /* Group memberships */

    /* Sessions */

    /* Organizations */

		public function list_organizations( $user_id = '', $page = 1 ){
			if( $user_id !== '' ){
				return $this->run( "users/$user_id/organizations" );
			}

			return $this->run( "organizations", array( 'page' => $page ) );
		}

		public function build_zendesk_organization( $name = '', $other = array() ){
			$org = array( 'organization' => array() );

			if( $name !== '' ){
				$org['organization']['name'] = $name;
			}

			if( !empty( $other ) ){
				foreach( $other as $key => $val ){
					$user['organization'][$key] = $val;
				}
			}

			return $org;
		}

		/**
		 * Create an organization.
		 *
		 * @param  mixed  $organization If a string, an organization will be created
		 *                              with the name equal to that string. Otherwise,
		 *                              send in an object created using the build_zendesk_organization
		 *                              method.
		 * @return [type]               [description]
		 */
		public function create_organization( $organization ){
			if( gettype( $organization ) == 'string' ){
				$organization = $this->build_zendesk_organization( $organization );
			}

			return $this->run( 'organizations', $organization, 'POST' );
		}

		public function delete_organization( $organization_id ){
			return $this->run( "organizations/$organization_id", array(), 'DELETE' );
		}

		public function delete_many_organizations( $org_ids ){
			if( gettype( $org_ids ) === 'string' ){
				return $this->run( "organizations/destroy_many.json?ids=" . $org_ids, array(), 'DELETE', false );
			}else if( gettype( $org_ids ) === 'array' ){
				return $this->run( "organizations/destroy_many.json?ids=" . implode( ',', $org_ids ), array(), 'DELETE', false );
			}else{
				return "Error: invalid data type.";
			}
		}

    /* Organization Subscriptions */

    /* Organization Memberships */

		public function list_organization_memberships( $organization_id = '', $user_id = '', $page = 1 ){
			if( $organization_id === '' && $user_id === '' ){
				return $this->run( 'organization_memberships', array( 'page' => $page ) );
			}else if( $organization_id === '' ){
				return $this->run( "users/$user_id/organization_memberships", array( 'page' => $page ) );
			}else{
				return $this->run( "organizations/$organization_id/organization_memberships", array( 'page' => $page ) );
			}
		}

		// Maybe make this name shorter?
		public function build_zendesk_organization_membership( $user_id, $org_id ){
			return array( 'organization_memberships' => array( 'user_id' => $user_id, 'organization_id' => $org_id ) ); // example
		}

		public function create_many_memberships( $memberships ){
			return $this->run( "organization_memberships/create_many", $memberships, "POST" );
		}

    /* Automations */

    /* Macros */

    /* SLA Policies */

    /* Targets */

    /* Triggers */

    /* Views */

    /* Account Settings */

    /* Audit Logs */

    /* Brands */

    /* Dynamic content */

    /* Locales */

    /* Organization Fields */

    /* Schedules */

    /* Sharing agreements */

    /* Support addresses */

    /* Ticket forms */

    /* Ticket fields */

    /* User fields */

    /* Apps */

    /* App installation locations */

    /* App locations */

    /* OAuth clients */

    /* OAuth tokens */

    /* Authorized global clients */

    /* Activity stream */

    /* Bookmarks */

    /* Push notification devices */

    /* Resource collections */

    /* Tags */

    /* Channel framework */

    /* Twitter channel */


  }
}
