<?php
require_once "connection.php";
class Handler
{
    public function __construct()
    {
        $this->bot_token = "insert your bot token here";
        $this->apiLink = "https://api.telegram.org/bot$this->bot_token/";
        //group chat id to announce
        $this->chatId = "insert your group chat id here";
        
    }
    
    //function for get message update from tele
    public function getUpdates(){


        global $redis;
        $lastUpdateId = $redis->get('update_id');
        $updates = file_get_contents($this->apiLink.'getUpdates?offset='.$lastUpdateId.'&timeout=1');
        $updateArray = json_decode($updates, TRUE);    
        
        //loop every update
        foreach($updateArray['result'] as $key=>$val){    
                
               
                if(isset($val['message'])){
                    //if update come from message/command
                    $this->handleMessage($val);
                } else if(isset($val['inline_query'])){
                    //if update come from inline query
                    $this->handleInline($val);
                }
                else if(isset($val['callback_query'])){
                    //if update come from callback query (inline keyboard)
                    $this->handleCallback($val);
                }
        } 
             
        $response=array(
            'status' => 0,
            'message' => $updateArray['result'],
        );
        
        echo json_encode($response);
    }

    private function handleMessage($val)
    {
        // handle update message
        global $redis;

        //get data from decoded update object
        $updateId = $val['update_id'];
        $chatId = $val['message']['chat']['id'];
        $text = $val['message']['text'];
        $username = isset($val['message']["chat"]["username"]) ? $val['message']["chat"]["username"] : NULL;
        if(isset($val['message']["reply_to_message"])){
            $text = $val['message']['reply_to_message']['text'];
            $input = $val['message']['text'];   
        }

        //handle response based on message
        switch($text) {
            case "/start":
                $msg = "Selamat Datang Di BOT Webinar SPE ketik /menu atau /help untuk mencoba";
                break;
            case "/help":
                $msg = '*Daftar command adalah sebagai berikut*
/start
/help
/menu
/list
/register
/enroll
/announce';
                break;
                case "/setting":
                    $msg = 'Tidak ada setting';
                    break;
            case "/list":
                //list tiap seminar yang didaftar hari ini
                $day = date("Ymd");
                $sem =  $redis->hgetall($day);
                if($sem){
                    $result = "";
                    foreach($sem as $key=>$value){
                        $result = $result . $key . " " .json_decode($value)->title . "\n"; 
                    }
                    $msg = "Daftar Seminar Adalah :\n".$result;
                }
                else{
                    $msg = "Tidak Ada Seminar";
                }
                break;
            case "/register":
                //daftarkan seminar untuk hari ini
                //contoh force reply
                $setting = array("force_reply" => true,"input_field_placeholder" => "Judul Seminar");
                $msg = "Masukkan Judul Seminar";
                $data['reply_markup'] = json_encode($setting);
                break;
            case "/enroll":
                //ikuti seminar untuk hari ini
                //contoh force reply
                $setting = array("force_reply" => true,"input_field_placeholder" => "Id Seminar");
                $msg = "Masukkan Id Seminar Yang Ingin Diikuti";
                $data['reply_markup'] = json_encode($setting);
                break;
            case "/announce":
                //umumkan seminar untuk hari ini
                //contoh force reply
                $setting = array("force_reply" => true,"input_field_placeholder" => "Id Seminar");
                $msg = "Masukkan Id Seminar Yang Ingin Diumumkan";
                $data['reply_markup'] = json_encode($setting);
                break;
            case "/menu":
                //contoh menu custom keyboard
                $keyboard = array(array("/start","/help","/menu"),array("/list","/register","/enroll","/announce"));
                $setting = array("keyboard" => $keyboard,"resize_keyboard" => true,"one_time_keyboard" => true);
                $data['reply_markup'] = json_encode($setting);
                $msg = "Berikut Daftar Menu Gaes";
                break;
            case "Masukkan Judul Seminar":
                //daftarkan seminar hasil force reply
                $id = date("His");
                $day = date("Ymd");
                $announce = "Hi SPEcialteam!

Hari ini SPEcialwebinar Jogja hadir dengan tema \"$input\" pukul 12.00 WIB

Seperti biasanya, KUOTA PESERTA TERBATAS (hanya 15 orang). Jadi untuk SPEcialteam yg berminat segera tulis namanya dibawah ini! Jangan sampai keduluan yah!";
                $sem = array("count"=>0,"title"=>$input,"announce"=>$announce,"participant"=>array());
         
                $redis->hset($day, $id, json_encode($sem));
                $msg = "Webinar berhasil didaftarkan";
                break;
            case "Masukkan Id Seminar Yang Ingin Diumumkan":
                //umumkan seminar hasil force reply
                $day = date("Ymd");
                $sem = $redis->hget($day, $input);
                if($sem){
                    $keyboard = array(array(
                        array("text"=> "Yes", "callback_data"=> "announce ".$input),
                        array("text"=> "No", "callback_data"=> "abort")
                    ));
                    $setting = array("inline_keyboard" => $keyboard);
                    $data['reply_markup'] = json_encode($setting);
                    $msg = "Apakah Anda Yakin Akan mengumumkan seminar ".json_decode($sem)->title;
                } else {
                    $msg = "Seminar Tidak Ditemukan";
                }
                
                break;
            case "Masukkan Id Seminar Yang Ingin Diikuti":
                //ikuti seminar hasil force reply
                $day = date("Ymd");
                $sem = $redis->hget($day, $input);
                if(!$sem){
                    $msg = "Seminar Tidak Ditemukan";
                } else if (json_decode($sem)->count >= 7) {
                    $msg = "Seminar Sudah Penuh";
                } else if (in_array($username, json_decode($sem)->participant)){
                    $msg = "Anda Sudah Mendaftar";
                } else {
                    $decode = json_decode($sem);
                    $count = $decode->count + 1;
                    $announce =  $decode->announce . "\n" .$count  . ". $username";
                    $participant = $decode->participant;
                    $participant[] = $username;
                    $sem = array("count"=>$count ,"title"=>$decode->title,"announce"=>$announce,"participant"=> $participant);
                    $redis->hset($day, $input, json_encode($sem));
                    $msg = $announce;
                    $chatId = $this->chatId;
                }
                break;
            default : 
                $msg = NULL;
                break;
        }

    
        $data['chat_id'] =  $chatId;
        $data['text'] = $msg;
        $data['parse_mode'] = 'Markdown';
    
        $redis->set('update_id', $updateId+1);
        if($msg && ($val["chat"]["type"] == "private")){
           file_get_contents($this->apiLink."sendmessage?".http_build_query($data));    
        }
    }

    private function handleInline($val)
    {
        // handle inline message
        // inline message contohnya bot @sicker
        global $redis;

        //get data from decoded update object
        $updateId = $val['update_id'];
        $text =  $val['inline_query']["query"];
        $chatId = $val['inline_query']["id"];

        switch($text) {
            case "webinar" :
                //response list seminar hari ini
                $day = date("Ymd");
                $sem =  $redis->hgetall($day);
                if($sem){
                    $results = array();
                    foreach($sem as $key=>$value){
                        $title = json_decode($value)->title;
                        $results[] = array(
                            "type"=> "article",
                            "id"=> $key,
                            "title"=> json_decode($value)->title,
                            "input_message_content"=> array(
                              "message_text"=> "Untuk mendaftar seminar $title, kirim command /enroll ke bot lalu masukkan id seminar ".$key
                            )
                        ); 
                    }
                    
                }
                else{
                    $results = array(
                        array(
                          "type"=> "article",
                          "id"=> "1",
                          "title"=> "Tidak Ada Seminar",
                          "input_message_content"=> array(
                            "message_text"=> "Tidak Ada Seminar"
                          )
                        )
                    );
                }
                break;
            default:
            $results = array(
                array(
                  "type"=> "article",
                  "id"=> "1",
                  "title"=> "Tidak Ada Seminar",
                  "input_message_content"=> array(
                    "message_text"=> "Tidak Ada Seminar"
                  )
                )
            );

        }
        $results = json_encode($results);

        $data['inline_query_id'] =  $chatId;
        $data['results'] = $results;
        $data['cache_time'] = 1;
        
        $redis->set('update_id', $updateId+1);
        if($results){   
           echo file_get_contents($this->apiLink."answerInlineQuery?".http_build_query($data));    
        }
    }

    private function handleCallback($val)
    {
        // handle callback query
        global $redis;

        //get data from decoded update object
        $updateId = $val['update_id'];
        $chatId = $val['callback_query']['message']['chat']['id'];
        $text =  $val['callback_query']["data"];
        $queryId = $val['callback_query']["id"];
        $strArray = explode(' ',$text);
        $callback = $strArray[0];
        
        switch($callback) {
            case "announce" :
                //case inline button announce
                $day = date("Ymd");
                $sem = $redis->hget($day, $strArray[1]);
                $chatId = $this->chatId;
                $msg = json_decode($sem)->announce;
                $notif = "Seminar Diumumkan";
                break;
            case "abort" :
                //case inline button abort
                $msg = "Tidak Jadi";
                $notif = "Tidak Jadi";
                break;
        }

        $data['chat_id'] =  $chatId;
        $data['text'] = $msg;
        $data['parse_mode'] = 'Markdown';
    
        $redis->set('update_id', $updateId+1);
        if($msg){
            //answer untuk mengirim notifikasi
            file_get_contents($this->apiLink."answerCallbackQuery?callback_query_id=$queryId&text=$notif");    
            //sendmessage untuk mengirim pesan
            file_get_contents($this->apiLink."sendmessage?".http_build_query($data));    
        }
    }
}

