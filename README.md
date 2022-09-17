# modxExtraCachePlugin
Plugin for MODX Revo, increase server response time 


### Результаты тестирования:
Тестовый стенд: 
+ Centos 8
+ Фронтэнд: NGINX/1.22.0 gzip = Off
+ Бэкэнд: Apache/2.4.6 mpm-itk/2.4.7-04 mod_fcgid/2.3.9
+ 1 ядро: Intel(R) Xeon(R) CPU E5645 @ 2.40GHz
+ 1024MB RAM
+ PHP 7.4.3 with Zend OPcache
+ MODX Revo 2.8.4-pl: modxExtraCachePlugin

Генерация одной страницы, до попадания в кэш:
```html
Total parse time	0.3557382 s
Total queries	45
Total queries time	0.0204265 s
Memory peak usage	4 Mb
MODX version	MODX Revolution 2.8.4-pl (traditional)
PHP version	7.4.30
Database version	mysql 10.3.36-MariaDB
From cache	false
```

Нагрузочное тестирование, 10 потоков, 60 секунд:
```bash
$ ab -kc 10 -t 60 https://test_site.ru/
```

```
This is ApacheBench, Version 2.3 <$Revision: 1430300 $>
Copyright 1996 Adam Twiss, Zeus Technology Ltd, http://www.zeustech.net/
Licensed to The Apache Software Foundation, http://www.apache.org/

Benchmarking test_site.ru (be patient)
Finished 1689 requests


Server Software:        nginx/1.22.0
Server Hostname:        test_site.ru
Server Port:            443
SSL/TLS Protocol:       TLSv1.2,ECDHE-RSA-AES128-GCM-SHA256,2048,128

Document Path:          /
Document Length:        43563 bytes

Concurrency Level:      10
Time taken for tests:   60.015 seconds
Complete requests:      1689
Failed requests:        0
Write errors:           0
Keep-Alive requests:    0
Total transferred:      74341335 bytes
HTML transferred:       73577907 bytes
Requests per second:    28.14 [#/sec] (mean)
Time per request:       355.331 [ms] (mean)
Time per request:       35.533 [ms] (mean, across all concurrent requests)
Transfer rate:          1209.67 [Kbytes/sec] received

Connection Times (ms)
              min  mean[+/-sd] median   max
Connect:        5   65  27.5     63     182
Processing:    61  289  42.2    285     479
Waiting:       23  272  40.5    270     438
Total:         68  354  45.8    350     581

Percentage of the requests served within a certain time (ms)
  50%    350
  66%    370
  75%    381
  80%    389
  90%    410
  95%    431
  98%    463
  99%    486
 100%    581 (longest request)
```
