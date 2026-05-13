<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Класс для перехвата писем через фильтр wp_mail
 */
class VK_Notifier_Email_Interceptor {
	private $logger;
	private $options;
	private $vk_api;
	private $message_builder;

	public function __construct( $logger ) {
		$this->logger  = $logger;
		$this->options = get_option( 'vk_notifier_options', array() );
		add_filter( 'wp_mail', array( $this, 'intercept_mail' ), 10, 1 );
		add_action( 'vk_notifier_async_send', array( $this, 'handle_async_send' ), 10, 1 );
	}

	/**
	 * Перехват письма.
	 *
	 * @param array $args Аргументы wp_mail.
	 * @return array
	 */
	public function intercept_mail( $args ) {
		$this->options = get_option( 'vk_notifier_options', array() );

		if ( empty( $this->options['token'] ) || empty( $this->options['recipients'] ) ) {
			return $args;
		}

		if ( ! $this->should_forward( isset( $args['to'] ) ? $args['to'] : array() ) ) {
			return $args;
		}

		$send_mode = isset( $this->options['send_mode'] ) ? $this->options['send_mode'] : 'instant';

		if ( 'cron' === $send_mode ) {
			$this->schedule_forward( $args );
		} else {
			$this->handle_async_send( $this->normalize_email_payload( $args ) );
		}


		return $args;
	}

	public function handle_async_send( $email_data ) {
		$this->options = get_option( 'vk_notifier_options', array() );

		if ( empty( $this->options['token'] ) || empty( $this->options['recipients'] ) ) {
			return;
		}

		if ( ! is_array( $email_data ) ) {
			return;
		}

		$this->message_builder = new VK_Notifier_Message_Builder();
		$vk_message           = $this->message_builder->build( $email_data );
		$this->send_to_vk( $vk_message, $email_data );
	}

	/**
	 * Проверка, нужно ли пересылать письмо на основе белого списка email.
	 *
	 * @param string|array $to Адрес(а) получателя.
	 * @return bool
	 */
	private function should_forward( $to ) {
		$forward_mode = isset( $this->options['forward_mode'] ) ? $this->options['forward_mode'] : 'whitelist';
		if ( 'all' === $forward_mode ) {
			return true;
		}

		$whitelist = $this->normalize_email_list( isset( $this->options['email_whitelist'] ) ? $this->options['email_whitelist'] : '' );

		if ( empty( $whitelist ) ) {
			return false;
		}

		$recipients = is_array( $to ) ? $to : preg_split( '/[,;]/', (string) $to );
		$normalized = array();

		foreach ( $recipients as $recipient ) {
			$email = $this->extract_email( $recipient );
			if ( $email ) {
				$normalized[] = $email;
			}
		}

		return (bool) array_intersect( $normalized, $whitelist );
	}

	/**
	 * Отправка в ВК с учётом списка получателей.
	 *
	 * @param string $vk_message      Сообщение для VK.
	 * @param array  $original_email  Оригинальные аргументы письма.
	 */
	private function send_to_vk( $vk_message, $original_email ) {
		$recipients_raw = isset( $this->options['recipients'] ) ? $this->options['recipients'] : '';
		if ( empty( $recipients_raw ) ) {
			return;
		}

		$recipients = array_filter( array_map( 'trim', explode( ',', $recipients_raw ) ) );
		$group_id   = isset( $this->options['group_id'] ) ? absint( $this->options['group_id'] ) : null;

		$this->vk_api = new VK_Notifier_VK_API( $this->options['token'] );

		foreach ( $recipients as $recipient ) {
			$prepared = $this->vk_api->prepare_recipient( $recipient );

			if ( isset( $prepared['error'] ) ) {
				$this->maybe_log(
					'error',
					'Некорректный получатель VK: ' . $recipient . '. ' . $prepared['error_msg'],
					array(
						'recipient'  => $recipient,
						'error_code' => isset( $prepared['error_code'] ) ? $prepared['error_code'] : '',
					),
					'validation'
				);
				continue;
			}

			$result = $this->vk_api->send_message( $recipient, $vk_message, $group_id );

			if ( true === $result ) {
				$this->maybe_log(
					'success',
					'Сообщение успешно отправлено получателю ' . $recipient,
					array(
						'recipient' => (int) $recipient,
						'type'      => $prepared['type'],
						'subject'   => isset( $original_email['subject'] ) ? $original_email['subject'] : '',
						'to'        => isset( $original_email['to'] ) ? $original_email['to'] : '',
						'send_mode' => isset( $this->options['send_mode'] ) ? $this->options['send_mode'] : 'instant',
					),
					'messages.send'
				);
				continue;
			}

			$error_msg  = is_array( $result ) && isset( $result['error_msg'] ) ? $result['error_msg'] : 'Неизвестная ошибка';
			$error_code = is_array( $result ) && isset( $result['error_code'] ) ? $result['error_code'] : '';

			$this->maybe_log(
				'error',
				'Ошибка отправки получателю ' . $recipient . ': [' . $error_code . '] ' . $error_msg,
				array(
					'recipient'  => $recipient,
					'type'       => $prepared['type'],
					'api_error'  => $result,
					'send_mode'  => isset( $this->options['send_mode'] ) ? $this->options['send_mode'] : 'instant',
				),
				'messages.send'
			);
		}
	}

	private function schedule_forward( $args ) {
		$payload = $this->normalize_email_payload( $args );

		$this->maybe_log(
			'info',
			'Сообщение поставлено в очередь WP-Cron для отправки в VK.',
			array(
				'queue'     => 'wp_cron',
				'send_mode' => 'cron',
				'subject'   => isset( $payload['subject'] ) ? $payload['subject'] : '',
			),
			'email_intercept'
		);

		$scheduled = wp_schedule_single_event( time(), 'vk_notifier_async_send', array( $payload ) );

		if ( false === $scheduled ) {
			$this->maybe_log(
				'error',
				'Не удалось поставить сообщение в очередь на отправку в VK.',
				array(
					'queue' => 'wp_cron',
				),
				'email_intercept'
			);
		}
	}

	/**
	 * Логирование с учётом уровня, установленного в настройках.
	 */
	private function maybe_log( $level, $message, $details = array(), $event = '' ) {
		if ( ! $this->logger ) {
			return;
		}

		$log_level_setting = isset( $this->options['log_level'] ) ? $this->options['log_level'] : 'all';

		if ( 'all' === $log_level_setting ) {
			$this->logger->add( $level, $message, $details, $event );
		} elseif ( 'error' === $log_level_setting && 'error' === $level ) {
			$this->logger->add( $level, $message, $details, $event );
		} elseif ( 'success' === $log_level_setting && 'success' === $level ) {
			$this->logger->add( $level, $message, $details, $event );
		} elseif ( 'info' === $log_level_setting && 'info' === $level ) {
			$this->logger->add( $level, $message, $details, $event );
		}
	}

	/**
	 * Нормализует список email.
	 *
	 * @param string $emails Строка email через запятую.
	 * @return array
	 */
	private function normalize_email_list( $emails ) {
		$items  = preg_split( '/[,;\n\r]+/', (string) $emails );
		$result = array();

		foreach ( $items as $item ) {
			$email = $this->extract_email( $item );
			if ( $email ) {
				$result[] = $email;
			}
		}

		return array_values( array_unique( $result ) );
	}

	/**
	 * Извлекает email из строки вида Name <mail@example.com>.
	 *
	 * @param string $value Исходная строка.
	 * @return string
	 */
	private function extract_email( $value ) {
		$value = trim( (string) $value );
		if ( preg_match( '/<([^>]+)>/', $value, $matches ) ) {
			$value = $matches[1];
		}

		$value = strtolower( trim( $value ) );
		$email = sanitize_email( $value );

		return is_email( $email ) ? $email : '';
	}

	private function normalize_email_payload( $args ) {
		$payload = is_array( $args ) ? $args : array();

		return array(
			'to'          => isset( $payload['to'] ) ? $payload['to'] : array(),
			'subject'     => isset( $payload['subject'] ) ? (string) $payload['subject'] : '',
			'message'     => isset( $payload['message'] ) ? (string) $payload['message'] : '',
			'attachments' => isset( $payload['attachments'] ) && is_array( $payload['attachments'] ) ? $payload['attachments'] : array(),
		);
	}
}
