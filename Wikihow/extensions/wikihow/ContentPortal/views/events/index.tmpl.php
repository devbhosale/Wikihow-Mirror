
<div class="well">
	<h2>Events</h2>
	<p class="lead">
		Here you can view all events that occur within the "Content Portal" application.
	</p>
	<?= paginate() ?>
	<div class="well-body">
		<? 
			foreach($events as $event):
			echo partial('events/_event', ['event' => $event]);
			endforeach;
		?>
	</div>
	<?= paginate() ?>
</div>
