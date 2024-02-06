ExternalAPIBundle
=================

WARNING: We cannot guarantee that this module is compatible with new versions of PuMuKIT.

This package allow to interact with PuMuKIT through an external API. It is a RESTful API that allows to create and update Multimedia Objects.

API Examples using curl:

##### New media package
```
curl -X POST -f -i --basic -u admin:admin http://localhost:8000/app_dev.php/api/ingest/createMediaPackage
```

##### Add attachment without override
```
curl -X POST -i --basic -u admin:admin http://localhost:8000/app_dev.php/api/ingest/addAttachment -F 'mediaPackage="<mediapackage id=\"5c982e5339d98b25008b456a\" start=\"2019-03-25T01:26:43Z\"></mediapackage>"' -F 'flavor="srt"' -F BODY=@Resources/data/Tests/Controller/IngestControllerTest/subtitle.srt
```

##### Add attachment overriding attachment
```
curl -X POST -i --basic -u admin:admin http://localhost:8000/app_dev.php/api/ingest/addAttachment -F 'mediaPackage="<mediapackage id=\"5c982e5339d98b25008b456a\" start=\"2019-03-25T01:26:43Z\"></mediapackage>"' -F 'flavor="srt"' -F BODY=@Resources/data/Tests/Controller/IngestControllerTest/subtitle.srt -F 'overriding="5c982e5339d98b25008b456a"'
```

##### Add track
```
curl -X POST -i --basic -u admin:admin http://localhost:8000/app_dev.php/api/ingest/addTrack -F 'mediaPackage="<mediapackage id=\"5c982e5339d98b25008b456a\" start=\"2019-03-25T01:26:43Z\"></mediapackage>"' -F 'flavor="presenter/source"' -F BODY=@Resources/data/Tests/Controller/IngestControllerTest/presenter.mp4
```

##### Add episode (to change title)
```
curl -X POST -i --basic -u admin:admin http://localhost:8000/app_dev.php/api/ingest/addDCCatalog -F 'mediaPackage="<mediapackage id=\"5c982e5339d98b25008b456a\" start=\"2019-03-25T01:26:43Z\"></mediapackage>"' -F 'flavor="dublincore/episode"' -F BODY=@Resources/data/Tests/Controller/IngestControllerTest/episode.xml
```

##### Add PuMuKIT episode (to change metadata)
```
curl -X POST -i --basic -u admin:admin http://localhost:8000/app_dev.php/api/ingest/addCatalog -F 'mediaPackage="<mediapackage id=\"5c982e5339d98b25008b456a\" start=\"2019-03-25T01:26:43Z\"></mediapackage>"' -F 'flavor="pumukit/episode"' -F BODY=@Resources/data/Tests/Controller/IngestControllerTest/pmk.json
```

##### addMediaPackage (do previous ones simultaneously):
```
curl -X POST -f -i --basic -u admin:admin http://localhost:8000/app_dev.php/api/ingest/addMediaPackage -F contributor='test CURL' -F title='Test CURL' -F 'flavor[]=presentation/source' -F 'BODY[]=@Resources/data/Tests/Controller/IngestControllerTest/presentation.mp4' -F 'flavor[]=presenter/source' -F 'BODY[]=@Resources/data/Tests/Controller/IngestControllerTest/presenter.mp4'
```

API DELETE Examples using curl
##### removeTag: ( Remove the custom tag configured for external api )
```
curl -X DELETE -i --basic -u admin:admin https://localhost:8000/app_dev.php/api/mmobjs/5c982e5339d98b25008b456a/tags/cod/CUSTOM_TAG
```
