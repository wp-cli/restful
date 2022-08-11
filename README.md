wp-cli/restful
==============

Unlock the potential of the WP REST API at the command line.

**Warning:** This project is at a very early stage. Treat it as an experiment, and understand that breaking changes will be made without warning. The sky may also fall on your head. Using RESTful WP-CLI requires the latest nightly build of [WP-CLI](http://wp-cli.org/), which you can install with `wp cli update --nightly`.

Initial development was [backed by a Kickstarter project](https://wp-cli.org/restful/). This project will evolve alongside the WP REST API's evolution in WordPress core.

[![Testing](https://github.com/wp-cli/restful/actions/workflows/testing.yml/badge.svg)](https://github.com/wp-cli/restful/actions/workflows/testing.yml) [![Build Status](https://travis-ci.org/wp-cli/restful.svg?branch=main)](https://travis-ci.org/wp-cli/restful)

Quick links: [Using](#using) | [Installing](#installing) | [Contributing](#contributing) | [Support](#support)

## Using

RESTful WP-CLI makes [WP REST API](https://developer.wordpress.org/rest-api/reference/) endpoints available as [WP-CLI](http://wp-cli.org/) commands.

As WordPress becomes more of an application framework embedded into the web, RESTful WP-CLI enables WP-CLI users to interact with a given WordPress install through the higher-level, self-expressed abstraction of how WordPress understands itself. For instance, on an eCommerce website, instead of having to know data is stored as `wp post list --post_type=edd_product`, RESTful WP-CLI exposes the properly-modeled data at `wp rest product list`.

Here's an overview of how RESTful WP-CLI works, in two parts.

### 1. Auto-discovers WP REST API endpoints from any WordPress site running WordPress 4.7 or higher

Target a specific WordPress install with `--path=<path>`, `--ssh=<host>`, or `--http=<domain>`:

```
# The `--path=<path>` global parameter tells WP-CLI to interact with a WordPress install at a given path.
# Because this is a stock WordPress install, you see the posts, pages, and other resources you'd expect to see.
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
# Because Wired has some custom post types, they're automatically registered as WP-CLI commands.
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
# Because runcommand has a completely custom data model, you can only interact with commands, excerpts, and sparks.
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

If WP-CLI is operating directly against a WordPress install, you can use the `--debug` flag to track the number of queries and total execution time. This can be useful for measuring and profiling API requests.

```
$ wp rest category list --debug
Debug (rest): REST command executed 3 queries in 0.000311 seconds. Use --debug=rest to see all queries. (1.118s)
+---------------+
| name          |
+---------------+
| Test Category |
| Uncategorized |
+---------------+
```

There are many things RESTful WP-CLI can't yet do. Please [review the issue backlog](https://github.com/wp-cli/restful/issues), and open a new issue if you can't find an exising issue for your topic.

## Installing

Installing this package requires WP-CLI 1.3.0-alpha or greater. Update to the latest nightly release with `wp cli update --nightly`.

Once you've done so, you can install this package with `wp package install wp-cli/restful`.

## Contributing

We appreciate you taking the initiative to contribute to this project.

Contributing isn’t limited to just code. We encourage you to contribute in the way that best fits your abilities, by writing tutorials, giving a demo at your local meetup, helping other users with their support questions, or revising our documentation.

For a more thorough introduction, [check out WP-CLI's guide to contributing](https://make.wordpress.org/cli/handbook/contributing/). This package follows those policy and guidelines.

### Reporting a bug

Think you’ve found a bug? We’d love for you to help us get it fixed.

Before you create a new issue, you should [search existing issues](https://github.com/wp-cli/restful/issues?q=label%3Abug%20) to see if there’s an existing resolution to it, or if it’s already been fixed in a newer version.

Once you’ve done a bit of searching and discovered there isn’t an open or fixed issue for your bug, please [create a new issue](https://github.com/wp-cli/restful/issues/new). Include as much detail as you can, and clear steps to reproduce if possible. For more guidance, [review our bug report documentation](https://make.wordpress.org/cli/handbook/bug-reports/).

### Creating a pull request

Want to contribute a new feature? Please first [open a new issue](https://github.com/wp-cli/restful/issues/new) to discuss whether the feature is a good fit for the project.

Once you've decided to commit the time to seeing your pull request through, [please follow our guidelines for creating a pull request](https://make.wordpress.org/cli/handbook/pull-requests/) to make sure it's a pleasant experience. See "[Setting up](https://make.wordpress.org/cli/handbook/pull-requests/#setting-up)" for details specific to working on this package locally.

## Support

GitHub issues aren't for general support questions, but there are other venues you can try: https://wp-cli.org/#support


*This README.md is generated dynamically from the project's codebase using `wp scaffold package-readme` ([doc](https://github.com/wp-cli/scaffold-package-command#wp-scaffold-package-readme)). To suggest changes, please submit a pull request against the corresponding part of the codebase.*
