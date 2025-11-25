jQuery(document).ready(function($) {
    
    $('#generate-secret').click(function() {
        var button = $(this);
        button.prop('disabled', true).text('Generating...');
        
        $.post(freepass_ajax.ajax_url, {
            action: 'freepass_generate_secret',
            nonce: freepass_ajax.nonce
        }, function(response) {
            if (response.success) {
                var html = '<img src="' + response.data.qr + '" alt="QR Code">';
                html += '<p>Scan this QR code with Google Authenticator app</p>';
                html += '<p><strong>Secret Key:</strong> <code>' + response.data.secret + '</code></p>';
                
                $('#qr-display').html(html).show();
                button.hide();
            } else {
                alert('Failed to generate secret key');
                button.prop('disabled', false).text('Generate QR Code');
            }
        }).fail(function() {
            alert('Error generating secret key');
            button.prop('disabled', false).text('Generate QR Code');
        });
    });
    
});