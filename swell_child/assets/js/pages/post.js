/**
 * Convert Gutenberg galleries within single post content into Swiper sliders.
 * Only runs on single posts. Safe from double execution.
 */
document.addEventListener('DOMContentLoaded', () => {
  const content = document.querySelector('.post_content');
  if (!content) return;

  const galleries = content.querySelectorAll('.wp-block-gallery');
  galleries.forEach((gallery) => {
    if (gallery.dataset.soraSwiped === '1') return;

    const imgs = gallery.querySelectorAll('figure img, .blocks-gallery-item img');
    if (imgs.length < 2) return;

    const wrapper = document.createElement('div');
    wrapper.className = 'swiper sora-image-swiper';

    const track = document.createElement('div');
    track.className = 'swiper-wrapper';

    imgs.forEach((img) => {
      const slide = document.createElement('div');
      slide.className = 'swiper-slide';
      slide.appendChild(img.cloneNode(true));
      track.appendChild(slide);
    });

    const pagination = document.createElement('div');
    pagination.className = 'swiper-pagination';

    wrapper.appendChild(track);
    wrapper.appendChild(pagination);

    gallery.replaceWith(wrapper);

    new Swiper(wrapper, {
      loop: true,
      slidesPerView: 1,
      pagination: { el: pagination, clickable: true },
    });

    wrapper.dataset.soraSwiped = '1';
  });
});
