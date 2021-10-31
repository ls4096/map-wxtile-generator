# Weather data tile generator for Web Mercator maps

This is a weather data tile generator for digital maps, which is compatible with Leaflet and in general any other mapping software capable of displaying maps from tiles with the Web Mercator projection.

For each invocation, this code will:
1. parse the HTTP GET parameters for the Web Mercator map cartesian coordinates (`x`, `y`) for the given zoom level (`z`) and for the weather data type (`t`) of the requested tile,
2. connect to the weather data providing server over TCP (for example, a [sailnavsim-core](https://github.com/ls4096/sailnavsim-core) instance) to fetch the raw weather data,
3. and finally generate the weather tile image and output it in PNG format.

This can be run as part of an existing or a new web service (as long as it is capable of running PHP code) or as a standalone PHP program (possibly with some modifications) which outputs the PNG image binary data to stdout.

An example of some generated weather map tiles from wind data overlayed over a base map in Leaflet is shown below (as seen in the sailing navigation simulator here: https://8bitbyte.ca/sailnavsim/).
[Wind data example](./examples/wind-example.png)

## Dependencies

- PHP
- PHP-Imagick

## Tested environments

- PHP 7.0 on Debian 9 (Stretch), x86-64
- PHP 7.3 on Debian 10 (Buster), x86-64

## Configuration

To configure the weather data tile generator, modify the values in `wxtile_config.php` to point it to a running instance of a compatible weather data providing server (for example, a running instance of sailnavsim-core).

If using LeafletJS, you can simply add a new tile layer to point to your web service (as in the example shown below). Note that the values of `WEB_HOST` and `WEB_PORT` here are not the same as those configured in the `wxtile_config.php` file; these should point to your web service hosting the tile generator code rather than to the weather data providing server.

`L.tileLayer('https://WEB_HOST:WEB_PORT/WXTILE_PATH/wxtile.php?z={z}&x={x}&y={y}&t=wind').addTo(map);`
