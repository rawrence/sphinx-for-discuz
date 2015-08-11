# 如果您觉得这太麻烦了，请参考： http://www.xungle.com/ #

# 用sphinx实现的discuz全文搜索 #
这篇文章主要介绍用sphinx(csft)做discuz论坛的全文搜索，操作系统为linux，如果需要windows下的可直接参考：http://www.coreseek.cn/news/1/52/

## sphinx + mmseg安装 ##
这里的sphinx指的是csft，详见：http://www.coreseek.cn/
mmseg install
```
wget http://www.coreseek.cn/uploads/sources/mmseg3_0b3.tar.gz
tar xvzf mmseg3_0b3.tar.gz
./configure –prefix=/usr/local/mmseg
make && make install
```
生成词典：
(如果这个词库词量过小，可到搜狗上下载词库: http://pinyin.sogou.com/dict/list.php ，很多词库找不到TXT版的下载，可以直接通过地址: http://pinyin.sogou.com/dict/download_txt.php?id=词库id ，来下载，words.txt在sphinx目录下)
```
mmseg -u words.txt		(utf-8编码)
cp ciku.txt.uni /usr/local/mmseg/dict/uni.lib
```
## sphinx： ##
```
wget http://www.coreseek.cn/uploads/csft/3.1/Source/csft-3.1.tar.gz
./configure --prefix=/usr/local/sphinx –with-mysql=/usr/local/mysql-5.1.28-rc \
--with-mmseg=/usr/local/mmseg --with-mmseg-includes=/usr/local/mmseg/include/mmseg/ \
--with-mmseg-libs=/usr/local/mmseg/lib/ --with-mysql-includes=/usr/local/mysql-5.1.28-rc/include/mysql/ \
--with-mysql-libs=/usr/local/mysql-5.1.28-rc/lib/mysql/
make && make install
```
注意：修改/usr/local/mysql-5.1.28-rc为相应的mysql目录


配置文件：dz\_data.conf (在sphinx目录下)
> 注意：修改source dzbbs和source threads 中的sql\_host、sql\_user、sql\_pass、sql\_db、sql\_port参数，配置文件是按discuz的默认表前缀写的sql，所以修改了表前缀的请将cdb\_替换为更改后的前缀。


首次运行：
```
/usr/local/sphinx/bin/indexer --config /usr/local/sphinx/etc/sphinx.conf dzbbs
/usr/local/sphinx/bin/indexer --config /usr/local/sphinx/etc/sphinx.conf threads
/usr/local/sphinx/bin/searchd --config /usr/local/sphinx/etc/sphinx.conf
```
如果论坛数据量比较大，这次执行可能比较耗时，这里生成的就是主索引。

为了及时搜索到新帖，使用增量索引，用cron来实现，使用crontab -e( root )或crontab -u user -e来添加。
下面的配置代表每天0点到2点，6点到23点这段时间中每5分钟生成一次增量索引，每天4点合并一次主索引和增量索引
全部帖子：
```
*/5 0-2,6-23 * * * /usr/local/sphinx/bin/indexer --config /usr/local/sphinx/etc/dz_data.conf dzbbs_delta --rotate
0 4 * * * /usr/local/sphinx/bin/indexer --config /usr/local/sphinx/etc/dz_data.conf dzbbs_merge --rotate && /usr/local/sphinx/bin/indexer --config /usr/local/sphi
nx/etc/dz_data.conf --merge dzbbs dzbbs_merge --rotate

*/5 0-2,6-23 * * * /usr/local/sphinx/bin/indexer --config /usr/local/sphinx/etc/dz_data.conf threads_delta --rotate
10 4 * * * /usr/local/sphinx/bin/indexer --config /usr/local/sphinx/etc/dz_data.conf threads_merge --rotate && /usr/local/sphinx/bin/indexer --config /usr/local/sp
hinx/etc/dz_data.conf --merge threads threads_merge --rotate
```
简单的说下crontab中前两个位置的配置，
```
*/5 0-2,6-23
```
> 前面的位置表示分钟，`*`/5代表每五分钟，如果是`*`则代表每分钟，也可以是0-59之间的具体整数，如10 4代表4点10分执行，这里可以根据需要调整;
> 后面的位置表示小时，0-2,6-23代表0点到2点、6点到23点，当然也可以写成`*`来实现每小时都执行，这里主要是考虑到2点到6点这段时间发帖量很小;