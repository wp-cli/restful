wp-rest-cli
===========

A [Kickstarter-backed project](http://wp-cli.org/restful/) by @danielbachhuber to unlock the potential of the WP REST API at the command line.

**Warning:** This project is at a very early stage. Treat it as an experiment, and understand that breaking changes will be made without warning. The sky may also fall on your head. Using wp-rest-cli requires the latest nightly build of WP-CLI, which you can install with `wp cli update --nightly`.

[![Build Status](https://travis-ci.org/danielbachhuber/wp-rest-cli.svg?branch=master)](https://travis-ci.org/danielbachhuber/wp-rest-cli)

Quick links: [Overview](#overview) | [Installing](#installing) | [Contributing](#Contributing)

## Overview

wp-rest-cli makes [WP REST API](http://v2.wp-api.org/) endpoints available as WP-CLI commands. It does so by:

* Auto-discovering those endpoints from any WordPress site (local or remote).
* Registering WP-CLI commands for the endpoints it understands.

For example:

    $ wp --http=demo.wp-api.org rest
    usage: wp rest attachment <command>
       or: wp rest category <command>
       or: wp rest comment <command>
       or: wp rest meta <command>
       or: wp rest page <command>
       or: wp rest pages-revision <command>
       or: wp rest post <command>
       or: wp rest posts-revision <command>
       or: wp rest status <command>
       or: wp rest tag <command>
       or: wp rest taxonomy <command>
       or: wp rest type <command>
       or: wp rest user <command>
    $ wp --http=demo.wp-api.org rest tag get 65
    +----------+-------------------------------------------------------------------+
    | Field    | Value                                                             |
    +----------+-------------------------------------------------------------------+
    | id       | 65                                                                |
    | link     | http://demo.wp-api.org/tag/dolor-in-sunt-placeat-molestiae-ipsam/ |
    | name     | Dolor in sunt placeat molestiae ipsam                             |
    | slug     | dolor-in-sunt-placeat-molestiae-ipsam                             |
    | taxonomy | post_tag                                                          |
    +----------+-------------------------------------------------------------------+
    
There are many things wp-rest-cli can't yet do. Please [review the issue backlog](https://github.com/danielbachhuber/wp-rest-cli/issues), and open a new issue if you can't find an exising issue for your topic.

## Installing

wp-rest-cli requires the latest nightly version of WP-CLI. Update with `wp cli update --nightly`.

Once you've done so, you can install wp-rest-cli with `wp package install danielbachhuber/wp-rest-cli`.

## Contributing

Code and ideas are more than welcome. Please [open an issue](https://github.com/danielbachhuber/wp-rest-cli/issues) with questions, feedback, and violent dissent. Pull requests are expected to include test coverage.
