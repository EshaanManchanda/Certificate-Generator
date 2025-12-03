# Complete SMTP Setup Guide for Certificate Generator
## Hostinger & Gmail SMTP Configuration

---

## 🎯 Recommended: Hostinger SMTP (Free & Professional)

Since you already have Hostinger email accounts, this is the best option:
- ✅ Free (included with your hosting)
- ✅ Professional (uses your domain: certificates@paintingolympics.in)
- ✅ Better deliverability
- ✅ No daily limits
- ✅ Easier to manage

### Step 1: Get Your Hostinger SMTP Credentials

1. **Log into Hostinger Control Panel** (hpanel.hostinger.com)
2. Go to **Emails** section
3. Find your email account (e.g., certificates@paintingolympics.in or admin@paintingolympics.in)
4. Note down these settings:

```
Incoming Server (IMAP):
Host: imap.hostinger.com
Port: 993
Security: SSL/TLS

Outgoing Server (SMTP):
Host: smtp.hostinger.com
Port: 465 (SSL) or 587 (TLS)
Security: SSL or TLS
Username: your-email@paintingolympics.in
Password: [Your email password]
```

### Step 2: Install WP Mail SMTP Plugin

1. Go to **WordPress Admin Dashboard**
2. Navigate to **Plugins → Add New**
3. Search for **"WP Mail SMTP"**
4. Click **Install Now** on "WP Mail SMTP by WPForms"
5. Click **Activate**

### Step 3: Configure WP Mail SMTP with Hostinger

1. Go to **WP Mail SMTP → Settings**

2. **General Tab:**
   - **From Email:** certificates@paintingolympics.in (or your Hostinger email)
   - **From Name:** Painting Olympics Certificate System
   - **Force From Email:** Yes (check this)
   - **Force From Name:** Yes (check this)

3. **Mailer Selection:**
   - Select **"Other SMTP"**

4. **Other SMTP Settings:**
   ```
   SMTP Host: smtp.hostinger.com
   SMTP Port: 465
   Encryption: SSL

   Authentication: ON (toggle to enable)
   SMTP Username: certificates@paintingolympics.in
   SMTP Password: [Your Hostinger email password]
   ```

   **Alternative (if 465 doesn't work):**
   ```
   SMTP Port: 587
   Encryption: TLS
   ```

5. Click **Save Settings**

### Step 4: Test Email

1. Go to **WP Mail SMTP → Email Test**
2. Enter your email address (e.g., eshaanmanchanda01@gmail.com)
3. Click **Send Email**
4. Check if you receive the test email

✅ **If successful:** Your SMTP is configured correctly!
❌ **If failed:** Check the error message and verify your credentials

### Step 5: Send Certificate Test

1. Go to your Certificate Generator
2. Try sending a certificate to a test student
3. Check the email logs - you should see success messages now!

---

## 📧 Alternative: Gmail SMTP (Quick Setup)

If you prefer using your existing Gmail account:

### Step 1: Enable 2-Step Verification on Gmail

1. Go to **myaccount.google.com**
2. Click **Security** (left sidebar)
3. Find **2-Step Verification**
4. Enable it if not already enabled

### Step 2: Create App-Specific Password

1. In Google Account Security settings
2. Find **App passwords** (under 2-Step Verification)
3. Select **Mail** and **Windows Computer** (or Other)
4. Click **Generate**
5. **Copy the 16-character password** (you'll need this)

### Step 3: Configure WP Mail SMTP with Gmail

1. Go to **WP Mail SMTP → Settings**

2. **General Tab:**
   - **From Email:** eshaanmanchanda01@gmail.com
   - **From Name:** Painting Olympics Certificate System

3. **Mailer Selection:**
   - Select **"Gmail"** (if available) OR **"Other SMTP"**

4. **If using "Other SMTP":**
   ```
   SMTP Host: smtp.gmail.com
   SMTP Port: 587
   Encryption: TLS

   Authentication: ON
   SMTP Username: eshaanmanchanda01@gmail.com
   SMTP Password: [16-character app password from Step 2]
   ```

5. Click **Save Settings**

### Step 4: Test Gmail SMTP

1. Go to **WP Mail SMTP → Email Test**
2. Send test email
3. Verify delivery

---

## 🔍 Troubleshooting

### Common Issues & Solutions

#### Issue: "SMTP connect() failed"
**Solutions:**
- Check SMTP host spelling (smtp.hostinger.com or smtp.gmail.com)
- Try different port (465 with SSL or 587 with TLS)
- Verify your email password is correct
- For Hostinger: Make sure email account is active in hPanel

#### Issue: "Could not authenticate"
**Solutions:**
- **Hostinger:** Use your full email as username (certificates@paintingolympics.in)
- **Gmail:** Make sure you're using App Password, not regular password
- Check for typos in username/password

#### Issue: "Connection timed out"
**Solutions:**
- Contact Hostinger support - they may need to whitelist your IP
- Check if firewall is blocking SMTP ports
- Try alternate port (465 vs 587)

#### Issue: Emails going to spam
**Solutions:**
- Use Hostinger SMTP (better deliverability)
- Set up SPF/DKIM records in Hostinger DNS (ask Hostinger support)
- Use professional From address (certificates@paintingolympics.in)

---

## 📊 Comparison: Hostinger vs Gmail SMTP

| Feature | Hostinger SMTP | Gmail SMTP |
|---------|----------------|------------|
| **Cost** | Free (included) | Free (with limits) |
| **From Email** | certificates@paintingolympics.in | eshaanmanchanda01@gmail.com |
| **Professionalism** | ⭐⭐⭐⭐⭐ More professional | ⭐⭐⭐ Less professional |
| **Deliverability** | ⭐⭐⭐⭐⭐ Better | ⭐⭐⭐⭐ Good |
| **Daily Limit** | Higher/None | 500 emails/day |
| **Setup Difficulty** | Easy | Easy (needs app password) |
| **Recommended For** | Production use | Quick testing |

---

## ✅ Verification Checklist

After setup, verify these:

- [ ] WP Mail SMTP plugin installed and activated
- [ ] SMTP settings configured (Hostinger or Gmail)
- [ ] Test email sent successfully from WP Mail SMTP
- [ ] Certificate email sent successfully
- [ ] Email received (check spam folder if not in inbox)
- [ ] Check database: `wp_cert_email_logs` shows status='sent'
- [ ] Admin notice gone (or shows green/healthy status)

---

## 🎯 Recommended Configuration

**For Production (Recommended):**
```
Plugin: WP Mail SMTP
SMTP Provider: Hostinger
SMTP Host: smtp.hostinger.com
SMTP Port: 465
Encryption: SSL
From Email: certificates@paintingolympics.in
From Name: Painting Olympics Certificate System
```

**For Testing:**
```
Plugin: WP Mail SMTP
SMTP Provider: Gmail
SMTP Host: smtp.gmail.com
SMTP Port: 587
Encryption: TLS
From Email: eshaanmanchanda01@gmail.com
From Name: Test Certificate System
```

---

## 📞 Getting Help

### If Hostinger SMTP Doesn't Work:
1. Contact Hostinger support via live chat
2. Ask: "I need SMTP credentials for my email certificates@paintingolympics.in"
3. Ask them to verify SMTP is enabled on your hosting plan
4. Request help with SPF/DKIM setup for better deliverability

### If Gmail SMTP Doesn't Work:
1. Verify 2-Step Verification is enabled
2. Generate new App Password
3. Try port 465 with SSL instead of 587 with TLS
4. Check Google Account security settings for blocked sign-ins

### Check Your Logs:
After setup, if emails still fail, check:
- `wp-content/debug.log` (if WP_DEBUG_LOG is enabled)
- Database: `SELECT * FROM wp_cert_email_logs ORDER BY sent_at DESC LIMIT 10;`
- Your enhanced error logs (search for "Certificate Generator:" in PHP error log)

---

## 🎉 Expected Results

After proper SMTP setup, your logs should show:

```
Certificate Generator: Email configuration: Array
(
    [smtp_active] => 1
    [from] => certificates@paintingolympics.in
    [to] => recipient@example.com
)

Certificate Generator: Email attempt 1/3 for recipient@example.com
Certificate Generator: Email sent successfully on attempt 1 to recipient@example.com
```

And in your database:
```sql
status: 'sent'
error_message: NULL
```

---

## 💡 Pro Tips

1. **Use Hostinger SMTP for production** - More professional and better deliverability
2. **Set up SPF/DKIM records** - Ask Hostinger support to help configure these
3. **Use descriptive From Name** - "Painting Olympics Certificate System" is better than "WordPress"
4. **Monitor your email logs** - Check `wp_cert_email_logs` table regularly
5. **Test before bulk sending** - Send one test certificate before batch sending
6. **Whitelist your email** - Ask certificate recipients to whitelist your sender email

---

## 🔒 Security Notes

- Never share your SMTP password
- Use App Passwords for Gmail (not your main password)
- Hostinger automatically uses secure connections (SSL/TLS)
- WP Mail SMTP encrypts passwords in WordPress database

---

Need more help? Check the error logs and share the specific error message!
