<?php
class TwitterSearch {
  private $_wpdb;
  private $tapi;

  public $paging = false;
  public $limit = 15;

  public $term = '';
  public $archive = false;
  public $last_successful_cron = 0;

  private $_statuses = array();
  private $_id = 0;

  private $_nextPage = null;
  private $_refresh_url = null;

  public function getId()
  {
    return $this->_id;
  }

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
          $params = array('include_entities' => 1);
          if ($nextPage != null) {
            // Add all of the existing params, plus the page number
            foreach (explode('&', $nextPage) as $keyValuePair) {
              $splodedPair = explode('=', $keyValuePair);
              $params[$splodedPair[0]] = urldecode($splodedPair[1]);
            }
          } else {
            // TODO: Should/can we specify a larger rpp?
            $params = array_merge($params, array('q' => $this->term, 'rpp' => 100));
            if(isset($latestStatusIdCached)) $params['since_id'] = $latestStatusIdCached;
          }
          try {
            $response = $this->tapi->search($params);
          } catch (Exception $e) {
            error_log(print_r($e, true));
          }

          foreach ($response->results as $status) {
            $status_obj = new TwitterStatus($this->_wpdb, $this->tapi);
            $status_obj->load_json($status);
            $status_obj->process_entities();
            $status_obj->cacheToDb();

            $this->_wpdb->insert(TasForWp::$StatusSearchTableName,
              array(
                'status_id' => $status_obj->id_str,
                'search_id' => $this->_id
              )
            );
            $this->_statuses[] = $status_obj;
          }

          $nextPage = str_replace('?', '', $response->next_page);
        } while ($nextPage != null);

        $this->_wpdb->update(TasForWp::$SearchTableName, array('last_successful_cron' => time()), array('id' => $this->_id));
      }
  }

  public function older_statuses_available()
  {
    return isset($this->_nextPage);
  }

  private function fetchStatuses()
  {
    // We're only going to look in the DB for the statuses associated with this search
    // if the tag indicates that we should archive the statuses, it's a waste of an SQL
    // call otherwise.
    if ($this->archive) {
      $limit_clause = " LIMIT ";
      if($this->paging) {
        $limit_clause .= ($this->page * $this->limit).",";
      }
      $limit_clause .= $this->limit;

      $query = sprintf("SELECT * FROM `%s` WHERE id IN (SELECT status_id FROM `%s` WHERE search_id = %s)%s",
        TasForWp::$StatusByIdTableName,
        TasForWp::$StatusSearchTableName,
        $this->_id,
        $limit_clause
      );
      $rows = $this->_wpdb->get_results($query);
      $this->_nextPage = (count($rows) < $this->limit) ? null : true;
      foreach ($rows as $row) {
        $status_obj = new TwitterStatus($row->id);
        $status_obj->load_json($row->status_json);
        #$status_obj->process_entities();
        $this->_statuses[] = $status_obj;
      }
    } else {
      try {
        $params = array('q' => $this->term, 'include_entities' => 1);
        if($this->limit > 0) {
          $params['rpp'] = $this->limit;
        }
        if(isset($this->page)) {
          $params['page'] = $this->page;
        }
        if(isset($this->max_status_id)) {
          $params['max_id'] = $this->max_status_id;
        }
        $response = $this->tapi->search($params);

        $this->_nextPage = $response->next_page;
        $this->_refresh_url = $response->refresh_url;        

        foreach($response->results as $result)
        {
          $status_obj = new TwitterStatus($this->_wpdb, $this->tapi);
          $status_obj->load_json($result);
          #$status_obj->process_entities();
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
  
  public function getNextPageUrl() {
  	return $this->_nextPage;  
  }
  
  public function getRefreshUrl() {
  	return $this->_refresh_url;
  }

  private function getMaxStatusId()
  {
    return $this->_wpdb->get_var("SELECT max(status_id) FROM `".TasForWp::$StatusSearchTableName."` WHERE search_id = $this->_id");
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