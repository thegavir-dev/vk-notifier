<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Страница настроек в админке
 */
class VK_Notifier_Admin_Settings {
	private $logger;
	private $options;

	public function __construct( $logger ) {
		$this->logger  = $logger;
		$this->options = get_option( 'vk_notifier_options', array() );

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_vk_notifier_test', array( $this, 'ajax_test_message' ) );
		add_action( 'wp_ajax_vk_notifier_clear_logs', array( $this, 'ajax_clear_logs' ) );
		add_action( 'wp_ajax_vk_notifier_find_conversations', array( $this, 'ajax_find_conversations' ) );
	}

	public function add_admin_menu() {
		add_options_page(
			'VK Notifier',
			'VK Notifier',
			'manage_options',
			'vk-notifier',
			array( $this, 'render_settings_page' )
		);

		add_options_page(
			'Логи VK Notifier',
			'VK Notifier Logs',
			'manage_options',
			'vk-notifier-logs',
			array( $this, 'render_logs_page' )
		);

		add_options_page(
			'Помощь VK Notifier',
			'VK Notifier Help',
			'manage_options',
			'vk-notifier-help',
			array( $this, 'render_help_page' )
		);
	}

	public function register_settings() {
		register_setting( 'vk_notifier_settings', 'vk_notifier_options', array( $this, 'sanitize_options' ) );

		add_settings_section( 'vk_notifier_main', 'Основные настройки', null, 'vk-notifier' );

		add_settings_field( 'vk_token', 'Токен VK', array( $this, 'field_token' ), 'vk-notifier', 'vk_notifier_main' );
		add_settings_field( 'vk_group_id', 'ID сообщества VK', array( $this, 'field_group_id' ), 'vk-notifier', 'vk_notifier_main' );
		add_settings_field( 'vk_recipients', 'Получатели VK', array( $this, 'field_recipients' ), 'vk-notifier', 'vk_notifier_main' );
		add_settings_field( 'vk_forward_mode', 'Пересылать', array( $this, 'field_forward_mode' ), 'vk-notifier', 'vk_notifier_main' );
		add_settings_field( 'vk_email_whitelist', 'Белый список email (для пересылки)', array( $this, 'field_email_whitelist' ), 'vk-notifier', 'vk_notifier_main' );
		add_settings_field( 'vk_message_prefix', 'Префикс сообщения', array( $this, 'field_message_prefix' ), 'vk-notifier', 'vk_notifier_main' );
		add_settings_field( 'vk_attachment_notice', 'Текст о вложениях', array( $this, 'field_attachment_notice' ), 'vk-notifier', 'vk_notifier_main' );
		add_settings_field( 'vk_log_level', 'Уровень логирования', array( $this, 'field_log_level' ), 'vk-notifier', 'vk_notifier_main' );
		add_settings_field( 'vk_log_retention_days', 'Хранить логи (дней)', array( $this, 'field_log_retention_days' ), 'vk-notifier', 'vk_notifier_main' );
		add_settings_field( 'vk_send_mode', 'Режим отправки уведомлений', array( $this, 'field_send_mode' ), 'vk-notifier', 'vk_notifier_main' );
	}

	public function field_token() {
		$value = isset( $this->options['token'] ) ? $this->options['token'] : '';
		echo '<input type="text" name="vk_notifier_options[token]" value="' . esc_attr( $value ) . '" class="regular-text">';
		echo '<p class="description">Токен доступа VK (сообщества или пользователя). <a href="' . esc_url( admin_url( 'options-general.php?page=vk-notifier-help' ) ) . '">Как получить?</a></p>';
	}

	public function field_group_id() {
		$value = isset( $this->options['group_id'] ) ? $this->options['group_id'] : '';
		echo '<input type="text" name="vk_notifier_options[group_id]" value="' . esc_attr( $value ) . '" class="regular-text">';
		echo '<p class="description">ID сообщества: положительное число, например 123456789. Обязательно для токенов сообщества.</p>';
	}

	public function field_recipients() {
		$value = isset( $this->options['recipients'] ) ? $this->options['recipients'] : '';
		echo '<input type="text" name="vk_notifier_options[recipients]" value="' . esc_attr( $value ) . '" class="regular-text">';
		echo '<p class="description">ID пользователей или положительные peer_id бесед через запятую. Для бесед используйте peer_id из кнопки «Найти доступные беседы сообщества». Отрицательные ID не поддерживаются.</p>';
	}

	public function field_email_whitelist() {
		$value = isset( $this->options['email_whitelist'] ) ? $this->options['email_whitelist'] : '';
		echo '<input type="text" name="vk_notifier_options[email_whitelist]" value="' . esc_attr( $value ) . '" class="regular-text" id="vk-notifier-email-whitelist">';
		echo '<p class="description">Email-адреса, письма на которые нужно дублировать в VK. Несколько адресов через запятую.</p>';
	}

	public function field_forward_mode() {
		$selected = isset( $this->options['forward_mode'] ) ? $this->options['forward_mode'] : 'whitelist';

		echo '<fieldset>';
		echo '<label style="display:block; margin-bottom:10px;">';
		echo '<input type="radio" name="vk_notifier_options[forward_mode]" value="whitelist" ' . checked( $selected, 'whitelist', false ) . '> ';
		echo '<strong>Только письма из белого списка</strong>';
		echo '<div class="description" style="margin:4px 0 0 24px;">Текущее совместимое поведение: письма пересылаются только при совпадении email получателя с белым списком.</div>';
		echo '</label>';
		echo '<label style="display:block; margin-bottom:10px;">';
		echo '<input type="radio" name="vk_notifier_options[forward_mode]" value="all" ' . checked( $selected, 'all', false ) . '> ';
		echo '<strong>Все письма</strong>';
		echo '<div class="description" style="margin:4px 0 0 24px;">Дублировать все перехваченные письма сайта в VK, без проверки белого списка.</div>';
		echo '</label>';
		echo '</fieldset>';
	}

	public function field_message_prefix() {
		$value = isset( $this->options['message_prefix'] ) ? $this->options['message_prefix'] : '';
		echo '<input type="text" name="vk_notifier_options[message_prefix]" value="' . esc_attr( $value ) . '" class="regular-text">';
		echo '<p class="description">Текст, который будет добавлен в начало сообщения.</p>';
	}

	public function field_attachment_notice() {
		$value = isset( $this->options['attachment_notice'] ) ? $this->options['attachment_notice'] : '';
		echo '<input type="text" name="vk_notifier_options[attachment_notice]" value="' . esc_attr( $value ) . '" class="regular-text">';
		echo '<p class="description">Текст, добавляемый если в письме есть вложения.</p>';
	}


	public function field_send_mode() {
		$selected = isset( $this->options['send_mode'] ) ? $this->options['send_mode'] : 'instant';
		$cron_enabled = $this->is_wp_cron_enabled();

		echo '<fieldset>';
		echo '<label style="display:block; margin-bottom:10px;">';
		echo '<input type="radio" name="vk_notifier_options[send_mode]" value="instant" ' . checked( $selected, 'instant', false ) . '> ';
		echo '<strong>Сразу</strong>';
		echo '<div class="description" style="margin:4px 0 0 24px;">Отправлять уведомление в VK сразу при обработке email. Подходит для большинства сайтов.</div>';
		echo '</label>';
		echo '<label style="display:block; margin-bottom:10px;">';
		echo '<input type="radio" name="vk_notifier_options[send_mode]" value="cron" ' . checked( $selected, 'cron', false ) . '> ';
		echo '<strong>Через WP-Cron</strong>';
		echo '<div class="description" style="margin:4px 0 0 24px;">Ставить отправку уведомления в очередь WordPress и выполнять её фоново. На сайтах с нестабильным WP-Cron уведомления могут приходить с задержкой.</div>';
		echo '</label>';
		echo '</fieldset>';
		echo '<p class="description">Тестовая отправка всегда выполняется сразу, независимо от выбранного режима.</p>';
		echo '<div class="vk-notifier-cron-status-wrap">';
		echo '<span class="vk-notifier-cron-label">Статус WP-Cron:</span> ';
		echo '<span class="vk-notifier-status-badge ' . ( $cron_enabled ? 'vk-notifier-status-enabled' : 'vk-notifier-status-disabled' ) . '">';
		echo esc_html( $cron_enabled ? 'WP-Cron активен' : 'WP-Cron отключён в конфигурации WordPress' );
		echo '</span>';
		echo '</div>';
		if ( ! $cron_enabled ) {
			echo '<p class="description">Если WP-Cron отключён, рекомендуется использовать режим <strong>«Сразу»</strong>.</p>';
		}
	}

	public function field_log_level() {
		$levels = array(
			'all'     => 'Все',
			'error'   => 'Только ошибки',
			'success' => 'Только успешные',
			'info'    => 'Только информационные',
		);
		$selected = isset( $this->options['log_level'] ) ? $this->options['log_level'] : 'all';

		echo '<select name="vk_notifier_options[log_level]">';
		foreach ( $levels as $val => $label ) {
			echo '<option value="' . esc_attr( $val ) . '" ' . selected( $selected, $val, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">Какие события записывать в лог.</p>';
	}

	public function field_log_retention_days() {
		$value = isset( $this->options['log_retention_days'] ) ? absint( $this->options['log_retention_days'] ) : 30;
		echo '<input type="number" min="0" step="1" name="vk_notifier_options[log_retention_days]" value="' . esc_attr( $value ) . '" class="small-text">';
		echo '<p class="description">Количество дней хранения логов. Значение 0 отключает автоматическую ротацию.</p>';
	}

	public function sanitize_options( $input ) {
		$sanitized                      = array();
		$sanitized['token']             = isset( $input['token'] ) ? sanitize_text_field( $input['token'] ) : '';
		$sanitized['group_id']          = isset( $input['group_id'] ) ? (string) absint( $input['group_id'] ) : '';
		$sanitized['recipients']        = $this->sanitize_recipients( isset( $input['recipients'] ) ? $input['recipients'] : '' );
		$sanitized['forward_mode']      = isset( $input['forward_mode'] ) && in_array( $input['forward_mode'], array( 'all', 'whitelist' ), true ) ? $input['forward_mode'] : 'whitelist';
		$sanitized['email_whitelist']   = $this->sanitize_email_whitelist( isset( $input['email_whitelist'] ) ? $input['email_whitelist'] : '' );
		$sanitized['message_prefix']    = isset( $input['message_prefix'] ) ? sanitize_text_field( $input['message_prefix'] ) : '';
		$sanitized['attachment_notice'] = isset( $input['attachment_notice'] ) ? sanitize_text_field( $input['attachment_notice'] ) : '';
		$sanitized['log_level']         = isset( $input['log_level'] ) && in_array( $input['log_level'], array( 'all', 'error', 'success', 'info' ), true ) ? $input['log_level'] : 'all';
		$sanitized['log_retention_days'] = isset( $input['log_retention_days'] ) ? max( 0, absint( $input['log_retention_days'] ) ) : 30;
		$sanitized['send_mode']         = isset( $input['send_mode'] ) && in_array( $input['send_mode'], array( 'instant', 'cron' ), true ) ? $input['send_mode'] : 'instant';

		return $sanitized;
	}

	public function render_settings_page() {
		?>
		<div class="wrap vk-notifier-admin-page">
			<?php $this->render_admin_styles(); ?>
			<h1>VK Notifier – настройки</h1>
			<?php if ( isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible">
					<p>Настройки сохранены.</p>
				</div>
			<?php endif; ?>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'vk_notifier_settings' );
				do_settings_sections( 'vk-notifier' );
				submit_button();
				?>
			</form>
			<hr>
			<h2>Тестирование</h2>
			<p>Для беседы используйте только положительный peer_id. Для токена сообщества отрицательные ID приводят к неверной отправке.</p>
			<p>Если чат не принимает сообщение, нажмите кнопку ниже: плагин запросит у VK список бесед, доступных именно этому токену, и покажет правильные peer_id для настроек.</p>
			<p>
				<button id="vk-notifier-test-btn" class="button button-secondary">Отправить тестовое сообщение в ВК</button>
				<button id="vk-notifier-find-conversations" class="button" style="margin-left: 10px;">Найти доступные беседы сообщества</button>
			</p>
			<div id="vk-notifier-test-result" style="margin: 10px 0;"></div>
			<div id="vk-notifier-conversations-result" style="margin: 10px 0;"></div>
		</div>
		<?php
	}

	public function render_logs_page() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only display filters, no data modification
		$level         = isset( $_GET['log_level'] ) ? sanitize_text_field( wp_unslash( $_GET['log_level'] ) ) : 'all';
		$event_filter  = isset( $_GET['event_type'] ) ? sanitize_text_field( wp_unslash( $_GET['event_type'] ) ) : 'all';
		$search_query  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$logs          = $this->logger->get_logs( $level, 200 );
		$prepared_logs = $this->prepare_logs_for_display( $logs, $event_filter, $search_query );
		?>
		<div class="wrap vk-notifier-admin-page">
			<?php $this->render_admin_styles(); ?>
			<h1>Логи VK Notifier</h1>
			<p class="description">Здесь отображаются попытки отправки уведомлений в VK, ответы API и диагностические сообщения плагина.</p>

			<form method="get" id="vk-notifier-logs-form" class="vk-notifier-filters">
				<input type="hidden" name="page" value="vk-notifier-logs">
				<select name="log_level">
					<option value="all" <?php selected( $level, 'all' ); ?>>Все уровни</option>
					<option value="error" <?php selected( $level, 'error' ); ?>>Ошибки</option>
					<option value="success" <?php selected( $level, 'success' ); ?>>Успешно</option>
					<option value="info" <?php selected( $level, 'info' ); ?>>Инфо</option>
				</select>
				<select name="event_type">
					<option value="all" <?php selected( $event_filter, 'all' ); ?>>Все события</option>
					<option value="messages.send" <?php selected( $event_filter, 'messages.send' ); ?>>messages.send</option>
					<option value="find_conversations" <?php selected( $event_filter, 'find_conversations' ); ?>>find_conversations</option>
					<option value="email_intercept" <?php selected( $event_filter, 'email_intercept' ); ?>>email_intercept</option>
					<option value="validation" <?php selected( $event_filter, 'validation' ); ?>>validation</option>
				</select>
				<input type="search" name="s" value="<?php echo esc_attr( $search_query ); ?>" placeholder="Поиск по recipient, коду VK, тексту...">
				<button type="submit" class="button">Фильтр</button>
				<button type="button" id="vk-notifier-clear-logs" class="button" style="margin-left: 8px;">Очистить логи</button>
				<span id="vk-notifier-clear-result" style="margin-left: 10px;"></span>
			</form>

			<p class="vk-notifier-results-count">Показано записей: <?php echo esc_html( count( $prepared_logs ) ); ?></p>

			<table class="wp-list-table widefat striped vk-notifier-log-table">
				<thead>
					<tr>
						<th style="width:60px;">ID</th>
						<th style="width:160px;">Дата</th>
						<th style="width:110px;">Уровень</th>
						<th style="width:150px;">Событие</th>
						<th style="width:150px;">Получатель</th>
						<th style="width:90px;">Код VK</th>
						<th>Краткий результат</th>
						<th style="width:110px;">Детали</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $prepared_logs ) ) : ?>
						<tr><td colspan="8">Нет записей по выбранным фильтрам.</td></tr>
					<?php else : ?>
						<?php foreach ( $prepared_logs as $log ) : ?>
							<tr>
								<td><?php echo esc_html( $log['id'] ); ?></td>
								<td><?php echo esc_html( $log['timestamp'] ); ?></td>
								<td><span class="vk-notifier-badge vk-notifier-badge-<?php echo esc_attr( $log['level'] ); ?>"><?php echo esc_html( $this->get_level_label( $log['level'] ) ); ?></span></td>
								<td><code><?php echo esc_html( $log['event'] ); ?></code></td>
								<td>
									<?php if ( $log['recipient'] ) : ?>
										<code><?php echo esc_html( $log['recipient'] ); ?></code>
										<?php if ( $log['recipient_type_label'] ) : ?>
											<div class="vk-notifier-subtext"><?php echo esc_html( $log['recipient_type_label'] ); ?></div>
										<?php endif; ?>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
								<td><?php echo $log['vk_code'] ? '<code>' . esc_html( $log['vk_code'] ) . '</code>' : '—'; ?></td>
								<td>
									<strong><?php echo esc_html( $log['summary'] ); ?></strong>
									<?php if ( $log['message'] && $log['message'] !== $log['summary'] ) : ?>
										<div class="vk-notifier-subtext"><?php echo esc_html( $log['message'] ); ?></div>
									<?php endif; ?>
								</td>
								<td>
									<details class="vk-notifier-details">
										<summary>Показать</summary>
										<div class="vk-notifier-detail-box">
											<table class="widefat striped vk-notifier-mini-table">
												<tbody>
													<tr><td>Метод</td><td><code><?php echo esc_html( $log['event'] ); ?></code></td></tr>
													<tr><td>Получатель</td><td><?php echo $log['recipient'] ? '<code>' . esc_html( $log['recipient'] ) . '</code>' : '—'; ?></td></tr>
													<tr><td>Group ID</td><td><?php echo $log['group_id'] ? '<code>' . esc_html( $log['group_id'] ) . '</code>' : '—'; ?></td></tr>
													<tr><td>HTTP код</td><td><?php echo $log['http_code'] ? esc_html( $log['http_code'] ) : '—'; ?></td></tr>
													<tr><td>VK error code</td><td><?php echo $log['vk_code'] ? '<code>' . esc_html( $log['vk_code'] ) . '</code>' : '—'; ?></td></tr>
													<tr><td>VK error message</td><td><?php echo $log['vk_error_message'] ? esc_html( $log['vk_error_message'] ) : '—'; ?></td></tr>
												</tbody>
											</table>
											<?php if ( $log['hint'] ) : ?>
												<p class="vk-notifier-hint"><strong>Подсказка:</strong> <?php echo esc_html( $log['hint'] ); ?></p>
											<?php endif; ?>
											<details>
												<summary>Показать сырой JSON</summary>
												<pre><?php echo esc_html( $log['raw_json'] ); ?></pre>
											</details>
										</div>
									</details>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<div class="notice inline vk-notifier-inline-help">
				<p><strong>Частые ошибки VK:</strong> <code>901</code> — пользователь не разрешил сообщения сообществу; <code>917</code> — у сообщества нет доступа к беседе; <code>932</code> — сообщество не может взаимодействовать с этим peer. <a href="<?php echo esc_url( admin_url( 'options-general.php?page=vk-notifier-help' ) ); ?>">Открыть страницу помощи VK Notifier</a></p>
			</div>
		</div>
		<?php
	}

	public function render_help_page() {
		?>
		<div class="wrap vk-notifier-admin-page">
			<?php $this->render_admin_styles(); ?>
			<h1>Помощь по VK Notifier</h1>
			<p class="description">Краткая инструкция по настройке плагина, поиску правильного получателя и диагностике ошибок VK API.</p>

			<div class="vk-notifier-help-grid">
				<div class="vk-notifier-help-card">
					<h2>Что делает плагин</h2>
					<ul>
						<li>Перехватывает email-уведомления WordPress.</li>
						<li>Формирует текст сообщения для VK.</li>
						<li>Отправляет уведомление от имени сообщества в личку или беседу.</li>
						<li>Пишет технические события и ответы VK API в журнал.</li>
					</ul>
				</div>

				<div class="vk-notifier-help-card">
					<h2>Быстрый старт</h2>
					<ol>
						<li>Включите сообщения сообщества в настройках VK.</li>
						<li>Создайте токен сообщества с правом <code>messages</code>.</li>
						<li>Укажите <strong>Group ID</strong> без минуса, например <code>123456789</code>.</li>
						<li>Найдите доступную беседу через кнопку <strong>«Найти доступные беседы сообщества»</strong>.</li>
						<li>Сохраните <code>peer_id</code> или <code>user_id</code> в поле получателей.</li>
						<li>Выберите режим отправки: <strong>Сразу</strong> или <strong>Через WP-Cron</strong>.</li>
						<li>Выполните тестовую отправку.</li>
					</ol>
				</div>

				<div class="vk-notifier-help-card">
					<h2>Как заполнять поле «Получатели VK»</h2>
					<p><strong>Личное сообщение пользователю</strong></p>
					<p><code>123456789</code></p>
					<p><strong>Сообщение в беседу</strong></p>
					<p><code>2000000001</code></p>
					<p><strong>Несколько получателей</strong></p>
					<p><code>123456789, 2000000001</code></p>
					<p class="vk-notifier-hint">Не используйте отрицательные ID вроде <code>-123456789</code>. Для бесед берите <code>peer_id</code> из встроенной диагностики, а не из адресной строки браузера.</p>
					<p class="vk-notifier-hint">В примерах на этой странице используются условные значения. Укажите свои реальные ID из настроек VK и встроенной диагностики плагина.</p>
				</div>

				<div class="vk-notifier-help-card">
					<h2>Как найти правильный peer_id беседы</h2>
					<ol>
						<li>Откройте страницу настроек VK Notifier.</li>
						<li>Нажмите <strong>«Найти доступные беседы сообщества»</strong>.</li>
						<li>Плагин запросит у VK список бесед, доступных именно этому токену.</li>
						<li>Скопируйте значение из колонки <code>peer_id</code>.</li>
						<li>Вставьте это значение в поле <strong>Получатели VK</strong>.</li>
					</ol>
					<p class="vk-notifier-hint">Если беседа видна в интерфейсе VK, но не отображается в диагностике плагина, текущий токен не имеет к ней доступа через API.</p>
				</div>

				<div class="vk-notifier-help-card">
					<h2>Частые ошибки VK</h2>
					<ul>
						<li><code>901</code> — пользователь не разрешил сообщения сообществу.</li>
						<li><code>917</code> — у сообщества нет доступа к беседе или указан неверный <code>peer_id</code>.</li>
						<li><code>932</code> — сообщество не может взаимодействовать с этим получателем.</li>
					</ul>
				</div>

				<div class="vk-notifier-help-card">
					<h2>Как читать логи</h2>
					<ul>
						<li><strong>Получатель</strong> — куда шла отправка.</li>
						<li><strong>Код VK</strong> — код ошибки VK, если она была.</li>
						<li><strong>Краткий результат</strong> — человеческое описание результата.</li>
						<li><strong>Детали</strong> — техническая информация и сырой JSON ответа.</li>
					</ul>
				</div>

				<div class="vk-notifier-help-card">
					<h2>Полезные ссылки</h2>
					<ul>
						<li><a href="https://vk.com/dev/messages.send" target="_blank" rel="noopener noreferrer">Документация VK API: messages.send</a></li>
						<li><a href="https://vk.com/dev/messages.getConversations" target="_blank" rel="noopener noreferrer">Документация VK API: messages.getConversations</a></li>
						<li><a href="https://vk.com/dev/access_token?f=3.%2520Community%2520access%2520token" target="_blank" rel="noopener noreferrer">Как получить токен сообщества</a></li>
						<li><a href="https://vk.com/groups" target="_blank" rel="noopener noreferrer">Управление сообществами VK</a></li>
					</ul>
				</div>
			</div>

			<div class="notice notice-warning inline vk-notifier-inline-help">
				<p><strong>Важно:</strong> используйте <code>group_id</code> без минуса, не указывайте отрицательные ID в получателях, для бесед берите <code>peer_id</code> из встроенной диагностики, а при проблемах сначала проверяйте страницу логов.</p>
			</div>
		</div>
		<?php
	}

	public function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, 'vk-notifier' ) ) {
			return;
		}

		wp_enqueue_script( 'vk-notifier-admin', VK_NOTIFIER_PLUGIN_URL . 'assets/js/admin-scripts.js', array( 'jquery' ), VK_NOTIFIER_VERSION, true );
		wp_localize_script(
			'vk-notifier-admin',
			'vk_notifier_ajax',
			array(
				'ajax_url'            => admin_url( 'admin-ajax.php' ),
				'test_nonce'          => wp_create_nonce( 'vk_notifier_test_nonce' ),
				'clear_nonce'         => wp_create_nonce( 'vk_notifier_clear_nonce' ),
				'conversations_nonce' => wp_create_nonce( 'vk_notifier_conversations_nonce' ),
			)
		);
	}

	public function ajax_test_message() {
		check_ajax_referer( 'vk_notifier_test_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( -1 );
		}

		$options        = get_option( 'vk_notifier_options' );
		$token          = isset( $options['token'] ) ? $options['token'] : '';
		$group_id       = isset( $options['group_id'] ) ? absint( $options['group_id'] ) : null;
		$recipients_raw = isset( $options['recipients'] ) ? $options['recipients'] : '';

		if ( empty( $token ) || empty( $recipients_raw ) ) {
			wp_send_json_error( 'Токен или получатели не заданы.' );
		}

		$vk_api        = new VK_Notifier_VK_API( $token );
		$recipients    = array_filter( array_map( 'trim', explode( ',', $recipients_raw ) ) );
		$test_message  = '🔔 Тестовое сообщение от VK Notifier. Если вы это читаете, плагин работает корректно.';
		$success_count = 0;
		$errors        = array();

		foreach ( $recipients as $recipient ) {
			$prepared = $vk_api->prepare_recipient( $recipient );
			if ( isset( $prepared['error'] ) ) {
				$errors[] = 'ID ' . $recipient . ': ' . $prepared['error_msg'];
				continue;
			}

			$result = $vk_api->send_message( $recipient, $test_message, $group_id );
			if ( true === $result ) {
				$success_count++;
				continue;
			}

			$error_msg  = is_array( $result ) && isset( $result['error_msg'] ) ? $result['error_msg'] : 'неизвестная ошибка';
			$error_code = is_array( $result ) && isset( $result['error_code'] ) ? $result['error_code'] : 'n/a';
			$hint       = '';

			if ( 901 === (int) $error_code ) {
				$hint = ' Проверьте, что пользователь писал сообществу первым или разрешил сообщения.';
			} elseif ( 917 === (int) $error_code || 932 === (int) $error_code ) {
				$hint = ' Проверьте, что используется peer_id из встроенной диагностики и что сообщество имеет доступ к беседе.';
			}

			$errors[] = 'ID ' . $recipient . ': [' . $error_code . '] ' . $error_msg . $hint;
		}

		if ( $success_count > 0 && empty( $errors ) ) {
			wp_send_json_success( 'Тестовое сообщение отправлено (' . $success_count . ' получателей).' );
		} elseif ( $success_count > 0 ) {
			wp_send_json_error( 'Частичный успех: ' . $success_count . ' отправлено. Ошибки: ' . implode( '; ', $errors ) );
		} else {
			wp_send_json_error( 'Ошибки: ' . implode( '; ', $errors ) );
		}
	}

	public function ajax_find_conversations() {
		check_ajax_referer( 'vk_notifier_conversations_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( -1 );
		}

		$options  = get_option( 'vk_notifier_options' );
		$token    = isset( $options['token'] ) ? $options['token'] : '';
		$group_id = isset( $options['group_id'] ) ? absint( $options['group_id'] ) : null;

		if ( empty( $token ) ) {
			wp_send_json_error( 'Токен VK не задан.' );
		}

		$vk_api = new VK_Notifier_VK_API( $token );
		$result = $vk_api->get_conversations( $group_id, 20 );

		if ( empty( $result['ok'] ) ) {
			$error_code = isset( $result['error']['error_code'] ) ? $result['error']['error_code'] : 'n/a';
			$error_msg  = isset( $result['error']['error_msg'] ) ? $result['error']['error_msg'] : 'Неизвестная ошибка';
			$this->maybe_log(
				'error',
				'Не удалось получить список доступных бесед VK.',
				array(
					'group_id'  => $group_id,
					'api_error' => isset( $result['error'] ) ? $result['error'] : array(),
				),
				'find_conversations'
			);
			wp_send_json_error( 'Не удалось получить список бесед: [' . $error_code . '] ' . $error_msg );
		}

		$response      = isset( $result['response'] ) ? $result['response'] : array();
		$items         = isset( $response['items'] ) && is_array( $response['items'] ) ? $response['items'] : array();
		$prepared_rows = array();

		foreach ( $items as $item ) {
			$conversation = isset( $item['conversation'] ) ? $item['conversation'] : array();
			$peer         = isset( $conversation['peer'] ) ? $conversation['peer'] : array();
			if ( empty( $peer['id'] ) ) {
				continue;
			}

			if ( isset( $peer['type'] ) && 'chat' !== $peer['type'] ) {
				continue;
			}

			$chat_settings = isset( $conversation['chat_settings'] ) ? $conversation['chat_settings'] : array();
			$title         = isset( $chat_settings['title'] ) ? $chat_settings['title'] : 'Без названия';
			$local_id      = isset( $peer['local_id'] ) ? (int) $peer['local_id'] : ( (int) $peer['id'] - 2000000000 );
			$members_count = isset( $chat_settings['members_count'] ) ? (int) $chat_settings['members_count'] : null;

			$prepared_rows[] = array(
				'peer_id'       => (int) $peer['id'],
				'chat_id'       => $local_id,
				'title'         => $title,
				'members_count' => $members_count,
			);
		}

		if ( empty( $prepared_rows ) ) {
			$this->maybe_log(
				'info',
				'Поиск доступных бесед VK не вернул результатов.',
				array(
					'group_id' => $group_id,
					'count'    => 0,
				),
				'find_conversations'
			);
			wp_send_json_success(
				array(
					'message'       => 'VK не вернул ни одной доступной беседы для этого токена.',
					'conversations' => array(),
				)
			);
		}

		$this->maybe_log(
			'info',
			'Найдены доступные беседы VK.',
			array(
				'group_id' => $group_id,
				'count'    => count( $prepared_rows ),
			),
			'find_conversations'
		);

		wp_send_json_success(
			array(
				'message'       => 'Найдены беседы, доступные этому токену.',
				'conversations' => $prepared_rows,
			)
		);
	}

	public function ajax_clear_logs() {
		check_ajax_referer( 'vk_notifier_clear_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( -1 );
		}

		$this->logger->clear_logs();
		wp_send_json_success( 'Логи успешно очищены.' );
	}

	private function maybe_log( $level, $message, $details = array(), $event = '' ) {
		if ( ! $this->logger ) {
			return;
		}

		$options           = get_option( 'vk_notifier_options', array() );
		$log_level_setting = isset( $options['log_level'] ) ? $options['log_level'] : 'all';

		if ( 'all' === $log_level_setting || $log_level_setting === $level ) {
			$this->logger->add( $level, $message, $details, $event );
		}
	}


	private function is_wp_cron_enabled() {
		return ! ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON );
	}

	private function sanitize_recipients( $value ) {
		$items = preg_split( '/[,;\n\r]+/', (string) $value );
		$clean = array();

		foreach ( $items as $item ) {
			$item = trim( $item );
			if ( '' === $item || ! preg_match( '/^-?\d+$/', $item ) ) {
				continue;
			}

			$number = (int) $item;
			if ( $number <= 0 ) {
				continue;
			}

			$clean[] = (string) $number;
		}

		return implode( ', ', array_unique( $clean ) );
	}

	private function sanitize_email_whitelist( $value ) {
		$items = preg_split( '/[,;\n\r]+/', (string) $value );
		$clean = array();

		foreach ( $items as $item ) {
			$item = trim( strtolower( $item ) );
			if ( preg_match( '/<([^>]+)>/', $item, $matches ) ) {
				$item = $matches[1];
			}

			$email = sanitize_email( $item );
			if ( is_email( $email ) ) {
				$clean[] = $email;
			}
		}

		return implode( ', ', array_unique( $clean ) );
	}

	private function prepare_logs_for_display( $logs, $event_filter, $search_query ) {
		$prepared = array();

		foreach ( $logs as $log ) {
			$item = $this->normalize_log_entry( $log );

			if ( 'all' !== $event_filter && $item['event'] !== $event_filter ) {
				continue;
			}

			if ( '' !== $search_query ) {
				$haystack = strtolower( implode( ' ', array_filter( array(
					$item['message'],
					$item['summary'],
					$item['recipient'],
					$item['vk_code'],
					$item['vk_error_message'],
					$item['raw_json'],
				) ) ) );
				if ( false === strpos( $haystack, strtolower( $search_query ) ) ) {
					continue;
				}
			}

			$prepared[] = $item;
		}

		return $prepared;
	}

	private function normalize_log_entry( $log ) {
		$details = json_decode( (string) $log->details, true );
		if ( ! is_array( $details ) ) {
			$details = array();
		}

		$raw_body         = '';
		$group_id         = '';
		$http_code        = '';
		$vk_code          = '';
		$vk_error_message = '';
		$recipient        = '';
		$event            = isset( $log->event ) && '' !== (string) $log->event ? (string) $log->event : $this->detect_log_event( $log->message, $details );

		if ( isset( $details['recipient'] ) ) {
			$recipient = (string) $details['recipient'];
		}
		if ( isset( $details['group_id'] ) ) {
			$group_id = (string) $details['group_id'];
		}
		if ( isset( $details['http_code'] ) ) {
			$http_code = (string) $details['http_code'];
		}
		if ( isset( $details['error_code'] ) ) {
			$vk_code = (string) $details['error_code'];
		}
		if ( isset( $details['error_msg'] ) ) {
			$vk_error_message = (string) $details['error_msg'];
		}

		if ( isset( $details['api_error'] ) && is_array( $details['api_error'] ) ) {
			if ( isset( $details['api_error']['error_code'] ) ) {
				$vk_code = (string) $details['api_error']['error_code'];
			}
			if ( isset( $details['api_error']['error_msg'] ) ) {
				$vk_error_message = (string) $details['api_error']['error_msg'];
			}
			if ( isset( $details['api_error']['http_code'] ) ) {
				$http_code = (string) $details['api_error']['http_code'];
			}
		}

		if ( isset( $details['body'] ) ) {
			$body_decoded = json_decode( (string) $details['body'], true );
			$raw_body     = (string) $details['body'];
			if ( is_array( $body_decoded ) && isset( $body_decoded['error'] ) && is_array( $body_decoded['error'] ) ) {
				if ( isset( $body_decoded['error']['error_code'] ) ) {
					$vk_code = (string) $body_decoded['error']['error_code'];
				}
				if ( isset( $body_decoded['error']['error_msg'] ) ) {
					$vk_error_message = (string) $body_decoded['error']['error_msg'];
				}
			}
		}

		if ( isset( $details['params'] ) && is_array( $details['params'] ) ) {
			if ( isset( $details['params']['group_id'] ) ) {
				$group_id = (string) $details['params']['group_id'];
			}
		}

		$summary = $this->build_log_summary( $log->level, $log->message, $vk_code, $vk_error_message, $event );
		$hint    = $this->get_error_hint( $vk_code );

		return array(
			'id'                   => (int) $log->id,
			'timestamp'            => (string) $log->timestamp,
			'level'                => (string) $log->level,
			'event'                => $event,
			'message'              => (string) $log->message,
			'recipient'            => $recipient,
			'recipient_type_label' => $this->get_recipient_type_label( $recipient, $details ),
			'group_id'             => $group_id,
			'http_code'            => $http_code,
			'vk_code'              => $vk_code,
			'vk_error_message'     => $vk_error_message,
			'summary'              => $summary,
			'hint'                 => $hint,
			'raw_json'             => wp_json_encode( $details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			'details_array'        => $details,
		);
	}

	private function detect_log_event( $message, $details ) {
		if ( false !== stripos( $message, 'messages.send' ) ) {
			return 'messages.send';
		}
		if ( false !== stripos( $message, 'получателю' ) || isset( $details['api_error'] ) ) {
			return 'email_intercept';
		}
		if ( false !== stripos( $message, 'некорректный получатель' ) ) {
			return 'validation';
		}
		if ( false !== stripos( $message, 'conversation' ) || false !== stripos( $message, 'бесед' ) ) {
			return 'find_conversations';
		}
		return 'messages.send';
	}

	private function build_log_summary( $level, $message, $vk_code, $vk_error_message, $event ) {
		if ( 'success' === $level ) {
			return 'Сообщение отправлено';
		}
		if ( 'error' === $level ) {
			if ( '901' === (string) $vk_code ) {
				return 'Пользователь не разрешил сообщения';
			}
			if ( '917' === (string) $vk_code ) {
				return 'Нет доступа к беседе';
			}
			if ( '932' === (string) $vk_code ) {
				return 'Сообщество не может работать с этим peer';
			}
			if ( false !== stripos( $message, 'Некорректный получатель' ) ) {
				return 'Получатель отклонён при валидации';
			}
			if ( $vk_error_message ) {
				return $vk_error_message;
			}
			return 'Ошибка отправки';
		}
		if ( 'find_conversations' === $event ) {
			return 'Диагностика бесед';
		}
		if ( false !== stripos( $message, 'VK API запрос' ) ) {
			return 'Запрос отправлен';
		}
		if ( false !== stripos( $message, 'VK API ответ' ) ) {
			return 'Получен ответ VK';
		}
		return $message;
	}

	private function get_level_label( $level ) {
		$labels = array(
			'error'   => 'Ошибка',
			'success' => 'Успех',
			'info'    => 'Инфо',
		);
		return isset( $labels[ $level ] ) ? $labels[ $level ] : $level;
	}

	private function get_error_hint( $vk_code ) {
		if ( '901' === (string) $vk_code ) {
			return 'Пользователь должен сначала написать сообществу или разрешить ему сообщения.';
		}
		if ( '917' === (string) $vk_code ) {
			return 'Для беседы используйте peer_id из встроенной диагностики: адресная строка браузера может не совпадать с peer_id, доступным токену.';
		}
		if ( '932' === (string) $vk_code ) {
			return 'Проверьте тип получателя и доступ сообщества к этому peer.';
		}
		return '';
	}

	private function get_recipient_type_label( $recipient, $details ) {
		if ( isset( $details['type'] ) ) {
			if ( 'chat' === $details['type'] ) {
				return 'чат';
			}
			if ( 'user' === $details['type'] ) {
				return 'пользователь';
			}
		}

		if ( '' === $recipient ) {
			return '';
		}

		if ( is_numeric( $recipient ) && (int) $recipient >= 2000000000 ) {
			return 'чат';
		}

		return 'пользователь';
	}

	private function render_admin_styles() {
		?>
		<style>
			.vk-notifier-admin-page .vk-notifier-filters {
				display:flex;
				gap:8px;
				align-items:center;
				flex-wrap:wrap;
				margin:16px 0;
			}
			.vk-notifier-admin-page .vk-notifier-results-count {
				margin:8px 0 14px;
				color:#50575e;
			}
			.vk-notifier-admin-page .vk-notifier-log-table td,
			.vk-notifier-admin-page .vk-notifier-log-table th {
				vertical-align:top;
			}
			.vk-notifier-admin-page .vk-notifier-log-table code {
				word-break:break-all;
			}
			.vk-notifier-admin-page .vk-notifier-subtext {
				margin-top:4px;
				color:#646970;
				font-size:12px;
				line-height:1.4;
			}
			.vk-notifier-admin-page .vk-notifier-badge {
				display:inline-block;
				padding:4px 8px;
				border-radius:999px;
				font-size:12px;
				font-weight:600;
				line-height:1;
			}
			.vk-notifier-admin-page .vk-notifier-badge-error { background:#fbeaea; color:#a61b1b; }
			.vk-notifier-admin-page .vk-notifier-badge-success { background:#e7f6ea; color:#116329; }
			.vk-notifier-admin-page .vk-notifier-badge-info { background:#eef4ff; color:#1d4f91; }
			.vk-notifier-admin-page .vk-notifier-details summary {
				cursor:pointer;
				color:#2271b1;
			}
			.vk-notifier-admin-page .vk-notifier-detail-box {
				margin-top:8px;
				padding:10px;
				background:#fff;
				border:1px solid #dcdcde;
				border-radius:6px;
			}
			.vk-notifier-admin-page .vk-notifier-detail-box pre {
				max-height:280px;
				overflow:auto;
				background:#f6f7f7;
				padding:10px;
				border:1px solid #dcdcde;
				white-space:pre-wrap;
				word-break:break-word;
			}
			.vk-notifier-admin-page .vk-notifier-mini-table td:first-child {
				width:140px;
				font-weight:600;
			}
			.vk-notifier-admin-page .vk-notifier-cron-status-wrap {
				margin:10px 0 6px;
				display:flex;
				align-items:center;
				gap:8px;
				flex-wrap:wrap;
			}
			.vk-notifier-admin-page .vk-notifier-cron-label {
				font-weight:600;
			}
			.vk-notifier-admin-page .vk-notifier-status-badge {
				display:inline-block;
				padding:4px 10px;
				border-radius:999px;
				font-size:12px;
				font-weight:600;
				line-height:1.3;
			}
			.vk-notifier-admin-page .vk-notifier-status-enabled {
				background:#e7f6ea;
				color:#116329;
			}
			.vk-notifier-admin-page .vk-notifier-status-disabled {
				background:#fbeaea;
				color:#a61b1b;
			}
			.vk-notifier-admin-page .vk-notifier-hint {
				margin:10px 0;
				padding:10px 12px;
				background:#f0f6fc;
				border-left:4px solid #2271b1;
			}
			.vk-notifier-admin-page .vk-notifier-inline-help {
				margin-top:16px;
			}
			.vk-notifier-admin-page .vk-notifier-help-grid {
				display:grid;
				grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));
				gap:16px;
				margin-top:16px;
			}
			.vk-notifier-admin-page .vk-notifier-help-card {
				background:#fff;
				border:1px solid #dcdcde;
				border-radius:8px;
				padding:18px;
			}
			.vk-notifier-admin-page .vk-notifier-help-card h2 {
				margin-top:0;
				font-size:18px;
			}
			.vk-notifier-admin-page .vk-notifier-help-card code {
				word-break:break-all;
			}
		</style>
		<?php
	}
}
