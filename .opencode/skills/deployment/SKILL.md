---
name: deployment
description: Use when deploying or touching production readiness: migrations, queues (database driver + worker), build, post-deploy checks, rate limiting, logging verification. Full guides in docs/deployment/.
---

# Deployment Skill

## Quick Reference
- Full deploy guide: `docs/deployment/linux-cloud.md`
- Production checklist: `docs/deployment/production-checklist.md`

## Key Commands
```bash
php artisan migrate
npm run build
php artisan test
```

## Queue
- Driver: `database`
- Ensure worker active + restart on deploy
- Booking notifications use same queue

## Critical Checks
- Run migrations (6 new booking tables + alter appointments for online booking)
- Verify worker is processing jobs
- Check post-deploy: access public booking page, create appointment, verify email
- No new scheduler tasks for booking

## Rate Limiting
- Public booking routes: `throttle:30,1`

## Logging
- Post-deploy verify `booking.created` events in logs
