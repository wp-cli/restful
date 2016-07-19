wp-cli/restful
==============

Unlock the potential of the WP REST API at the command line.

**Warning:** This project is at a very early stage. Treat it as an experiment, and understand that breaking changes will be made without warning. The sky may also fall on your head. Using wp-rest-cli requires the latest nightly build of [WP-CLI](http://wp-cli.org/), which you can install with `wp cli update --nightly`.

Project backed by [Pressed](https://www.pressed.net/), [Chris Lema](https://chrislema.com/), [Human Made](https://hmn.md/), [Pagely](https://pagely.com/), [Pantheon](https://pantheon.io/) and many others. [Learn more →](http://wp-cli.org/restful/)

[![Build Status](https://travis-ci.org/wp-cli/restful.svg?branch=master)](https://travis-ci.org/wp-cli/restful)

Quick links: [Using](#using) | [Installing](#installing) | [Contributing](#contributing)

## Using

RESTful WP-CLI makes [WP REST API](http://v2.wp-api.org/) endpoints available as [WP-CLI](http://wp-cli.org/) commands. It does so by:

1. Auto-discovering WP REST API endpoints from any WordPress site running WordPress 4.4 or higher. Target a specific WordPress install with `--path=<path>`, `--ssh=<host>`, or `--http=<domain>`.
2. Registering WP-CLI commands for the resource endpoints it understands, in the `wp rest` namespace.

For example:

```
$ wp @wpdev rest
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

$ wp @wpdev rest user
usage: wp rest user create --username=<username> [--name=<name>] [--first_name=<first_name>] [--last_name=<last_name>] --email=<email> [--url=<url>] [--description=<description>] [--nickname=<nickname>] [--slug=<slug>] [--roles=<roles>] --password=<password> [--capabilities=<capabilities>] [--porcelain]
   or: wp rest user delete <id> [--force=<force>] [--reassign=<reassign>] [--porcelain]
   or: wp rest user diff <alias> [<resource>] [--fields=<fields>]
   or: wp rest user edit <id>
   or: wp rest user generate [--count=<count>] [--format=<format>] --username=<username> [--name=<name>] [--first_name=<first_name>] [--last_name=<last_name>] --email=<email> [--url=<url>] [--description=<description>] [--nickname=<nickname>] [--slug=<slug>] [--roles=<roles>] --password=<password> [--capabilities=<capabilities>] [--porcelain]
   or: wp rest user get <id> [--context=<context>] [--fields=<fields>] [--field=<field>] [--format=<format>]
   or: wp rest user list [--context=<context>] [--page=<page>] [--per_page=<per_page>] [--search=<search>] [--exclude=<exclude>] [--include=<include>] [--offset=<offset>] [--order=<order>] [--orderby=<orderby>] [--slug=<slug>] [--roles=<roles>] [--fields=<fields>] [--field=<field>] [--format=<format>]
   or: wp rest user update <id> [--username=<username>] [--name=<name>] [--first_name=<first_name>] [--last_name=<last_name>] [--email=<email>] [--url=<url>] [--description=<description>] [--nickname=<nickname>] [--slug=<slug>] [--roles=<roles>] [--password=<password>] [--capabilities=<capabilities>] [--porcelain]
```

In addition to the standard list, get, create, update and delete commands, RESTful WP-CLI also registers commands for higher-level operations like:

```
# Use `wp rest * edit` to open an existing item in the editor
$ wp rest category edit 1 --user=daniel
---
description:
name: Uncategorized
slug: uncategorized
parent: 0

# Use `wp rest * generate` to generate dummy content
$ wp @wpdev rest post generate --count=50 --title="Test Post" --user=daniel
Generating items  100% [==============================================] 0:01 / 0:02
```

There are many things RESTful WP-CLI can't yet do. Please [review the issue backlog](https://github.com/wp-cli/restful/issues), and open a new issue if you can't find an exising issue for your topic.

## Installing

Installing this package requires WP-CLI 0.24.0-alpha-c650e14 or greater. Update to the latest nightly release with `wp cli update --nightly`.

Once you've done so, you can install this package with `wp package install wp-cli/restful`.

## Contributing

We appreciate you taking the initiative to contribute to this project.

Contributing isn’t limited to just code. We encourage you to contribute in the way that best fits your abilities, by writing tutorials, giving a demo at your local meetup, helping other users with their support questions, or revising our documentation.

### Reporting a bug

Think you’ve found a bug? We’d love for you to help us get it fixed.

Before you create a new issue, you should [search existing issues](https://github.com/wp-cli/restful/issues?q=label%3Abug%20) to see if there’s an existing resolution to it, or if it’s already been fixed in a newer version.

Once you’ve done a bit of searching and discovered there isn’t an open or fixed issue for your bug, please [create a new issue](https://github.com/wp-cli/restful/issues/new) with the following:

1. What you were doing (e.g. "When I run `wp post list`").
2. What you saw (e.g. "I see a fatal about a class being undefined.").
3. What you expected to see (e.g. "I expected to see the list of posts.")

Include as much detail as you can, and clear steps to reproduce if possible.

### Creating a pull request

Want to contribute a new feature? Please first [open a new issue](https://github.com/wp-cli/restful/issues/new) to discuss whether the feature is a good fit for the project.

Once you've decided to commit the time to seeing your pull request through, please follow our guidelines for creating a pull request to make sure it's a pleasant experience:

1. Create a feature branch for each contribution.
2. Submit your pull request early for feedback.
3. Include functional tests with your changes. [Read the WP-CLI documentation](https://wp-cli.org/docs/pull-requests/#functional-tests) for an introduction.
4. Follow the [WordPress Coding Standards](http://make.wordpress.org/core/handbook/coding-standards/).

*This README.md is generated dynamically from the project's codebase using `wp scaffold package-readme` ([doc](https://github.com/wp-cli/scaffold-package-command#wp-scaffold-package-readme)). To suggest changes, please submit a pull request against the corresponding part of the codebase.*
