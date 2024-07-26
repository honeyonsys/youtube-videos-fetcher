jQuery(document).ready(function($) {
    function fetchVideos(pageToken = '') {
        showLoader();
        $.post(youtubeSyncPlugin.ajax_url, {
            action: 'youtube_sync_plugin_fetch_videos',
            page_token: pageToken
        }, function(response) {
            if (response.success) {
                var videos = response.data.videos;
                var nextPageToken = response.data.nextPageToken;
                var totalResults = response.data.totalResults;
                var resultsPerPage = response.data.resultsPerPage;
                var totalPages = Math.ceil(totalResults / resultsPerPage);

                var html = '<form method="post" action="">';
                html += '<input type="submit" id="import-selected-videos" class="button button-primary" value="Import Selected Videos" />';
                html += '<table class="wp-list-table widefat fixed striped">';
                html += '<thead><tr><th><input type="checkbox" id="select-all" /></th><th>Video ID</th><th>Title</th><th>Thumbnail</th><th>Status</th></tr></thead><tbody>';

                for (var i = 0; i < videos.length; i++) {
                    var video = videos[i];
                    html += '<tr>';
                    html += '<td><input type="checkbox" class="video-checkbox" data-video-id="' + video.video_id + '" data-video-title="' + video.title + '" data-video-thumbnail="' + video.thumbnail + '" /></td>';
                    html += '<td>' + video.video_id + '</td>';
                    html += '<td>' + video.title + '</td>';
                    html += '<td><img src="' + video.thumbnail + '" width="100" /></td>';
                    html += '<td class="' + (video.status === 'Imported' ? 'imported' : 'not-imported') + '">' + video.status + '</td>';
                    html += '</tr>';
                }

                html += '</tbody></table></form>';

                html += '<div class="pagination">';

                if (pageToken !== '') {
                    html += '<a href="#" class="page-numbers" data-page-token="" data-page="1">First</a>';
                }
                for (var page = 1; page <= totalPages; page++) {
                    var pageTokenAttr = page === 1 ? '' : nextPageToken;
                    html += '<a href="#" class="page-numbers ' + (pageToken === '' && page === 1 ? 'current' : '') + '" data-page-token="' + pageTokenAttr + '" data-page="' + page + '">' + page + '</a>';
                }
                if (nextPageToken) {
                    html += '<a href="#" class="page-numbers" data-page-token="' + nextPageToken + '" data-page="' + totalPages + '">Last</a>';
                }

                html += '</div>';

                $('#video-list').html(html);
                hideLoader();
            } else {
                $('#video-list').html('<div class="error"><p>' + response.data + '</p></div>');
                hideLoader();
            }
        });
    }

    fetchVideos();

    $(document).on('click', '.page-numbers', function(e) {
        e.preventDefault();
        var pageToken = $(this).data('page-token');
        fetchVideos(pageToken);
    });

    $(document).on('click', '#select-all', function() {
        $('.video-checkbox').prop('checked', this.checked);
    });

    $(document).on('click', '#import-selected-videos', function(e) {
        e.preventDefault();

        var selectedVideos = {};
        $('.video-checkbox:checked').each(function() {
            var videoId = $(this).data('video-id');
            selectedVideos[videoId] = {
                video_id: videoId,
                title: $(this).data('video-title'),
                thumbnail: $(this).data('video-thumbnail')
            };
        });

        showLoader();

        $.post(youtubeSyncPlugin.ajax_url, {
            action: 'youtube_sync_plugin_import_videos',
            video_ids: Object.keys(selectedVideos),
            videos: selectedVideos
        }, function(response) {
            if (response.success) {
                alert(response.data);
                fetchVideos();
            } else {
                alert('Failed to import videos');
                hideLoader();
            }
        });
    });

    function showLoader() {
        $('body').append('<div id="loader-overlay"><div class="loader"></div></div>');
    }

    function hideLoader() {
        $('#loader-overlay').remove();
    }
});
