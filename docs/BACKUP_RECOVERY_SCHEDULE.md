# Data Backup and Recovery Schedule

**PUPTracker Violation System**

## Overview

This document establishes the backup and disaster recovery procedures for the PUPTracker Violation System, ensuring data protection, integrity, and availability in case of system failures or data loss incidents.

**Last Updated:** July 31, 2026
**Version:** 1.0
**Effective Date:** August 1, 2026

---

## 1. Backup Strategy Overview

### 1.1 Backup Approach

The PUPTracker system employs a **3-2-1 Backup Strategy**:

- **3** copies of data (production + 2 backups)
- **2** different storage media types (local + cloud)
- **1** offsite copy (geographic redundancy)

### 1.2 Data Classification

| Classification | Importance               | Backup Frequency | Retention | Examples                                    |
| -------------- | ------------------------ | ---------------- | --------- | ------------------------------------------- |
| **Critical**   | Essential for operations | Every 6 hours    | 30 days   | User accounts, violation records, sanctions |
| **Important**  | Business operations      | Daily            | 14 days   | Student records, announcements, audit logs  |
| **Standard**   | Regular operations       | Weekly           | 8 weeks   | Session data, temporary files, logs         |
| **Archive**    | Historical data          | Monthly          | 1 year    | Old reports, archived records               |

---

## 2. Backup Schedule

### 2.1 Full Backup Schedule

| Type                 | Frequency     | Time (UTC+8)               | Duration | Storage      | Retention |
| -------------------- | ------------- | -------------------------- | -------- | ------------ | --------- |
| **Full Database**    | Daily         | 02:00 - 03:00              | ~1 hour  | 500GB+       | 30 days   |
| **Full File System** | Weekly        | Saturday 03:00             | ~2 hours | 2TB+         | 8 weeks   |
| **Incremental**      | Every 6 hours | 02:00, 08:00, 14:00, 20:00 | ~10 min  | 200GB        | 7 days    |
| **Transaction Log**  | Every 30 min  | Continuous                 | N/A      | 50GB         | 24 hours  |
| **Offsite Sync**     | Daily         | 04:00                      | ~1 hour  | Cloud        | 30 days   |
| **Archive Backup**   | Monthly       | 1st of month, 02:00        | Variable | Tape/Archive | 1 year    |

### 2.2 Daily Backup Timeline

```
00:00 - Database warm backup starts
02:00 - Full database backup starts (transaction lock begins)
02:30 - File system backup starts
02:45 - Transaction log backup starts
03:00 - Full backup completion
03:15 - Offsite replication begins
04:00 - All backups completed, verification begins
```

---

## 3. Backup Components

### 3.1 Database Backup

**Database:** MySQL/MariaDB
**Size:** ~100-200GB
**Backup Method:** mysqldump with binary log position tracking

```sql
-- Daily full backup script
mysqldump \
  --single-transaction \
  --quick \
  --lock-tables=false \
  --verbose \
  --routines \
  --triggers \
  --all-databases > backup_$(date +%Y%m%d_%H%M%S).sql
```

**Backup Files:**

- Location: `/backups/database/daily/`
- Naming: `database_YYYYMMDD_HHMMSS.sql.gz`
- Compression: gzip (reduces size 70-80%)

### 3.2 File System Backup

**Components Backed Up:**

- `/app/` - Application code
- `/storage/` - User uploads, session data
- `/config/` - Configuration files
- `/resources/` - Views, assets
- `/database/migrations/` - Database schema changes
- `/public/uploads/` - Student photos, documents

**Excluded Directories:**

- `/node_modules/` (can be regenerated)
- `/vendor/` (can be regenerated via composer)
- `/bootstrap/cache/` (application cache)
- `/storage/logs/` (application logs, kept separately)
- `/temp/` (temporary files)

**Backup Method:** Incremental tar/rsync

```bash
# Full backup
tar -czf backup_files_full_$(date +%Y%m%d).tar.gz \
  --exclude=node_modules \
  --exclude=vendor \
  --exclude=bootstrap/cache \
  /var/www/pup-tracker

# Incremental using rsync
rsync -avz --delete \
  --exclude=node_modules \
  --exclude=vendor \
  /var/www/pup-tracker/ \
  /backups/filesystem/incremental/
```

### 3.3 Configuration Backup

Critical files backed up hourly:

- `.env` (encrypted) - Environment variables
- `config/*.php` - Application configuration
- `.htaccess` - Web server rules
- `nginx.conf` - Nginx configuration
- Database credentials (encrypted)

### 3.4 Audit Log Backup

**Frequency:** Daily
**Retention:** 1 year
**Components:**

- Application audit logs
- Database transaction logs
- System logs
- Access logs

---

## 4. Storage Infrastructure

### 4.1 Primary Storage (Local)

| Tier             | Capacity | Location         | Purpose                        | RPO      | RTO     |
| ---------------- | -------- | ---------------- | ------------------------------ | -------- | ------- |
| **Hot Storage**  | 500GB    | NAS on-site      | Last 7 days, immediate restore | 6 hours  | 15 min  |
| **Warm Storage** | 2TB      | Secondary server | Last 30 days, quick restore    | 24 hours | 1 hour  |
| **Cold Storage** | Archive  | Off-site vault   | Long-term archive, rare access | Monthly  | 4 hours |

### 4.2 Cloud Storage (Secondary)

**Provider:** AWS S3 / Azure Blob / Google Cloud Storage
**Redundancy:** Geo-redundant storage (cross-region)
**Encryption:** AES-256 at rest, TLS in transit

**Tier Structure:**

- **S3 Standard:** Last 30 days backups (warm)
- **S3 Intelligent-Tiering:** 30-90 days (transitioning)
- **S3 Glacier:** 90+ days (archive)

### 4.3 Storage Allocation

```
Total Backup Capacity: 3TB
├── Hot Backup (7 days): 500GB
├── Warm Backup (23 days): 1TB
├── Cloud Backup (30 days): 800GB
└── Archive (yearly): 700GB
```

---

## 5. Backup Verification Procedures

### 5.1 Automated Verification

**Performed:** Immediately after each backup

1. **File Integrity Check**

    ```bash
    md5sum backup_file.sql.gz > backup_file.sql.gz.md5
    md5sum -c backup_file.sql.gz.md5
    ```

2. **Database Integrity**

    ```sql
    -- Check database integrity
    CHECK TABLE table_name;
    SHOW ENGINE INNODB STATUS;
    ```

3. **Backup Completeness**
    - Verify backup file size (within ±10% of expected)
    - Confirm all tables included
    - Verify compression ratio

### 5.2 Periodic Test Restores

| Frequency         | Scope                        | Procedure                               |
| ----------------- | ---------------------------- | --------------------------------------- |
| **Weekly**        | Test 1 random database table | Restore to staging, verify data         |
| **Monthly**       | Full database restore        | Restore to separate instance, run tests |
| **Quarterly**     | Full system restore          | Complete DR exercise, measure RTO       |
| **Semi-Annually** | Full offsite backup restore  | Test cloud backup accessibility         |

**Test Restore Checklist:**

- [ ] Backup accessible and readable
- [ ] Restore completes without errors
- [ ] Data integrity verified (row count, checksums)
- [ ] All foreign keys valid
- [ ] Application starts successfully
- [ ] Critical features functional
- [ ] Performance acceptable
- [ ] Test results documented

---

## 6. Recovery Time Objectives (RTO) and Recovery Point Objectives (RPO)

### 6.1 Service Level Targets

| Incident Type            | RTO      | RPO      | Backup Used               | Notification  |
| ------------------------ | -------- | -------- | ------------------------- | ------------- |
| **Disk Failure**         | 15 min   | 6 hours  | Hot backup                | Immediate     |
| **Corruption**           | 30 min   | 24 hours | Warm backup               | Within 1 hour |
| **Malware/Breach**       | 2 hours  | 24 hours | Daily backup pre-incident | Within 30 min |
| **Ransomware**           | 4 hours  | 1 week   | Multiple backups          | Within 15 min |
| **Catastrophic Failure** | 24 hours | 24 hours | Offsite backup            | Within 1 hour |

### 6.2 Recovery Procedures

**Stage 1: Assessment (0-15 minutes)**

- Identify incident type and scope
- Assess impact on operations
- Determine recovery point needed
- Activate recovery team

**Stage 2: Preparation (15-30 minutes)**

- Retrieve appropriate backup
- Prepare recovery environment
- Verify backup integrity
- Notify stakeholders

**Stage 3: Recovery (30 min - 4 hours)**

- Restore database from backup
- Restore file system
- Restart services
- Verify system functionality

**Stage 4: Verification (4-6 hours)**

- Run smoke tests
- Verify data accuracy
- Confirm all services operational
- Document recovery details

**Stage 5: Post-Recovery (6+ hours)**

- Restore from most recent backup post-incident
- Verify data consistency
- Return to normal operations
- Conduct incident review

---

## 7. Disaster Recovery Plan

### 7.1 Disaster Recovery Sites

**Primary Site:** Production data center (on-campus)
**Secondary Site:** Cloud-hosted replica (AWS/Azure)
**Tertiary Site:** Offsite tape storage (secure vault)

### 7.2 DR Exercise Schedule

| Frequency         | Type             | Scope                    | Resources  |
| ----------------- | ---------------- | ------------------------ | ---------- |
| **Monthly**       | Tabletop         | DR procedures review     | 1-2 hours  |
| **Quarterly**     | Partial failover | Single system component  | 4-6 hours  |
| **Semi-Annually** | Full failover    | Complete system recovery | 8-12 hours |
| **Annually**      | Full DR test     | End-to-end recovery      | 24 hours   |

### 7.3 DR Contact List

| Role           | Name   | Phone   | Email   | Backup        |
| -------------- | ------ | ------- | ------- | ------------- |
| DR Coordinator | [Name] | [Phone] | [Email] | [Backup Name] |
| Database Admin | [Name] | [Phone] | [Email] | [Backup Name] |
| System Admin   | [Name] | [Phone] | [Email] | [Backup Name] |
| IT Manager     | [Name] | [Phone] | [Email] | [Backup Name] |
| CTO            | [Name] | [Phone] | [Email] | [Backup Name] |

---

## 8. Backup Restore Procedures

### 8.1 Full Database Restore

```bash
#!/bin/bash
# Full Database Restore Script

BACKUP_FILE=$1
BACKUP_DATE=$(date '+%Y%m%d_%H%M%S')

# 1. Verify backup integrity
echo "Verifying backup integrity..."
md5sum -c "${BACKUP_FILE}.md5" || { echo "Backup corrupted!"; exit 1; }

# 2. Stop application (optional, for consistency)
sudo systemctl stop apache2
sleep 5

# 3. Backup current database
echo "Backing up current database..."
mysqldump --single-transaction --all-databases | gzip > current_backup_${BACKUP_DATE}.sql.gz

# 4. Restore from backup
echo "Restoring database from backup..."
gunzip < "${BACKUP_FILE}" | mysql -u root -p

# 5. Verify restoration
echo "Verifying restoration..."
mysql -e "SELECT COUNT(*) as table_count FROM information_schema.TABLES WHERE TABLE_SCHEMA != 'information_schema' AND TABLE_SCHEMA != 'mysql' AND TABLE_SCHEMA != 'performance_schema';"

# 6. Restart application
echo "Restarting application..."
sudo systemctl start apache2
php artisan cache:clear

echo "Database restore completed at ${BACKUP_DATE}"
```

### 8.2 Partial Table Restore

```sql
-- Restore specific table from backup
CREATE TABLE table_name_temp LIKE table_name;
-- Load from backup dump
SOURCE backup_file_partial.sql;
-- Compare and merge if needed
```

### 8.3 File System Restore

```bash
# Extract files from backup
tar -xzf backup_files_$(date +%Y%m%d).tar.gz -C /restore/path/

# Or using rsync
rsync -avz /backups/filesystem/incremental/ /restore/path/
```

---

## 9. Backup Monitoring and Reporting

### 9.1 Daily Backup Report

**Sent To:** IT Management, Security Team
**Time:** 06:00 AM daily

**Report Contents:**

- Backup completion status (✓/✗)
- Backup size (GB)
- Backup duration (minutes)
- Success/failure reason (if failed)
- Next scheduled backup time
- Critical alerts (if any)

### 9.2 Backup Dashboard

**Monitored Metrics:**

- Last successful backup timestamp
- Next scheduled backup countdown
- Backup success rate (7-day rolling)
- Backup storage utilization (%)
- Recovery time estimate

### 9.3 Failure Alerts

**Threshold:** If backup fails or takes 2x expected duration
**Alert Method:** Email, SMS, Slack
**Escalation:**

- If failure persists 2 hours → Page on-call DBA
- If failure persists 4 hours → Page IT Manager

---

## 10. Compliance and Documentation

### 10.1 Regulatory Compliance

**Applicable Standards:**

- ISO/IEC 27001 (Information Security Management)
- ISO/IEC 27035 (Incident Management)
- NIST Cybersecurity Framework (Data Protection)
- PCI DSS (if processing payment data)
- GDPR (if serving EU users)

**Compliance Checklist:**

- [ ] Backup encryption enabled
- [ ] Access controls documented
- [ ] Regular restore tests performed
- [ ] Audit trail maintained
- [ ] Data retention policy enforced
- [ ] Disaster recovery tested
- [ ] Compliance audits scheduled

### 10.2 Documentation and Logs

**Maintained Documents:**

- Backup schedule (this document)
- Backup procedures manual
- Recovery runbooks
- Disaster recovery plan
- Incident response procedures
- Compliance certification

**Audit Trail:**

```sql
-- Backup audit log table
CREATE TABLE backup_audit_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    backup_id VARCHAR(100),
    backup_type ENUM('Full', 'Incremental', 'Differential'),
    start_time DATETIME,
    end_time DATETIME,
    backup_size_gb DECIMAL(10, 2),
    file_count INT,
    status ENUM('Success', 'Failed', 'Partial'),
    checksum VARCHAR(255),
    restored BOOLEAN DEFAULT FALSE,
    restore_date DATETIME,
    verified BOOLEAN DEFAULT FALSE,
    verify_date DATETIME,
    notes TEXT,
    performed_by VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### 10.3 Annual Review

**Review Schedule:** Last week of June annually
**Attendees:** IT Director, Database Admin, Security Lead, Compliance Officer

**Review Items:**

- Backup retention policy effectiveness
- Storage capacity requirements
- Recovery procedures updates
- Technology upgrades needed
- Compliance status
- Incident lessons learned
- Budget allocation

---

## 11. Emergency Contacts and Escalation

### 11.1 Backup Escalation Process

```
LEVEL 1 (Backup Admin, 0-1 hour)
  ↓ (Unresolved)
LEVEL 2 (DBA Lead, 1-2 hours)
  ↓ (Unresolved)
LEVEL 3 (IT Manager, 2-4 hours)
  ↓ (Unresolved)
LEVEL 4 (CTO, 4+ hours)
```

### 11.2 Critical Contacts

**24/7 On-Call Support:**

- Main: (02) 9999-8888 ext. Security
- DBA On-Call: [Phone Number]
- Emergency: [Emergency Number]

**Email Escalation:**

- Backup Issues: backups@pup.edu.ph
- Security Issues: security@pup.edu.ph
- Urgent Issues: urgent-support@pup.edu.ph

---

## 12. Appendix

### 12.1 Backup Commands Reference

```bash
# MySQL Full Backup
mysqldump -u root -p --all-databases | gzip > backup.sql.gz

# Incremental with Rsync
rsync -avz --delete /source/ /destination/

# Generate Checksum
md5sum backup_file > backup_file.md5

# List Backup Contents
tar -tzf backup.tar.gz

# Test Backup Restoration (dry-run)
tar -tzf backup.tar.gz > /dev/null && echo "Backup OK"
```

### 12.2 Recovery Time Estimation

**Database Size → Recovery Time:**

- 1-10 GB: 5-10 minutes
- 10-50 GB: 10-30 minutes
- 50-100 GB: 30-60 minutes
- 100-500 GB: 1-4 hours
- 500+ GB: 4-8 hours

(Assuming gigabit network and modern hardware)

### 12.3 Backup Capacity Planning

**Annual Growth Estimate:** 15-20%
**Current Capacity:** 3TB
**Capacity Review:** Quarterly
**Expansion Timeline:** When reaching 80% utilization

---

**Document Version Control:**

| Version | Date       | Changes          | Author  |
| ------- | ---------- | ---------------- | ------- |
| 1.0     | 2026-07-31 | Initial document | Copilot |

**Next Review Date:** August 1, 2027
**Last Reviewed:** July 31, 2026
