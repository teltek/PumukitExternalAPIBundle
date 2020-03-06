PuMuKIT /api/remove Documentation
========================================

Table of Contents
---------------------

* [DELETE /tag](#post-tag)

# DELETE /tag
**Description:**  
Removes if exists custom TAG configured. This case is used when 3th party services have proprietary custom tags to process videos.

**Required (form) parameters:**    
*mediaPackage:* The edited mediaPackage as XML.

**Response formats:**  
[text/xml](http://www.w3.org/XML/)

**Status codes:**  
*200:* OK, Returns media package format like Opencast.  
*404:* 
 - MediaPackage not found on BBDD
 - {CUSTOM_TAG} not found on MultimediaObject

*500:* Internal Server Error, *NONE*.

**Example curl:**  
```
curl -X DELETE -f -i --basic -u admin:admin http://localhost:8000/app_dev.php/api/remove/tag -F 'mediaPackage="<mediapackage id=\"5c982e5339d98b25008b456a\" />'
```
