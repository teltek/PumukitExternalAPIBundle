PuMuKIT /api/ingest Documentation
========================================

Table of Contents
---------------------

* [GET /createMediaPackage](#get-createmediapackage)
* [POST /addAttachment](#post-addattachment)
* [POST /addDCCatalog](#post-adddccatalog)
* [POST /addMediaPackage](#post-addmediapackage)
* [POST /addTrack](#post-addtrack)

# GET /createMediaPackage
**Description:**  
Creates an empty media package

**Path parameters:**  
*NONE*

**Optional (query) parameters:**  
*series:* Series id for the multimedia object. If not set a new series will be created

**Response formats:**  
[text/xml](http://www.w3.org/XML/)

**Status codes:**  
*200:* OK, Returns media package format like Opencast  
*404:* Not Found, series does not exist.  
*500:* Internal Server Error, *NONE*

# POST /addAttachment
**Description:**  
Adds an attachment to a given media package using an input stream.

**Path parameters:**  
*NONE*

**Required (form) parameters:**  
*flavor:* Type of attachment  
*mediaPackage:* The mediapackage as XML

**BODY (upload) parameter:**  
The attachment file

**Response formats:**  
[text/xml](http://www.w3.org/XML/)

**Status codes:**  
*200:* OK, Returns media package format like Opencast with attached file  
*400:* Bad Request, media package or data not valid  
*404:* Not Found, media package does not exist.  
*500:* Internal Server Error, *NONE*

# POST /addDCCatalog
**Description:**  
Adds a dublincore episode catalog to a given media package

**Path parameters:**  
*NONE*

**Required (form) parameters:**  
*mediaPackage:* The mediapackage to modify as XML  
*dublincore:* DublinCore catalog as XML  
*flavor:* DublinCore Flavor (Only dublincore/episode and dublincore/series are supported at the moment)

**Response formats:**  
[text/xml](http://www.w3.org/XML/)

**Status codes:**  
*200:* OK, Returns media package format like Opencast with attached file.  
*400:* Bad Request, media package or data not valid.  
*404:* Not Found, media package does not exist.  
*500:* Internal Server Error, *NONE*.  

# POST /addMediaPackage
**Description:**  
Creates mediapackage from given media tracks and dublincore metadata.

**Path parameters:**  
*NONE*

**Required (form) parameters:**  
*flavor:* The kind of media track (see /addTrack). If several tracks are added, this can be an array of flavors (each value corresponding to the corresponding track on the BODY parameter)

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

# POST /addTrack
**Description:**  
Adds track file to given mediapackage.

**Path parameters:**  
*NONE*

**Required (form) parameters:**  
*flavor:* The kind of media track (see /addTrack). If several tracks are added, this can be an array of flavors (each value corresponding to the corresponding track on the BODY parameter).  
*mediaPackage:* The mediapackage as XML.

**BODY (upload) parameter:**  
The track file.

**Optional (form) parameters:**  
*tags:* The tags for the media track.

**Response formats:**  
[text/xml](http://www.w3.org/XML/)

**Status codes:**  
*200:* OK, Returns media package format like Opencast with attached file.  
*400:* Bad Request, media package or data not valid.  
*404:* Not Found, media package does not exist.  
*500:* Internal Server Error, *NONE*
