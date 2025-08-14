/**
 * API Key Fix Script
 * 
 * This script fixes issues with the API key generation and display functionality.
 * It directly handles the API key generation process without relying on the existing code.
 */

(function($) {
  $(document).ready(function() {
    console.log('API Key Fix Script loaded');
    
    // Only run on the settings page with API tab active
    if (window.location.href.indexOf('certificate_generator_settings') > -1 && 
        window.location.href.indexOf('tab=api') > -1) {
      
      console.log('On API Settings tab - applying fixes');
      
      // Check if the API key section exists
      const apiKeySection = $('#api-key-section');
      if (apiKeySection.length === 0) {
        console.error('API key section not found');
        return;
      }
      
      // Check if we need to create the generate button
      if ($('#generate-api-key').length === 0) {
        console.log('Adding missing Generate API Key button');
        apiKeySection.prepend('<button type="button" id="generate-api-key" class="button button-secondary">Generate API Key</button>');
      }
      
      // Ensure the hidden API key field exists
      if ($('#certificate_generator_api_key').length === 0) {
        console.log('Adding missing hidden API key field');
        apiKeySection.append('<input type="hidden" id="certificate_generator_api_key" name="certificate_generator_api_key" value="" />');
      }
      
      // Remove any existing click handlers and add our own
      $('#generate-api-key, #regenerate-api-key').off('click').on('click', function() {
        console.log('Generate/Regenerate API Key button clicked');
        
        if ($(this).attr('id') === 'regenerate-api-key') {
          if (!confirm('Are you sure you want to regenerate the API key? This will invalidate the current key.')) {
            return;
          }
        }
        
        // Generate a secure random API key
        const apiKey = generateSecureApiKey();
        console.log('Generated new API key (first 8 chars):', apiKey.substring(0, 8));
        
        // Set the hidden field value
        $('#certificate_generator_api_key').val(apiKey);
        
        // Check if display field exists, create if not
        if ($('#certificate_generator_api_key_display').length === 0) {
          console.log('Creating missing API key display field');
          apiKeySection.append('<input type="text" id="certificate_generator_api_key_display" value="" readonly style="width: 300px; margin-top: 10px;" />');
        }
        
        // Update display field
        $('#certificate_generator_api_key_display').val(apiKey.substring(0, 8) + '...' + apiKey.substring(apiKey.length - 8));
        
        // Ensure show/copy/regenerate buttons exist
        if ($('#show-api-key').length === 0) {
          console.log('Adding missing Show API Key button');
          apiKeySection.append('<button type="button" id="show-api-key" class="button button-secondary" style="margin-left: 10px;">Show Full Key</button>');
        }
        
        if ($('#copy-api-key').length === 0) {
          console.log('Adding missing Copy API Key button');
          apiKeySection.append('<button type="button" id="copy-api-key" class="button button-secondary" style="margin-left: 10px;">Copy</button>');
        }
        
        if ($('#regenerate-api-key').length === 0 && $(this).attr('id') !== 'regenerate-api-key') {
          console.log('Adding missing Regenerate API Key button');
          apiKeySection.append('<button type="button" id="regenerate-api-key" class="button button-secondary" style="margin-left: 10px;">Regenerate</button>');
        }
        
        // Hide generate button if it was clicked
        if ($(this).attr('id') === 'generate-api-key') {
          $(this).hide();
        }
        
        // Show all other buttons
        $('#certificate_generator_api_key_display, #show-api-key, #regenerate-api-key, #copy-api-key').show();
        
        // Add description if missing
        if (apiKeySection.find('.description').length === 0) {
          apiKeySection.append('<p class="description">Keep this API key secure. It provides full access to certificate generation.</p>');
        }
        
        // Add success message
        $('<div class="notice notice-success inline"><p>API key generated successfully! Remember to save your settings.</p></div>')
          .insertAfter(apiKeySection)
          .delay(5000)
          .fadeOut();
      });
      
      // Show/hide API key
      $(document).on('click', '#show-api-key', function() {
        console.log('Show/Hide API Key button clicked');
        const $input = $('#certificate_generator_api_key_display');
        const fullKey = $('#certificate_generator_api_key').val();
        
        if ($(this).text() === 'Show Full Key') {
          $input.val(fullKey);
          $(this).text('Hide Key');
        } else {
          $input.val(fullKey.substring(0, 8) + '...' + fullKey.substring(fullKey.length - 8));
          $(this).text('Show Full Key');
        }
      });
      
      // Copy API key
      $(document).on('click', '#copy-api-key', function() {
        console.log('Copy API Key button clicked');
        const apiKey = $('#certificate_generator_api_key').val();
        
        // Try modern clipboard API first
        if (navigator.clipboard) {
          navigator.clipboard.writeText(apiKey)
            .then(function() {
              alert('API key copied to clipboard!');
            })
            .catch(function(err) {
              console.error('Could not copy text: ', err);
              fallbackCopyTextToClipboard(apiKey);
            });
        } else {
          fallbackCopyTextToClipboard(apiKey);
        }
      });
      
      // Add a notice about the fix
      $('<div class="notice notice-info inline"><p><strong>API Key Fix:</strong> An enhanced API key generation script has been applied to fix issues with the API key functionality.</p></div>')
        .insertBefore(apiKeySection);
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
  
  // Fallback copy method for older browsers
  function fallbackCopyTextToClipboard(text) {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    
    // Make the textarea out of viewport
    textArea.style.position = 'fixed';
    textArea.style.left = '-999999px';
    textArea.style.top = '-999999px';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
      const successful = document.execCommand('copy');
      const msg = successful ? 'successful' : 'unsuccessful';
      console.log('Fallback: Copying text command was ' + msg);
      alert('API key copied to clipboard!');
    } catch (err) {
      console.error('Fallback: Oops, unable to copy', err);
      alert('Failed to copy API key. Please select and copy it manually.');
    }
    
    document.body.removeChild(textArea);
  }
})(jQuery);