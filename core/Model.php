<?php

namespace core;

use lib\Meta;

class Model extends Object {

    protected $_classes = array(
        'db'    => 'plugin\db\PDO_MySQL',
        'cache' => 'plugin\cache\MemcachedCache'
    );

    protected $_db;
    protected $_cache;

    protected $_has_one = array();
    protected $_has_many = array();
    protected $_belongs_to = array();
    protected $_has_and_belongs_to_many = array(); 
    
    protected $_no_cache = array();
        
    // id can either be an unique identifier 
    // or a WHERE relationship
    public function __construct($id = null) {
        $db = $this->_classes['db'];
        $this->_db = new $db(); 
        $cache = $this->_classes['cache'];
        $this->_cache = new $cache(); 
        
        if ( !is_numeric($id) && !is_null($id) ) {
            $result = $this->_db->find_object($this, $id); 
            $id = $result[PRIMARY_KEY];
        }

        if ( is_numeric($id) ) {
            if ( !$this->pull_from_cache($this, $id) ) {
                $this->_db->load_object($this, $id);
                $this->cache();
            }
        }
    }

    public function __get($field_name) {
        if ( !$this->is_foreign($field_name) ) {
            return $this->$field_name;
        }

        if ( $this->belongs_to($field_name) ) {
            return $this->_get_belongs_to($field_name);
        } elseif ( $this->has_many($field_name) ) {
            return $this->_get_has_many($field_name);
        } elseif ( $this->has_one($field_name) ) {
            return $this->_get_has_one($field_name);
        } elseif ( $this->habtm($field_name) ) {
            return $this->_get_habtm($field_name);
        }
    }
    
    public function __set($field_name, $value) {
        $this->$field_name = $value;
    }

    public function belongs_to($field_name) {
        return ( in_array($field_name, $this->_belongs_to) );
    }

    public function cache() {
        $meta = new Meta();
        $properties = $meta->get_protected_vars($this);
        $data = json_encode($properties);

        $id = PRIMARY_KEY;
        $key = get_class($this) . '_' . $this->$id;
        $this->_cache->store($key, $data);
    }

    public function delete($where = null) {
        $key = get_class($this) . '_' . $this->$id;
        $this->_cache->delete($key);
        $this->_db->delete($this, $where);
    }

    public function find_all($where = null, $obj = null) {
        if ( is_null($obj) ) {
            $obj = $this;
        }

        $all_obj_ids = $obj->_db->find_all_objects($obj, $where);

        $collection = array();
        $obj_name = get_class($obj);
        foreach ( $all_obj_ids as $obj_id ) {
            $collection[$obj_id] = new $obj_name($obj_id);
        }
        return $collection;
    }

    protected function _get_belongs_to($field_name) {
        $lookup_id = $field_name . PK_SEPARATOR . PRIMARY_KEY;
        $obj_id = $this->$lookup_id;

        return new $field_name($obj_id); 
    }

    protected function _get_habtm($field_name) {
        $class_name = get_class($this);
        $table_name = ( $class_name < $field_name ) ? $class_name . HABTM_SEPARATOR . $field_name : $field_name . HABTM_SEPARATOR . $class_name;

        $lookup_id = $class_name . PK_SEPARATOR . PRIMARY_KEY;
        $id = PRIMARY_KEY;
        $where = array($lookup_id => $this->$id);

        $target_id = $field_name . PK_SEPARATOR . PRIMARY_KEY;
        $this->_db->find('`' . $target_id . '`', $table_name, $where);

        $rows = $this->_db->fetch_all('assoc');
        $collection = array();
        foreach ( $rows as $row ) {
            $new_id = $row[$target_id];
            $collection[$new_id] = new $field_name($new_id); 
        }
        return $collection;
    }

    protected function _get_has_many($field_name) {
        $lookup_id = get_class($this) . PK_SEPARATOR . PRIMARY_KEY;
        $id = PRIMARY_KEY;
        $where = array($lookup_id => $this->$id);

        return $this->find_all($where, $field_name);
    }

    protected function _get_has_one($field_name) {
        $lookup_id = get_class($this) . PK_SEPARATOR . PRIMARY_KEY;
        $id = PRIMARY_KEY;
        $where = array($lookup_id => $this->$id);

        $result = $this->_db->find_object($field_name, $where);
        $obj_id = $result[PRIMARY_KEY];

        return new $field_name($obj_id); 
    }

    public function habtm($field_name) {
        return ( in_array($field_name, $this->_has_and_belongs_to_many) );
    }
    
    public function has_many($field_name) {
        return ( in_array($field_name, $this->_has_many) );
    }

    public function has_one($field_name) {
        return ( in_array($field_name, $this->_has_one) );
    }

    public function is_foreign($field_name) {
        return ( $this->has_many($field_name) || $this->has_one($field_name) || 
                 $this->habtm($field_name) || $this->belongs_to($field_name) );
    }

    public function pull_from_cache($obj, $id) {
        $key = get_class($obj) . '_' . $id;
        $cached_data = $obj->_cache->retrieve($key);
        if ( !$cached_data ) {
            return false;
        }

        $cached_obj = json_decode($cached_data, true);
        foreach ( $cached_obj as $key => $val ) {
            $obj->$key = $val;
        }
        return $obj;
    }

    public function store() {
        // TODO: Validate data!
        // TODO: Sanitize!
        $this->_db->upsert($this);
        $id = PRIMARY_KEY;
        $this->$id = $this->_db->insert_id();
        $this->cache();
    }

}

?>
