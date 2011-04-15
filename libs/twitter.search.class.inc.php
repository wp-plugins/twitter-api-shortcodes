<?php
class TwitterSearch {
  private $_wpdb;
  private $tapi;

  public $paging = false;
  public $limit = 0;

  public $term = '';
  public $archive = false;
  public $last_successful_cron = 0;

  private $_statuses = array();
  private $_id = 0;

  public function __construct($id, $wpdb, $tapi=null)
  {
    $this->_wpdb  = $wpdb;
    $this->tapi   = isset($tapi) ? $tapi : new TwitterAPIWrapper();
    $this->_id = $id;

    $searchRow = $this->_wpdb->get_row("SELECT * FROM `".TasForWp::$SearchTableName."` WHERE id = $this->_id");
    $this->term = $searchRow->search_term;
    $this->archive = $searchRow->archive;
    $this->last_successful_cron = $searchRow->last_successful_cron;
  }

  public function fetchAndCacheLatest()
  {
    if ($this->archive) {
        $nextPage = null;

        $latestStatusIdCached = $this->getMaxStatusId();

        do {
          $params = array();
          if ($nextPage != null) {
            // Add all of the existing params, plus the page number
            foreach (explode('&', $nextPage) as $keyValuePair) {
              $splodedPair = explode('=', $keyValuePair);
              $params[$splodedPair[0]] = urldecode($splodedPair[1]);
            }
          } else {
            // TODO: Should/can we specify a larger rpp?
            $params = array('q' => $this->term, 'rpp' => 100);
          }
          $response = $this->tapi->search($params);

          foreach ($response->results as $status) {
            if (strval($status->id) != $latestStatusIdCached) {
              $status_obj = new TwitterStatus($this->_wpdb, $this->tapi);
              $status_obj->load_json($status);
              $status_obj->cacheToDb();

              $this->_wpdb->insert(TasForWp::$StatusSearchTableName,
                array(
                  'status_id' => $status_obj->id_str,
                  'search_id' => $this->_id
                )
              );
              $this->_statuses[] = $status_obj;
            } else {
              break 2;
            }
          }

          $nextPage = str_replace('?', '', $response->next_page);
        } while ($nextPage != null);

        $this->_wpdb->update(TasForWp::$SearchTableName, array('last_successful_cron' => time()), array('id' => $this->_id));
      }
  }

  private function fetchStatuses()
  {
    $jsonObjs = array();

    // We're only going to look in the DB for the statuses associated with this search
    // if the tag indicates that we should archive the statuses, it's a waste of an SQL
    // call otherwise.
    if ($this->archive) {
      $query = sprintf("SELECT * FROM `%s` WHERE id IN (SELECT status_id FROM `%s` WHERE search_id = %s)%s",
        TasForWp::$StatusByIdTableName,
        TasForWp::$StatusSearchTableName,
        $this->_id,
        $this->limit > 0 ? "LIMIT $this->limit" : ""
      );
      $rows = $this->_wpdb->get_results($query);
      foreach ($rows as $row) {
        array_push($jsonObjs, json_decode($row->status_json));
      }
    } else {
      try {
        $params = array('q' => $this->term);
        if($this->limit > 0) {
          $params['rpp'] = $this->limit;
        }
        $response = $this->tapi->search($params);
        #$jsonObjs = $response->results;

        foreach($response->results as $result)
        {
          $status_obj = new TwitterStatus($this->_wpdb, $this->tapi);
          $status_obj->load_json($result);
          $this->_statuses[] = $status_obj;
        }
      } catch (Exception $e) {
        // TODO: Should elegantly inform the user
      }
    }
  }

  public function getStatuses()
  {
    if(count($this->_statuses) == 0)
    {
      $this->fetchStatuses();
    }
    return $this->_statuses;
  }

  private function getMaxStatusId()
  {
    return $this->_wpdb->get_var("SELECT max(status_id) FROM `".TasForWp::$StatusByIdTableName."` WHERE search_id = $this->_id");
  }

  public static function getSearches($wpdb, $tapi)
  {
    $searches = array();
    foreach($wpdb->get_results("SELECT id FROM `". TasForWp::$SearchTableName ."`") as $searchRow)
    {
      $searches[] = new TwitterSearch($searchRow->id, $wpdb, $tapi);
    }
    return $searches;
  }
}