<?php

/**
 * WP_Model
 *
 * A simple class for creating active
 * record, eloquent-esque models of WordPress Posts.
 *
 * @author     AnthonyBudd <anthonybudd94@gmail.com>
 */
Abstract Class AIOGDPRSlimModel{

	public $ID;
	public $_post;
	public $data        = array();
	public $attributes  = array();
	public $default 	= array();
	public $virtual 	= array();


	/**
	 * Create a new instace with data
	 *
	 * @param array $insert
	 * @return void
	 */
	public function __construct(Array $insert = array()){
		if(!empty($this->default)){
			foreach($this->default as $attribute => $value){
				$this->data[$attribute] = $value;
			}
		}

		foreach($insert as $attribute => $value){
			if(in_array($attribute, $this->attributes)){
				$this->set($attribute, $value);
			}
		}
	
		$this->set('title',   isset($insert['title'])?   $insert['title'] :   '');	
		$this->set('content', isset($insert['content'])? $insert['content'] : '');
		$this->boot();
	}

	/**
	 * Initalize the model, load in any addional data
	 *
	 * @return void
	 */
	protected function boot(){
		$this->triggerEvent('booting');

		if(isset($this->ID)){
			$this->_post = get_post($this->ID);
			$this->set('title',   $this->_post->post_title);
			$this->set('content', $this->_post->post_content);

			foreach($this->attributes as $attribute){
				$meta = $this->getMeta($attribute);
				if(empty($meta) && isset($this->default[$attribute])){
					$this->set($attribute, $this->default[$attribute]);
				}else{
					$this->set($attribute, $meta);
				}
			}
		}

		$this->triggerEvent('booted');
	}

	/**
	 * Register the post type using the propery $postType as the post type
	 *
	 * @param  array  $args  see: register_post_type()
	 * @return void
	 */
	public static function register($args = array()){
		$postType = Self::getPostType();

		register_post_type($postType, $args);
	}

	/**
	 * Create a new model with data, save and return the model
	 *
	 * @param array $insert
	 */
	public static function insert(Array $insert = array()){
		return Self::newInstance($insert)->save();
	}


	// -----------------------------------------------------
	// EVENTS
	// -----------------------------------------------------
	/**
	 * Fire event if the event method exists
	 *
	 * @param  string $event event name
	 * @return bool
	 */
	protected function triggerEvent($event){
		if(method_exists($this, $event)){
			$this->$event($this);
			return TRUE;
		}

		return FALSE;
	}


	// -----------------------------------------------------
	// UTILITY METHODS
	// -----------------------------------------------------
	/**
	 * Create a new model without calling the constructor.
	 *
	 * @return object
	 */
	protected static function newWithoutConstructor(){
		$class = get_called_class();
		$reflection = new ReflectionClass($class);
		return $reflection->newInstanceWithoutConstructor();
	}

	public function isArrayOfModels(Array $array){
		if(!is_array($array)){
			return FALSE;
		}

		$types = array_unique(array_map('gettype', $array));
		return (count($types) === 1 && $types[0] === "object" && $array[0] instanceof WP_Model);
	}

	public static function extract($array, $column){
		$return = array();

		if(is_array($array)){
			foreach($array as $value){
				if(is_object($value)){
					$return[] = @$value->$column;
				}elseif(is_array($value)){
					$return[] = @$value[$column];
				}
			}
		}

		return $return;
	}

 	private function getAttributes(){
 		return array_merge($this->attributes, array('title', 'content', 'the_content'));
 	}

	/**
	 * Returns the post type
	 *
	 * @return string
	 *
	 * @throws \Exception
	 */
	public static function getPostType(){
		$model = Self::newWithoutConstructor();

		if(isset($model->postType)){
			return $model->postType;
		}

		throw new Exception('$postType not defined');
	}

	/**
	 * Returns a new model
	 *
	 * @return object
	 */
	public static function newInstance($insert = array()){
		$class = get_called_class();
		return new $class($insert);
	}

    /**
     * Returns TRUE if $attribute is in the $virtual array
     * and has a corresponding vitaul property method
     *
     * @param  string $attribute
	 * @return bool
	 */
	public function isVirtualProperty($attribute){
		return (isset($this->virtual) &&
			in_array($attribute, $this->virtual) &&
			method_exists($this, ('_get'. ucfirst($attribute))));
	}

	/**
	 * Calls virtual property method
	 *
	 * @param  string $attribute
	 * @return mixed
	 */
	public function getVirtualProperty($attribute){
		return call_user_func(array($this, ('_get'. ucfirst($attribute))));
	}



	// -----------------------------------------------------
	// Meta
	// -----------------------------------------------------
	/**
	 * Returns meta value for a meta key
	 *
	 * @param  string meta_key
	 * @return string
	 */
    public function getMeta($key){
		return get_post_meta($this->ID, $key, TRUE);
	}

	/**
	 * Set meta value for a meta key
	 *
	 * @param  string meta_key
	 * @param  string meta_value
	 * @return void
	 */
	public function setMeta($key, $value){
		if(is_object($value) && $value instanceof WP_Model){
			if($value->new || $value->dirty){
				$value->save();
			}

			$value = $value->ID;
		}elseif($this->isArrayOfModels($value)){
		   	$IDs = array();
			foreach($value as $model){
				if($model->new || $model->dirty){
					$model->save();
				}

				$IDs[] = $model->ID;
			}

			$value = $IDs;	
		}

		update_post_meta($this->ID, $key, $value);
	}

	/**
	 * Delete meta's meta
	 *
	 * @param  string meta_key
	 * @return void
	 */
	public function deleteMeta($key){
		delete_post_meta($this->ID, $key);
	}


    // -----------------------------------------------------
	// GETTERS & SETTERS
	// -----------------------------------------------------
	/**
	 * Get property of model or $default
	 *
	 * @param  property $attribute
	 * @param  property $default
	 * @return mixed
	 *
	 * @todo  investagte this method
	 */
	public function get($attribute, $default = NULL){
		if($attribute === 'the_content'){
			return apply_filters('the_content', $this->data['content']);
		}elseif(isset($this->data[$attribute])){
			return $this->data[$attribute];
		}else{
			return $default;
		}	
	}

	/**
	 * Set propert of the model
	 *
	 * @param string $attribute
	 * @param string $value
	 * @return void
	 */
	public function set($attribute, $value){
		if(in_array($attribute, $this->getAttributes())){
			$this->data[$attribute] = $value;
		}
	}

	// -----------------------------------------------------
	// MAGIC METHODS
	// -----------------------------------------------------
	/**
	 * @return void
	 */
	public function __set($attribute, $value){
		if(in_array($attribute, $this->getAttributes())){
			$this->set($attribute, $value);
		}
	}

	/**
	 * @return void
	 */
	public function __get($attribute){
		if(in_array($attribute, $this->getAttributes())){
			return $this->get($attribute);
		}else if($this->isVirtualProperty($attribute)){
			return $this->getVirtualProperty($attribute);
		}else if($attribute === 'post_title'){
			return $this->title;
		}else if($attribute === 'post_content'){
			return $this->content;
		}
	}


	// -----------------------------------------------------
	// HELPER METHODS
	// -----------------------------------------------------
	/**
	 * Check if the post exists by Post ID
	 *
	 * @param  string|int   $ID   Post ID
	 * @param  bool 		$postTypeSafe Require post to be the same post type as the model
	 * @return bool
	 */
	public static function exists($ID, $postTypeSafe = TRUE){
		if($postTypeSafe){
			if(
				(get_post_status($ID) !== FALSE) &&
				(get_post_type($ID)   === Self::getPostType())){
				return TRUE;
			}
		}else{
			return (get_post_status($ID) !== FALSE);
		}

		return FALSE;
	}

	/**
	 * Returns the total posts without using WP_Model::all()
	 *
	 * @return int
	 */
	public static function count($postStatus = 'publish'){
		$count = wp_count_posts(Self::getPostType());
		return !is_null($count)? (isset($count->$postStatus)? intval($count->$postStatus) : 0) : 0;
	}

	/**
	 * Returns the original post object
	 *
	 * @return WP_Post
	 */
	public function post(){
		return $this->_post;
	}

	/**
	 * Returns TRUE if the model's post has an associated featured image
	 *
	 * @return bool
	 */
	public function hasFeaturedImage(){
		return (get_the_post_thumbnail_url($this->ID) !== FALSE)? TRUE : FALSE;
	}

	/**
	 * Get model's featured image or return $default if it does not exist
	 *
	 * @param  string $default
	 * @return string
	 */
	public function featuredImage($default = ''){
		$featuredImage = get_the_post_thumbnail_url($this->ID);
		return ($featuredImage !== FALSE)? $featuredImage : $default;
	}

	/**
	 * Returns an asoc array representaion of the model
	 *
	 * @return array
	 */
	public function toArray(){
		$model = array();

		foreach($this->attributes as $key => $attribute){
			if(!empty($this->protected) && !in_array($attribute, $this->protected)){
				// Do not add to $model
			}else{
				$model[$attribute] = $this->$attribute;
			}
		}

		if(!empty($this->serialize)){
			foreach($this->serialize as $key => $attribute){
				if(!empty($this->protected) && !in_array($attribute, $this->protected)){
					// Do not add to $model
				}else{
					$model[$attribute] = $this->$attribute;
				}
			}
		}

		$model['ID'] 		= $this->ID;
		$model['title'] 	= $this->title;
		$model['content'] 	= $this->content;
		return $model;
	}

	public function postDate($format = 'd-m-Y'){
		return date($format, strtotime($this->_post->post_date));
	}

	/**
	 * Get the model for a single page or in the loop
	 *
	 * @return object|NULL
	 */
	public static function single(){
		return Self::find(get_the_ID());
	}

	/**
	 * returns the post's permalink
	 *
	 * @return string
	 */
	public function permalink(){
		return get_permalink($this->ID);
	}


	// ----------------------------------------------------
	// FINDERS
	// ----------------------------------------------------
	/**
	 * Find model by it's post ID
	 *
	 * @param  int $ID
	 * @return Object|NULL
	 */
	public static function find($ID){
		if(Self::exists($ID)){
			$class = Self::newInstance();
			$class->ID = $ID;
			$class->boot();
			return $class;
		}

		return NULL;
	}

	/**
	 * Find most recent models
	 * @param  integer $limit
	 * @return Array
	 */
	public static function latest($limit = 1){
		$class = get_called_class();
		return $class::finder('Latest__', array('limit' => $limit));
	}

	public static function _finderLatest__($args){
		return array(
			'posts_per_page' => (isset($args['limit'])? $args['limit'] : 3),
    	);
	}

	public static function _postFinderLatest__($results, $args){
		if($args['limit'] == '1'){
			return @$results[0];
		}
		return $results;
	}

	/**
	 * Returns all models
	 *
	 * @param  string $limit
	 * @return array
	 */
	public static function all($limit = '999999999'){
		$return = array();
		$args = array(
			'post_type' 	 => Self::getPostType(),
			'posts_per_page' => $limit,
			'order'          => 'DESC',
			'orderby'        => 'id',
			'post_status'    => array('publish'),
		);

		foreach((new WP_Query($args))->get_posts() as $post){
			$return[] = Self::find($post->ID);
		}

		return $return;
	}

	/**
	 * Retun an array of models as asoc array. Key by $value
	 *
	 * @param  string  $value
	 * @param  array   $models
	 * @return array
	 */
	public static function asList($value = NULL, $models = FALSE){
		if(!is_array($models)){
			$self = get_called_class();
			$models = $self::all();
		}

		$return = array();
		foreach($models as $model){
			if(is_int($model) || $model instanceof WP_Post){
				$model = Self::find($model->ID);
			}

			if(is_null($value)){
				$return[$model->ID] = $model;
			}else{
				$return[$model->ID] = $model->$value;
			}
		}

		return $return;
	}

	/**
	 * Execute funder method
	 *
	 * @param  string $finder
	 * @param  array $arguments
	 * @return array
	 */
	public static function finder($finder, Array $arguments = array()){
		$return = array();
		$finderMethod = '_finder'.ucfirst($finder);
		$class = get_called_class();
		$model = $class::newWithoutConstructor();
		if(!in_array($finderMethod, Self::extract(( new ReflectionClass(get_called_class()) )->getMethods(), 'name'))){
			throw new Exception("Finder method {$finderMethod} not found in {$class}");
		}

		$args = $model->$finderMethod($arguments);
		if(!is_array($args)){
			throw new Exception("Finder method must return an array");
		}

		$args['post_type'] = Self::getPostType();
		foreach((new WP_Query($args))->get_posts() as $key => $post){
			$return[] = Self::find($post->ID);
		}

		$postFinderMethod = '_postFinder'.ucfirst($finder);
		if(in_array($postFinderMethod, Self::extract(( new ReflectionClass(get_called_class()) )->getMethods(), 'name'))){
			return $model->$postFinderMethod($return, $arguments);
		}

		return $return;
	}

	/**
	 * @return void
	 */
	public static function in(Array $ids = array()){
		$results = array();
		if(!is_array($ids)){
			$ids = func_get_args();
		}

		foreach($ids as $key => $id){
			if(Self::exists($id)){
				$results[] = Self::find($id);
			}
		}

		return $results;
	}

	// -----------------------------------------------------
	// SAVE
	// -----------------------------------------------------
	/**
	 * Save the model and all of it's associated data
	 *
	 * @param Array $overrides  List of parameters to override for wp_insert_post(), such as post_status
	 *
	 * @return Object $this
	 */
	public function save($overrides = array()){
		$this->triggerEvent('saving');

		$overwrite = array_merge($overrides, array(
			'post_type' => Self::getPostType()
		));

		if(!is_null($this->ID)){

			$defaults = array(
				'ID'           =>  $this->ID,
				'post_title'   =>  $this->title,
				'post_content' => ($this->content !== NULL)? $this->content :  ' ',
			);
			wp_update_post(array_merge($defaults, $overwrite));

		}else{

			$this->triggerEvent('inserting');
			$defaults = array(
				'post_status'  => 'publish',
				'post_title'   =>  $this->title,
				'post_content' => ($this->content !== NULL)? $this->content :  ' ',
			);
			$this->ID = wp_insert_post(array_merge($defaults, $overwrite));
			$this->_post = get_post($this->ID);
			$this->triggerEvent('inserted');

		}

		foreach($this->attributes as $attribute){
			$this->setMeta($attribute, $this->get($attribute, ''));
		}

		$this->setMeta('_id', $this->ID);
		$this->triggerEvent('saved');
		return $this;
	}

	// -----------------------------------------------------
	// DELETE
	// -----------------------------------------------------
	/**
	 * @return void
	 */
	public function delete(){
		$this->triggerEvent('deleting');
		wp_trash_post($this->ID);
		$this->triggerEvent('deleted');
	}

	/**
	 * @return void
	 */
	public function hardDelete(){
		$this->triggerEvent('hardDeleting');

		wp_update_post(array(
			'ID'           => $this->ID,
			'post_title'   => '',
			'post_content' => '',
		));

		foreach($this->attributes as $attribute){
			$this->deleteMeta($attribute);
			$this->set($attribute, NULL);
		}

		$this->setMeta('_id', $this->ID);
		$this->setMeta('_hardDeleted', '1');
		wp_delete_post($this->ID, TRUE);
		$this->triggerEvent('hardDeleted');
	}

	/**
	 * @return void
	 */
	public static function restore($ID){
		wp_untrash_post($ID);
		return Self::find($ID);
	}
}