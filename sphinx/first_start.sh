# main index and sart searchd
/usr/local/sphinx/bin/indexer --config /usr/local/sphinx/etc/dz_data.conf dzbbs
/usr/local/sphinx/bin/indexer --config /usr/local/sphinx/etc/dz_data.conf threads
/usr/local/sphinx/bin/searchd --config /usr/local/sphinx/etc/dz_data.conf



# crontab for Post
*/5 0-2,6-23 * * * /usr/local/sphinx/bin/indexer --config /usr/local/sphinx/etc/dz_data.conf dzbbs_delta --rotate
0 4 * * * /usr/local/sphinx/bin/indexer --config /usr/local/sphinx/etc/dz_data.conf dzbbs_merge --rotate && /usr/local/sphinx/bin/indexer --config /usr/local/sphi
nx/etc/dz_data.conf --merge dzbbs dzbbs_merge --rotate

# crontab for  threads 
*/5 0-2,6-23 * * * /usr/local/sphinx/bin/indexer --config /usr/local/sphinx/etc/dz_data.conf threads_delta --rotate
10 4 * * * /usr/local/sphinx/bin/indexer --config /usr/local/sphinx/etc/dz_data.conf threads_merge --rotate && /usr/local/sphinx/bin/indexer --config /usr/local/sp
hinx/etc/dz_data.conf --merge threads threads_merge --rotate