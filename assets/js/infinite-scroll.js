/**
 * ChurchPlugins Infinite Scroll
 * 
 * Usage: create an element with the class cp-infinite-scroll in order for it to be picked up by this script.
 * 
 * The element must have a data-url attribute that points to an AJAX endpoint (most likely admin-ajax.php)
 * It also must have a data-config attribute that contains a JSON object with the AJAX config.
 * The AJAX endpoint should expect a page parameter, and should display content based on the page.
 * If the AJAX endpoint returns no content, the scroll listener assume the content is ended and will stop.
 */

(function($) {
	class InfiniteScroll {
		constructor(el) {
			this.el = $(el);

			this.url = new URL(el.dataset.url);

			if( ! el.dataset.config ) {
				throw new Error("InfiniteScroll expects an AJAX config.");
			}
			
			this.config = JSON.parse(el.dataset.config);
			this.page = 0;
			this.done = false;
			this._loading = false;
			this.nextPage();
			window.addEventListener('scroll', this.scrollListener.bind(this));
		}
	
		nextPage() {
			if( this.loading || this.done ) return;
			this.page++;
			this.config.page = this.page;
			this.loading = true;
			this.loadNextPage();
		}

		loadNextPage() {
			$.ajax({
				url: this.url.toString(),
				data: this.config,
				success: (data) => {
					if( !data ) {
						this.done = true;
						this.cancelScrollListener();
						return;
					}
					const newElem = $(data);
				  this.el.append(newElem);
					this.loading = false;
				},
				error: (err) => {
					this.done = true;
					this.loading = false;
				}
			})
		}

		cancelScrollListener() {
			window.removeEventListener('scroll', this.scrollListener.bind(this));
		}

		scrollListener() {
			if( this.loading ) return;
			const elem = this.el.get(0).getBoundingClientRect();
			if( elem.bottom < window.innerHeight ) {
				this.nextPage();
			}
		}

		set loading(val) {
			if( val ) {
				this.el.addClass('loading');
				this.el.append('<div class="cp-infinite-scroll-loading"></div>');
			}
			else {
				this.el.removeClass('loading');
				this.el.find('.cp-infinite-scroll-loading').remove();
			}
			this._loading = val;
		}

		get loading() {
			return this._loading;
		}
	}


	$(document).ready(() => {
		document.querySelectorAll('.cp-infinite-scroll').forEach((el) => {
			new InfiniteScroll(el);
		});
	})
})(jQuery);

