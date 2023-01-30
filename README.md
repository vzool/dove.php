# :bird: Dove Notification File System (DNFS)

<p>
<a href="https://github.com/vzool/dove.php/blob/main/README.ar.md"><img src="https://img.shields.io/badge/lang-ar-green.svg" alt="ar" data-canonical-src="https://img.shields.io/badge/lang-en-green.svg" style="max-width: 100%;"></a>
<a href="https://github.com/vzool/dove.php/actions"><img src="https://github.com/vzool/dove.php/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/vzool/dove.php"><img src="https://img.shields.io/packagist/dt/vzool/dove.php" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/vzool/dove.php"><img src="https://img.shields.io/packagist/v/vzool/dove.php" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/vzool/dove.php"><img src="https://img.shields.io/packagist/l/vzool/dove.php" alt="License"></a>
</p>

Dove is a notification system based on the file storage system to deliver messages, it is working as a one-direction stream of data from the server to the clients.
So, the server just puts the messages, and later the clients will check for any updates on their own schedules.

DNFS by default will store all its data in `.dove` directory in the same directory where `dove.php` is located, this can be changed by `$path` argument in the constructor:

```php
# constructor
$dove = new Dove(
    string $client, # client address used to reference.
    int $expiration_in_days = 0, # disabled by default, store forever without removing any.
    string $hash_function = 'sha1', # built-in hash function or any anonymous function act like `sha1()`.
    string $path = __DIR__ . '/.dove/'
);
```

Dove doesn't process the sent or received data in any way like encryption or encoding, so it just acts like a bridge to transfer data from server to clients. If you have some concerns like binary data just encode them by using `base64` and to maintain privacy use encryption, all that were left to the developer choices.

"**Expiration of Messages**" is temporarily stored in request memory when a new instance of Dove object is created, by default its value is zero which means disabled, otherwise it will be in days. If the expiration is disabled the DNFS will store all the messages without deleting any. So, the delete operation after expiration is calculated and old messages are removed when a Pull call is performed with a specific client.

DNFS is a lazy actor which it does not require any scheduled job to be active in order to do its job, it is only waiting for client action to update the status of the message.

### :sparkles: Motivation
The main idea came from the [Passky-Server](https://github.com/Rabbit-Company/Passky-Server) project chats on the [discord server](https://discord.gg/y2ZBKbW5TA) about what happened to the LastPass data breach, which affects Personally Identifiable Information (PII) and lets a bad actor uses that information to stage a Phishing-Attack.
There were many ideas shared, one of them was [Zica Zajc](https://github.com/zigazajc007) who is a great man and the CEO of Passky project, he suggested that the server can store the messages and the clients will check them later.
So, I thought it will be a better for everyone to consildate this idea into a usable library.
### :eyes: Anatomy

Dove is a very small library that is less than 120 LOC (lines of code), and the core implementation took only 51%, 14% for HTTP handling and the rest is for testing.

Yes, one single file has them all, `dove.php` file contains the implementation, HTTP router, and testing, isn't this great? :yum::v:

In fact, Dove is a special library in which you can use the single file `dove.php`, or install it via composer without any namespaces required. Both of these methods will make you use the full functions of the library.

**Dove Storage Data Structure (DSDS)**

![dove-storage-system](images/dove-storage-system.png)

The `.dove` directory can be in a public path with a directory listing feature if it does support by the web server, but you can also put it in a private location and DNFS will be the only access point to those data.

**REST API Pull Request**

![rest-api-pull-request](images/rest-api-pull-request.png)

That was a list of times, in descending order from the newest to the old. So, by selecting a time you can ask for the latest messages after that time, or read the message contents.

**REST API Pull Request After Some Time**

![rest-api-pull-request](images/rest-api-pull-request-after-time.png)

**REST API Pull Request After Last Time**

![rest-api-pull-request](images/rest-api-pull-request-after-last-time.png)

**REST API Read Request**

![rest-api-pull-request](images/rest-api-read-request.png)

DNFS tries to implement zero-trust by making a clear border between inside and outside, so it always encodes times automatically, and the key used to encode/decode will be changed automatically, according to `dove.php` contents and location.
So, if the client got some time references then the `dove.php` contents got updated somehow or the file location changed, then the old references will not work unless the client asks for new references, and then the client can get the rest of the messages with updated references.

### :office: Requirements

- PHP 7.3+ (older versions will be supported one by one in the future if applicable).

### :anchor: Installation & Usage
Dove project will do its best to be compatible with all its released versions, so in future development releases, there will be no breaking changes.
#### :wrench: Single File Library (Server-Side)

The whole library is just a single file called `dove.php`, so you can just copy and paste it where ever is relevant to you.

Use the following when you want to process messages:

```php
<?php

define('DOVE', 1);
require_once 'dove.php';

$dove = new Dove('abdelaziz');
$time = $dove->Push('Salam, World!');
$message = $dove->Read($time); # `$time` just act like id

$times = $dove->Pull($time); # all times of messages after `$time`
$times = $dove->Pull(); # all times of messages

$dove->Delete($time); # delete one message
$dove->Delete(); # delete all messages
?>
```

Then, to handle client requests run the following:

```shell
php -S localhost:8080 dove.php
```

#### :musical_note: [Composer](https://getcomposer.org/) Dependency Manager for PHP (Server-Side)

```shell
composer require vzool/dove.php
```
Use the following when you want to process messages:
```php
<?php

define('DOVE', 1);
require_once 'vendor/vzool/dove.php/dove.php';

$dove = new Dove('abdelaziz');
$time = $dove->Push('Salam, World!');
$message = $dove->Read($time); # `$time` act like id
# ...
?>
```
Then, to handle client requests only include this in the path of `$_REQUEST` and it will handle the requests automatically.
```php
<?php require_once 'vendor/vzool/dove.php/dove.php'; ?>

# OR

<?php
    define('DOVE', 1);
    require_once 'vendor/vzool/dove.php/dove.php';
    Dove::Serve();
?>
```

#### :earth_africa: HTTP REST API (Client-Side) [GET/POST/ANY]

- Pull all times of messages:
    - `http://localhost:8080/dove.php?client=abdelaziz&cmd=pull`
    or
    - `http://localhost:8080/dove.php?client=abdelaziz`

- Pull the latest times of messages after the time (369):
    - `http://localhost:8080/dove.php?client=abdelaziz&cmd=pull&time=369`
    or
    - `http://localhost:8080/dove.php?&client=abdelaziz&time=369`

- Read a message in its time `http://localhost:8080/dove.php?client=abdelaziz&cmd=read&time=369`

### :checkered_flag: Benchmark
- CPU: 3.7 GHz 6-Core Intel Core i5
- RAM: 72 GB 2667 MHz DDR4
- OS: masOS Ventura 13.1
```shell
====================================================================
Dove Benchmarking started at: 2023-01-30 11:19:45
====================================================================
Write messages for 30 sec...
Write finished on: 2023-01-30 11:20:16
--------------------------------------------------------------------
Read all written messages...
Read finished on: 2023-01-30 11:20:21
--------------------------------------------------------------------
Delete all written messages...
Delete finished on: 2023-01-30 11:20:41
====================================================================
Write Count 165,908 (msg) in 30 sec
Write Speed 5,530 (msg/sec).
--------------------------------------------------------------------
Read Count 165,908 (msg) in 5 sec
Read Speed 33,182 (msg/sec).
--------------------------------------------------------------------
Delete Count 165,908 (msg) in 20 sec
Delete Speed 8,295 (msg/sec).
--------------------------------------------------------------------
Average Count 497,724 (msg).
Average Speed 47,007 (msg/sec).
====================================================================
Dove Benchmarking done at: (2023-01-30 11:20:41) and took 55 sec
====================================================================
```

You can run your own benchmarks on your PC with the following command `php benchmark.php`
### :microscope: Test

It should work without any issues, otherwise, an exception will be thrown. 

```bash
php dove.php
# OR
composer test
```