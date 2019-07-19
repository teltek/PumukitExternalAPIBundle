ExternalAPIBundle:
---------------------

Este bundle en su versión inicial busca implementar una API similar a la API /ingest Opencast para crear nuevos objetos multimedia.

En el futuro podrá mejorarse y variar para adecuarse más a las necesidades de PuMuKIT.

Ejemplos de cómo usar la API (Curl):

New mediapackage
```
curl -X POST -f -i --basic -u admino:admino http://localhost:8000/app_dev.php/api/ingest/createMediaPackage
```

Add attachment
```
curl -X POST -i --basic -u admino:admino http://localhost:8000/app_dev.php/api/ingest/addAttachment -F 'mediaPackage="<mediapackage id=\"5c982e5339d98b25008b456a\" start=\"2019-03-25T01:26:43Z\"><media/><metadata/><attachments/><publications/></mediapackage>"' -F 'flavor="srt"' -F BODY=@Resources/data/Tests/Controller/IngestControllerTest/subtitle.srt
```

Add track
```
curl -X POST -i --basic -u admin:admin http://localhost:8000/app_dev.php/api/ingest/addTrack -F 'mediaPackage="<mediapackage id=\"5c982e5339d98b25008b456a\" start=\"2019-03-25T01:26:43Z\"><media/><metadata/><attachments/><publications/></mediapackage>"' -F 'flavor="presenter/source"' -F BODY=@Resources/data/Tests/Controller/IngestControllerTest/presenter.mp4
```

Add episode (to change title)
```
curl -X POST -i --basic -u admin:admin http://localhost:8000/app_dev.php/api/ingest/addDCCatalog -F 'mediaPackage="<mediapackage id=\"5c982e5339d98b25008b456a\" start=\"2019-03-25T01:26:43Z\"><media/><metadata/><attachments/><publications/></mediapackage>"' -F 'flavor="dublincore/episode"' -F BODY=@Resources/data/Tests/Controller/IngestControllerTest/episode.xml
```

Add pumukit episode (to change metadata)
```
curl -X POST -i --basic -u admin:admin http://localhost:8000/app_dev.php/api/ingest/addCatalog -F 'mediaPackage="<mediapackage id=\"5c982e5339d98b25008b456a\" start=\"2019-03-25T01:26:43Z\"><media/><metadata/><attachments/><publications/></mediapackage>"' -F 'flavor="pumukit/episode"' -F BODY=@Resources/data/Tests/Controller/IngestControllerTest/pmk.json
```

addMediaPackage (do previous ones simultaneously):
```
curl -X POST -f -i --basic -u admino:admino http://localhost:8000/app_dev.php/api/ingest/addMediaPackage -F contributor='test CURL' -F title='Test CURL' -F 'flavor[]=presentation/source' -F 'BODY[]=@Resources/data/Tests/Controller/IngestControllerTest/presentation.mp4' -F 'flavor[]=presenter/source' -F 'BODY[]=@Resources/data/Tests/Controller/IngestControllerTest/presenter.mp4'
```
