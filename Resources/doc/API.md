API INGEST MAPPING DESCRIPTION
==============================


## **General**

##### POST /api/ingest/createMediaPackage
___

##### POST /api/ingest/addAttachment
____

##### POST /api/ingest/addTrack
____

##### POST /api/ingest/addCatalog
____

##### POST /api/ingest/addDCCatalog
____

Adds a dublincore catalog to a given multimedia object. The dublincore/episode values can be used to edit the metadata values (like title). The dublincore/series reasigns the multimedia object to a new existing series.

**Response**

- *200:* OK, Returns media package format like Opencast with attached file.  
- *400:* Bad Request, media package or data not valid.  
- *404:* Not Found, media package does not exist.  
- *500:* Internal Server Error, *NONE*. 


**Required fields**

|Field                   | Type     | Description                           |
| ---------------------- | -------- | ------------------------------   |
| mediaPackage           | string   |  The mediapackage to modify as XML.
| flavour                | string   |  DublinCore Flavor (Only dublincore/episode and dublincore/series are supported at the moment).
| body                   | string   |  DublinCore catalog as XML.


##### Sample episode request

```
curl -X POST -i --basic -u api-user:api-password http://localhost/api/ingest/addDCCatalog \
-F 'mediaPackage="<mediapackage id=\"5c982e5339d98b25008b456a\" start=\"2019-03-25T01:26:43Z\"></mediapackage>"' \
-F 'flavor="dublincore/episode"' -F BODY=@Resources/data/Tests/Controller/IngestControllerTest/episode.xml
```

**Data mapping**

| Field                  | Type     | Description                      |
| ---------------------- | -------- | ------------------------------   |
| <dcterms:created>      | string   |  Video record date
| <dcterms:temporal>     | string   |  *NOT USED*
| <dcterms:spatial>      | string   |  *NOT USED*
| <dcterms:title>        | string   |  Video title
| <dcterms:description>  | string   |  Video description
| <dcterms:roleCod>      | string   |  Person to the roleCod defined 
| <dcterms:accessRights> | string   |  Video Copyright
| <dcterms:license>      | string   |  Video License
| <dcterms:identifier>   | string   |  *NOT USED* on episode request



##### Sample series request

```
curl -X POST -i --basic -u api-user:api-password http://localhost/api/ingest/addDCCatalog \
-F 'mediaPackage="<mediapackage id=\"5c982e5339d98b25008b456a\" start=\"2019-03-25T01:26:43Z\"></mediapackage>"' \
-F 'flavor="dublincore/series"' -F BODY=@Resources/data/Tests/Controller/IngestControllerTest/series.xml 
```

**Data mapping**

| Field                  | Type     | Description                           |
| ---------------------- | -------- | ------------------------------   |
| <dcterms:identifier>   | string   | Set series if exists on multimedia object or create one.



#### POST /api/ingest/addMediaPackage 

Creates mediapackage from given media tracks and dublincore metadata.

**Response**

- *200:* OK, Returns media package format like Opencast with attached file.  
- *400:* Bad Request, media package or data not valid  
- *404:* Not Found, series does not exist.  
- *500:* Internal Server Error, *NONE*.  


**Required fields**

|Field                   | Type     | Description                           |
| ---------------------- | -------- | ------------------------------   |
| flavour                | string   | The kind of media track (see /addTrack). If several tracks are added, this can be an array of flavors (each value corresponding to the corresponding track on the BODY parameter).
| BODY                   | string   | The track file or files (this can be an array of tracks, each requiring one flavor parameter

##### Sample request

```
curl -X POST -f -i --basic -u api-user:api-password http://localhost/api/ingest/addMediaPackage \
-F contributor='Contributor Name' -F title='Example CURL' \
-F 'flavor[]=presentation/source' -F 'BODY[]=@Resources/data/Tests/Controller/IngestControllerTest/presentation.mp4' \
-F 'flavor[]=presenter/source' -F 'BODY[]=@Resources/data/Tests/Controller/IngestControllerTest/presenter.mp4'
```

**Data mapping**

| Field                  | Type     | Description                      |
| ---------------------- | -------- | ------------------------------   |
| <dcterms:created>      | string   |  Video record date
| <dcterms:temporal>     | string   |  *NOT USED*
| <dcterms:spatial>      | string   |  *NOT USED*
| <dcterms:title>        | string   |  Video title
| <dcterms:description>  | string   |  Video description
| <dcterms:roleCod>      | string   |  Person to the roleCod defined 
| <dcterms:accessRights> | string   |  Video Copyright
| <dcterms:license>      | string   |  Video License
| <dcterms:identifier>   | string   |  *NOT USED* on episode request
