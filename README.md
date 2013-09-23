Element: Storage in Table
=========================

###### Element: Storage – Database Table Implementation

***(incomplete, untested, rewritten too many times)***

Storage in table build with only basic set of API calls similar to [Memcached](http://memcached.org "A distributed memory object caching system"):

- exists()
- get()
- add()
- set()
- replace()
- delete()

Uses PDO connection, lazy scheme load.

> PS: Right now the [Blob_Prototype](./Blob/Prototype.php) can serve as an example of usage.

**Enjoy!**

[@martin_adamko](http://twitter.com/martin_adamko)  
*Say hi on Twitter*