/**
 * Stock Sync Admin JavaScript
 * 
 * ใช้สำหรับ Admin Dashboard
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        
        // ===== Test Connection =====
        $('#ssw-test-connection').on('click', function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const $result = $('#ssw-result');
            const originalText = $btn.text();
            
            $btn.prop('disabled', true).text('Testing...');
            $result.fadeOut(200);
            
            $.ajax({
                url: ssw_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'ssw_test_connection',
                    nonce: ssw_vars.nonce_test
                },
                timeout: 30000,
                success: function(response) {
                    if (response.success) {
                        showNotice('success', response.data);
                    } else {
                        showNotice('error', response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    showNotice('error', 'Connection test failed - ' + error);
                },
                complete: function() {
                    $btn.prop('disabled', false).text(originalText);
                    $result.fadeIn(200);
                }
            });
        });
        
        // ===== Manual Sync =====
        $('#ssw-manual-sync').on('click', function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to run a full sync now? This may take a while.')) {
                return;
            }
            
            const $btn = $(this);
            const $result = $('#ssw-result');
            const originalText = $btn.text();
            
            $btn.prop('disabled', true).text('Syncing...');
            $result.fadeOut(200);
            
            $.ajax({
                url: ssw_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'ssw_manual_sync',
                    nonce: ssw_vars.nonce_sync
                },
                timeout: 0, // No timeout
                success: function(response) {
                    if (response.success) {
                        const synced = response.data.synced || 0;
                        const errors = response.data.errors || 0;
                        const message = 'Sync completed: ' + synced + ' products synced';
                        
                        if (errors > 0) {
                            message += ', ' + errors + ' errors';
                        }
                        
                        showNotice('success', message);
                        
                        // Reload page to update status
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        showNotice('error', 'Sync failed: ' + response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    showNotice('error', 'Sync failed - ' + error);
                },
                complete: function() {
                    $btn.prop('disabled', false).text(originalText);
                    $result.fadeIn(200);
                }
            });
        });
        
        /**
         * Show Notice Message
         */
        function showNotice(type, message) {
            const $result = $('#ssw-result');
            const noticeClass = 'notice notice-' + (type === 'error' ? 'error' : 'success');
            const html = '<div class="' + noticeClass + '"><p>' + esc_html(message) + '</p></div>';
            
            $result.html(html).fadeIn(200);
        }
        
        /**
         * Escape HTML
         */
        function esc_html(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }
    });
    
})(jQuery);
