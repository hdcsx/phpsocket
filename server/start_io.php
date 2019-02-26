<?php
use PHPSocketIO\SocketIO;
use Workerman\Lib\Timer;
use Workerman\Worker;

include dirname(__DIR__) . '/vendor/autoload.php';
$io = new SocketIO(2020);

$io->on('workerStart', function () {
    $web            = new Worker('http://0.0.0.0:9191');
    $web->onMessage = function ($conn, $data) {
        global $io;
        $_POST = $_POST ? $_POST : $_GET;
        if (!isset($_GET['msg'])) {
            return $conn->send('fail, $_GET["msg"] not found');
        }
        $io->emit('new message', array(
            'username' => 'httppush',
            'message'  => $_GET['msg'],
        ));;
        $conn->send('httppush ok');
    };
    $web->listen();
    // 一个定时器，第一个参数单位为秒
    Timer::add(1, function () {
        global $io;

    });
});

$io->on('connection', function ($socket) {
    global $io;
    $param = $socket->handshake['query'];
    if (!isset($param['token'])) {
        $socket->disconnect();
        return;
    }
    // $headers = $socket->handshake['headers'];
    echo "new connection coming..." . $socket->conn->remoteAddress . "\n";
    $socket->addedUser = false;

    // when the client emits 'new message', this listens and executes
    $socket->on('new message', function ($data) use ($socket) {
        echo $data . "\n";
        if ($data == 'exit') {
            $socket->disconnect();
            return;
        }
        // 广播给除啦自己的所有客户端
        $socket->broadcast->emit('new message', array(
            'username' => $socket->username,
            'message'  => $data,
        ));
        //系统回复一个消息
        $socket->emit('typing', array(
            'username' => 'System',
            'message'  => '上条消息发送时间:' . date('Y-m-d H:i:s'),
        ));
        // 向某个分组的所有客户端发送事件
        // $io->to('group name')->emit('event name', $data);
        // 2、向所有客户端发送事件
        // $io->emit('event name', $data);
        // 1、向当前客户端发送事件
        // $socket->emit('event name', $data);
        // 1、加入分组（一个连接可以加入多个分组）

        // $socket->join('group name');
        // 2、离开分组（连接断开时会自动从分组中离开）

        // $socket->leave('group name');
    });

    // when the client emits 'add user', this listens and executes
    $socket->on('add user', function ($username) use ($socket) {
        global $usernames, $numUsers;
        // we store the username in the socket session for this client
        $socket->username = $username;
        // add the client's username to the global list
        $usernames[$username] = $username;
        ++$numUsers;
        $socket->addedUser = true;
        $socket->emit('login', array(
            'numUsers' => $numUsers,
        ));
        // echo globally (all clients) that a person has connected
        $socket->broadcast->emit('user joined', array(
            'username' => $socket->username,
            'numUsers' => $numUsers,
        ));
    });

    // when the client emits 'typing', we broadcast it to others
    $socket->on('typing', function () use ($socket) {
        $socket->broadcast->emit('typing', array(
            'username' => $socket->username,
        ));
    });

    // when the client emits 'stop typing', we broadcast it to others
    $socket->on('stop typing', function () use ($socket) {
        $socket->broadcast->emit('stop typing', array(
            'username' => $socket->username,
        ));
    });

    // when the user disconnects.. perform this
    $socket->on('disconnect', function () use ($socket) {
        echo "disconnected\n";
        global $usernames, $numUsers;
        // remove the username from global usernames list
        if ($socket->addedUser) {
            unset($usernames[$socket->username]);
            --$numUsers;

            // echo globally that this client has left
            $socket->broadcast->emit('user left', array(
                'username' => $socket->username,
                'numUsers' => $numUsers,
            ));
        }
    });

});

if (!defined('GLOBAL_START')) {
    Worker::runAll();
}
