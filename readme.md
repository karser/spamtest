# spamtest

- Test your Email Deliverability by schedule and receive email reports.
- See if your emails are being delivered to Inbox or Spam folder at Gmail, Outlook and etc.

## Prepare accounts.json file
Define your SMTP accounts here in the following format
```
[
  {
    "note": "gsuite my email",
    "dsn": "smtp://my@email.com:pA$$wOrD@smtp.gmail.com:587",
    "fromName": "Sender name",
    "fromEmail": "my@email.com"
  }
]
```

## Getting started with docker-compose

### Verify accounts without creating a glockapps test
First you want to make sure that your accounts config is correct and emailable.
```
version: "3.2"

services:
  spamtest:
    image: karser/spamtest:latest
    environment:
      ACCOUNTS_PATH: '/accounts.json'
      SUBJECT: 'Your test email subject'
      BODY_PATH: '/body.html'
      EMAIL: 'your@email.com,your-second@email.com'
    volumes:
      - ./body.html:/body.html
      - ./accounts.json:/accounts.json
    command: ['bin/console', 'app:spamtest', '--verify']
```

### Run a glockapps tests with email report by a cron schedule once a week
```
version: "3.2"

services:
  spamtest:
    image: karser/spamtest:latest
    restart: unless-stopped
    environment:
      GLOCKAPPS_KEY: 'XXXXXXXXX'
      ACCOUNTS_PATH: '/accounts.json'
      SUBJECT: 'Your test email subject'
      BODY_PATH: '/body.html'
      REPORT_DSN: 'smtp://my@email.com:pA$$wOrD@smtp.gmail.com:587'
      REPORT_FROM_EMAIL: 'my@email.com'
      REPORT_FROM_NAME: 'Spamtest'
      EMAIL: 'your@email.com,your-second@email.com'
      CRON_CONFIG: |
        0 0 * * 1 /var/app/bin/console app:spamtest >> /var/log/cron.log 2>&1
        0 8 * * 1 /var/app/bin/console app:report >> /var/log/cron.log 2>&1
    volumes:
      - ./body.html:/body.html
      - ./accounts.json:/accounts.json
    command: ['/usr/local/bin/cron-entrypoint']
```


## Getting started without docker

### Clone the repo and install dependencies
```
git clone
composer install
```

### Verify accounts without creating a glockapps test
First you want to make sure that your accounts config is correct and emailable.
```
bin/console app:spamtest \
  --verify --email=your@email.com \
  --accounts-path=/path/to/accounts.json \
  --subject='Your test email subject' \
  --body-path=/path/to/body.html
```

### Run a glockapps test
```
bin/console app:spamtest \
  --glockapps-key=XXXXXXXXX
  --accounts-path=/path/to/accounts.json \
  --subject='Your test email subject' \
  --body-path=/path/to/body.html
```

### Send email report
```
bin/console app:spamtest \
  --glockapps-key=XXXXXXXXX
  --accounts-path=/path/to/accounts.json \
  --report-dsn='smtp://my@email.com:pA$$wOrD@smtp.gmail.com:587' \
  --report-from-email=my@email.com \
  --report-from-name=Spamtest \
```