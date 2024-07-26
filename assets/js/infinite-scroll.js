jQuery(document).ready(function($) {
    var page = 1;
    var loading = false;
    var loadedVideoIds = [];

    loadMoreVideos();

    function loadMoreVideos() {
        if (loading) return;

        loading = true;
        $('#loading').show();

        $.ajax({
            url: myAjax.ajaxurl,
            type: 'GET',
            data: {
                action: 'fetch_videos_data',
                page: page
            },
            success: function(data) {
                if (data.length > 0) {
                    var rows = '';
                    $.each(data, function(index, video) {
                        if (!loadedVideoIds.includes(video.video_id)) {
                            loadedVideoIds.push(video.video_id);
                            rows += `<div class="col-md-2 col-sm-2 content-main-card">
                                <div class="content-main-card-img small-square-news" style="background: #000000">
                                    <span class="language-icon">
                                    <span>En</span>  <span>Uk</span>  <span>De-DE</span>  <span>En</span> </span>
                                    <a class="yt_video_modal_btn"  data-toggle="modal" data-target="#videoModal${video.video_id}">
                                        <img class="" src="${video.thumbnail}" style="display: inline;" alt="thumbnails of the images" >
                                        <span class="thumb-play-btn"><i class="fa fa-play" aria-hidden="true"></i></span>
                                    </a>                             
                                </div>
                                <div class="content-card">
                                    <a class="yt_video_modal_btn"  data-toggle="modal" data-target="#videoModal${video.video_id}">
                                        <h4>${video.title}</h4>
                                        <p>${truncateDescription(video.description, 100)}</p>
                                    </a>
                                </div>
                            </div>
                            <div class="yt_video_modal modal fade" id="videoModal${video.video_id}" role="dialog">
                                <div class="modal-dialog">
                                    <!-- Modal content-->
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="main_video">
                                                <div class="cutm-banner-res embed-responsive embed-responsive-16by9">
                                                    <iframe class="embed-responsive-item" id="player" frameborder="0" allowfullscreen="" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" title="Bonjour · Tim Sterbak ·&nbsp;Managing Director ·&nbsp;IST Metz" width="100%" height="100%" src="https://www.youtube.com/embed/${video.video_id}?autoplay=0&amp;color=white&amp;showinfo=0&amp;controls=1&amp;modestbranding=1&amp;cc_load_policy=1&amp;cc_lang_pref=en&amp;rel=0&amp;vq=hd1080&amp;origin=https%3A%2F%2Fstaging.inkish.tv&amp;enablejsapi=1&amp;widgetid=3"></iframe>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>`;
                        }
                    });
                    $('#append_video_content').append(rows);
                    initializejs();
                    page++;
                    loading = false;
                    $('#loading').hide();
                } else {
                    $('#loading').text('No more videos.');
                }
            }
        });
    }

    $(window).scroll(function() {
        if ($(window).scrollTop() + $(window).height() >= $(document).height() - 100) {
            loadMoreVideos();
        }
    });

    // Function to truncate the description
    function truncateDescription(description, maxLength) {
        if (description.length > maxLength) {
            return description.substring(0, maxLength) + '...';
        }
        return description;
    }

    // Custom modal
    function initializejs() {
        $('.yt_video_modal_btn').on('click', function() {
            $('.yt_video_modal').fadeOut('fast');  // Ensure any open modals fade out quickly
   
            var target_id = $(this).data('target');
            $(target_id).fadeIn('slow');  // Fade in the target modal
    
            $(target_id).find('.close').on('click', function() {
                $(target_id).fadeOut('slow');  // Fade out the modal when close button is clicked
            });
        });
    }
    

    initializejs();
});
