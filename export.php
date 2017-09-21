<?php

class Exporter {

    private $username = 'xxxxx';
    private $password = 'xxxxx';
    private $baseUrl  = 'https://myconfluence.com';
    public $fileName  = 'confluence_spaces.csv';

    public $curl;

    function __construct() {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_USERPWD, $this->username . ':' . $this->password);
        $this->curl = $curl;
    }

    function getSpaces($path = '/rest/api/space?type=global&expand=metadata.labels,description.plain', $spaces = array()) {
        $data = $this->getData($path);

        foreach ($data->results as $space) {
            $spaceKey = $space->key;
            $spaceName = $space->name;

            echo "fetching $spaceName\n";

            $query = urlencode("space=\"$spaceKey\" order by lastModified desc");

            $lastModifiedContent = $this->getData("/rest/api/search?limit=1&cql=$query");
            $lastModifiedDate = reset($lastModifiedContent->results)->lastModified;

            $space->lastModified = $lastModifiedDate;

            $spaces[] = $space;
        }

        if (!empty($data->_links->next)) {
            return $this->getSpaces($data->_links->next, $spaces);
        } else {
            return $spaces;
        }
    }

    function getData($path) {
        curl_setopt($this->curl, CURLOPT_URL, $this->baseUrl . $path);
        $response = curl_exec($this->curl);
        return json_decode($response);
    }

    function writeCsv($spaces) {
        $fp = fopen($this->fileName, 'w');

        // sort by last modified (oldest first)
        // fixes warning...
        date_default_timezone_set('UTC');
        usort($spaces, function($a, $b) {
            return strtotime($a->lastModified) - strtotime($b->lastModified);
        });

        $header = array(
          'NAME',
          'LABELS',
          'DESCRIPTION',
          'LASTMODIFIED',
          'URL',
        );

        fputcsv($fp, $header);

        foreach ($spaces as $space) {
            $fields = array();

            // name
            $fields[] = $space->name;

            // labels
            $labels = array();
            foreach ($space->metadata->labels->results as $label) {
                $labels[] = $label->name;
            }
            $fields[] = join(',', $labels);

            // description
            $fields[] = $space->description->plain->value;

            // lastModified
            $fields[] = $space->lastModified;

            // url
            $fields[] = $this->baseUrl . $space->_links->webui;

            fputcsv($fp, $fields);
        }

        fclose($fp);
    }

}

$exporter = new Exporter();
$spaces = $exporter->getSpaces();
$exporter->writeCsv($spaces);

curl_close($exporter->curl);

echo 'done! see ' . $exporter->fileName;
