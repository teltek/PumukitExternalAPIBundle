
PuMuKIT /api/series Documentation
========================================

Table of Contents
---------------------

* [POST /](#post-create)

# POST /
**Description:**  
Creates an empty series returning its id as plain text.

**Path parameters:**  
*NONE*

**Optional (query) parameters:**  
*series:* Series id for the multimedia object. If not set a new series will be created.

**Optional (form) parameters:**  
*title:*
* If set as a string, the series title will be this one in all languages.  
* If set as an array (see last example), the series title will be this one on each added language.  
It is possible, although not recommended, to add languages that aren't enabled on the platform.__

**Response formats:**  
[text/html]()

**Status codes:**  
*200:* OK, Returns id as string.  
*500:* Internal Server Error, *NONE*.

**Examples curl:**  
Series with default title:
```
curl -k -X POST -f -i --basic -u api-user:api-password https://gcms-local.teltek.es/api/series
```

Series with same custom title for all languages:
```
curl -k -X POST -f -i --basic -u api-user:api-password https://gcms-local.teltek.es/api/series -F "title=New custom title"
```

Series with different custom titles for each language:
```
curl -k -X POST -f -i --basic -u api-user:api-password https://gcms-local.teltek.es/api/series -F "title[en]=EN TITLE" -F "title[es]=ES TITLE"
```
