jQuery(document).ready(function($) {
    function toggleEmailWhitelistField() {
        var mode = $('input[name="vk_notifier_options[forward_mode]"]:checked').val() || 'whitelist';
        var fieldRow = $('#vk-notifier-email-whitelist').closest('tr');

        if (mode === 'all') {
            fieldRow.hide();
        } else {
            fieldRow.show();
        }
    }

    toggleEmailWhitelistField();
    $('input[name="vk_notifier_options[forward_mode]"]').on('change', toggleEmailWhitelistField);

    // Тестовое сообщение
    $('#vk-notifier-test-btn').on('click', function(e) {
        e.preventDefault();
        var btn = $(this);
        var resultSpan = $('#vk-notifier-test-result');
        btn.prop('disabled', true);
        resultSpan.text('Отправка...');

        $.post(vk_notifier_ajax.ajax_url, {
            action: 'vk_notifier_test',
            nonce: vk_notifier_ajax.test_nonce
        }, function(response) {
            if (response.success) {
                resultSpan.empty().append(
                    $('<span>').css('color', 'green').text(response.data || '')
                );
            } else {
                resultSpan.empty().append(
                    $('<span>').css('color', 'red').text(response.data || 'Ошибка')
                );
            }
            btn.prop('disabled', false);
        }).fail(function(xhr) {
            var message = 'Ошибка соединения';
            if (xhr && xhr.responseText) {
                message += ': ' + xhr.responseText;
            }
            resultSpan.empty().append(
                $('<span>').css('color', 'red').text(message)
            );
            btn.prop('disabled', false);
        });
    });

    // Поиск доступных бесед
    $('#vk-notifier-find-conversations').on('click', function(e) {
        e.preventDefault();
        var btn = $(this);
        var resultBox = $('#vk-notifier-conversations-result');
        btn.prop('disabled', true);
        resultBox.html('Идёт запрос к VK...');

        $.post(vk_notifier_ajax.ajax_url, {
            action: 'vk_notifier_find_conversations',
            nonce: vk_notifier_ajax.conversations_nonce
        }, function(response) {
            if (response.success) {
                var data = response.data || {};
                var html = '<div style="padding:10px;border:1px solid #ccd0d4;background:#fff;">';
                html += '<p style="margin-top:0;"><strong>' + $('<div>').text(data.message || 'Готово').html() + '</strong></p>';

                if (data.conversations && data.conversations.length) {
                    html += '<table class="widefat striped" style="max-width:900px;"><thead><tr><th>Название</th><th>peer_id</th><th>chat_id</th><th>Участников</th></tr></thead><tbody>';
                    data.conversations.forEach(function(item) {
                        html += '<tr>' +
                            '<td>' + $('<div>').text(item.title || '').html() + '</td>' +
                            '<td><code>' + $('<div>').text(String(item.peer_id || '')).html() + '</code></td>' +
                            '<td><code>' + $('<div>').text(String(item.chat_id || '')).html() + '</code></td>' +
                            '<td>' + $('<div>').text(item.members_count !== null ? String(item.members_count) : '—').html() + '</td>' +
                        '</tr>';
                    });
                    html += '</tbody></table>';
                    html += '<p>Используйте в настройках именно <code>peer_id</code> из этой таблицы.</p>';
                }

                html += '</div>';
                resultBox.html(html);
            } else {
                resultBox.html('<span style="color:red">' + $('<div>').text(response.data || 'Ошибка').html() + '</span>');
            }
            btn.prop('disabled', false);
        }).fail(function(xhr) {
            var message = 'Ошибка соединения';
            if (xhr && xhr.responseText) {
                message += ': ' + xhr.responseText;
            }
            resultBox.html('<span style="color:red">' + $('<div>').text(message).html() + '</span>');
            btn.prop('disabled', false);
        });
    });

    // Очистка логов
    $('#vk-notifier-clear-logs').on('click', function(e) {
        e.preventDefault();
        if (!confirm('Вы уверены, что хотите очистить все логи?')) {
            return;
        }
        var btn = $(this);
        var resultSpan = $('#vk-notifier-clear-result');
        btn.prop('disabled', true);
        resultSpan.text('Очистка...');

        $.post(vk_notifier_ajax.ajax_url, {
            action: 'vk_notifier_clear_logs',
            nonce: vk_notifier_ajax.clear_nonce
        }, function(response) {
            if (response.success) {
                resultSpan.empty().append(
                    $('<span>').css('color', 'green').text(response.data || '')
                );
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                resultSpan.empty().append(
                    $('<span>').css('color', 'red').text(response.data || 'Ошибка')
                );
                btn.prop('disabled', false);
            }
        }).fail(function(xhr) {
            var message = 'Ошибка соединения';
            if (xhr && xhr.responseText) {
                message += ': ' + xhr.responseText;
            }
            resultSpan.empty().append(
                $('<span>').css('color', 'red').text(message)
            );
            btn.prop('disabled', false);
        });
    });
});
