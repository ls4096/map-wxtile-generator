<?php
# Copyright (C) 2021-2023 ls4096 <ls4096@8bitbyte.ca>
#
# This Source Code Form is subject to the terms of the Mozilla Public
# License, v. 2.0. If a copy of the MPL was not distributed with this
# file, You can obtain one at https://mozilla.org/MPL/2.0/.


// Speed value in knots.
function wxlib_wind_spd_col($val, $isGust)
{
	$palette_keys = array();
	$palette_values = array();

	if ($isGust)
	{
		$palette_keys = array(
			0,
			24.9,
			25.1,
			34.9,
			35.1,
			44.9,
			45.1,
			100,
			1000
		);

		$palette_values = array(
			"003000", // 0
			"006000", // 24.9
			"608060", // 25.1
			"808040", // 34.9
			"808000", // 35.1
			"a08000", // 44.9
			"a00000", // 45.1
			"600000", // 100
			"600000"  // 1000
		);
	}
	else
	{
		$palette_keys = array(
			0,
			5,
			10,
			15,
			20,
			30,
			40,
			50,
			65,
			80,
			100,
			1000
		);

		$palette_values = array(
			"000060", // 0
			"004090", // 5
			"009040", // 10
			"609000", // 15
			"a09000", // 20
			"d07000", // 30
			"d03000", // 40
			"dd0000", // 50
			"d00060", // 65
			"dd60a0", // 80
			"ddb0d0", // 100
			"ddb0d0"  // 1000
		);
	}

	return wxlib_col_from_palette($palette_keys, $palette_values, $val);
}

function wxlib_ocean_current_spd_col($val)
{
	$palette_keys = array(
		0,
		1,
		2,
		3,
		5,
		100
	);

	$palette_values = array(
		"000030", // 0
		"008080", // 1
		"909000", // 2
		"a08000", // 3
		"a03060", // 5
		"a03060"  // 100
	);

	return wxlib_col_from_palette($palette_keys, $palette_values, $val);
}

function wxlib_wave_height_col($val)
{
	$palette_keys = array(
		0,
		0.5,
		1,
		2,
		3,
		4,
		5,
		7.5,
		10,
		15,
		20,
		100
	);

	$palette_values = array(
		"000060", // 0
		"003080", // 0.5
		"008030", // 1
		"409000", // 2
		"909000", // 3
		"b05800", // 4
		"b02000", // 5
		"c00000", // 7.5
		"b00040", // 10
		"b05080", // 15
		"b09090", // 20
		"b09090"  // 100
	);

	return wxlib_col_from_palette($palette_keys, $palette_values, $val);
}

function wxlib_col_from_palette($pk, $pv, $val)
{
	$v = floatval($val);
	$n = count($pk) - 1;

	if ($v < floatval($pk[0]))
	{
		return $pv[0];
	}

	if ($v > floatval($pk[$n]))
	{
		return $pv[$n];
	}

	for ($i = 0; $i < $n; $i++)
	{
		$min = floatval($pk[$i]);
		$max = floatval($pk[$i+1]);
		if (($v >= $min) && ($v <= $max))
		{
			return wxlib_col_from_range(($v - $min) / ($max - $min), $pv[$i], $pv[$i+1]);
		}
	}

	return [255, 255, 255];
}

function wxlib_col_from_range($val, $min_col, $max_col)
{
	$r_min = wxlib_get_dec_val($min_col[0].$min_col[1]);
	$g_min = wxlib_get_dec_val($min_col[2].$min_col[3]);
	$b_min = wxlib_get_dec_val($min_col[4].$min_col[5]);

	$r_max = wxlib_get_dec_val($max_col[0].$max_col[1]);
	$g_max = wxlib_get_dec_val($max_col[2].$max_col[3]);
	$b_max = wxlib_get_dec_val($max_col[4].$max_col[5]);

	$r = intval($val * ($r_max - $r_min) + $r_min);
	$g = intval($val * ($g_max - $g_min) + $g_min);
	$b = intval($val * ($b_max - $b_min) + $b_min);

	if ($r < 0) { $r = 0; }
	if ($g < 0) { $g = 0; }
	if ($b < 0) { $b = 0; }

	if ($r > 255) { $r = 255; }
	if ($g > 255) { $g = 255; }
	if ($b > 255) { $b = 255; }

	return [$r, $g, $b];
}

function wxlib_get_dec_val($h)
{
	return (wxlib_get_dec($h[0]) * 16 + wxlib_get_dec($h[1]));
}

function wxlib_get_dec($h)
{
	$b = ord($h);
	if (($b >= 0x30) && ($b <= 0x39))
	{
		// 0-9
		return ($b - 0x30);
	}
	else if (($b >= 0x61) && ($b <= 0x66))
	{
		// a-f
		return ($b - 0x61 + 10);
	}
	else if (($b >= 0x41) && ($b <= 0x46))
	{
		// A-F
		return ($b - 0x41 + 10);
	}

	return 0;
}

?>
