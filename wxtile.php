<?php
# Copyright (C) 2021 ls4096 <ls4096@8bitbyte.ca>
#
# This Source Code Form is subject to the terms of the Mozilla Public
# License, v. 2.0. If a copy of the MPL was not distributed with this
# file, You can obtain one at https://mozilla.org/MPL/2.0/.

include "wxtile_config.php";
include "wxlib.php";


header("Content-Type: image/png");


if (!isset($_GET["z"]) || !isset($_GET["x"]) || !isset($_GET["y"]))
{
	return;
}

if (!is_numeric($_GET["z"]) || !is_numeric($_GET["x"]) || !is_numeric($_GET["y"]))
{
	return;
}

$Z = intval($_GET["z"]);
$X = intval($_GET["x"]);
$Y = intval($_GET["y"]);

if ($Z < 1 || $X < 0 || $Y < 0)
{
	return;
}

$T = "wind"; // Default
if (isset($_GET["t"]))
{
	$T = $_GET["t"];
}

if (($T != "wind") &&
	($T != "wind_gust") &&
	($T != "ocean_current") &&
	($T != "sea_ice") &&
	($T != "wave_height"))
{
	return;
}


$ll = array();

// Lat/lon of 4 "center" points in Web Mercator tile...
$XI = [1, 3, 1, 3];
$YI = [1, 1, 3, 3];
for ($i = 0; $i < 4; $i++)
{
	// Algorithm adapted from here: https://wiki.openstreetmap.org/wiki/Slippy_map_tilenames#Tile_numbers_to_lon..2Flat._5

	$N = pow(2, $Z + 2);
	$lon = (($X * 4 + $XI[$i]) / $N * 360.0) - 180.0;
	$lat = rad2deg(atan(sinh(pi() * (1 - 2 * ($Y * 4 + $YI[$i]) / $N))));

	if ($lat < -90.0 || $lat > 90.0 || $lon < -180.0 || $lon > 180.0)
	{
		return;
	}

	array_push($ll, array($lat, $lon));
}


$wx = getWeatherData($ll, $T);

if (count($wx) == 4)
{
	$imgData = false;

	if ($T == "wind")
	{
		$imgData = getWindTileImage($ll[0][0], $wx, false);
	}
	else if ($T == "wind_gust")
	{
		$imgData = getWindTileImage($ll[0][0], $wx, true);
	}
	else if ($T == "ocean_current")
	{
		$imgData = getOceanCurrentTileImage($wx);
	}
	else if ($T == "sea_ice")
	{
		$imgData = getSeaIceTileImage($wx);
	}
	else if ($T == "wave_height")
	{
		$imgData = getWaveHeightTileImage($wx);
	}

	if ($imgData !== false)
	{
		echo $imgData;
	}
}



function getWindTileImage($lat, $wx, $isGust)
{
	$WIND_BARB_PX = 40;
	$WIND_CALM_RADIUS_PX = 6;
	$WIND_BARB_MARK_1050_PX = 12;

	$CENTER_XI = [64, 192, 64, 192];
	$CENTER_YI = [64, 64, 192, 192];

	$draw = new ImagickDraw();

	for ($i = 0; $i < 4; $i++)
	{
		$windDirRad = $wx[$i][0] * pi() / 180.0;
		$windSpd = $wx[$i][1] * 1.943844;

		$nsflip = (($lat < 0) ? -1.0 : 1.0);

		$wb_x = $WIND_BARB_PX * sin($windDirRad);
		$wb_y = -1.0 * $WIND_BARB_PX * cos($windDirRad);

		$wb_x0 = $CENTER_XI[$i];
		$wb_y0 = $CENTER_YI[$i];
		$wb_x1 = $wb_x0 + $wb_x;
		$wb_y1 = $wb_y0 + $wb_y;

		$fg_cols = wxlib_wind_spd_col($windSpd, $isGust);
		$fg = new ImagickPixel("rgba(".$fg_cols[0].",".$fg_cols[1].",".$fg_cols[2].",0.5)");

		$draw->setStrokeColor($fg);
		$draw->setFillColor($fg);
		$draw->setStrokeWidth(1.5);

		if ($windSpd < 0.5)
		{
			// Draw "calm" circle.

			$draw->setFillColor(new ImagickPixel("rgba(255,255,255,0.0)"));

			if ($isGust)
			{
				$poly_points = array(
					array("x" => $wb_x0 - $WIND_CALM_RADIUS_PX, "y" => $wb_y0 - $WIND_CALM_RADIUS_PX),
					array("x" => $wb_x0 + $WIND_CALM_RADIUS_PX, "y" => $wb_y0 - $WIND_CALM_RADIUS_PX),
					array("x" => $wb_x0 + $WIND_CALM_RADIUS_PX, "y" => $wb_y0 + $WIND_CALM_RADIUS_PX),
					array("x" => $wb_x0 - $WIND_CALM_RADIUS_PX, "y" => $wb_y0 + $WIND_CALM_RADIUS_PX)
				);
				$draw->polygon($poly_points);

				$poly_points = array(
					array("x" => $wb_x0 - ($WIND_CALM_RADIUS_PX / 2), "y" => $wb_y0 - ($WIND_CALM_RADIUS_PX / 2)),
					array("x" => $wb_x0 + ($WIND_CALM_RADIUS_PX / 2), "y" => $wb_y0 - ($WIND_CALM_RADIUS_PX / 2)),
					array("x" => $wb_x0 + ($WIND_CALM_RADIUS_PX / 2), "y" => $wb_y0 + ($WIND_CALM_RADIUS_PX / 2)),
					array("x" => $wb_x0 - ($WIND_CALM_RADIUS_PX / 2), "y" => $wb_y0 + ($WIND_CALM_RADIUS_PX / 2))
				);
				$draw->polygon($poly_points);
			}
			else
			{
				$draw->circle($wb_x0, $wb_y0, $wb_x0, $wb_y0 + $WIND_CALM_RADIUS_PX);
				$draw->circle($wb_x0, $wb_y0, $wb_x0, $wb_y0 + ($WIND_CALM_RADIUS_PX / 2));
			}

			$draw->setFillColor($fg);
		}
		else
		{
			// Draw wind barb.

			if ($isGust)
			{
				$poly_points = array(
					array("x" => $wb_x0 - ($WIND_CALM_RADIUS_PX / 2), "y" => $wb_y0 - ($WIND_CALM_RADIUS_PX / 2)),
					array("x" => $wb_x0 + ($WIND_CALM_RADIUS_PX / 2), "y" => $wb_y0 - ($WIND_CALM_RADIUS_PX / 2)),
					array("x" => $wb_x0 + ($WIND_CALM_RADIUS_PX / 2), "y" => $wb_y0 + ($WIND_CALM_RADIUS_PX / 2)),
					array("x" => $wb_x0 - ($WIND_CALM_RADIUS_PX / 2), "y" => $wb_y0 + ($WIND_CALM_RADIUS_PX / 2))
				);
				$draw->polygon($poly_points);
			}
			else
			{
				$draw->circle($wb_x0, $wb_y0, $wb_x0, $wb_y0 + ($WIND_CALM_RADIUS_PX / 2));
			}

			$draw->line($wb_x0, $wb_y0, $wb_x1, $wb_y1);

			$windSpd += 2.5;

			$barb_x = $wb_x1;
			$barb_y = $wb_y1;

			$has1050 = false;

			while ($windSpd >= 50)
			{
				// Draw 50-mark.

				$bm_x = $WIND_BARB_MARK_1050_PX * sin($windDirRad + $nsflip * pi() / 2);
				$bm_y = -1 * $WIND_BARB_MARK_1050_PX * cos($windDirRad + $nsflip * pi() / 2);

				$poly_points = [["x" => $barb_x, "y" => $barb_y], ["x" => $barb_x + $bm_x, "y" => $barb_y + $bm_y], ["x" => $barb_x - ($wb_x / 5), "y" => $barb_y - ($wb_y / 5)]];

				$draw->polygon($poly_points);

				$barb_x -= ($wb_x / 5);
				$barb_y -= ($wb_y / 5);

				$windSpd -= 50;
				$has1050 = true;
			}

			if ($has1050)
			{
				$barb_x -= ($wb_x / 10);
				$barb_y -= ($wb_y / 10);
			}

			while ($windSpd >= 10)
			{
				// Draw 10-mark.

				$bm_x = $WIND_BARB_MARK_1050_PX * sin($windDirRad + $nsflip * pi() / 3);
				$bm_y = -1 * $WIND_BARB_MARK_1050_PX * cos($windDirRad + $nsflip * pi() / 3);

				$draw->line($barb_x, $barb_y, $barb_x + $bm_x, $barb_y + $bm_y);

				$barb_x -= ($wb_x / 10);
				$barb_y -= ($wb_y / 10);

				$windSpd -= 10;
				$has1050 = true;
			}

			if (!$has1050)
			{
				$barb_x -= ($wb_x / 10);
				$barb_y -= ($wb_y / 10);
			}

			while ($windSpd >= 5)
			{
				// Draw 5-mark.

				$bm_x = 0.5 * $WIND_BARB_MARK_1050_PX * sin($windDirRad + $nsflip * pi() / 3);
				$bm_y = -0.5 * $WIND_BARB_MARK_1050_PX * cos($windDirRad + $nsflip * pi() / 3);

				$draw->line($barb_x, $barb_y, $barb_x + $bm_x, $barb_y + $bm_y);

				$barb_x -= ($wb_x / 10);
				$barb_y -= ($wb_y / 10);

				$windSpd -= 5;
			}
		}
	}

	$im = new Imagick();
	$im->newImage(256, 256, "rgba(255,255,255,0.0)");
	$im->setImageFormat("png");
	$im->drawImage($draw);

	return $im->getImageBlob();
}

function getSeaIceTileImage($wx)
{
	$CENTER_XI = [64, 192, 64, 192];
	$CENTER_YI = [64, 64, 192, 192];

	$draw = new ImagickDraw();

	$needsDraw = false;

	for ($i = 0; $i < 4; $i++)
	{
		if ($wx[$i] < 0.0)
		{
			continue;
		}

		$needsDraw = true;
		$seaIce = $wx[$i];

		$fg = new ImagickPixel("rgba(255,255,255,".($seaIce / 250.0 + 0.1).")");

		$draw->setStrokeColor($fg);
		$draw->setFillColor($fg);
		$draw->setStrokeWidth(1.5);

		$l_x = $CENTER_XI[$i];
		$l_y = $CENTER_YI[$i];

		$d_xy = 0.64 * $seaIce;

		$poly_points = [["x" => $l_x - $d_xy, "y" => $l_y - $d_xy],
			["x" => $l_x + $d_xy, "y" => $l_y - $d_xy],
			["x" => $l_x + $d_xy, "y" => $l_y + $d_xy],
			["x" => $l_x - $d_xy, "y" => $l_y + $d_xy]];

		$draw->polygon($poly_points);

		if ($seaIce > 99.9)
		{
			$draw->setStrokeColor(new ImagickPixel("rgba(255,255,255,0.1)"));
			$draw->line($l_x - $d_xy, $l_y - $d_xy, $l_x + $d_xy, $l_y + $d_xy);
			$draw->line($l_x - $d_xy, $l_y + $d_xy, $l_x + $d_xy, $l_y - $d_xy);
			$draw->setStrokeColor($fg);
		}
	}

	if ($needsDraw)
	{
		$im = new Imagick();
		$im->newImage(256, 256, "rgba(255,255,255,0.0)");
		$im->setImageFormat("png");
		$im->drawImage($draw);

		return $im->getImageBlob();
	}
	else
	{
		return false;
	}
}

function getWaveHeightTileImage($wx)
{
	$CENTER_XI = [64, 192, 64, 192];
	$CENTER_YI = [64, 64, 192, 192];

	$WAVE_LINE_PX = 8;

	$draw = new ImagickDraw();

	$needsDraw = false;

	for ($i = 0; $i < 4; $i++)
	{
		if ($wx[$i] < -100.0)
		{
			continue;
		}

		$needsDraw = true;
		$waveHeight = $wx[$i];

		$fg_cols = wxlib_wave_height_col($waveHeight);
		$fg = new ImagickPixel("rgba(".$fg_cols[0].",".$fg_cols[1].",".$fg_cols[2].",0.5)");

		$draw->setStrokeColor($fg);
		$draw->setFillColor($fg);
		$draw->setStrokeWidth(1.5);

		$l_x = $CENTER_XI[$i];
		$l_y = $CENTER_YI[$i] - ($WAVE_LINE_PX / 2);

		if ($waveHeight < 0.5)
		{
			$draw->line($l_x - 0.8 * $WAVE_LINE_PX, $l_y + 0.6 * $WAVE_LINE_PX, $l_x + 0.8 * $WAVE_LINE_PX, $l_y + 0.6 * $WAVE_LINE_PX);
		}
		else
		{
			while ($waveHeight >= 4.5)
			{
				$poly_points = [["x" => $l_x - 0.8 * $WAVE_LINE_PX, "y" => $l_y + 0.6 * $WAVE_LINE_PX],
					["x" => $l_x, "y" => $l_y],
					["x" => $l_x + 0.8 * $WAVE_LINE_PX, "y" => $l_y + 0.6 * $WAVE_LINE_PX]];

				$draw->polygon($poly_points);

				$waveHeight -= 5.0;

				if ($waveHeight >= 4.5)
				{
					$l_y -= ($WAVE_LINE_PX / 1.5);
				}
				else
				{
					$l_y -= ($WAVE_LINE_PX / 2);
				}
			}

			while ($waveHeight >= 0.5)
			{
				$draw->line($l_x, $l_y, $l_x + 0.8 * $WAVE_LINE_PX, $l_y + 0.6 * $WAVE_LINE_PX);
				$draw->line($l_x, $l_y, $l_x - 0.8 * $WAVE_LINE_PX, $l_y + 0.6 * $WAVE_LINE_PX);

				$waveHeight -= 1.0;
				$l_y -= ($WAVE_LINE_PX / 2);
			}
		}

	}

	if ($needsDraw)
	{
		$im = new Imagick();
		$im->newImage(256, 256, "rgba(255,255,255,0.0)");
		$im->setImageFormat("png");
		$im->drawImage($draw);

		return $im->getImageBlob();
	}
	else
	{
		return false;
	}
}

function getOceanCurrentTileImage($wx)
{
	$LINE_SPEED_FACTOR_PX = 20;

	$CENTER_XI = [64, 192, 64, 192];
	$CENTER_YI = [64, 64, 192, 192];

	$draw = new ImagickDraw();

	$needsDraw = false;

	for ($i = 0; $i < 4; $i++)
	{
		if ($wx[$i][0] < -100.0 || $wx[$i][1] < -100.0)
		{
			continue;
		}

		$needsDraw = true;

		$curDirRad = $wx[$i][0] * pi() / 180.0;
		$curSpd = $wx[$i][1] * 1.943844;

		$l_x = $LINE_SPEED_FACTOR_PX * $curSpd * sin($curDirRad);
		$l_y = -1.0 * $LINE_SPEED_FACTOR_PX * $curSpd * cos($curDirRad);

		$l_x0 = $CENTER_XI[$i] - ($l_x / 2);
		$l_y0 = $CENTER_YI[$i] - ($l_y / 2);
		$l_x1 = $l_x0 + $l_x;
		$l_y1 = $l_y0 + $l_y;

		$fg_cols = wxlib_ocean_current_spd_col($curSpd);
		$fg = new ImagickPixel("rgba(".$fg_cols[0].",".$fg_cols[1].",".$fg_cols[2].",0.5)");

		$draw->setStrokeColor($fg);
		$draw->setFillColor($fg);
		$draw->setStrokeWidth(1.5);

		// Draw current vector.
		$draw->line($l_x0, $l_y0, $l_x1, $l_y1);

		$draw->line($l_x1, $l_y1, $l_x1 + 2 * $curSpd * sin($curDirRad + (135.0 * pi() / 180.0)), $l_y1 - 2 * $curSpd * cos($curDirRad + (135.0 * pi() / 180.0)));
		$draw->line($l_x1, $l_y1, $l_x1 + 2 * $curSpd * sin($curDirRad - (135.0 * pi() / 180.0)), $l_y1 - 2 * $curSpd * cos($curDirRad - (135.0 * pi() / 180.0)));
	}

	if ($needsDraw)
	{
		$im = new Imagick();
		$im->newImage(256, 256, "rgba(255,255,255,0.0)");
		$im->setImageFormat("png");
		$im->drawImage($draw);

		return $im->getImageBlob();
	}
	else
	{
		return false;
	}
}

function getWeatherData($ll, $wxType)
{
	global $WXTILE_CONF_HOST;
	global $WXTILE_CONF_PORT;

	$f = fsockopen($WXTILE_CONF_HOST, $WXTILE_CONF_PORT, $errno, $errstr, 5);

	foreach ($ll as $lld)
	{
		fwrite($f, $wxType.",".$lld[0].",".$lld[1]."\n");
	}

	$wx = array();

	if ($wxType == "sea_ice" || $wxType == "wave_height")
	{
		for ($i = 0; $i < 4; $i++)
		{
			$line = fgets($f);
			if (preg_match("/^".$wxType.",[0-9\-\.]*,[0-9\-\.]*,([0-9\-\.]*)$/", trim($line), $r))
			{
				array_push($wx, floatval($r[1]));
			}
			else
			{
				fclose($f);
				return array();
			}
		}
	}
	else
	{
		for ($i = 0; $i < 4; $i++)
		{
			$line = fgets($f);
			if (preg_match("/^".$wxType.",[0-9\-\.]*,[0-9\-\.]*,([0-9\-\.]*),([0-9\-\.]*)$/", trim($line), $r))
			{
				array_push($wx, array(floatval($r[1]), floatval($r[2])));
			}
			else
			{
				fclose($f);
				return array();
			}
		}
	}

	fclose($f);
	return $wx;
}

?>
