PuMuKIT /api/mmobjs/{id} Documentation
========================================

Table of Contents
---------------------

* [DELETE /tag/cod/{cod}](#post-tag)

# DELETE /tags/cod/{cod}
**Description:**  
Removes specified tag from multimedia object, if allowed.

This is used when 3th party services have proprietary custom tags to tag videos to be processed.

**Response formats:**  
[text/xml](http://www.w3.org/XML/)

**Status codes:**  
*200:* OK.  
*403:*
 - {CUSTOM_TAG} not allowed to be removed
*404:* 
 - MultimediaObject not found on BBDD
 - Tag not found on BBDD
 - {CUSTOM_TAG} not found on MultimediaObject
*500:* Internal Server Error, *NONE*.

**Example curl:**  
```
curl -X DELETE -i --basic -u admin:admin https://localhost:8000/app_dev.php/api/mmobjs/5c982e5339d98b25008b456a/tags/cod/CUSTOM_TAG
```
