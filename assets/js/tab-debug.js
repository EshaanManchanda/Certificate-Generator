/**
 * Tab Navigation Debug Script
 * 
 * This script helps identify issues with the tab navigation in the settings page.
 */

(function($) {
  $(document).ready(function() {
    console.log('Tab Debug Script loaded');
    
    // Check if we're on the settings page
    if (window.location.href.indexOf('certificate_generator_settings') > -1) {
      console.log('On Certificate Generator settings page');
      
      // Log active tab
      const urlParams = new URLSearchParams(window.location.search);
      const activeTab = urlParams.get('tab') || 'general';
      console.log('Active tab from URL:', activeTab);
      
      // Check tab navigation
      $('.nav-tab-wrapper .nav-tab').each(function() {
        const tabHref = $(this).attr('href');
        const isActive = $(this).hasClass('nav-tab-active');
        console.log('Tab:', tabHref, 'Active:', isActive);
      });
    }
  });
})(jQuery);

(function($) {
  $(document).ready(function() {
    console.log('Tab Debug Script loaded');
    
    // Check if we're on the settings page
    if (window.location.href.indexOf('certificate_generator_settings') > -1) {
      console.log('On Certificate Generator settings page');
      
      // Log active tab
      const urlParams = new URLSearchParams(window.location.search);
      const activeTab = urlParams.get('tab') || 'general';
      console.log('Active tab from URL:', activeTab);
      
      // Check tab navigation
      $('.nav-tab-wrapper .nav-tab').each(function() {
        const tabHref = $(this).attr('href');
        const isActive = $(this).hasClass('nav-tab-active');
        console.log('Tab:', tabHref, 'Active:', isActive);
      });
      
      // Check if settings sections are visible
      $('h2.nav-tab-wrapper + form').each(function() {
        console.log('Form after tab wrapper found');
      });
      
      // Check settings sections
      $('.settings-section').each(function() {
        const sectionId = $(this).attr('id');
        const isVisible = $(this).is(':visible');
        console.log('Settings section:', sectionId, 'Visible:', isVisible);
      });
      
      // Add tab switcher for debugging
      $('<div id="tab-debug-section" style="margin-top: 20px; padding: 15px; background: #f8f8f8; border: 1px solid #ddd;">' +
        '<h3>Tab Navigation Debug</h3>' +
        '<p>Use these links to switch tabs:</p>' +
        '<a href="?page=certificate_generator_settings&tab=general" class="button">General Tab</a> ' +
        '<a href="?page=certificate_generator_settings&tab=templates" class="button">Templates Tab</a> ' +
        '<a href="?page=certificate_generator_settings&tab=api" class="button">API Tab</a> ' +
        '<a href="?page=certificate_generator_settings&tab=tools" class="button">Tools Tab</a>' +
        '</div>').insertAfter('.wrap h1');
      
      // Add a fix for the API tab if it's active but not showing content
      if (activeTab === 'api') {
        console.log('Checking API tab content');
        const apiSectionVisible = $('#certificate_generator_api_section').is(':visible');
        console.log('API section visible:', apiSectionVisible);
        
        if (!apiSectionVisible) {
          console.log('Attempting to fix API tab display');
          // Force show API section
          $('form .submit').before('<div id="certificate_generator_api_section_debug" style="border: 1px solid #ddd; padding: 15px; margin: 15px 0; background: #f9f9f9;">' +
            '<h3>API Configuration (Debug View)</h3>' +
            '<p>This is a debug view of the API configuration section.</p>' +
            '<div id="api-key-debug-field"></div>' +
            '<div id="api-enabled-debug-field"></div>' +
            '</div>');
          
          // Check if API key field exists and clone it
          if ($('#certificate_generator_api_key').length) {
            $('#api-key-debug-field').html('<label>API Key:</label><br>' + $('#certificate_generator_api_key').clone().attr('id', 'debug_api_key')[0].outerHTML);
          } else {
            $('#api-key-debug-field').html('<p>API Key field not found in the DOM</p>');
          }
          
          // Check if API enabled field exists and clone it
          if ($('#certificate_generator_api_key_enabled').length) {
            $('#api-enabled-debug-field').html('<label>Enable API:</label><br>' + $('#certificate_generator_api_key_enabled').clone().attr('id', 'debug_api_enabled')[0].outerHTML);
          } else {
            $('#api-enabled-debug-field').html('<p>API Enabled field not found in the DOM</p>');
          }
        }
      }
    }
  });
})(jQuery);