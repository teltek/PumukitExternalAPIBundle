PuMuKIT /api/ingest Documentation
========================================

Table of Contents
---------------------

* [POST /createMediaPackage](#post-createmediapackage)
* [POST /addAttachment](#post-addattachment)
* [POST /addDCCatalog](#post-adddccatalog)
* [POST /addMediaPackage](#post-addmediapackage)
* [POST /addTrack](#post-addtrack)

# POST /createMediaPackage
**Description:**  
Creates an empty multimedia object and returns a mediaPackage formatted XML.

**Path parameters:**  
*NONE*

**Optional (query) parameters:**  
*series:* Series id for the multimedia object. If not set a new series will be created.

**Response formats:**  
[text/xml](http://www.w3.org/XML/)

**Status codes:**  
*200:* OK, Returns media package format like Opencast.  
*404:* Not Found, series does not exist.  
*500:* Internal Server Error, *NONE*.

**Example curl:**  
```
curl -X POST -f -i --basic -u api-user:api-password https://gcms-local.teltek.es/api/ingest/createMediaPackage
```

# POST /addAttachment
**Description:**  
Adds an attachment to a given multimedia object from file.

**Path parameters:**  
*NONE*

**Required (form) parameters:**  
*flavor:* Type of attachment (e.g. 'srt' for .srt type subtitles).  
*mediaPackage:* The edited mediaPackage as XML.

**BODY (upload) parameter:**  
The attachment file.

**Optional (form) parameters:**  
*language:* Language field on the attachment.  

**Response formats:**  
[text/xml](http://www.w3.org/XML/)

**Status codes:**  
*200:* OK, Returns media package format like Opencast with attached file.  
*400:* Bad Request, media package or data not valid.  
*404:* Not Found, media package does not exist.  
*500:* Internal Server Error, *NONE*.

**Example curl:**  
```
curl -X POST -i --basic -u api-user:api-password https://gcms-local.teltek.es/app_dev.php/api/ingest/addAttachment \
-F 'mediaPackage="<mediapackage id=\"5c982e5339d98b25008b456a\" start=\"2019-03-25T01:26:43Z\"></mediapackage>"' \
-F 'flavor="srt"' -F BODY=@Resources/data/Tests/Controller/IngestControllerTest/subtitle.srt
```

# POST /addDCCatalog
**Description:**  
Adds a dublincore catalog to a given multimedia object. The dublincore/episode values can be used to edit the metadata values (like title). The dublincore/series reasigns the multimedia object to a new existing series.

**Path parameters:**  
*NONE*

**Required (form) parameters:**  
*mediaPackage:* The mediaPackage to modify as XML.  
*dublincore:* DublinCore catalog as XML.  
*flavor:* DublinCore Flavor (Only dublincore/episode and dublincore/series are supported at the moment).

**Response formats:**  
[text/xml](http://www.w3.org/XML/)

**Status codes:**  
*200:* OK, Returns media package format like Opencast with attached file.  
*400:* Bad Request, media package or data not valid.  
*404:* Not Found, media package does not exist.  
*500:* Internal Server Error, *NONE*.  

**Example curl:**  
```
# Episode
curl -X POST -i --basic -u api-user:api-password https://gcms-local.teltek.es/app_dev.php/api/ingest/addDCCatalog \
-F 'mediaPackage="<mediapackage id=\"5c982e5339d98b25008b456a\" start=\"2019-03-25T01:26:43Z\"></mediapackage>"' \
-F 'flavor="dublincore/episode"' -F BODY=@Resources/data/Tests/Controller/IngestControllerTest/episode.xml 

# Series
curl -X POST -i --basic -u api-user:api-password https://gcms-local.teltek.es/app_dev.php/api/ingest/addDCCatalog \
-F 'mediaPackage="<mediapackage id=\"5c982e5339d98b25008b456a\" start=\"2019-03-25T01:26:43Z\"></mediapackage>"' \
-F 'flavor="dublincore/series"' -F BODY=@Resources/data/Tests/Controller/IngestControllerTest/series.xml 
```

# POST /addMediaPackage
**Description:**  
Creates mediaPackage from given media tracks and dublincore metadata.

**Path parameters:**  
*NONE*

**Required (form) parameters:**  
*flavor:* The kind of media track (see /addTrack). If several tracks are added, this can be an array of flavors (each value corresponding to the corresponding track on the BODY parameter).

**BODY (upload) parameter:**  
The track file or files (this can be an array of tracks, each requiring one flavor parameter**

**Optional (form) parameters:**  
*accessRights:* Copyright field on the multimedia object.  
*contributor:* Person name. Added with contributor role as a new person or an existing one if it already exists. Can be an array of names.  
*creator:* Person name. Added with creator role as a new person or an existing one if it already exists. Can be an array of names.  
*description:* Description field on the multimedia object.  
*license:* License field on the multimedia object.  
*publisher:* Person name. Added with publisher role as a new person or an existing one if it already exists. Can be an array of names.  
*title:* Title field on the multimedia object.  
*series:* Series id for the multimedia object.

**Response formats:**  
[text/xml](http://www.w3.org/XML/)

**Status codes:**  
*200:* OK, Returns media package format like Opencast with attached file.  
*400:* Bad Request, media package or data not valid  
*404:* Not Found, series does not exist.  
*500:* Internal Server Error, *NONE*.  

**Example curl:**  
```
# Multiple tracks
curl -X POST -f -i --basic -u api-user:api-password https://gcms-local.teltek.es/app_dev.php/api/ingest/addMediaPackage \
-F contributor='Contributor Name' -F title='Example CURL' \
-F 'flavor[]=presentation/source' -F 'BODY[]=@Resources/data/Tests/Controller/IngestControllerTest/presentation.mp4' \
-F 'flavor[]=presenter/source' -F 'BODY[]=@Resources/data/Tests/Controller/IngestControllerTest/presenter.mp4'
```

# POST /addTrack
**Description:**  
Adds track file to given mediaPackage.

**Path parameters:**  
*NONE*

**Required (form) parameters:**  
*flavor:* The kind of media track (see /addTrack). If several tracks are added, this can be an array of flavors (each value corresponding to the corresponding track on the BODY parameter).  
*mediaPackage:* The mediaPackage as XML.

**BODY (upload) parameter:**  
The track file.

**Response formats:**  
[text/xml](http://www.w3.org/XML/)

**Status codes:**  
*200:* OK, Returns media package format like Opencast with attached file.  
*400:* Bad Request, media package or data not valid.  
*404:* Not Found, media package does not exist.  
*500:* Internal Server Error, *NONE*

**Example curl:**  
```
curl -X POST -i --basic -u api-user:api-password https://gcms-local.teltek.es/app_dev.php/api/ingest/addTrack \
-F 'mediaPackage="<mediapackage id=\"5c982e5339d98b25008b456a\" start=\"2019-03-25T01:26:43Z\"></mediapackage>"' \
-F 'flavor="presenter/source"' -F BODY=@Resources/data/Tests/Controller/IngestControllerTest/presenter.mp4
```
