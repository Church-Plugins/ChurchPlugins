
(function($) {
	class InfiniteScroll {
		constructor(el) {
			this.el = $(el);
			this.url = new URL(el.dataset.url);
			this.config = JSON.parse(el.dataset.config);
			this.page = 0;
			this.done = false;
			this.loading = true;
			this.nextPage();
			window.addEventListener('scroll', this.scrollListener.bind(this));
		}
	
		nextPage() {
			if( this.done ) return;
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
			}
			else {
				this.el.removeClass('loading');
			}
		}
	}


	$(document).ready(() => {
		document.querySelectorAll('.cp-infinite-scroll').forEach((el) => {
			new InfiniteScroll(el);
		});
	})
})(jQuery);

