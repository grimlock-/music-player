<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title>Moozik</title>
	<link rel="stylesheet" href="css/main.css" />
	<link rel="stylesheet" href="css/layout-one.css" />
	<link rel="stylesheet" href="css/views.css" />
	<link rel="icon" type="image/x-icon" href="favicon.ico" />
	<link rel="icon" type="image/png" href="favicon.png" />
	<link rel="preload" href="img/album.png" as="image" />
	<link rel="preload" href="img/noartist.png" as="image" />
	<script>
	<?php
		require("config.php");
		echo "var thumbnail_format = \"$thumbnail_format\";
		var album_art_directory = \"$album_art_directory\";
		var song_art_directory = \"$song_art_directory\";
		var album_thumbnail_directory = \"$album_thumbnail_directory\";
		var song_thumbnail_directory = \"$song_thumbnail_directory\";";
	?>
	let ua = navigator.userAgent || navigator.vendor || window.opera;
	if(/windows phone/i.test(ua) ||
	/android/i.test(ua) ||
	// iOS detection from: http://stackoverflow.com/a/9039885/177710
	/iPad|iPhone|iPod/.test(ua) && !window.MSStream)
	{
		if(confirm("User agent looks like a phone browser. Navigate to mobile page?"))
			location.href = "mobile.html";
	}
	</script>
	<script type="module" src="js/main.js"></script>
</head>
<body>
	<div id="content" class="layout-one-grid">
		<div id="categories">
			<button type="button" id="timeline_cat" class="catButton" data-view="timeline">Logo</button>
			<button type="button" id="artists_cat" class="catButton" data-view="artists">Artists</button>
			<button type="button" id="albums_cat" class="catButton" data-view="albums">Albums</button>
			<button type="button" id="songs_cat" class="catButton" data-view="songs">Songs</button>
			<button type="button" id="genres_cat" class="catButton" data-view="genres">Genres</button>
			<button type="button" id="realtimeline_cat" class="catButton" data-view="real_timeline">Timeline</button>
			<button type="button" id="favorites_cat" class="catButton" data-view="favorites">Favorites</button>
			<button type="button" id="random_cat" class="catButton" data-view="random">Random</button>
			<button type="button" id="playlists_cat" class="catButton" data-view="playlists">Playlists</button>
			<button type="button" id="videos_cat" class="catButton" data-view="videos">Videos</button>
		</div>
		<input type="search" id="quicksearch" autocomplete="off" placeholder="Quick Search" />
		<div id="quicksearch_results" class="hidden"></div>
		<div id="queue"><div id="songs"></div><div id="queue_info"></div></div>
		<div id="controls">
			<div id="previous">Previous</div>
			<div id="playpause">Play</div>
			<div id="stop">Stop</div>
			<div id="next">Next</div>
			<div id="loop">Loop: off</div>
			<div id="volume">Vol <input type="range" id="volume_slider" min="0" max="1" value="1" step="any" /></div>
			<div id="seekbar_stuff"><span id="tracktime">--:--</span> <input type="range" id="seekbar" min="0" max="10" value="0" step="1" /><span id="tracklen">--:--</span></div>
			<div id="clear">clear queue</div>
		</div>
		<div id="divider"></div>
		<div id="art" class="hidden"></div>
		<div id="instance"></div>
		<div id="messages" class="hidden"></div>
	</div>
	<template id="timeline_template">
		<h1>Collection Timeline</h1>
		<div id="group_options">
			<button type="button" id="year">Year</button>
			<button type="button" id="month">Year+Month</button>
			<button type="button" id="day">Day</button>
		</div>
		<div id="group_buttons">
			<!-- This will contain the "pagination" buttons like "2012", "2013", "2013-01", "2013-02", etc. -->
		</div>
		<div id="size_buttons">
			<button type="button" id="large">Large</button>
			<button type="button" id="medium">Medium</button>
			<button type="button" id="small">Small</button>
		</div>
		<div id="items">
		</div>
		<div id="collection_spotlight" class="hidden">
			<input type="image" class="close" src="img/close.png" />
			<h3 class="collection_title">Collection title here</h3>
			<img src="img/album.png">
			<div class="album_aliases"></div>
			<div id="collection_items"></div>
		</div>
	</template>
	<template id="artists_template">
		<h1>Artists</h1>
		<div id="group_buttons"></div>
		<div id="artists"></div>
	</template>
	<template id="albums_template">
		<h1>Albums</h1>
		<div id="album_type_filter"></div>
		<div id="filter_notice"></div>
		<div id="group_buttons"></div>
		<div id="albums"></div>
	</template>
	<template id="genres_template">
		<h1>Genres</h1>
	</template>
	<template id="realtimeline_template">
		<h1>Real-World Timeline</h1>
	</template>
	<template id="favorites_template">
		<h1>Favorites</h1>
		<select name="type">
			<option value="song">Songs</option>
			<option value="video">Videos</option>
			<option value="album">Albums</option>
		</select>
	</template>
	<template id="random_template">
		<h1>Random stuff</h1>

		<!-- event to change <input>'s placeholder attr -->
		<select name="type">
			<option value="song">Songs</option>
			<option value="video">Videos</option>
			<option value="album">Albums</option>
		</select>

		<input type="number" id="quantity" />

		<!-- event to get endpoint data on click -->
		<button id="regen">Click for more random songs</button>

		<div id="items"></div>
	</template>
	<template id="playlists_template">
		<h1>Playlists</h1>
	</template>
	<template id="videos_template">
		<h1>Videos</h1>
	</template>
	<template id="lyric_template">
		<h1>Song lyric viewer, maybe have an API endpoint?</h1>
	</template>"
	<template id="video_viewer_template">
		<h1>Video Viewer</h1>
		<video>
		</video>
	</template>
</body>
</html>
