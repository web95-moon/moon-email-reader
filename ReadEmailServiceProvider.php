<?php

namespace Moon\Reademail;

use Illuminate\Support\Facades\App;
use Illuminate\Support\ServiceProvider;

class ReadEmailServiceProvider extends ServiceProvider
{

	public function boot()
	{
		$this->publishes([__DIR__.'/config/moonemailreader.php' => config_path('moonemailreader.php')]);
	}

	
}