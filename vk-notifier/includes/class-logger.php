<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Класс логирования
 */
class VK_Notifier_Logger {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'vk_notifier_logs';
    }

    /**
     * Создание таблицы при активации
     */
    public function create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            level varchar(20) NOT NULL,
            event varchar(50) NOT NULL DEFAULT '',
            message text NOT NULL,
            details text,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Добавление записи в лог
     *
     * @param string $level   error, success, info
     * @param string $message текст лога
     * @param array  $details дополнительные данные (опционально)
     * @param string $event   тип события (опционально)
     */
    public function add( $level, $message, $details = array(), $event = '' ) {
        global $wpdb;
        $details_json = ! empty( $details ) ? wp_json_encode( $details ) : '';
        $event        = substr( preg_replace( '/[^a-zA-Z0-9_.-]/', '', (string) $event ), 0, 50 );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert(
            $this->table_name,
            array(
                'level'   => $level,
                'event'   => $event,
                'message' => $message,
                'details' => $details_json,
            ),
            array( '%s', '%s', '%s', '%s' )
        );
    }

    /**
     * Получить логи с возможностью фильтрации по уровню
     *
     * @param string $level all, error, success, info
     * @param int    $limit
     * @return array
     */
    public function get_logs( $level = 'all', $limit = 100 ) {
        global $wpdb;

        if ( $level !== 'all' ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            return $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT * FROM `' . esc_sql( $this->table_name ) . '` WHERE level = %s ORDER BY id DESC LIMIT %d',
                    $level,
                    absint( $limit )
                )
            );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM `' . esc_sql( $this->table_name ) . '` ORDER BY id DESC LIMIT %d',
                absint( $limit )
            )
        );
    }

    /**
     * Очистить все логи
     */
    public function clear_logs() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
        $wpdb->query( 'TRUNCATE TABLE `' . esc_sql( $this->table_name ) . '`' );
    }

    /**
     * Удалить старые логи по сроку хранения.
     *
     * @param int $days Количество дней хранения. 0 — без ограничений.
     * @return int|false
     */
    public function cleanup_old_logs( $days ) {
        global $wpdb;
        $days = absint( $days );

        if ( 0 === $days ) {
            return 0;
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $deleted = $wpdb->query(
            $wpdb->prepare(
                'DELETE FROM `' . esc_sql( $this->table_name ) . '` WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)',
                $days
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        return $deleted;
    }
}
