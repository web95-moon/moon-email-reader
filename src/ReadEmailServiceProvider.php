<?php

namespace moon\reademail;

use Illuminate\Support\Facades\App;

class ReadEmailServiceProvider extends \Illuminate\Support\ServiceProvider
{

	public function boot()
	{
		$this->publishes([__DIR__.'/config/moonemailreader.php' => config_path('moonemailreader.php')]);
	}

	
}