<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Класс для построения текста сообщения из email
 */
class VK_Notifier_Message_Builder {

    private $options;

    public function __construct() {
        $this->options = get_option( 'vk_notifier_options', array() );
    }

    /**
     * Формирует текст для отправки в ВК на основе данных письма
     *
     * @param array $email_data Массив с ключами: 'to', 'subject', 'message', 'headers', 'attachments'
     * @return string
     */
	public function build( $email_data ) {
		$prefix = isset( $this->options['message_prefix'] ) ? $this->options['message_prefix'] : '📧 Сообщение с сайта';
		$attachment_notice = isset( $this->options['attachment_notice'] ) ? $this->options['attachment_notice'] : '⚠️ Сообщение содержит вложение. Проверьте почту или раздел заявок на сайте.';
		$subject           = isset( $email_data['subject'] ) ? $email_data['subject'] : '';
		$message           = isset( $email_data['message'] ) ? $email_data['message'] : '';
		$attachments       = isset( $email_data['attachments'] ) ? $email_data['attachments'] : array();

		// Очищаем тему и тело письма от HTML (если есть)
		$subject = $this->clean_text( $subject );
		$message = $this->clean_text( $message );

        $output = $prefix . "\n\n";
        $output .= "📌 Тема: " . $subject . "\n\n";
        $output .= "📝 Текст:\n" . $message;

        // Если есть вложения, добавляем уведомление
		if ( ! empty( $attachments ) && is_array( $attachments ) ) {
			$output .= "\n\n" . $attachment_notice;
		}

        // Обрезаем сообщение, если оно превышает лимит VK (4096 символов)
        // Оставляем запас 6 символов для многоточия
        $max_length = 4090;
		if ( $this->get_text_length( $output ) > $max_length ) {
			$output = $this->truncate_text( $output, $max_length ) . '…';
		}

        return $output;
    }

    /**
     * Очищает текст от HTML-тегов и лишних пробелов
     *
     * @param string $text
     * @return string
     */
	private function clean_text( $text ) {
        // Сначала заменяем блочные теги на переносы строк
        $text = preg_replace( '/<br\s*\/?>/i', "\n", $text );
        $text = preg_replace( '/<\/p>/i', "\n\n", $text );
        $text = preg_replace( '/<\/div>/i', "\n", $text );
        $text = preg_replace( '/<\/li>/i', "\n", $text );

        // Теперь удаляем оставшиеся теги
        $text = wp_strip_all_tags( $text );

        // Преобразуем HTML-сущности
        $text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );

        // Убираем лишние пробелы (но не переносы строк)
        $text = preg_replace( '/[ \t]+/', ' ', $text );
		$text = preg_replace( "/\n\s*\n\s*\n/", "\n\n", $text );
		return trim( $text );
	}

	private function get_text_length( $text ) {
		if ( function_exists( 'mb_strlen' ) ) {
			return mb_strlen( $text );
		}

		if ( function_exists( 'iconv_strlen' ) ) {
			$length = iconv_strlen( $text, 'UTF-8' );
			if ( false !== $length ) {
				return $length;
			}
		}

		return strlen( $text );
	}

	private function truncate_text( $text, $length ) {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $text, 0, $length );
		}

		if ( function_exists( 'iconv_substr' ) ) {
			$result = iconv_substr( $text, 0, $length, 'UTF-8' );
			if ( false !== $result ) {
				return $result;
			}
		}

		return substr( $text, 0, $length );
	}
}
