# Certificate Generator API Integration Guide

This guide explains how to integrate with the Certificate Generator plugin's REST API for automated certificate issuance, particularly for AI agents and external applications.

## Overview

The Certificate Generator plugin provides secure REST API endpoints that allow external applications to:
- Issue certificates programmatically
- Validate API credentials
- Check system health
- Integrate with AI agents for automated certificate generation

## Setup

### 1. Enable API Access

1. Navigate to **WordPress Admin > Settings > Certificate Generator**
2. Scroll to the **API Configuration** section
3. Check **"Enable REST API access for certificate generation"**
4. Click **"Generate API Key"** to create a secure API key
5. **Copy and securely store** the generated API key
6. Click **"Save Changes"**

### 2. Security Considerations

- **Keep your API key secure** - treat it like a password
- **Use HTTPS** for all API requests in production
- **Regenerate keys** periodically for enhanced security
- **Monitor API logs** for unauthorized access attempts

## API Endpoints

### Base URL
```
https://yourdomain.com/wp-json/certificate-generator/v1/
```

### Authentication

All API requests (except health check) require authentication using the API key in the Authorization header:

```
Authorization: Bearer YOUR_API_KEY_HERE
```

### Available Endpoints

#### 1. Issue Certificate

**Endpoint:** `POST /issue-certificate`

**Description:** Generates and issues a certificate for a student.

**Required Headers:**
```
Content-Type: application/json
Authorization: Bearer YOUR_API_KEY_HERE
```

**Required Parameters:**
- `student_email` (string, email): Email address of the student
- `certificate_type` (string): Type of certificate to issue (must match existing certificate type)

**Optional Parameters:**
- `student_name` (string): Name of the student (if not provided, email will be used)
- `school_name` (string): Name of the school
- `teacher_email` (string, email): Email address of the teacher
- `issue_date` (string, YYYY-MM-DD): Issue date (defaults to current date)

**Example Request:**
```bash
curl -X POST https://yourdomain.com/wp-json/certificate-generator/v1/issue-certificate \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_API_KEY_HERE" \
  -d '{
    "student_email": "student@example.com",
    "certificate_type": "Math Excellence",
    "student_name": "John Doe",
    "school_name": "Example High School",
    "issue_date": "2024-01-15"
  }'
```

**Success Response (200):**
```json
{
  "status": "success",
  "message": "Certificate issued successfully.",
  "download_url": "https://yourdomain.com/wp-content/uploads/certificates/certificate_123_456_1642234567.pdf",
  "certificate_id": 456,
  "email_sent": true
}
```

**Error Responses:**
- `401 Unauthorized`: Invalid or missing API key
- `403 Forbidden`: API access disabled
- `404 Not Found`: Certificate type not found
- `500 Internal Server Error`: Certificate generation failed

#### 2. Health Check

**Endpoint:** `GET /health`

**Description:** Checks if the API is operational (no authentication required).

**Example Request:**
```bash
curl https://yourdomain.com/wp-json/certificate-generator/v1/health
```

**Response (200):**
```json
{
  "status": "healthy",
  "plugin_version": "3.3.1",
  "wordpress_version": "6.4",
  "timestamp": "2024-01-15T10:30:00+00:00"
}
```

#### 3. Validate API Key

**Endpoint:** `POST /validate-key`

**Description:** Validates if the provided API key is correct.

**Required Headers:**
```
Authorization: Bearer YOUR_API_KEY_HERE
```

**Example Request:**
```bash
curl -X POST https://yourdomain.com/wp-json/certificate-generator/v1/validate-key \
  -H "Authorization: Bearer YOUR_API_KEY_HERE"
```

**Success Response (200):**
```json
{
  "status": "valid",
  "message": "API key is valid",
  "timestamp": "2024-01-15T10:30:00+00:00"
}
```

## Integration Examples

### PHP Example

```php
<?php
function issue_certificate_via_api($student_email, $certificate_type, $api_key, $base_url) {
    $endpoint = rtrim($base_url, '/') . '/wp-json/certificate-generator/v1/issue-certificate';
    
    $data = [
        'student_email' => $student_email,
        'certificate_type' => $certificate_type
    ];
    
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'http_code' => $http_code,
        'response' => json_decode($response, true)
    ];
}

// Usage
$result = issue_certificate_via_api(
    'student@example.com',
    'Math Excellence',
    'your-api-key-here',
    'https://yourdomain.com'
);

if ($result['http_code'] === 200) {
    echo "Certificate issued: " . $result['response']['download_url'];
} else {
    echo "Error: " . $result['response']['message'];
}
?>
```

### Python Example

```python
import requests
import json

def issue_certificate(student_email, certificate_type, api_key, base_url):
    endpoint = f"{base_url.rstrip('/')}/wp-json/certificate-generator/v1/issue-certificate"
    
    headers = {
        'Content-Type': 'application/json',
        'Authorization': f'Bearer {api_key}'
    }
    
    data = {
        'student_email': student_email,
        'certificate_type': certificate_type
    }
    
    try:
        response = requests.post(endpoint, headers=headers, json=data, timeout=30)
        return {
            'status_code': response.status_code,
            'data': response.json()
        }
    except requests.exceptions.RequestException as e:
        return {
            'status_code': 0,
            'error': str(e)
        }

# Usage
result = issue_certificate(
    'student@example.com',
    'Math Excellence',
    'your-api-key-here',
    'https://yourdomain.com'
)

if result['status_code'] == 200:
    print(f"Certificate issued: {result['data']['download_url']}")
else:
    print(f"Error: {result.get('data', {}).get('message', result.get('error'))}")
```

### JavaScript/Node.js Example

```javascript
const axios = require('axios');

async function issueCertificate(studentEmail, certificateType, apiKey, baseUrl) {
    const endpoint = `${baseUrl.replace(/\/$/, '')}/wp-json/certificate-generator/v1/issue-certificate`;
    
    const headers = {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${apiKey}`
    };
    
    const data = {
        student_email: studentEmail,
        certificate_type: certificateType
    };
    
    try {
        const response = await axios.post(endpoint, data, { headers, timeout: 30000 });
        return {
            success: true,
            data: response.data
        };
    } catch (error) {
        return {
            success: false,
            error: error.response?.data?.message || error.message
        };
    }
}

// Usage
(async () => {
    const result = await issueCertificate(
        'student@example.com',
        'Math Excellence',
        'your-api-key-here',
        'https://yourdomain.com'
    );
    
    if (result.success) {
        console.log(`Certificate issued: ${result.data.download_url}`);
    } else {
        console.error(`Error: ${result.error}`);
    }
})();
```

## AI Agent Integration

### For AI Chatbots and Virtual Assistants

When integrating with AI agents, consider these patterns:

1. **Validation Flow:**
   - First, validate the API key using `/validate-key`
   - Check system health with `/health`
   - Then proceed with certificate issuance

2. **Error Handling:**
   - Implement retry logic for temporary failures
   - Provide clear error messages to users
   - Log all API interactions for debugging

3. **User Experience:**
   - Confirm certificate details before issuance
   - Provide download links immediately
   - Send email notifications if enabled

### Example AI Agent Workflow

```
1. User: "Issue a Math Excellence certificate for john@example.com"
2. AI Agent: Validates input parameters
3. AI Agent: Calls /validate-key to ensure API access
4. AI Agent: Calls /issue-certificate with user data
5. AI Agent: Returns download link to user
6. AI Agent: Confirms email was sent (if enabled)
```

## Troubleshooting

### Common Issues

1. **401 Unauthorized**
   - Check API key is correct
   - Ensure Authorization header format: `Bearer YOUR_KEY`
   - Verify API access is enabled in settings

2. **404 Certificate Type Not Found**
   - Verify certificate type exists in WordPress admin
   - Check spelling and case sensitivity
   - Ensure certificate is published

3. **500 Internal Server Error**
   - Check WordPress error logs
   - Verify file permissions for uploads directory
   - Ensure required dependencies are installed

### API Logs

API activity is logged and can be viewed in:
- WordPress debug logs (if `WP_DEBUG_LOG` is enabled)
- Plugin settings page (API activity section)

### Testing

Use the health check endpoint to verify API availability:

```bash
curl https://yourdomain.com/wp-json/certificate-generator/v1/health
```

## Security Best Practices

1. **API Key Management:**
   - Store API keys securely (environment variables, secure vaults)
   - Never commit API keys to version control
   - Rotate keys regularly

2. **Network Security:**
   - Always use HTTPS in production
   - Implement rate limiting if needed
   - Monitor for unusual API usage patterns

3. **Access Control:**
   - Limit API access to trusted applications only
   - Consider IP whitelisting for additional security
   - Disable API access when not needed

## Support

For technical support or questions about API integration:

1. Check the plugin's error logs
2. Review this documentation
3. Test with the provided examples
4. Contact the plugin developer with specific error messages and request details

## Changelog

### Version 3.3.1
- Initial API implementation
- Added secure API key authentication
- Implemented certificate issuance endpoint
- Added health check and key validation endpoints
- Comprehensive error handling and logging