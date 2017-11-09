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
      else {
        $sql = 'insert into ' . TABLE_NAME_USERS . ' (userid, lastmessage) values(?, ?) on conflict on constraint users_pkey do update set lastmessage = ?';
        $sth = $dbh->prepare($sql);
        $sth->execute(array($event->getUserId(), $event->getText(), $event->getText()));

        $bot->replyMessage($event->getReplyToken(), new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('saved'));
      }
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
