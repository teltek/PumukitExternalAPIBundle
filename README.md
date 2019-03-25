ExternalAPIBundle:
---------------------

Este bundle en su versión inicial busca implementar una API similar a la API /ingest Opencast para crear nuevos objetos multimedia.

En el futuro podrá mejorarse y variar para adecuarse más a las necesidades de PuMuKIT.

Ejemplos de cómo usar la API (Curl):

New mediapackage
```
curl -X POST -f -i --basic -u admin:admin https://gcms-local.teltek.es/api/ingest/createMediaPackage
```

Add attachment
```
curl -X POST -i --basic -u admino:admino https://gcms-local.teltek.es/app_dev.php/api/ingest/addAttachment -F 'mediaPackage="<mediapackage id=\"5c982e5339d98b25008b456a\" start=\"2019-03-25T01:26:43Z\"><media/><metadata/><attachments/><publications/></mediapackage>"' -F 'flavor="srt"' -F BODY=@Resources/data/Tests/Controller/IngestControllerTest/subtitle.srt
```

Add track
```
curl -X POST -i --basic -u admin:admin https://gcms-local.teltek.es/app_dev.php/api/ingest/addTrack -F 'mediaPackage="<mediapackage id=\"5c982e5339d98b25008b456a\" start=\"2019-03-25T01:26:43Z\"><media/><metadata/><attachments/><publications/></mediapackage>"' -F 'flavor="presenter/source"' -F BODY=@Resources/data/Tests/Controller/IngestControllerTest/presenter.mp4
```

Add episode (to change title)
```
curl -X POST -i --basic -u admin:admin https://gcms-local.teltek.es/app_dev.php/api/ingest/addDCCatalog -F 'mediaPackage="<mediapackage id=\"5c982e5339d98b25008b456a\" start=\"2019-03-25T01:26:43Z\"><media/><metadata/><attachments/><publications/></mediapackage>"' -F 'flavor="dublincore/episode"' -F BODY=@Resources/data/Tests/Controller/IngestControllerTest/episode.xml 
```
