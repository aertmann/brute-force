# AE.BruteForce

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/aertmann/brute-force/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/aertmann/brute-force/?branch=master)
[![Latest Stable Version](https://poser.pugx.org/moc/varnish/v/stable)](https://packagist.org/packages/aertmann/brute-force)
[![Total Downloads](https://poser.pugx.org/moc/varnish/downloads)](https://packagist.org/packages/aertmann/brute-force)
[![License](https://poser.pugx.org/aertmann/brute-force/license)](https://packagist.org/packages/aertmann/brute-force)

## Introduction

This package provides simple brute-force prevention (account locking) for Neos/Flow.

A notification email can be send to an administrator when an account has been locked.

Compatible with Neos 2.x + / Flow 3.x+

Be aware that there are ways to circumvent this protection and it can be misused, see [Blocking Brute Force Attacks](https://www.owasp.org/index.php/Blocking_Brute_Force_Attacks) for more information.

## Installation

`composer require "aertmann/brute-foce:~1.0"`

## Configuration

Failed attempts threshold and notification mail can be configured using [``Settings.yaml``](Configuration/Settings.yaml).