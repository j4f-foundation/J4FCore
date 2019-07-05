@echo off

rem GetPublicAddress
for /f "tokens=1* delims=: " %%A in (
  'nslookup myip.opendns.com. resolver1.opendns.com 2^>NUL^|find "Address:"'
) Do set ExtIP=%%B

rem Stat client
cd .. && php client.php -user USER -ip %ExtIP% -port 6969 -miner