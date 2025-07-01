/**
 * API Debug Script
 * 
 * This script adds debugging functionality to the API settings page.
 */

(function($) {
  $(document).ready(function() {
    console.log('API Debug Script loaded');
    
    // Check if we're on the settings page
    if (window.location.href.indexOf('certificate_generator_settings') > -1) {
      console.log('On Certificate Generator settings page');
      
      // Log active tab
      const urlParams = new URLSearchParams(window.location.search);
      const activeTab = urlParams.get('tab') || 'general';
      console.log('Active tab:', activeTab);
      
      // Remove forced redirection to API tab
      // Add any other API-specific debug functionality here
    }
  });
})(jQuery);

/**
 * API Key Debug Script
 * 
 * This script helps identify issues with the API key generation and display functionality.
 */

(function($) {
  $(document).ready(function() {
    console.log('API Debug Script loaded');
    
    // Check if we're on the settings page
    if (window.location.href.indexOf('certificate_generator_settings') > -1) {
      console.log('On Certificate Generator settings page');
      
      // Log active tab
      const urlParams = new URLSearchParams(window.location.search);
      const activeTab = urlParams.get('tab') || 'general';
      console.log('Active tab:', activeTab);
      
      // Force API tab if not already active
      if (activeTab !== 'api') {
        console.log('Redirecting to API tab for debugging');
        window.location.href = '?page=certificate_generator_settings&tab=api';
        return;
      }
      
      // Log API section elements
      console.log('API section exists:', $('#certificate_generator_api_section').length > 0);
      
      // Log API key elements
      if ($('#generate-api-key').length) {
        console.log('Generate API Key button found');
        
        // Add click event listener for debugging
        $('#generate-api-key').on('click', function() {
          console.log('Generate API Key button clicked');
          
          // Generate a secure random API key
          const apiKey = generateSecureApiKey();
          console.log('Generated API key (first 8 chars):', apiKey.substring(0, 8));
          
          $('#certificate_generator_api_key').val(apiKey);
          $('#certificate_generator_api_key_display').val(apiKey.substring(0, 8) + '...' + apiKey.substring(apiKey.length - 8));
          
          // Update the UI
          $('#generate-api-key').hide();
          $('#certificate_generator_api_key_display, #show-api-key, #regenerate-api-key, #copy-api-key').show();
          
          alert('API key generated successfully! Remember to save your settings.');
        });
      } else {
        console.log('Generate API Key button NOT found');
      }
      
      // Check if API key field exists
      if ($('#certificate_generator_api_key').length) {
        console.log('API Key hidden field found');
        console.log('API Key value length:', $('#certificate_generator_api_key').val().length);
      } else {
        console.log('API Key hidden field NOT found');
      }
      
      if ($('#certificate_generator_api_key_display').length) {
        console.log('API Key display field found');
        console.log('API Key display value:', $('#certificate_generator_api_key_display').val());
      } else {
        console.log('API Key display field NOT found');
      }
      
      // Check if API enabled checkbox exists
      if ($('#certificate_generator_api_key_enabled').length) {
        console.log('API Enabled checkbox found');
        console.log('API Enabled checked:', $('#certificate_generator_api_key_enabled').is(':checked'));
      } else {
        console.log('API Enabled checkbox NOT found');
      }
      
      // Add a manual API key generation button for testing
      $('<div id="api-debug-section" style="margin-top: 20px; padding: 15px; background: #f8f8f8; border: 1px solid #ddd;">' +
        '<h3>API Debug Tools</h3>' +
        '<p>Use these tools to debug API key issues:</p>' +
        '<button id="debug-generate-key" class="button button-primary">Force Generate API Key</button> ' +
        '<button id="debug-show-options" class="button button-secondary">Show Saved Options</button>' +
        '<div id="debug-output" style="margin-top: 10px; padding: 10px; background: #fff; border: 1px solid #ddd; max-height: 200px; overflow: auto;"></div>' +
        '</div>').insertAfter('#api-key-section');
      
      // Debug button click handlers
      $('#debug-generate-key').on('click', function() {
        const apiKey = generateSecureApiKey();
        $('#debug-output').html('<strong>Generated Key:</strong> ' + apiKey);
        
        // Directly set the key fields
        $('#certificate_generator_api_key').val(apiKey);
        if ($('#certificate_generator_api_key_display').length) {
          $('#certificate_generator_api_key_display').val(apiKey.substring(0, 8) + '...' + apiKey.substring(apiKey.length - 8));
        } else {
          $('#debug-output').append('<br><strong>Error:</strong> Display field not found, creating one...');
          $('<input type="text" id="certificate_generator_api_key_display" value="' + apiKey.substring(0, 8) + '...' + apiKey.substring(apiKey.length - 8) + '" readonly style="width: 300px;" />').insertBefore('#debug-generate-key');
        }
      });
      
      $('#debug-show-options').on('click', function() {
        // Make an AJAX request to get the current option values
        $.ajax({
          url: ajaxurl,
          type: 'POST',
          data: {
            action: 'certificate_generator_debug_get_options'
          },
          success: function(response) {
            $('#debug-output').html('<pre>' + JSON.stringify(response, null, 2) + '</pre>');
          },
          error: function() {
            $('#debug-output').html('<strong>Error:</strong> Failed to retrieve options');
          }
        });
      });
    }
  });
  
  // Helper function to generate a secure API key
  function generateSecureApiKey() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    let result = '';
    for (let i = 0; i < 64; i++) {
      result += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return result;
  }
})(jQuery);