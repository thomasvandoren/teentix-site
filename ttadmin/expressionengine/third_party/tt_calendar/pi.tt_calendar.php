<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$plugin_info = array(
  'pi_name' => 'TeenTix Calendar JSON',
  'pi_version' => '0.0.1',
  'pi_author' => 'Thomas Van Doren',
  'pi_author_url' => 'https://github.com/thomasvandoren',
  'pi_description' => '',
  'pi_usage' => 'FIXME!
{exp:json:entries channel="news"}

{exp:json:entries channel="products" search:product_size="10"}

{exp:json:members member_id="1"}',
);

if ( ! class_exists('Calendar')) {
  require PATH_THIRD.'calendar/mod.calendar'.EXT;
}

class Category {
  public $id;
  public $url_title;
  public $name;

  public function __construct(array $category_array)
  {
    $this->id = $category_array['0'];
    $this->url_title = $category_array['6'];
    $this->name = $category_array['2'];
  }
}

class Organization {
  public $name;
  public $link;
}

class Location {
  public $title;
  public $address;
  public $link;
}

class Image {
  public $image;
  public $width;
  public $height;
  public $caption;
}

class Review {
  public $title;
  public $link;
  public $author;
  public $author_link;
  public $date;
  public $preview;
}

class Event {
  public $empty = false;

  public $id;
  public $url_title;
  public $title;
  public $categories;
  // FIXME: what about single occurrence events? (thomasvandoren, 2016-03-05)
  public $all_day;
  public $start_date;
  public $end_date;
  public $recurs;
  public $upcoming_occurrences;
  public $availability;
  public $partner;
  public $location;
  public $video_embed;
  public $images;

  public $description;

  public $reviews;

  public $website;
  public $facebook_link;
  public $twitter_link;
  public $youtube_link;

  public $age_restrictions;
  public $ticket_info;


  function __construct(Calendar_event $solspace_event, array $row, array $categories)
  {
    $this->id = $solspace_event->default_data['event_id'];
    $this->url_title = $row['url_title'];
    $this->title = $row['title'];

    // TODO: deal with "none" values for all these $row[] values. Convert them to null. (thomasvandoren, 2016-03-06)

    // TODO: remove HTML tags from description? (thomasvandoren, 2016-03-06)
    $this->description = $row['field_id_50'];
    $this->age_restrictions = $row['field_id_52'];
    $this->ticket_info = $row['field_id_58'];
    $this->availability = $row['field_id_57'] === 'y';

    $this->website = $row['field_id_53'];
    $this->facebook_link = $row['field_id_54'];
    $this->twitter_link = $row['field_id_55'];
    $this->youtube_link = $row['field_id_56'];

    $this->all_day = $solspace_event->default_data['all_day'];

    // FIXME: use check_yes() (thomasvandoren, 2016-03-06)
    $this->recurs = $solspace_event->default_data['recurs'] === 'y';

    $this->start_date = $this->date_from_string($solspace_event->default_data['start_date']);
    $this->end_date = $this->date_from_string($solspace_event->default_data['last_date']);

    $this->categories = array();
    foreach($categories as $category_array) {
      array_push($this->categories, new Category($category_array));
    }

    $event_organization_id = $row['field_id_46'];  // 3927
    $event_venue_id = $row['field_id_47'];  // 3928
    $this->partner = new Organization();
    $this->location = new Location();




    // FIXME: add this stuff in later!
//    $this->images = array(new Image());
//    $this->reviews = array(new Review());
//    $this->upcoming_occurrences = array();
//
//    $d = new DateTime("2016-03-05");
//    $end = new DateTime("2016-05-08");
//    while ($d->getTimestamp() <= $end->getTimestamp()) {
//      array_push($this->upcoming_occurrences, $d);
//      $d = clone $d;
//      $d->add(new DateInterval("P1D"));
//    }
  }

  private function date_from_string($date_string) {
    if ($date_string == null || $date_string === "") {
      return null;
    } else {
      return new DateTime($date_string);
    }
  }
}

class NoEvent {
  public $empty = true;
}

class tt_calendar extends Calendar
{
  public function event_json()
  {
    // Call solspace events API, which will populate $this->event_cache.
    $r = $this->events();

    if (isset($this->event_cache)) {
      return json_encode(new Event(array_pop($this->event_cache), $this->row_cache, array_pop($this->categories_cache), $r));
    } else {
      return json_encode(new NoEvent());
    }
  }
}

/* End of file pi.json.php */
/* Location: ./system/expressionengine/third_party/tt_calendar_json/pi.tt_calendar_json.php */
