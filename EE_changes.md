ExpressionEngine changes
========================

Record of core library and add-on changes.

## Core library

* Check that `$this->validation` is set before referencing it in
  create_new_session(). This works around an issue with webservice
  code. https://github.com/teentix/site/commit/5c780b66ca8a13be6374ab2dd9cba2d937b172f8

* Add CORS headers in index.php.
  https://github.com/teentix/site/commit/bdb609e6b3dacf09ca277e1abe4886357e9332be

## Webservice add-on

* Check for and avoid `ee()->cache` as that seems to be something that is not
  available in our version of EE.
  https://github.com/teentix/site/commit/9c96fa89531eb3bfbe517b9c63c5e43e5d3cde25
  https://github.com/teentix/site/commit/e8f80b13c3d02d76488292a5aed744f5ef4e9b22

* Use PHP date formatting. The date formatting it was trying to use was broken,
  probably because it was using something that is only available in new
  versions of EE.
  https://github.com/teentix/site/commit/bf2dfeba86ec06abbc87da66dfe12cc894e43ebb
  https://github.com/teentix/site/commit/068f29b47bb5f61ab1bbc5d897def4382af20849

* Fix webservice header parsing so auth and data items can be passed using
  `webservice-auth-item-name` and `webservice-data-item-name`
  respectively. Previously, the header parsing support for webservice add-on
  was very broken.
  https://github.com/teentix/site/commit/a0058fadb2c0199d41ba050aa071c4c374838303
  https://github.com/teentix/site/commit/88b115c7db9a7fec3039d5d1e614b981b14a509e
  https://github.com/teentix/site/commit/1292c835e088cc56bc4bcdec8bae78a6b859ba67
  https://github.com/teentix/site/commit/483dabcec2ccd47f39ab2a6f6c638759840201d1

* Update `webservice_base_api.php` and `webservice_lib.php` to consider members
  that have a valid session and are accessing a favorites endpoint
  (read_favorites, create_favorite, delete_favorite) to be authenticated.
  https://github.com/teentix/site/commit/e3cbdd83bf20182899f51af69fac73678ce7cdc8
