# Bulk Email Sending Guide - Certificate Generator

## 🎉 Send 600-1000 Certificates Automatically!

This guide explains how to use the new **Rate-Limited Bulk Email System** to send hundreds or thousands of certificate emails safely.

---

## ✅ Features

- **Automatic Sending:** Set it and forget it - runs in background
- **Rate Limited:** Respects Hostinger's limits (80 emails/hour)
- **Safe & Reliable:** Retries failed emails automatically
- **Progress Tracking:** See exactly how many sent/pending
- **Pause/Resume:** Full control over the sending process
- **Unattended:** Close your browser - it keeps working

---

## 🚀 Quick Start (3 Steps)

### Step 1: Access Bulk Send Page

1. Log into **WordPress Admin**
2. Go to **Settings → Bulk Send Certificates**
3. You'll see the queue status dashboard

### Step 2: Start Bulk Send

1. Select post type: **Students**, **Teachers**, or **Schools**
2. Click **"Start Bulk Send"** button
3. System adds all certificates to queue
4. Sending begins automatically!

### Step 3: Monitor Progress

- Dashboard shows real-time progress
- Auto-refreshes every 30 seconds
- Estimated completion time displayed
- You can close the page - sending continues

---

## 📊 Dashboard Overview

### Queue Status Section

Shows current state of email queue:

- **Total in Queue:** Total number of emails
- **Pending:** Waiting to be sent
- **Sent:** Successfully delivered
- **Failed:** Will retry automatically (up to 3 times)
- **Progress Bar:** Visual percentage complete
- **ETA:** Estimated time remaining

### Rate Limiting Section

Shows hourly sending limits:

- **Emails Sent (Last Hour):** X / 80
- **Remaining This Hour:** How many you can send now
- **Can Send Now:** Green ✅ or Red ❌

### Queue Controls

- **⏸️ Pause Queue:** Temporarily stop sending
- **▶️ Resume Queue:** Continue sending
- **🔄 Refresh Status:** Update stats manually
- **🗑️ Clear Queue:** Remove all pending (use with caution!)

---

## ⏱️ Time Estimates

### Sending Speed
- **80 emails per hour** (safe rate to avoid limits)
- **~1 email every 45 seconds**
- **Automatic batches of 10 emails** every 8 minutes

### Total Time for Common Scenarios

| Number of Emails | Time Required |
|-----------------|---------------|
| 100 emails | ~1.5 hours |
| 500 emails | ~6.5 hours |
| 1000 emails | ~13 hours |
| 1500 emails | ~19 hours |

**Example:**
- Start at 8:00 AM
- Sending 1000 emails
- Complete by ~9:00 PM same day

---

## 🔧 How It Works (Behind the Scenes)

### The Process

1. **You Click "Start Bulk Send"**
   - All certificates added to database queue
   - System checks which ones haven't been sent
   - Skips duplicates automatically

2. **Background Processing Starts**
   - WordPress Cron runs every 5 minutes
   - Checks rate limits before sending
   - Sends batch of 10 emails
   - Waits 8 minutes
   - Repeats until queue is empty

3. **Rate Limiting Protection**
   - Tracks emails sent per hour
   - Never exceeds 80/hour (safe limit)
   - Auto-pauses if limit reached
   - Auto-resumes after 1 hour

4. **Error Handling**
   - Failed emails automatically retry
   - Up to 3 attempts per email
   - Detailed error logging
   - Admin notifications for issues

---

## 💡 Best Practices

### Before Starting

1. **Verify SMTP is Working**
   - Send test certificate to yourself first
   - Check it arrives successfully
   - Verify "From" address is correct

2. **Check Queue Status**
   - Make sure queue is empty
   - Clear old completed items if needed

3. **Estimate Time**
   - Calculate how long it will take
   - Plan accordingly (don't expect instant sending)

### During Sending

1. **Don't Restart Multiple Times**
   - Let it finish once started
   - Don't click "Start" again while queue is active
   - System prevents duplicates anyway

2. **Monitor Periodically**
   - Check dashboard every few hours
   - Look for high failure rates
   - Verify progress is happening

3. **Use Pause if Needed**
   - Pause for maintenance/updates
   - Pause if seeing errors
   - Always resume when ready

### After Completion

1. **Verify Success**
   - Check "Sent" count matches expected
   - Review failed emails (if any)
   - Check a few recipient inboxes

2. **Clean Up**
   - System auto-cleans after 30 days
   - Or manually clear completed items

3. **Review Logs**
   - Settings → Email Logs
   - Check for patterns in failures
   - Address any issues for next time

---

## ⚠️ Common Issues & Solutions

### Issue: "Rate limit reached"

**Symptom:** Message says to wait X hours

**Cause:** You've sent 80 emails in the last hour

**Solution:**
- Just wait - it will auto-resume
- This is normal and expected
- Not a problem, just rate limiting working

**Prevention:**
- Don't send test emails right before bulk send
- Clear queue after testing

---

### Issue: Many Failed Emails

**Symptom:** High percentage of failures

**Causes & Solutions:**

**1. SMTP Not Configured Properly**
- Check WP Mail SMTP settings
- Verify username = from email
- Test with WP Mail SMTP test function

**2. Wrong Email Addresses**
- Check student/teacher data
- Invalid emails can't be sent
- Clean up data before bulk send

**3. Hostinger Rate Limit Hit Hard**
- You might have hit daily limit
- Contact Hostinger support
- Ask to increase limits
- Consider upgrading hosting plan

---

### Issue: Queue Stuck / Not Processing

**Symptom:** Pending count not decreasing

**Checks:**

**1. Is Queue Paused?**
- Dashboard shows "PAUSED" status
- Click "Resume Queue" button

**2. Is WP-Cron Working?**
```
Check: Settings → Site Health → Cron
Should show: Working properly
```

**3. Server Issues?**
- Check server error logs
- Contact hosting support
- Might need WP-Cron fix

**Solution:**
```
Add to wp-config.php:
define('ALTERNATE_WP_CRON', true);
```

---

### Issue: Sending Too Slow

**Symptom:** Taking longer than expected

**Causes:**

1. **Rate Limiting Working Correctly**
   - 80/hour is the safe limit
   - This prevents Hostinger blocks
   - Can't go faster without issues

2. **Want Faster Sending?**

**Options:**

**A. Upgrade Hosting**
- Business/Premium plans: higher limits
- Contact Hostinger for rate increase

**B. Use Third-Party Service**
- SendGrid: 100/day free, then paid
- Mailgun: 5,000/month free (first 3 months)
- Amazon SES: $0.10 per 1,000 emails

**C. Split Across Multiple Days**
- Send 500 today, 500 tomorrow
- Spreads load over time

---

## 🎯 Advanced Features

### Custom Rate Limits

Want to adjust sending speed? Contact support to modify:

```php
$config = [
    'emails_per_hour' => 80,  // Adjust (max 100-300)
    'batch_size' => 10,       // Emails per batch
    'batch_delay' => 480      // Seconds between batches
];
```

### Priority Sending

Some emails more urgent? Can add priority:
- High priority (1-3): Sent first
- Normal priority (5): Default
- Low priority (7-10): Sent last

### Scheduled Sending

Want to send at specific time?
- Add to queue with scheduled_time
- Will send at that time automatically

---

## 📞 Support

### Check Logs

**Email Logs:**
- Settings → Email Logs
- Filter by status: Failed
- See error messages

**PHP Error Logs:**
- Check server error_log
- Look for "Certificate Generator:" messages

### Contact Hostinger

If rate limits are blocking you:

```
Message to Hostinger:
"Hi, I need to send certificate emails to students.
Currently limited to ~100/hour.
Can you increase my email sending limit?
Domain: paintingolympics.in
Email: certificates@paintingolympics.in"
```

### Get Plugin Support

If bulk sending isn't working:
1. Check error logs
2. Verify SMTP working
3. Test single certificate send first
4. Share error messages for help

---

## ✅ Success Checklist

Before bulk sending:

- [ ] SMTP configured and tested
- [ ] WP Mail SMTP test email works
- [ ] Sent single certificate successfully
- [ ] Checked queue is empty
- [ ] Estimated time is acceptable
- [ ] Have time for process to complete

During sending:

- [ ] Dashboard shows progress
- [ ] No high failure rate
- [ ] Rate limit status is green
- [ ] Queue is not paused

After completion:

- [ ] All certificates sent
- [ ] Checked recipient inboxes
- [ ] Reviewed failed emails (if any)
- [ ] Cleaned up queue

---

## 📊 Example Workflow

**Scenario:** Send 1000 student certificates

### Morning (8:00 AM):

1. ✅ Log into WordPress
2. ✅ Go to Settings → Bulk Send Certificates
3. ✅ Verify queue is empty
4. ✅ Select "Students" post type
5. ✅ Click "Start Bulk Send"
6. ✅ See: "1000 emails queued successfully"
7. ✅ Dashboard shows: 0 of 1000 sent (0%)

### Mid-Day (12:00 PM):

1. ✅ Check dashboard
2. ✅ See: 300 of 1000 sent (30%)
3. ✅ ETA: 9 hours remaining
4. ✅ Rate limit: 80/80 used this hour
5. ✅ Status: Waiting for hour reset

### Evening (6:00 PM):

1. ✅ Check dashboard
2. ✅ See: 750 of 1000 sent (75%)
3. ✅ ETA: 3 hours remaining
4. ✅ Everything running smoothly

### Night (9:00 PM):

1. ✅ Check dashboard
2. ✅ See: 1000 of 1000 sent (100%) 🎉
3. ✅ Failed: 5 (will retry automatically)
4. ✅ Success!

---

## 🎉 Congratulations!

You can now send hundreds or thousands of certificate emails safely and automatically!

**Key Points to Remember:**
- Sending takes time (80/hour limit)
- System handles everything automatically
- You don't need to watch it
- Check back periodically
- Failed emails retry automatically

**You're all set!** 🚀

Start your bulk send and let the system do the work!
