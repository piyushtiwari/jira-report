# jira-report
Generate Custom Jira Reports 

Set Up Steps-
1. Create a file named `htdocs/.env` duplicate to `htdocs/.env.example` and populate the values of JIRA_DOMAIN, JIRA_USERNAME and JIRA_PASSWORD
2. Add `192.168.57.101  jira-report.dev www.jira-report.dev` in Host file. In Linux/Mac, host file location is `/etc/hosts`
3. Run `vagrant up` to create a vm. You must have virtualbox installed.
4. SSH into the newly created VM by `vagrant ssh`
4. Install the composer dependencies by
```
cd /var/www/htdocs/
composer install
```

You can now view the Sprint Reports at http://jira-report.dev/?sprint=73
