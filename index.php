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
                    'server': 'http://t3n.local:12345/chat/'
                };
                $(function() {
                    var chat = {
                        'id': null,
                        'get': function() {
                            if (chat.id === null || chat.timestamp === null) {
                                $('#console').append('<p class="error">Cant fetch posts: no valid ID and/or timestamp!</p>');
                            }
                            $.ajax({
                                'async': true,
                                'url': settings.server + 'get',
                                'cache': false,
                                'dataType': 'json',
                                'timeout': 30000,
                                'type': 'POST',
                                'data': {'id': chat.id, 'timestamp': chat.timestamp},
                                'success': function(data) {
                                    $('#console').append('<p class="info">'+data.status + ' / message: '+data.message+' / timestamp '+ data.timestamp + '</p>');
                                    chat.timestamp = data.timestamp;

                                    //fetch messages;

                                    chat.get();
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
                                'data': {'username': 'User ' + Math.floor(Math.random() * (999 - 100 + 1)) + min},
                                'success': function(data) {
                                    $('#console').append('<p class="info">'+data.status + ' / message: '+data.message+' / id '+id+' / timestamp '+data.timestamp+'</p>');
                                    
                                    chat.id = data.id;
                                    chat.timestamp = data.timestamp;
                                    chat.get();
                                }
                            });
                        }
                    };
                    

                });
            })(jQuery);
        </script>
    </body>
</html>