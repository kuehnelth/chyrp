<?php
	class Photo extends Feather {
		public function __construct() {
			$this->setField(array("attr" => "photo", "type" => "file", "label" => __("Photo", "photo")));
			$this->setField(array("attr" => "from_url", "type" => "text", "label" => __("From URL?", "photo"), "optional" => true, "no_value" => true));
			$this->setField(array("attr" => "caption", "type" => "text_block", "label" => __("Caption", "photo"), "optional" => true, "preview" => true, "bookmarklet" => "selection"));
			$this->setFilter("caption", "markup_post_text");
			$this->respondTo("delete_post", "delete_file");
			$this->respondTo("filter_post", "filter_post");
			$this->respondTo("new_post_options", "alt_text_field");
			$this->respondTo("edit_post_options", "alt_text_field");
		}
		public function submit() {
			$filename = "";
			if (isset($_FILES['photo']) and $_FILES['photo']['error'] == 0)
				$filename = upload($_FILES['photo'], array("jpg", "jpeg", "png", "gif", "tiff", "bmp"));
			elseif (!empty($_POST['from_url'])) {
				$file = tempnam(sys_get_temp_dir(), "chyrp");
				file_put_contents($file, get_remote($_POST['from_url']));
				$fake_file = array("name" => basename(parse_url($_POST['from_url'], PHP_URL_PATH)),
				                   "tmp_name" => $file);
				$filename = upload($fake_file, array("jpg", "jpeg", "png", "gif", "tiff", "bmp"), "", true);
			} else
				error(__("Error"), __("Couldn't upload photo."));

			$values = array("filename" => $filename, "caption" => $_POST['caption']);
			$clean = (!empty($_POST['slug'])) ? $_POST['slug'] : "" ;
			$url = Post::check_url($clean);

			$post = Post::add($values, $clean, $url);

			$route = Route::current();
			if (isset($_POST['bookmarklet']))
				redirect($route->url("bookmarklet/done/"));
			else
				redirect($post->url());
		}
		public function update() {
			$post = new Post($_POST['id']);

			if (isset($_FILES['photo']) and $_FILES['photo']['error'] == 0) {
				$this->delete_file($post);
				$filename = upload($_FILES['photo']);
			} else
				$filename = $post->filename;

			$values = array("filename" => $filename, "caption" => $_POST['caption']);

			$post->update($values);
		}
		public function title($post) {
			$caption = $post->title_from_excerpt();
			return fallback($caption, $post->filename, true);
		}
		public function excerpt($post) {
			return $post->caption;
		}
		public function feed_content($post) {
			return self::image_tag_for($post, 500, 500)."<br /><br />".$post->caption;
		}
		public function delete_file($post) {
			if ($post->feather != "photo") return;
			unlink(MAIN_DIR.Config::current()->uploads_path.$post->filename);
		}
		public function filter_post($post) {
			if ($post->feather != "photo") return;
			$post->image = $this->image_tag_for($post);
		}
		public function image_tag_for($post, $max_width = 500, $max_height = null, $more_args = "quality=100") {
			$filename = $post->filename;
			$ext = pathinfo($filename, PATHINFO_EXTENSION);
			$config = Config::current();
			return '<a href="'.$config->chyrp_url.$config->uploads_path.$filename.'"><img src="'.$config->chyrp_url.'/feathers/photo/thumb.php?file=../..'.$config->uploads_path.urlencode($filename).'&amp;sizex='.$max_width.'&amp;sizey='.$max_height.'&amp;'.$more_args.'" alt="'.fallback($post->alt_text, $filename, true).'" /></a>';
		}
		public function alt_text_field($post = null) {
			if (isset($post) and $post->feather != "photo") return;
			if (!isset($_GET['feather']) and Config::current()->enabled_feathers[0] != "photo" or
			    isset($_GET['feather']) and $_GET['feather'] != "photo") return;
?>
					<p>
						<label for="option_alt_text"><?php echo __("Alt-Text", "photo"); ?></label>
						<input class="text" type="text" name="option[alt_text]" value="<?php echo fix(fallback($post->alt_text, "", true)); ?>" id="alt_text" />
					</p>
<?php
		}
	}