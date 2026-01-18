<?php

class JWT
{
  private static $secret;

  public static function init($secret) // записываем значение секретного ключа в это статическое поле
  {
    self::$secret = $secret;
  }
  // идея в конфиге приложения один раз вызывается JWT::init('секрет_проекта'), а дальше encode/decode уже знают, каким ключом подписывать и проверять токены.


  // ссли секрет ещё не был задан через init выбрасывается исключение - без него нельзя безопасно подписать токен
  public static function encode($payload)
  {
    if (!self::$secret) {
      throw new Exception("JWT secret not initialized. Call JWT::init() first.");
    }

    // ормируется заголовок JWT: тип JWT и алгоритм подписи HS256
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload = array_merge($payload, [
      'iat' => time(), //iat — время выпуска токена (issued at)
      'exp' => time() + (60 * 60 * 24) // exp — время истечения. 24 часа 
    ]);

    // заголовок и нагрузка кодируются в base64URL - специальный вариант base64 он используется в URL/HTTP (замена символов + и /, убирание =).
    $base64UrlHeader = self::base64UrlEncode($header);
    $base64UrlPayload = self::base64UrlEncode(json_encode($payload));

    // считается HMAC‑подпись по строке "header.payload" с использованием секретного ключа и алгоритма sha256
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, self::$secret, true);
    $base64UrlSignature = self::base64UrlEncode($signature);

    // метод возвращает строку вида header.payload.signature - это и есть готовый JWT‑токен который можно записать в cookie\localStorage или заголовок authorization
    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
  }

  public static function decode($token)
  {
    // без инициализированного секрета разбирать токен нельзя поэтому выбрасывается исключение
    if (!self::$secret) {
      throw new Exception("JWT secret not initialized. Call JWT::init() first.");
    }

    // токен режется по точкам на три части
    $parts = explode('.', $token);

    if (count($parts) != 3) {
      throw new Exception("Некорректный формат токена");
    }

    list($header, $payload, $signature) = $parts;

    // проверяем подпись
    $validSignature = hash_hmac('sha256', $header . "." . $payload, self::$secret, true);
    $validSignatureBase64 = self::base64UrlEncode($validSignature);

    // если подпись не совпала то токен либо подделан либо повреждён тогда выбрасывается исключение «Неверная подпись токена»
    if (!hash_equals($signature, $validSignatureBase64)) {
      throw new Exception("Неверная подпись токена");
    }

    // нагрузка декодируется из Base64URL в JSON‑строку, а потом из JSON в PHP‑массив
    $decodedPayload = json_decode(self::base64UrlDecode($payload), true);

    // проверяем срок действия
    if (isset($decodedPayload['exp']) && $decodedPayload['exp'] < time()) {
      throw new Exception("Срок действия токена истек");
    }

    // если всё ок возвращается массив с данными (user_id, email, role, iat, exp и тп.) и вызывающий код может по ним найти пользователя в базе
    return $decodedPayload;
  }
  // просто проверить "живой" ли токен
  public static function validate($token)
  {
    try {
      self::decode($token);
      return true;
    } catch (Exception $e) {
      return false;
    }
  }

  // Обычное base64_encode но затем: 
  // "+" заменяется на "-"
  // "/" на "_" (чтобы строка была дружелюбна к URL). 
  // убираются символы "=" в конце, которые в JWT не требуются
  private static function base64UrlEncode($data)
  {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
  }
  // обратная операция заменяет 
// "-" на "+"
//  "_" на "/" 
// декодирует обычным base64_decode
  private static function base64UrlDecode($data)
  {
    return base64_decode(strtr($data, '-_', '+/'));
  }
}
?>