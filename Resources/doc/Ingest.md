PuMuKIT /api/ingest Documentation
========================================

Table of Contents
---------------------

* [POST /createMediaPackage](#post-createmediapackage)
* [POST /addAttachment](#post-addattachment)
* [POST /addDCCatalog](#post-adddccatalog)
* [POST /addMediaPackage](#post-addmediapackage)
* [POST /addTrack](#post-addtrack)
* [GET /getDownloadTrack/{trackId}](#get-getdownloadtracktrackid)

# POST /createMediaPackage
**Description:**  
Creates an empty multimedia object and returns a mediaPackage formatted XML.

**Path parameters:**  
*NONE*

**Optional (query) parameters:**  
*series:* Series id for the multimedia object. If not set a new series will be created.

**Optional (form) parameters:**  
*seriesTitle:* Set series title  when created in all languages of the platform.  

**Response formats:**  
[text/xml](http://www.w3.org/XML/)

**Status codes:**  
*200:* OK, Returns media package format like Opencast.  
*404:* Not Found, series does not exist.  
*500:* Internal Server Error, *NONE*.

**Example curl:**  
```
curl -X POST -f -i --basic -u api-user:api-password https://gcms-local.teltek.es/api/ingest/createMediaPackage -F 'seriesTitle="seriesTitle"'
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
curl -X POST -i --basic -u api-user:api-password http://localhost/api/ingest/addAttachment \
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

**Optional (form) parameters:**  
*seriesTitle:* Set series title  when created in all languages of the platform.  

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
curl -X POST -i --basic -u api-user:api-password http://localhost/api/ingest/addDCCatalog \
-F 'mediaPackage="<mediapackage id=\"5c982e5339d98b25008b456a\" start=\"2019-03-25T01:26:43Z\"></mediapackage>"' \
 -F 'seriesTitle="seriesTitle"' -F 'flavor="dublincore/episode"' -F BODY=@Resources/data/Tests/Controller/IngestControllerTest/episode.xml 

# Series
curl -X POST -i --basic -u api-user:api-password http://localhost/api/ingest/addDCCatalog \
-F 'mediaPackage="<mediapackage id=\"5c982e5339d98b25008b456a\" start=\"2019-03-25T01:26:43Z\"></mediapackage>"' \
 -F 'seriesTitle="seriesTitle"' -F 'flavor="dublincore/series"' -F BODY=@Resources/data/Tests/Controller/IngestControllerTest/series.xml 
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
*seriesTitle:* Set series title when created in all languages of the platform.  


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
curl -X POST -f -i --basic -u api-user:api-password http://localhost/api/ingest/addMediaPackage \
 -F 'seriesTitle="seriesTitle"' -F contributor='Contributor Name' -F title='Example CURL' \
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
curl -X POST -i --basic -u api-user:api-password http://localhost/api/ingest/addTrack \
-F 'mediaPackage="<mediapackage id=\"5c982e5339d98b25008b456a\" start=\"2019-03-25T01:26:43Z\"></mediapackage>"' \
-F 'flavor="presenter/source"' -F BODY=@Resources/data/Tests/Controller/IngestControllerTest/presenter.mp4
```

# GET /getDownloadTrack/{trackId}
**Description:**  
Downloads the media file of a single track, identified directly by its track id. When the track is stored on an external system it answers with a `302` redirect to the real (time-limited) file URL; otherwise the file is streamed back. Access is granted by the Ingest API credentials only, bypassing the `play` permission, so videos can be downloaded regardless of their publication status.

This replaces the old `/trackfile/{trackId}.mp4` link, which stopped working when video download security was hardened in PuMuKIT 5.1 (it now requires a player token and the `play` permission). The caller already knows the track id (it is taken from the `mmobj.json` listing, e.g. the first track tagged `etiqmedia`), so no track is selected here.

**Path parameters:**  
*trackId:* The id of the track to download (24 hex chars).

**Response formats:**  
The media file (binary) or a `302` redirect to it.

**Status codes:**  
*200:* OK, the media file is returned.  
*302:* Found, redirect to the external file URL.  
*404:* Not Found, the track does not exist or the file is missing on disk.

**Example curl:**  
```
# Replaces:  curl -L https://domain.es/trackfile/5dca96b2f9556400433440a2.mp4 --output video.mp4
curl -L --basic -u api-user:api-password \
https://domain.es/api/ingest/getDownloadTrack/5dca96b2f9556400433440a2 --output video.mp4
```
