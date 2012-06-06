{
  "wordpress_tests": {
    "plugin_name": "twitter-api-shortcodes",
    "project_path": "/Users/ryangeyer/Code/PHP/Wordpress/mine/tasforwp-svn/twitter-api-shortcodes/trunk",
    "project_wptests_path": "tests",
    "dbname": "tasforwp-test",
    "dbuser": "root"
  },
  "run_list": ["recipe[wordpress_tests::instrument_project]","recipe[wordpress_tests::create_symlinks]"]
}