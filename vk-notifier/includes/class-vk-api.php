<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Класс для отправки сообщений через API ВКонтакте
 */
class VK_Notifier_VK_API {
	private $access_token;
	private $api_version = '5.199';

	public function __construct( $token ) {
		$this->access_token = $token;
	}

	/**
	 * Базовый запрос к API VK.
	 *
	 * @param string $method Метод VK API.
	 * @param array  $params Параметры запроса.
	 * @param string $http_method GET|POST.
	 * @return array
	 */
	public function api_request( $method, $params = array(), $http_method = 'POST' ) {
		$url             = 'https://api.vk.com/method/' . ltrim( $method, '/' );
		$params['access_token'] = $this->access_token;
		$params['v']            = $this->api_version;

		$args = array(
			'timeout' => 15,
		);

		if ( 'GET' === strtoupper( $http_method ) ) {
			$response = wp_remote_get( add_query_arg( $params, $url ), $args );
		} else {
			$args['body'] = $params;
			$response     = wp_remote_post( $url, $args );
		}

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'    => false,
				'error' => array(
					'error_code' => 'http_request_failed',
					'error_msg'  => $response->get_error_message(),
				),
			);
		}

		$http_code = (int) wp_remote_retrieve_response_code( $response );
		$body      = wp_remote_retrieve_body( $response );
		$data      = json_decode( $body, true );

		if ( $http_code < 200 || $http_code >= 300 ) {
			return array(
				'ok'    => false,
				'error' => array(
					'error_code' => $http_code,
					'error_msg'  => 'VK API вернул HTTP ' . $http_code,
					'http_code'  => $http_code,
					'raw_body'   => $body,
				),
			);
		}

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			return array(
				'ok'    => false,
				'error' => array(
					'error_code' => 'invalid_json',
					'error_msg'  => 'VK API вернул некорректный JSON.',
					'raw_body'   => $body,
				),
			);
		}

		if ( isset( $data['error'] ) && is_array( $data['error'] ) ) {
			return array(
				'ok'    => false,
				'error' => $data['error'],
				'raw'   => $data,
			);
		}

		if ( ! array_key_exists( 'response', $data ) ) {
			return array(
				'ok'    => false,
				'error' => array(
					'error_code' => 'missing_response',
					'error_msg'  => 'VK API не вернул поле response.',
					'raw_body'   => $body,
				),
				'raw'   => $data,
			);
		}

		return array(
			'ok'       => true,
			'response' => $data['response'],
			'raw'      => $data,
		);
	}

	/**
	 * Отправка сообщения одному получателю.
	 *
	 * @param int|string $recipient ID пользователя или peer_id беседы.
	 * @param string     $message   Текст сообщения.
	 * @param int|null   $group_id  ID сообщества для токена сообщества.
	 * @return true|array
	 */
	public function send_message( $recipient, $message, $group_id = null ) {
		$prepared_recipient = $this->prepare_recipient( $recipient );

		if ( isset( $prepared_recipient['error'] ) ) {
			return $prepared_recipient;
		}

		$random_id = abs( crc32( uniqid( '', true ) ) );

		$params = array(
			'random_id' => $random_id,
			'message'   => $message,
		);

		if ( ! empty( $group_id ) ) {
			$params['group_id'] = absint( $group_id );
		}

		$params[ $prepared_recipient['key'] ] = $prepared_recipient['value'];

		$result = $this->api_request( 'messages.send', $params, 'POST' );

		if ( ! $result['ok'] ) {
			return $result['error'];
		}

		return true;
	}

	/**
	 * Подготовка и валидация получателя.
	 *
	 * @param int|string $recipient ID получателя.
	 * @return array
	 */
	public function prepare_recipient( $recipient ) {
		if ( ! is_numeric( $recipient ) ) {
			return array(
				'error'      => true,
				'error_code' => 'invalid_recipient',
				'error_msg'  => 'Некорректный ID получателя. Разрешены только числа.',
			);
		}

		$recipient = (int) trim( (string) $recipient );

		if ( 0 === $recipient ) {
			return array(
				'error'      => true,
				'error_code' => 'invalid_recipient',
				'error_msg'  => 'ID получателя не может быть равен 0.',
			);
		}

		if ( $recipient < 0 ) {
			return array(
				'error'      => true,
				'error_code' => 'community_id_not_supported',
				'error_msg'  => 'Указан отрицательный ID. Для токена сообщества это обычно ID сообщества, а не адресат. Используйте положительный user_id или peer_id беседы вида 2000000xxx.',
				'recipient'  => $recipient,
			);
		}

		if ( $recipient >= 2000000000 ) {
			return array(
				'key'   => 'peer_id',
				'value' => $recipient,
				'type'  => 'chat',
			);
		}

		return array(
			'key'   => 'user_id',
			'value' => $recipient,
			'type'  => 'user',
		);
	}

	/**
	 * Получение списка бесед, доступных токену.
	 *
	 * @param int|null $group_id ID сообщества, если используется токен сообщества.
	 * @param int      $count    Количество бесед.
	 * @return array
	 */
	public function get_conversations( $group_id = null, $count = 20 ) {
		$params = array(
			'count'  => max( 1, min( 50, (int) $count ) ),
			'filter' => 'all',
		);

		if ( ! empty( $group_id ) ) {
			$params['group_id'] = absint( $group_id );
		}

		return $this->api_request( 'messages.getConversations', $params, 'POST' );
	}

	/**
	 * Получение информации по peer_id.
	 *
	 * @param array|string $peer_ids peer_id через запятую или массив.
	 * @param int|null     $group_id ID сообщества.
	 * @return array
	 */
	public function get_conversations_by_id( $peer_ids, $group_id = null ) {
		if ( is_array( $peer_ids ) ) {
			$peer_ids = implode( ',', array_map( 'intval', $peer_ids ) );
		}

		$params = array(
			'peer_ids' => (string) $peer_ids,
		);

		if ( ! empty( $group_id ) ) {
			$params['group_id'] = absint( $group_id );
		}

		return $this->api_request( 'messages.getConversationsById', $params, 'POST' );
	}

	/**
	 * Проверка токена.
	 *
	 * @return bool
	 */
	public function check_token() {
		$result = $this->api_request( 'account.getInfo', array(), 'GET' );
		return ! empty( $result['ok'] );
	}
}
