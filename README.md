# GitLabBundle

A Kimai 2 plugin, which send duration of cards to GitLab spend issues of timesheet.

## Installation

First clone it to your Kimai installation `plugins` directory:
```
cd /kimai/var/plugins/
git clone https://github.com/LibreCode/GitLabBundle.git
```

And then rebuild the cache: 
```
cd /kimai/
bin/console cache:clear
bin/console cache:warmup
```

You could also [download it as zip](https://github.com/LibreCode/GitLabBundle/archive/master.zip) and upload the directory via FTP:

```
/kimai/var/plugins/
├── GitLabBundle
│   ├── GitLabBundle.php
|   └ ... more files and directories follow here ... 
```
