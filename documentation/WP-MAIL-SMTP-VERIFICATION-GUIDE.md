# WP Mail SMTP Verification & Fix Guide

## 🚨 CRITICAL ISSUE IDENTIFIED

Your logs show:
```
[mailer_type] => mail
[smtp_host] => localhost
```

**This means WP Mail SMTP is NOT being used even though it's installed!**

---

## Why This Happens

WP Mail SMTP can be installed but not actually working if:
1. ❌ Mailer is set to "Default" instead of "Other SMTP"
2. ❌ "Force From Email" is disabled
3. ❌ "Force From Name" is disabled
4. ❌ SMTP settings are incomplete
5. ❌ Configuration was not saved properly

---

## ✅ Step-by-Step Fix (5 Minutes)

### Step 1: Access WP Mail SMTP Settings

1. Log into WordPress Admin
2. Go to **WP Mail SMTP → Settings** (in left sidebar)
3. You should see the configuration page

### Step 2: Configure General Settings

**Mail Section:**

1. **From Email:**
   - Enter: `abidihaya2000@gmail.com` or `certificates@paintingolympics.in`
   - ✅ **CHECK the box: "Force From Email"** ← CRITICAL!

2. **From Name:**
   - Enter: `Painting Olympics` or `Certificate System`
   - ✅ **CHECK the box: "Force From Name"** ← CRITICAL!

**Why "Force" is Critical:**
- Without "Force" checked, WordPress uses default PHP mail()
- WP Mail SMTP will be bypassed entirely
- You'll keep getting "mail function" errors

### Step 3: Select Mailer

**IMPORTANT:** Select the correct mailer!

**Option A: Using Hostinger Email (Recommended)**
- Select: **"Other SMTP"**
- DO NOT select "Default" or "mail"

**Option B: Using Gmail**
- Select: **"Gmail"** OR **"Other SMTP"**

### Step 4: Configure SMTP Settings

**For Hostinger Email:**
```
SMTP Host: smtp.hostinger.com
SMTP Port: 465
Encryption: SSL (or use Port 587 with TLS)

Enable Authentication: YES (toggle ON)
SMTP Username: certificates@paintingolympics.in (full email address)
SMTP Password: [Your Hostinger email password]
```

**For Gmail:**
```
SMTP Host: smtp.gmail.com
SMTP Port: 587
Encryption: TLS (or use Port 465 with SSL)

Enable Authentication: YES (toggle ON)
SMTP Username: abidihaya2000@gmail.com
SMTP Password: [16-character App Password - NOT your regular password]
```

**Gmail App Password Setup:**
1. Go to myaccount.google.com/security
2. Enable "2-Step Verification" if not enabled
3. Click "App passwords"
4. Generate password for "Mail"
5. Copy the 16-character password
6. Use THIS password (not your Gmail password)

### Step 5: Save Settings

1. Scroll to bottom
2. Click **"Save Settings"** button
3. Wait for "Settings saved successfully" message
4. **VERY IMPORTANT:** Refresh the page to ensure settings are loaded

### Step 6: Send Test Email

1. Scroll to **"Email Test"** tab at the top
2. Enter your email: `eshaanmanchanda01@gmail.com`
3. Click **"Send Email"**
4. Check your inbox (and spam folder)

**Expected Result:**
- ✅ "Test email was sent successfully!"
- ✅ Email appears in your inbox

**If Test Fails:**
- Check SMTP credentials
- Verify port and encryption match
- Ensure "Force" options are checked
- Try alternate port (587 vs 465)

### Step 7: Verify Configuration

After saving and testing, try sending a certificate:

**Check your error logs - you should NOW see:**
```
Certificate Generator: WP Mail SMTP is active and configured with mailer: smtp
[smtp_active] => 1
[smtp_settings] => Array
(
    [mailer] => smtp
    [smtp_host] => smtp.hostinger.com
    [smtp_port] => 465
    [from_email_force] => 1
    [from_name_force] => 1
)
```

**Instead of the old error:**
```
❌ [mailer_type] => mail
❌ [smtp_host] => localhost
```

---

## 🔍 Common Mistakes & Fixes

### Mistake #1: "Force" Options Not Checked
**Symptom:** Still using PHP mail() even though SMTP is configured
**Fix:** Go back and CHECK both "Force From Email" and "Force From Name"

### Mistake #2: Wrong Mailer Selected
**Symptom:** Settings look correct but still fails
**Fix:** Make sure "Other SMTP" is selected, NOT "Default" or "mail"

### Mistake #3: Incomplete SMTP Settings
**Symptom:** Test email fails immediately
**Fix:** Double-check all fields are filled in, especially:
- SMTP Host
- SMTP Port
- Authentication toggle is ON
- Username (full email address)
- Password

### Mistake #4: Wrong Port/Encryption Combination
**Symptom:** "SMTP connect() failed"
**Fix:** Use these combinations ONLY:
- Port 465 with SSL
- Port 587 with TLS
- (Don't mix them up!)

### Mistake #5: Using Gmail Password Instead of App Password
**Symptom:** "Authentication failed" with Gmail
**Fix:** You MUST use App Password for Gmail, not your regular password

---

## 📊 Verification Checklist

After configuration, verify these:

- [ ] WP Mail SMTP → Settings page shows "Other SMTP" selected
- [ ] "Force From Email" is CHECKED
- [ ] "Force From Name" is CHECKED
- [ ] SMTP Host is correct (smtp.hostinger.com or smtp.gmail.com)
- [ ] SMTP Port is correct (465 or 587)
- [ ] Encryption matches port (SSL with 465, TLS with 587)
- [ ] Authentication is ENABLED (toggle ON)
- [ ] Username is full email address
- [ ] Password is entered correctly
- [ ] "Save Settings" was clicked
- [ ] Test email was sent successfully
- [ ] Test email was received
- [ ] Certificate email now works

---

## 🎯 Quick Diagnosis

### Run a Test Certificate Email

1. Try sending a certificate
2. Check your error logs

**If you see:**
```
Certificate Generator: WP Mail SMTP is active but NOT properly configured.
Mailer is set to default PHP mail()
```

**Then:**
- Mailer is set to "Default" → Change to "Other SMTP"
- OR "Force" options are not checked → Check them

**If you see:**
```
Certificate Generator: WP Mail SMTP is active and configured with mailer: smtp
[smtp_host] => smtp.hostinger.com
```

**Then:**
- ✅ Configuration is correct!
- If still failing, check SMTP credentials
- Try the WP Mail SMTP test email function

---

## 🆘 Still Not Working?

### Check Enhanced Logs

Your plugin now logs detailed configuration info. Check for:

**Log Location:** Look in your error logs for:
```
Certificate Generator: Email configuration: Array
(
    [smtp_active] => ...
    [smtp_settings] => Array
    (
        [mailer] => ...
        [from_email_force] => ...
        [from_name_force] => ...
        [smtp_host] => ...
    )
)
```

This tells you EXACTLY what WP Mail SMTP is configured to do.

### If smtp_active is FALSE:
- WP Mail SMTP plugin might not be activated
- Or it's configured with "Default" mailer
- Check WordPress → Plugins → ensure WP Mail SMTP is active

### If smtp_active is TRUE but mailer is "mail":
- You selected "Default" instead of "Other SMTP"
- Go back to WP Mail SMTP settings
- Select "Other SMTP"
- Save settings

### If from_email_force is FALSE or not set:
- "Force From Email" box is unchecked
- Go back and CHECK it
- Save settings

---

## 📞 Getting Additional Help

### Contact Hostinger Support

If using Hostinger SMTP and it's not working:

**Live Chat Message:**
```
Hi, I'm trying to configure SMTP for my email: certificates@paintingolympics.in

I'm using:
- SMTP Host: smtp.hostinger.com
- Port: 465 with SSL

But getting connection errors. Can you verify:
1. Is SMTP enabled on my hosting plan?
2. Are these the correct settings?
3. Is my email account active?

My domain: paintingolympics.in
```

### Contact Gmail Support

If using Gmail and authentication fails:

1. Check: myaccount.google.com/security
2. Look for "Suspicious activity" or "Blocked sign-ins"
3. Allow "Less secure app access" (if available)
4. Make sure you're using App Password, not regular password

---

## 🎉 Success Indicators

You'll know it's working when:

1. ✅ WP Mail SMTP test email arrives in inbox
2. ✅ Certificate emails send successfully
3. ✅ Logs show:
   ```
   Certificate Generator: Email sent successfully on attempt 1
   [smtp_active] => 1
   [mailer] => smtp
   [smtp_host] => smtp.hostinger.com
   ```
4. ✅ Database shows status='sent' in wp_cert_email_logs
5. ✅ No more "Could not instantiate mail function" errors
6. ✅ Admin health notice shows healthy status

---

## 💡 Pro Tips

1. **Always use "Force" options** - They ensure WP Mail SMTP is actually used
2. **Test first** - Use WP Mail SMTP's test function before sending real certificates
3. **Use Hostinger for production** - More professional than Gmail
4. **Keep credentials secure** - Never share SMTP passwords
5. **Monitor logs** - Enhanced logging shows exactly what's happening
6. **Check spam folder** - First few emails might land in spam
7. **Whitelist sender** - Ask recipients to whitelist your email

---

## 📋 Summary

**The Problem:** WP Mail SMTP is installed but not being used because:
- "Force" options not checked
- Mailer set to "Default" instead of "Other SMTP"
- Configuration incomplete or incorrect

**The Solution:**
1. Select "Other SMTP" as mailer
2. Check "Force From Email"
3. Check "Force From Name"
4. Enter complete SMTP settings
5. Save and test

**Time Required:** 5 minutes

**Cost:** FREE (using your existing Hostinger or Gmail)

---

**After following this guide, your certificate emails will work!** 🚀

Check the enhanced logs after configuration - they'll tell you exactly if WP Mail SMTP is now being used correctly.
