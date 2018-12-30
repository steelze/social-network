<?php
namespace app\Model;

use app\DB;
use app\Auth\Auth;


class Post
{
    private $_db;
    private $_user = null;

    public function __construct() {
        $this->_db = DB::getInstance();
    }

    public function find($value, $column = null)
    {
        $column = ($column) ? $column : 'id';
        $data = $this->_db->select('posts', [], [$column => $value])->first();
        return ($data) ? $data : false;
    }

    public function create($body, $recepient = null)
    {
        $this->_db->insert('posts', [
            'body' => $body,
            'recepient' => $recepient,
            'user_id' => Auth::user()->id,
        ]);

        //if recepient, send notification
    }
    
    public function delete($id)
    {
        $this->_db->delete('posts', [
            'id' => $id,
        ]);
        //if recepient, send notification
    }

    public function newsfeed($start, $limit = 7)
    {
        $auth = new User();
        $id = (int)$auth->getUser()->id;
        $friends = $auth->friends();
        //If user has friends....
        if (count($friends)) {
            $placeholder = "OR users.id IN (".rtrim(str_repeat('?, ', count($friends)), ', ').")";
        } else {
            $placeholder = '';
        }
        //Add auth user to begining of friend array for sql binding
        array_unshift($friends, $id);
        //...
        $data = $this->_db->raw("SELECT posts.*, users.fname, users.lname, users.avatar, users.username FROM posts INNER JOIN users ON posts.user_id = users.id WHERE posts.deleted_at IS NULL AND posts.user_id = ? $placeholder AND posts.deleted_at IS NULL AND users.deleted_at IS NULL ORDER BY posts.created_at DESC LIMIT $start, $limit", $friends)->get();

        foreach ($data as $value) {
            $value->comment = $this->_db->raw("SELECT count(id) AS num FROM comments WHERE post_id = ?", [$value->id])->first()->num;
            $value->like = $this->_db->raw("SELECT count(id) AS num FROM likes WHERE post_id = ?", [$value->id])->first()->num;
            $value->hasLiked = ($this->_db->select('likes', [], ['post_id' => $value->id, 'user_id' => Auth::user()->id])->count()) ? true : false;
            $comments = new Comment();
            $value->comments = $comments->timeline($value->id);
            if ($value->recepient) {
                $user = new User($value->recepient);
                $value->recepient = $user->getUser();
            }
        }
        return $data;
    }
    
    public function timeline($user, $start, $limit = 7)
    {
        $auth = new User($user);
        $id = (int)$auth->getUser()->id;
        

        $data = $this->_db->raw("SELECT posts.*, users.fname, users.lname, users.avatar, users.username FROM posts INNER JOIN users ON posts.user_id = users.id WHERE posts.deleted_at IS NULL AND posts.user_id = ? OR posts.recepient = ? AND posts.deleted_at IS NULL AND users.deleted_at IS NULL ORDER BY posts.created_at DESC LIMIT $start, $limit", [$id, $id])->get();

        foreach ($data as $value) {
            $value->comment = $this->_db->raw("SELECT count(id) AS num FROM comments WHERE post_id = ?", [$value->id])->first()->num;
            $value->like = $this->_db->raw("SELECT count(id) AS num FROM likes WHERE post_id = ?", [$value->id])->first()->num;
            $value->hasLiked = ($this->_db->select('likes', [], ['post_id' => $value->id, 'user_id' => Auth::user()->id])->count()) ? true : false;
            $comments = new Comment();
            $value->comments = $comments->timeline($value->id);
            
            if ($value->recepient) {
                if ($value->recepient != $id) {
                    $user = new User($value->recepient);
                    $value->recepient = $user->getUser();
                } else {
                    $value->recepient = NULL;
                }
                
            } 
        }

        // 
        
        return $data;
    }
}
