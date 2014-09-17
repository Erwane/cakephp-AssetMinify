<?php

// JS
Router::connect('/asset_min/js_:id', array('plugin' => 'AssetMinify', 'controller' => 'minify', 'action' => 'js'), array(
  'id' => '[a-f0-9]+'
));

// CSS
Router::connect('/asset_min/css_:id', array('plugin' => 'AssetMinify', 'controller' => 'minify', 'action' => 'css'), array(
  'id' => '[a-f0-9]+'
));

// Builds
Router::connect('/asset_min/build/:type', array('plugin' => 'AssetMinify', 'controller' => 'minify', 'action' => 'build'));

