# Quick SMTP Setup - Certificate Generator

## 🚀 5-Minute Setup (Hostinger SMTP - Recommended)

### Your Hostinger SMTP Credentials:
```
SMTP Host: smtp.hostinger.com
SMTP Port: 465
Encryption: SSL
Username: [your-email]@paintingolympics.in
Password: [Your Hostinger email password]
```

### Setup Steps:

1. **Install WP Mail SMTP Plugin**
   - WordPress Admin → Plugins → Add New
   - Search "WP Mail SMTP"
   - Install & Activate

2. **Configure Plugin**
   - Go to: WP Mail SMTP → Settings
   - From Email: certificates@paintingolympics.in (or your Hostinger email)
   - From Name: Painting Olympics Certificate System
   - Mailer: Other SMTP
   - SMTP Host: smtp.hostinger.com
   - SMTP Port: 465
   - Encryption: SSL
   - Authentication: ON
   - SMTP Username: certificates@paintingolympics.in
   - SMTP Password: [Your email password]
   - Save Settings

3. **Test**
   - WP Mail SMTP → Email Test
   - Send test email to yourself

4. **Send Certificate**
   - Try sending a certificate
   - Should work now! ✅

---

## 📧 Alternative: Gmail SMTP (Quick Test)

### Your Gmail SMTP Settings:
```
SMTP Host: smtp.gmail.com
SMTP Port: 587
Encryption: TLS
Username: eshaanmanchanda01@gmail.com
Password: [16-character App Password]
```

### Setup Steps:

1. **Create Gmail App Password**
   - Go to: myaccount.google.com/security
   - Enable 2-Step Verification
   - Click "App passwords"
   - Generate password for "Mail"
   - Copy the 16-character code

2. **Configure WP Mail SMTP**
   - From Email: eshaanmanchanda01@gmail.com
   - Mailer: Other SMTP
   - SMTP Host: smtp.gmail.com
   - SMTP Port: 587
   - Encryption: TLS
   - Username: eshaanmanchanda01@gmail.com
   - Password: [16-character app password]

3. **Test & Send**

---

## 🔍 Quick Troubleshooting

**Problem:** SMTP connect() failed
- Check SMTP host spelling
- Try port 587 with TLS (instead of 465 with SSL)

**Problem:** Authentication failed
- Verify email password is correct
- For Gmail: Use App Password, not regular password

**Problem:** Still not working
- Contact Hostinger support: "Need help with SMTP for certificates@paintingolympics.in"
- Share the error message from WP Mail SMTP test

---

## ✅ Success Indicators

You'll know it's working when:
- WP Mail SMTP test email arrives in your inbox
- Certificate emails show status='sent' in database
- Logs show: "Email sent successfully on attempt 1"
- No more "Could not instantiate mail function" errors

---

**Full Guide:** See HOSTINGER-SMTP-SETUP-GUIDE.md for detailed instructions.
