# Quick Start: Send 1000 Certificates

## 🚀 5-Minute Setup

---

## Step 1: Verify SMTP Works (2 minutes)

1. Go to **WP Mail SMTP → Settings**
2. Check these are configured:
   - ✅ From Email: certificates@paintingolympics.in
   - ✅ Force From Email: CHECKED
   - ✅ SMTP Host: smtp.hostinger.com
   - ✅ Username: certificates@paintingolympics.in

3. Go to **WP Mail SMTP → Email Test**
4. Send test to yourself
5. Verify it arrives ✅

**If test fails:** Fix SMTP first before bulk sending!

---

## Step 2: Start Bulk Send (1 minute)

1. Go to **Settings → Bulk Send Certificates**
2. Select **Students** (or Teachers/Schools)
3. Click **"Start Bulk Send"**
4. See message: "X emails queued successfully"

✅ **Done! Sending has started!**

---

## Step 3: Monitor Progress (Optional)

**You can close your browser - it keeps working!**

To check progress:
1. Go back to **Settings → Bulk Send Certificates**
2. See:
   - Pending: X
   - Sent: Y
   - Progress: Z%
   - ETA: Remaining time

---

## ⏱️ What Happens Next?

### Automatic Process:

**Every 5 minutes:**
- System checks queue
- Checks rate limits
- Sends batch of 10 emails
- Waits 8 minutes
- Repeats

**Speed:**
- 80 emails per hour
- ~1 email every 45 seconds
- 1000 emails = ~13 hours

**Example Timeline:**
```
8:00 AM - Start bulk send (1000 emails)
8:05 AM - First 10 sent
8:13 AM - Next 10 sent
9:00 AM - ~75 sent (first hour complete)
9:00 PM - 1000 sent (complete!) 🎉
```

---

## 📊 Dashboard Features

### Queue Status
- **Pending:** Waiting to send
- **Sent:** Successfully delivered ✅
- **Failed:** Will retry automatically

### Progress Bar
- Shows % complete
- Visual indicator

### Rate Limiting
- Shows emails sent this hour
- Remaining capacity
- Auto-manages limits

### Controls
- ⏸️ **Pause:** Stop sending temporarily
- ▶️ **Resume:** Continue sending
- 🗑️ **Clear:** Remove all pending

---

## ⚠️ Important Notes

### Don't Panic If...

**"Rate limit reached"**
- ✅ Normal! This is expected
- System will auto-resume in 1 hour
- Just rate limiting working correctly

**"Queued X emails"**
- ✅ All emails added to queue
- Will send gradually over time
- Don't click "Start" again

**Dashboard shows high pending**
- ✅ Normal at the beginning
- Will decrease over time
- Check back in a few hours

### Do This:

✅ Let it run unattended
✅ Check progress occasionally
✅ Wait for completion message
✅ Verify sent count matches expected

### Don't Do This:

❌ Click "Start" multiple times
❌ Keep refreshing every minute
❌ Panic if not instant
❌ Clear queue unless emergency

---

## 🎯 Success Indicators

**Everything is working if you see:**

```
Dashboard shows:
✓ Pending: Decreasing
✓ Sent: Increasing
✓ Progress: Going up
✓ Status: Running
✓ Can Send: ✅ Yes (or temporarily no - will reset)
```

**Check back and see:**
- After 1 hour: ~75 sent
- After 6 hours: ~450 sent
- After 13 hours: 1000 sent ✅

---

## 🆘 Need Help?

### If Nothing is Sending:

1. **Check SMTP:**
   - WP Mail SMTP → Email Test
   - Must work first!

2. **Check Queue:**
   - Is it paused? Click Resume
   - Any emails in pending?

3. **Check Rate Limit:**
   - Shows "can send now"?
   - If no, wait for reset

### If Many Failures:

1. **Check Email Logs:**
   - Settings → Email Logs
   - Look at error messages

2. **Fix SMTP:**
   - Username must match From Email
   - Verify password is correct

3. **Test Single Email:**
   - Send one certificate manually
   - Fix issues before bulk

---

## 📋 Quick Reference

### Time Estimates

| Emails | Time |
|--------|------|
| 100 | ~1.5 hours |
| 500 | ~6.5 hours |
| 1000 | ~13 hours |

### Rate Limits

- **Per Hour:** 80 emails (safe limit)
- **Per Batch:** 10 emails
- **Batch Delay:** 8 minutes
- **Auto-Resume:** Yes

### Actions

- **Start:** Settings → Bulk Send Certificates
- **Monitor:** Same page (auto-refreshes)
- **Pause:** Click Pause button
- **Resume:** Click Resume button

---

## ✅ You're All Set!

**That's it! Three simple steps:**

1. ✅ Verify SMTP works
2. ✅ Click "Start Bulk Send"
3. ✅ Walk away and let it work

**The system handles everything else automatically!**

---

## 🎉 Expected Result

**After completion:**
- ✅ 1000 emails sent
- ✅ All certificates delivered
- ✅ Students receive their certificates
- ✅ You didn't have to do anything!

**Check:**
- Settings → Email Logs
- Should show 1000 "sent" status emails
- Few failures (auto-retried)

---

**For detailed information, see: BULK-EMAIL-GUIDE.md**

**Ready? Go to Settings → Bulk Send Certificates and get started!** 🚀
