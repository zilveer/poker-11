<?php
/*
 * This file is part of the NB Framework package.
 *
 * Copyright (c) 2018 https://nb.cx All rights reserved.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace service;
use nb\Service;
use util\Auth;
use util\Redis;

/**
 * Room
 *
 * @package service
 * @link https://nb.cx
 * @author: collin <collin@nb.cx>
 * @date: 2018/11/1
 */
class Room extends Service {

    public function establish() {
        $auth = Auth::init();

        if($auth->play !== 'hall') {
            $this->code = 4031;
            $this->msg = '操作不允许';
            return false;
        }

        list($name,$pass) = $this->input('name','pass');

        $room = \model\Room::create($auth->id + 5,true,$name,$pass);

        $room->add($auth);
        $this->data = $room;
        return true;
    }

    public function enter() {
        $auth = Auth::init();

        if($auth->play !== 'hall') {
            $this->code = 4031;
            $this->msg = '操作不允许';
            return false;
        }

        $id = $this->input('id');
        $id = $id?:$this->random();
        if($id === 0) {
            $this->code = 409;
            $this->msg = '没有空闲房间了';
            return false;
        }

        $room = \model\Room::get($id);

        if($room->number > 2) {
            $this->code = 409;
            $this->msg = '房间满了';
            return false;
        }

        //如果房间设置了密码，验证密码
        if($room->pass && $room->pass != $this->input('pass')) {
            $this->code = 410;
            $this->msg = '请输入正确的房间密码！';
            return false;
        }

        $room->add($auth);
        $this->data = $room;
        return true;
    }

    //退出房间
    public function quit() {
        $auth = Auth::init();

        if($auth->play === 'hall') {
            $this->code = 4032;
            $this->msg = '你已经在大厅了';
            return false;
        }

        $room = $auth->room;

        if($room->status === 'startd') {
            $this->code = 4033;
            $this->msg = '你必须等到本局游戏结束才可以退出房间！';
            return false;
        }

        //设置玩家的房间为0，表示退出房间
        $seat = $auth->seat;
        $auth->room = '0-0';

        //如果房间人数小于2，其房间为可自动销毁，将对其回收
        if($room->number<2) {
            $room->destroy();
        }
        else {
            //清楚玩家在游戏房间的位置信息
            $room->$seat = 0;
        }
        $this->data = $room;
        return true;
    }

    //掉线处理
    public function unline($user) {
        $room = $user->room;
        //通知其它玩家，该玩家掉线
        if($room->status === 'startd') {
            $user->online = 0;
        }
        //如果房间状态不是startd，则让玩家退出房间
        else {
            $seat = $user->seat;
            //设置玩家的房间为0，表示退出房间
            $user->room = '0-0';
            //清楚玩家在游戏房间的位置信息
            $room->$seat = 0;
        }
        $this->data = $room;
        return true;
    }


    public function synchro() {
        $auth = Auth::init();

        if($auth->play === 'hall') {
            $this->code = 4031;
            $this->msg = '不在房间';
            return false;
        }

        $this->data = $auth->room;
        return true;
    }

    private function random() {
        $stack = [];
        $room = \model\Room::init();
        foreach ($room as $v) {
            if($v->number == 2) {
                return $v->id;
            }
            $stack[$v->number][] = $v->id;
        }

        //如果有一人的房间
        if(isset($stack[1])) {
            return $stack[1][0];
        }

        //如果有没有人的房间
        if(isset($stack[0])) {
            return $stack[0][0];
        }

        return 0;
    }


}