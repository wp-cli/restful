RESTful WP-CLI makes [WP REST API](http://v2.wp-api.org/) endpoints available as [WP-CLI](http://wp-cli.org/) commands. As WordPress becomes more of an application framework embedded into the web, RESTful WP-CLI enables WP-CLI users to interact with a given WordPress install through a higher-level abstraction. For instance, on an eCommerce website, instead of having to know data is stored as `wp post list --post_type=edd_product`, RESTful WP-CLI exposes the properly-modeled data at `wp rest product list`.

Here's an overview of how RESTful WP-CLI works, in two parts.

### 1. Auto-discovers WP REST API endpoints from any WordPress site running WordPress 4.4 or higher

Target a specific WordPress install with `--path=<path>`, `--ssh=<host>`, or `--http=<domain>`:

```
# The `--path=<path>` global parameter tells WP-CLI to interact with a WordPress install at a given path.
$ wp --path=/srv/www/wordpress-develop.dev/src rest
usage: wp rest attachment <command>
   or: wp rest category <command>
   or: wp rest comment <command>
   or: wp rest page <command>
   or: wp rest page-revision <command>
   or: wp rest post <command>
   or: wp rest post-revision <command>
   or: wp rest status <command>
   or: wp rest tag <command>
   or: wp rest taxonomy <command>
   or: wp rest type <command>
   or: wp rest user <command>

# The `--http=<domain>` global parameter tells WP-CLI to auto-discover endpoints over HTTP.
$ wp --http=www.wired.com rest
usage: wp rest attachment <command>
   or: wp rest category <command>
   or: wp rest comment <command>
   or: wp rest liveblog <command>
   or: wp rest liveblog-revision <command>
   or: wp rest page <command>
   or: wp rest page-revision <command>
   or: wp rest podcast <command>
   or: wp rest post <command>
   or: wp rest post-revision <command>
   or: wp rest series <command>
   or: wp rest slack-channel <command>
   or: wp rest status <command>
   or: wp rest tag <command>
   or: wp rest taxonomy <command>
   or: wp rest type <command>
   or: wp rest user <command>
   or: wp rest video <command>

# The `--ssh=<host>` global parameter proxies command execution to a remote WordPress install.
$ wp --ssh=runcommand.io rest
usage: wp rest command <command>
   or: wp rest excerpt <command>
   or: wp rest spark <command>
```

### 2. Registers WP-CLI commands for the resource endpoints it understands, in the `wp rest` namespace.

In addition to the standard list, get, create, update and delete commands, RESTful WP-CLI also registers commands for higher-level operations like `edit`, `generate` and `diff`.

```
# In this example, `@wpdev` is a WP-CLI alias to `--path=/srv/www/wordpress-develop.dev/src`.
$ wp @wpdev rest user
usage: wp rest user create --username=<username> [--name=<name>] [--first_name=<first_name>] [--last_name=<last_name>] --email=<email> [--url=<url>] [--description=<description>] [--nickname=<nickname>] [--slug=<slug>] [--roles=<roles>] --password=<password> [--capabilities=<capabilities>] [--porcelain]
   or: wp rest user delete <id> [--force=<force>] [--reassign=<reassign>] [--porcelain]
   or: wp rest user diff <alias> [<resource>] [--fields=<fields>]
   or: wp rest user edit <id>
   or: wp rest user generate [--count=<count>] [--format=<format>] --username=<username> [--name=<name>] [--first_name=<first_name>] [--last_name=<last_name>] --email=<email> [--url=<url>] [--description=<description>] [--nickname=<nickname>] [--slug=<slug>] [--roles=<roles>] --password=<password> [--capabilities=<capabilities>] [--porcelain]
   or: wp rest user get <id> [--context=<context>] [--fields=<fields>] [--field=<field>] [--format=<format>]
   or: wp rest user list [--context=<context>] [--page=<page>] [--per_page=<per_page>] [--search=<search>] [--exclude=<exclude>] [--include=<include>] [--offset=<offset>] [--order=<order>] [--orderby=<orderby>] [--slug=<slug>] [--roles=<roles>] [--fields=<fields>] [--field=<field>] [--format=<format>]
   or: wp rest user update <id> [--username=<username>] [--name=<name>] [--first_name=<first_name>] [--last_name=<last_name>] [--email=<email>] [--url=<url>] [--description=<description>] [--nickname=<nickname>] [--slug=<slug>] [--roles=<roles>] [--password=<password>] [--capabilities=<capabilities>] [--porcelain]

# Use `wp rest * edit` to open an existing item in the editor.
$ wp rest category edit 1 --user=daniel
---
description:
name: Uncategorized
slug: uncategorized
parent: 0

# Use `wp rest * generate` to generate dummy content.
$ wp @wpdev rest post generate --count=50 --title="Test Post" --user=daniel
Generating items  100% [==============================================] 0:01 / 0:02

# Use `wp rest * diff` to diff a resource or collection of resources between environments.
$ wp @dev-rest rest command diff @prod-rest find-unused-themes --fields=title
(-) http://runcommand.dev/api/ (+) https://runcommand.io/api/
  command:
  + title: find-unused-themes
```

There are many things RESTful WP-CLI can't yet do. Please [review the issue backlog](https://github.com/wp-cli/restful/issues), and open a new issue if you can't find an exising issue for your topic.
