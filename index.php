<?php

require_once __DIR__ . '/vendor/autoload.php';
define('TABLE_NAME_USERS', 'users');

$httpClient = new \LINE\LINEBot\HTTPClient\CurlHTTPClient(getenv('CHANNEL_ACCESS_TOKEN'));
$bot = new \LINE\LINEBot($httpClient, ['channelSecret' => getenv('CHANNEL_SECRET')]);
$signature = $_SERVER["HTTP_" . \LINE\LINEBot\Constant\HTTPHeader::LINE_SIGNATURE];

$events = $bot->parseEventRequest(file_get_contents('php://input'), $signature);
foreach ($events as $event) {

  $profile = $bot->getProfile($event->getUserId())->getJSONDecodedBody();
  $displayName = $profile['displayName'];

  if ($event instanceof \LINE\LINEBot\Event\MessageEvent) {
    if ($event instanceof \LINE\LINEBot\Event\MessageEvent\TextMessage) {
      /*
      if($event->getText() === 'こんにちは') {
        $bot->replyMessage($event->getReplyToken(),
          (new \LINE\LINEBot\MessageBuilder\MultiMessageBuilder())
            ->add(new \LINE\LINEBot\MessageBuilder\StickerMessageBuilder(1, 17))
            ->add(new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('こんにちは！' . $displayName . 'さん'))
        );
      } else {
        $bot->replyMessage($event->getReplyToken(),
          (new \LINE\LINEBot\MessageBuilder\MultiMessageBuilder())
            ->add(new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('「こんにちは」と呼びかけて下さいね！'))
            ->add(new \LINE\LINEBot\MessageBuilder\StickerMessageBuilder(1, 4))
        );
      } */
      $dbh = dbConnection::getConnection();
      if($event->getText() === 'last') {
        $sql = 'select lastmessage from ' . TABLE_NAME_USERS . ' where ? = userid';
        $sth = $dbh->prepare($sql);
        $sth->execute(array($event->getUserId()));
        if($row = $sth->fetch()) {
          $bot->replyMessage($event->getReplyToken(), new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($row['lastmessage']));
        } else {
          $bot->replyMessage($event->getReplyToken(), new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('no history.'));
        }
      }
      else if($event->getText() === 'all') {
        $sql = 'select allmessages from ' . TABLE_NAME_USERS . ' where ? = userid';
        $sth = $dbh->prepare($sql);
        $sth->execute(array($event->getUserId()));
        if($row = $sth->fetch()) {
          $bot->replyMessage($event->getReplyToken(), new \LINE\LINEBot\MessageBuilder\TextMessageBuilder(json_decode($row['allmessages'])));
        } else {
          $bot->replyMessage($event->getReplyToken(), new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('no history.'));
        }
      }
      else {
        $sql = 'insert into ' . TABLE_NAME_USERS . ' (userid, lastmessage, allmessages) values(?, ?, ?) on conflict on constraint users_pkey do update set lastmessage = ?, allmessages = ?';
        $sth = $dbh->prepare($sql);
        $sth->execute(array($event->getUserId(), $event->getText(), json_encode(array($event->getText())), $event->getText(), json_encode(array($event->getText()), JSON_UNESCAPED_UNICODE)));

        $bot->replyMessage($event->getReplyToken(), new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('saved'));
      }


      /*
      $sql = 'select * from ' . TABLE_NAME_USERS . ' where ? = userid';
      $sth = $dbh->prepare($sql);
      $sth->execute(array($event->getUserId()));
      if($row = $sth->fetch()) {
        //$sqlAdd = 'insert into '. TABLE_NAME_USERS .' (session_id, access_token, refresh_token, expires_in) values (?, ?, ?, ?) returning *';
      } else {
        //$sqlAdd = 'insert into '. TABLE_NAME_USERS .' (userid, lastmessage, allmessages) values (?, ?, ?)';
        // insert into users (userid, lastmessage, allmessages) values('U5e8f9121ac6c4dde98356f48acba2642', 'aaaaa', 'bbbbb');
        // insert into users (userid, lastmessage, allmessages) values('U5e8f9121ac6c4dde98356f48acba2642', 'aaaaa', 'bbbbb') on conflict on constraint users_pkey do update set lastmessage = 'ccccc', allmessages = 'ddddd';
      }
      $sthAdd = $dbh->prepare($sqlAdd);
      $sthAdd->execute(array($event->getUserId(), $event->getText(), json_encode(array($event->getText()))));
      */

    }
    continue;
  }
}

class dbConnection {
  protected static $db;
  private function __construct() {

    try {
      $url = parse_url(getenv('DATABASE_URL'));
      $dsn = sprintf('pgsql:host=%s;dbname=%s', $url['host'], substr($url['path'], 1));
      self::$db = new PDO($dsn, $url['user'], $url['pass']);
      self::$db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
    }
    catch (PDOException $e) {
      echo "Connection Error: " . $e->getMessage();
    }
  }

  public static function getConnection() {
    if (!self::$db) {
      new dbConnection();
    }
    return self::$db;
  }
}

?>
