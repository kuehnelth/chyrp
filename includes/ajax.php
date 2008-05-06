<?php
	define('AJAX', true);
	require_once "common.php";

	switch($_POST['action']) {
		case "edit_post":
			if (!isset($_POST['id']))
				error(__("Unspecified ID"), __("Please specify an ID of the post you would like to edit."));

			$post = new Post($_POST['id'], array("filter" => false));

			if (!$post->editable())
				error(__("Access Denied"), __("You do not have sufficient privileges to edit posts."));

			$title = call_user_func(array(Post::feather_class($_POST['id']), "title"), $post);
			$theme_file = THEME_DIR."/forms/feathers/".$post->feather.".php";
			$default_file = FEATHERS_DIR."/".$post->feather."/fields.php";
			$fields_file = (file_exists($theme_file)) ? $theme_file : $default_file ;
?>
<form id="post_edit_<?php echo $post->id; ?>" class="inline" action="<?php echo $config->url."/admin/?action=update_post&amp;sub=text&amp;id=".$post->id; ?>" method="post" accept-charset="utf-8">
	<h2><?php echo sprintf(__("Editing &#8220;%s&#8221;"), truncate($title, 40, false)); ?></h2>
	<br />
<?php require $fields_file; ?>
	<a id="more_options_link_<?php echo $post->id; ?>" href="javascript:void(0)" class="more_options_link"><?php echo __("More Options &raquo;"); ?></a>
	<div id="more_options_<?php echo $post->id; ?>" class="more_options" style="display: none">
		<?php edit_post_options($post); ?>
		<br class="clear" />
	</div>
	<br />
	<input type="hidden" name="id" value="<?php echo fix($post->id, "html"); ?>" id="id" />
	<input type="hidden" name="ajax" value="true" id="ajax" />
	<div class="buttons">
		<input type="submit" value="<?php echo __("Update"); ?>" accesskey="s" /> <?php echo __("or"); ?>
		<a href="javascript:void(0)" id="post_cancel_edit_<?php echo $post->id; ?>" class="cancel"><?php echo __("Cancel"); ?></a>
	</div>
	<input type="hidden" name="hash" value="<?php echo $config->secure_hashkey; ?>" id="hash" />
</form>
<?php
			break;
		case "delete_post":
			$post = new Post($_POST['id']);
			if (!$post->deletable())
				error(__("Access Denied"), __("You do not have sufficient privileges to delete this post."));

			Post::delete($_POST['id']);
			break;
		case "view_post":
			fallback($_POST['offset'], 0);
			fallback($_POST['context']);

			$id = (isset($_POST['id'])) ? "`id` = :id" : false ;
			$reason = (isset($_POST['reason'])) ? "_".$_POST['reason'] : "" ;

			switch($_POST['context']) {
				default:
					$post = new Post(null, array("where" => array($private, $id),
					                             "offset" => $_POST['offset'],
					                             "limit" => 1));
					break;
				case "drafts":
					$post = new Post(null, array("where" => array("`status` = 'draft'", $id),
					                             "params" => array(":id" => $_POST['id']),
					                             "offset" => $_POST['offset'],
					                             "limit" => 1));
					break;
				case "archive":
					$post = new Post(null, array("where" => array("`created_at` like :created_at", $id),
					                             "params" => array(":created_at" => "'".$_POST['year']."-".$_POST['month']."%'", ":id" => $_POST['id']),
					                             "offset" => $_POST['offset'],
					                             "limit" => 1));
					break;
				case "search":
					$post = new Post(null, array("where" => array("`xml` like :query", $id),
					                             "params" => array(":query" => "'%".urldecode($_POST['query'])."%'", ":id" => $_POST['id']),
					                             "offset" => $_POST['offset'],
					                             "limit" => 1));
					break;
			}

			if ($post->no_results) {
				header("HTTP/1.1 404 Not Found");
				$trigger->call("not_found");
				exit;
			}

			$date_shown = true;
			$last = false;

			$trigger->call("above_post".$reason);
			$theme->load("content/post", array("post" => $post));
			$trigger->call("below_post");
			break;
		case "preview":
			$content = ($trigger->exists("preview_".$_POST['feather'])) ?
			            $trigger->filter("preview_".$_POST['feather'], urldecode(stripslashes($_POST['content']))) :
			            $trigger->filter("markup_post_text", urldecode(stripslashes($_POST['content']))) ;
			echo "<h2 class=\"preview-header\">".__("Preview")."</h2>\n<div class=\"preview-content\">".$content."</div>";
			break;
		case "check_confirm":
			if (!$visitor->group()->can("change_settings"))
				error(__("Access Denied"), __("You do not have sufficient privileges to enable/disable extensions."));

			$dir = ($_POST['type'] == "module") ? MODULES_DIR : FEATHERS_DIR ;
			$info = Spyc::YAMLLoad($dir."/".$_POST['check']."/info.yaml");
			fallback($info["confirm"], "");

			if (!empty($info["confirm"]))
				echo __($info["confirm"], $_POST['check']);

			break;
		case "organize_pages":
			foreach ($_POST['parent'] as $id => $parent)
				$sql->query("update `".$sql->prefix."pages` set `parent_id` = ".$sql->quote($parent)." where `id` = ".$sql->quote($id));

			foreach ($_POST['sort_pages'] as $index => $page) {
				$id = str_replace("page_list_", "", $page);
				$sql->query("update `".$sql->prefix."pages` set `list_order` = ".$sql->quote($index)." where `id` = ".$sql->quote($id));
			}

			break;
	}

	$trigger->call("ajax");
