#  Openhab SQL 0.4
  (c) 23.06.2020 by Mario Wagner

## Installation
### install php modules:
`apt install php-cli php-mysql git`
### download php script
```bash
# git clone https://github.com/mmwwagner/openhabsql
# cd openhabsql
```
### create file openhabsql.config.php:
```php
<?php
  $database['username']='username';
  $database['password']='password';
  $database['host']='localhost';
  $database['database']='openhab';
?>
```
## Help

`php openhabsql.php [options] [command]`

### Options

```bash
 -h --help           : help
 -d --debug          : debug, can be combined with any other options
 -c --csv            : displays output in csv format
 -t <days>           : time since in days
 -i <id>             : table id
 -f <filter>         : filters item names like 'level%temp'
 -s <column>         : sort table colums, 1=first col, -1=first col descending
```

### Commands

- listTables          : list of all tables with id and name
- listLastEntries     : list of last entries of all items
- listUnusedEntries   : list of unused entries of all items, needs option -t
- removeUnusedEntries : removes unused tables, needs option -t
- summarizeEntry      : summary all states of one item, needs option -i
- summarizeEntries    : summary all states of all items


Attention: removeUnusedEntries deletes tables without asking. 
           Be careful and make a mysqldump in advance!


## Release Notes

### V0.4 23.06.2020
- ask before deleting in command removeUnusedEntries
- optimized output in command removeUnusedEntries