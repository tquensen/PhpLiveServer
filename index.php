<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8" />
        <title>Chat</title>
        <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.4.2/jquery.min.js"></script>
    </head>
    <body>
        <div id="chat">

        </div>
        <div id="console">
        </div>
        <form action="#" method="POST">
            <input name="msg" id="msg" />
        </form>
        <script>
            (function($) {
                if (XMLHttpRequest)
                {
                    var request = new XMLHttpRequest();
                    if (request.withCredentials === undefined)
                    {
                      alert('Your Browser cant make CORS :(');
                    }
                }
                var settings = {
                    'server': 'http://127.0.0.1:12345/chat/'
                };
                $(function() {
                    var chat = {
                        'id': null,
                        'get': function() {
                            if (chat.id === null || chat.timestamp === null) {
                                $('#console').append('<p class="error">Cant fetch posts: no valid ID and/or timestamp!</p>');
                            }
                            $('#console').append('<p class="info">SENDING Get / '+chat.id+' / '+chat.timestamp+'</p>');
                            $.ajax({
                                'async': true,
                                'url': settings.server + 'get',
                                'cache': false,
                                'dataType': 'json',
                                'timeout': 30000,
                                'type': 'POST',
                                'data': {'id': chat.id, 'timestamp': chat.timestamp},
                                'success': function(data) {
                                   
                                    if (data) {
                                        if (data.messages) {
                                            var time = null;
                                            $.each(data.messages, function(i, v) {
                                                time = new Date(parseInt(v.time * 1000));
                                                $('#chat').append('<p class="'+v.type+'">'+time.getHours()+':'+time.getMinutes()+':'+time.getSeconds()+' - '+v.message+'</p>');
                                            });
                                        }

                                        if (data.status == true) {
                                             $('#console').append('<p class="info">'+data.status + ' / message: '+data.message+' / timestamp '+ data.timestamp + '</p>');
                                            chat.timestamp = data.timestamp;
                                            window.setTimeout(chat.get, 1000);
                                        }
                                    } else {
                                        $('#console').append('<p class="info">Get returned FALSE</p>');
                                        window.setTimeout(chat.get, 5000);
                                    }
                                    
                                },
                                'error': function() {
                                    $('#console').append('<p class="info">Get timed out / no new messages!</p>');
                                    window.setTimeout(chat.get, 5000);
                                }
                            });
                        },
                        'join': function() {
                            $.ajax({
                                'async': true,
                                'url': settings.server + 'join',
                                'cache': false,
                                'dataType': 'json',
                                'timeout': 30000,
                                'type': 'POST',
                                'data': {'username': 'User ' + Math.floor(Math.random() * (999 - 100 + 1)) + 100},
                                'success': function(data) {
                                    if (data) {
                                        $('#console').append('<p class="info">'+data.status + ' / message: '+data.message+' / id '+data.id+' / timestamp '+data.timestamp+'</p>');

                                        if (data.status == true) {
                                            chat.id = data.id;
                                            chat.timestamp = data.timestamp;
                                            chat.get();
                                        }
                                    } else {
                                        $('#console').append('<p class="info">JOIN returned FALSE</p>');
                                        window.setTimeout(chat.join, 5000);
                                    }
                                },
                                'error': function() {
                                    $('#console').append('<p class="info">Join timed out!</p>');
                                }
                            });
                        },
                        'set': function(message) {
                            if (chat.id === null) {
                                $('#console').append('<p class="error">Cant send post: no valid ID!</p>');
                            }
                            if (!message || !message.length) {
                                $('#console').append('<p class="error">Cant send post: no Message!</p>');
                            }
                            $.ajax({
                                'async': true,
                                'url': settings.server + 'set',
                                'cache': false,
                                'dataType': 'json',
                                'timeout': 30000,
                                'type': 'POST',
                                'data': {'id': chat.id, 'message': message},
                                'success': function(data) {
                                    if (data) {
                                        $('#console').append('<p class="info">'+data.status + ' / message: '+data.message+'</p>');
                                    } else {
                                        $('#console').append('<p class="info">SET returned FALSE</p>');
                                    }
                                },
                                'error': function() {
                                    $('#console').append('<p class="info">Send timed out / no new messages!</p>');
                                }
                            });
                        }
                    };

                    chat.join();

                    $('#msg').keydown(function(event) {
                         if(13 == event.keyCode) {
                             chat.set($(this).attr('value'));
                             $(this).attr('value', '');
                             return false;
                         }
                    });

                });
            })(jQuery);
        </script>
    </body>
</html>