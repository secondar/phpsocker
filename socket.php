<?php
error_reporting(E_ALL);
set_time_limit(0);// 设置超时时间为无限,防止超时
date_default_timezone_set('Asia/shanghai');
$sk=new ws('192.168.1.85','8080');
/**
 *
 */
class ws{
    private $objMaster;
    private $arrSockets;
    private $arrUsers;
    function __construct($host,$port,$linux=false){
        try {
            //创建服务端的socket套接流,net协议为IPv4，protocol协议为TCP
            $this->objMaster = socket_create(AF_INET,SOCK_STREAM,SOL_TCP) or die("err => socket_create\n");
            $strSeverName = '';
            if($linux){
                $strSeverName = posix_getpid();
            }else{
                $strSeverName = get_current_user();
            }
            $this->debug('info : '.date('Y-m-d H:i:s').' | '.$strSeverName.' -> socket_create');
            //设置IP和端口重用,在重启服务器后能重新使用此端口;
            socket_set_option($this->objMaster, SOL_SOCKET, SO_REUSEADDR, 1) or die("err => socket_set_option");
            $this->debug('info : '.date('Y-m-d H:i:s').' | '.$strSeverName.' -> socket_set_option');
            //绑定接收的套接流主机和端口,与客户端相对应
            socket_bind($this->objMaster,$host,$port) or die("err => socket_bind");
            $this->debug('info : '.date('Y-m-d H:i:s').' | '.$strSeverName.' -> socket_bind');
            //监听套接流
            socket_listen($this->objMaster) or die("err => socket_listen");
            $this->debug('info : '.date('Y-m-d H:i:s').' | '.$strSeverName.' -> socket_listen');
        }catch(\Exception $e){
            $this->debug( 'info : '.date('Y-m-d H:i:s').' | '.$strSeverName.' | server listen fail -> '.socket_strerror(socket_last_error()));
        }
        $this->arrSockets[0] = $this->objMaster;
        $this->arrUsers['server']=array('resource'=>$this->objMaster,'name'=>'sever','handshake'=>true);
        $this->run();
        $this->debug('info : '.date('Y-m-d H:i:s').' | '.$strSeverName.' -> run');
    }
    private function run(){
        while (true) {
            $arrChanges=$this->arrSockets;
            $write=NULL;
            $except=NULL;
            /*
            这个函数是同时接受多个连接的关键，我的理解它是为了阻塞程序继续往下执行。
            socket_select ($arrSockets, $write = NULL, $except = NULL, NULL);
            $arrSockets可以理解为一个数组，这个数组中存放的是文件描述符。当它有变化（就是有新消息到或者有客户端连接/断开）时，socket_select函数才会返回，继续往下执行。
            $write是监听是否有客户端写数据，传入NULL是不关心是否有写变化。
            $except是$arrSockets里面要被排除的元素，传入NULL是”监听”全部。
            最后一个参数是超时时间
            如果为0：则立即结束
            如果为n>1: 则最多在n秒后结束，如遇某一个连接有新动态，则提前返回
            如果为null：如遇某一个连接有新动态，则返回
            */
            socket_select($arrChanges,$write,$except,NULL) or die("err => socket_select ,".socket_last_error().
                socket_strerror(socket_last_error())."\n");
            foreach($arrChanges as $Socket){
                /*新连接到来时,被监听的端口是活跃的,如果是新数据到来或者客户端关闭链接时,活跃的是对应的客户端socket而不是服务器上被监听的端口
                如果客户端发来数据没有被读走,则socket_select将会始终显示客户端是活跃状态并将其保存在readfds数组中
                如果客户端先关闭了,则必须手动关闭服务器上相对应的客户端socket,否则socket_select也始终显示该客户端活跃(这个道理跟"有新连接到来然后没有用socket_access把它读出来,导致监听的端口一直活跃"是一样的)*/

                //如果有新的client连接进来
                if($Socket==$this->objMaster){
                    //接受一个socket连接
                    $objClient=socket_accept($this->objMaster);
                    //给新连接进来的socket一个唯一的ID
                    $key=uniqid();
                    $this->arrSockets[]=$objClient;     //将新连接进来的socket存进连接池
                    $this->arrUsers[$key]=array(
                        'resource'=>$objClient,        //记录新连接进来client的socket信息
                        'handshake'=>false             //标志该socket资源没有完成握手
                    );
                    $this->debug('info : '.date('Y-m-d H:i:s').' | key:'.$key.' | resource:'.$objClient.' -> Connect');
                }else{//否则1.为client断开socket连接，2.client发送信息
                    @$intLen = socket_recv($Socket, $objBuffer, 8192, 0);
                    $strUsers_Key=$this->search_key($Socket);
                    //如果接收的信息长度小于9，则该client的socket为断开连接
                    if($intLen<9){
                        //给该client的socket进行断开操作，并在$this->sockets和$this->users里面进行删除
                        $this->close($strUsers_Key);
                        continue;
                    }
                    //判断该socket是否已经握手
                    if(!$this->arrUsers[$strUsers_Key]['handshake']){
                        $this->connect($strUsers_Key,$objBuffer);
                    }else{
                        $arrMsg=$this->parse($objBuffer);
                        print_r(array($objBuffer));//exit();
                        if($arrMsg==false){
                            $arrMsg=json_decode($objBuffer,true);//给C#客户端用
                            print_r(array($arrMsg));
                            if($arrMsg==false){
                                continue;
                            }
                        }
                        switch ($arrMsg['type']) {
                            case 'login':
                                $arrMsg['uid']=rand(intval(time()/2),time());
                                $this->response_login($arrMsg,$strUsers_Key);
                                break;
                            case 'user_msg':
                                $arrMsg['from']=$this->arrUsers[$strUsers_Key]['name'];
                                $this-> broadcast($arrMsg,$arrMsg['uid'],$strUsers_Key);
                                break;
                        }
                        //如果不为空，则进行消息推送操作
                    }
                }
            }
        }
    }
    /**
     * [search_key 根据Socket在arrUsers里面查找相应的key]
     * @param  [type] $Socket [Socket]
     * @return [string]         [查询到的key]
     */
    private function search_key($Socket){
        foreach ($this->arrUsers as $k=>$v){
            if($Socket==$v['resource'])
            return $k;
        }
        return false;
    }
    /**
     * [close 指定关闭$k对应的socket]
     * @param  [string] $key [socket对应的$k]
     * @return [void]
     */
    private function close($key){
        $log='info : '.date('Y-m-d H:i:s').' | key : '.$key.' | resource : '.$this->arrUsers[$key]['resource'].' -> close';
        //断开相应socket
        socket_close($this->arrUsers[$key]['resource']);
        //删除相应的user信息
        unset($this->arrUsers[$key]);
        //重新定义sockets连接池
        $this->arrSockets = array();
        foreach($this->arrUsers as $v){
            $this->arrSockets[]=$v['resource'];
        }
        $this->debug($log);
    }
    /**
     * [debug 输出日志信息]
     * @param  [string] $str [需要输出的文本信息]
     * @return [void]
     */
    private function debug($str){
        $str=$str."\n";
        echo iconv('utf-8','gbk//IGNORE',$str);
    }
    /**
     * [connect 对client的请求进行回应，即握手操作]
     * @param  [string] $key    [clien的socket对应的健，即每个用户有唯一$k并对应socket]
     * @param  [type] $buffer [接收client请求的所有信息]
     * @return [bool]
     */
    private function connect($key,$buffer){
        //截取Sec-WebSocket-Key的值并加密，其中$key后面的一部分258EAFA5-E914-47DA-95CA-C5AB0DC85B11字符串应该是固定的
        $buf  = substr($buffer,strpos($buffer,'Sec-WebSocket-Key:')+18);
        $key_encrypt  = trim(substr($buf,0,strpos($buf,"\r\n")));
        $new_key = base64_encode(sha1($key_encrypt."258EAFA5-E914-47DA-95CA-C5AB0DC85B11",true));
        //按照协议组合信息进行返回
        $new_message = "HTTP/1.1 101 Switching Protocols\r\n";
        $new_message .= "Upgrade: websocket\r\n";
        $new_message .= "Sec-WebSocket-Version: 13\r\n";
        $new_message .= "Connection: Upgrade\r\n";
        $new_message .= "Sec-WebSocket-Accept: " . $new_key . "\r\n\r\n";
        socket_write($this->arrUsers[$key]['resource'],$new_message,strlen($new_message));
        //对已经握手的client做标志
        $this->arrUsers[$key]['handshake']=true;
        return true;
    }
    /**
     * [parse 解析数据]
     * @param  [type] $buffer  [接收client请求的所有信息]
     * @return [array]         [处理好的信息]
     */
    private function parse($buffer) {
        $decoded = '';
        $len = ord($buffer[1]) & 127;
        if ($len === 126) {
            $masks = substr($buffer, 4, 4);
            $data = substr($buffer, 8);
        } else if ($len === 127) {
            $masks = substr($buffer, 10, 4);
            $data = substr($buffer, 14);
        } else {
            $masks = substr($buffer, 2, 4);
            $data = substr($buffer, 6);
        }
        for ($index = 0; $index < strlen($data); $index++) {
            $decoded .= $data[$index] ^ $masks[$index % 4];
        }

        return json_decode($decoded, true);
    }
    /**
     * [build 将普通信息组装成websocket数据帧]
     * @param  [type] $msg         [对编码后的json]
     * @return [bool|string]      [description]
     */
    private function build($arrmsg) {
        $frame = [];
        $frame[0] = '81';
        $len = strlen($arrmsg);
        if ($len < 126) {
            $frame[1] = $len < 16 ? '0' . dechex($len) : dechex($len);
        } else if ($len < 65025) {
            $s = dechex($len);
            $frame[1] = '7e' . str_repeat('0', 4 - strlen($s)) . $s;
        } else {
            $s = dechex($len);
            $frame[1] = '7f' . str_repeat('0', 16 - strlen($s)) . $s;
        }
        $data = '';
        $l = strlen($arrmsg);
        for ($i = 0; $i < $l; $i++) {
            $data .= dechex(ord($arrmsg{$i}));
        }
        $frame[2] = $data;
        $data = implode('', $frame);
        return pack("H*", $data);
    }
    /**
     * [broadcast 发送消息]
     * @param  [array] $data [build处理好的数据]
     * @return [type]       [description]
     */
    private function broadcast($msg_content,$uid='all',$mykey) {
        if(empty($msg_content['content'])&&$msg_content['content']!=0){
            $this->debug('info : '.date('Y-m-d H:i:s').' | function:broadcast'.' | content is null -> err');
        }
        if(empty($msg_content['type'])){
            $this->debug('info : '.date('Y-m-d H:i:s').' | function:broadcast'.' | type is null -> err');
        }
        if(empty($msg_content['from'])){
            $this->debug('info : '.date('Y-m-d H:i:s').' | function:broadcast'.' | from is null -> err');
        }
        if($mykey){
            $this->debug('info : '.date('Y-m-d H:i:s').' | function:broadcast'.' | mykey is null -> err');
        }
        $data=$this->build(json_encode($msg_content));
        if($uid=='all'){
            foreach ($this->arrSockets as $Socket) {
                if ($Socket == $this->objMaster) {
                    continue;
                }
                socket_write($Socket, $data, strlen($data));
            }
        }else{
            $key=$this->search_uid($uid);
            socket_write($this->arrUsers[$key]['resource'], $data, strlen($data));
            socket_write($this->arrUsers[$mykey]['resource'], $data, strlen($data));
        }
    }
    /**
     * [response_login 登录信息]
     * @param  [array]  $arrmsg [信息数组]
     * @param  [string] $key    [description]
     * @return [void]
     */
    private function response_login($arrmsg,$key) {
        $this->arrUsers[$key]['name']=$arrmsg['name'];
        $this->arrUsers[$key]['uid']=$arrmsg['uid'];
        $response['type'] = 'system';
        $response['from'] = 'login';
        $response['name'] = $arrmsg['name'];
        $response['content'] = 'ok';
        $response['user_list_count'] = count($this->arrUsers)-1;
        $response['user_list'] = $this->get_userlist();
        $data=$this->build(json_encode($response));
        socket_write($this->arrUsers[$key]['resource'], $data, strlen($data));
        $this->update_list($arrmsg['name'],$key);
    }
    private function update_list($name,$key) {
        $response['name'] = $name;
        $response['type'] = 'system';
        $response['from'] = 'list';
        $response['content'] = 'ok';
        $response['user_list_count'] = count($this->arrUsers)-1;
        $response['user_list'] = $this->get_userlist();
        $data=$this->build(json_encode($response));
        // socket_write($this->arrUsers[$key]['resource'], $data, strlen($data));
        foreach ($this->arrSockets as $Socket) {
            if ($Socket == $this->objMaster) {
                continue;
            }
            socket_write($Socket, $data, strlen($data));
        }
    }
    private function get_userlist(){
        $arrUserlist=array();
        foreach ($this->arrUsers as $k => $v) {
            if($k != 'server'){
                $arrUserlist[]=array('uid'=>$v['uid'],'name'=>$v['name']);
            }
        }
        return $arrUserlist;
    }
    /**
     * [search_uid 根据UID查找KEY]
     * @param  [type] $uid [客户端UID]
     * @return [string]    [对应的KEY]
     */
    private function search_uid($uid){
        if(!empty($uid)){
            foreach ($this->arrUsers as $k => $v){
                if($k != 'server'){
                    if($v['uid']==$uid){
                        return $k;
                    }
                }
            }
        }
    }
}
?>
