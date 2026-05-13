<?php
/**
 * Plugin Name: VK Notifier
 * Plugin URI:
 * Description: Перехватывает email-уведомления WordPress и дублирует их в ВКонтакте (личные сообщения или беседы)
 * Version: 1.0.0
 * Author: StudioAVP
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: vk-notifier
 * Domain Path: /languages
 */

// Защита от прямого доступа
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Константы плагина
define( 'VK_NOTIFIER_VERSION', '1.0.0' );
define( 'VK_NOTIFIER_DB_VERSION', '1.0.0' );
define( 'VK_NOTIFIER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'VK_NOTIFIER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Автозагрузка классов (простая, без composer)
 */
spl_autoload_register( function ( $class ) {
    $prefix = 'VK_Notifier_';
    $base_dir = VK_NOTIFIER_PLUGIN_DIR . 'includes/';
    
    $len = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }
    
    $relative_class = substr( $class, $len );
    $file = $base_dir . 'class-' . str_replace( '_', '-', strtolower( $relative_class ) ) . '.php';
    
    if ( file_exists( $file ) ) {
        require $file;
    }
} );

/**
 * Класс основного плагина
 */
class VK_Notifier_Core {

    private static $instance = null;
    private $admin_settings;
    private $email_interceptor;
    private $logger;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    private function load_dependencies() {
        // Классы уже автозагружаются, но мы инициализируем их здесь при необходимости
        $this->logger = new VK_Notifier_Logger();
        $this->admin_settings = new VK_Notifier_Admin_Settings( $this->logger );
        $this->email_interceptor = new VK_Notifier_Email_Interceptor( $this->logger );
    }

    private function init_hooks() {
        add_action( 'plugins_loaded', array( $this, 'maybe_upgrade' ) );
        add_action( 'vk_notifier_cleanup_logs', array( $this, 'cleanup_logs' ) );
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
    }

    public function activate() {
        // Создаём таблицу логов
        $this->logger->create_table();
        update_option( 'vk_notifier_db_version', VK_NOTIFIER_DB_VERSION );
        // Устанавливаем опции по умолчанию
        add_option( 'vk_notifier_options', $this->get_default_options() );
        $this->ensure_default_options();
        $this->schedule_cleanup_logs();
    }

    public function deactivate() {
        // Опционально: очистка временных данных, но таблицу логов не удаляем (можно удалить при деинсталляции)
        wp_clear_scheduled_hook( 'vk_notifier_cleanup_logs' );
    }

    public function maybe_upgrade() {
        $this->ensure_default_options();

        if ( VK_NOTIFIER_DB_VERSION !== get_option( 'vk_notifier_db_version', '' ) ) {
            $this->logger->create_table();
            update_option( 'vk_notifier_db_version', VK_NOTIFIER_DB_VERSION );
        }

        $this->schedule_cleanup_logs();
    }

    public function cleanup_logs() {
        $options = get_option( 'vk_notifier_options', array() );
        $days    = isset( $options['log_retention_days'] ) ? absint( $options['log_retention_days'] ) : 30;

        $this->logger->cleanup_old_logs( $days );
    }

    private function schedule_cleanup_logs() {
        if ( ! wp_next_scheduled( 'vk_notifier_cleanup_logs' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'vk_notifier_cleanup_logs' );
        }
    }

    private function ensure_default_options() {
        $options = get_option( 'vk_notifier_options', array() );
        if ( ! is_array( $options ) ) {
            $options = array();
        }

        $merged = array_merge( $this->get_default_options(), $options );
        if ( $merged !== $options ) {
            update_option( 'vk_notifier_options', $merged );
        }
    }

    private function get_default_options() {
        return array(
            'token'               => '',
            'group_id'            => '',
            'recipients'          => '',
            'forward_mode'        => 'whitelist',
            'email_whitelist'     => '',
            'message_prefix'      => '📧 Сообщение с сайта ' . get_bloginfo( 'name' ),
            'attachment_notice'   => '⚠️ Сообщение содержит вложение. Проверьте почту или раздел заявок на сайте.',
            'log_level'           => 'all', // all, errors, success, info
            'log_retention_days'  => 30,
            'send_mode'           => 'instant', // instant, cron
        );
    }
}

// Запускаем плагин
VK_Notifier_Core::get_instance();
