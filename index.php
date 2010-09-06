<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8" />
        <title>Chat</title>
        <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.4.2/jquery.min.js"></script>
        <style>
            html {
                height: 100%;
}
            body {
                height: 100%;
                margin: 0;
                padding: 10px;
                color: #444;
                background: #3399ff;
                font: 14px/1.4em verdana, sans-serif;
}
            #chat, #console {
                margin-bottom: 10px;
                padding: 10px;
                background: #fff;
                border-top: #fff 1px solid;
                color: #666;
                -webkit-box-shadow: 0 1px 6px rgba(0,0,0,0.5), 0 -2px 10px rgba(0,0,0,0.3) inset;
                -box-shadow: 0 1px 6px rgba(0,0,0,0.5), 0 -2px 10px rgba(0,0,0,0.3) inset;
                box-shadow: 0 1px 6px rgba(0,0,0,0.5), 0 -2px 10px rgba(0,0,0,0.3) inset;
                -webkit-border-radius: 10px;
                -moz-border-radius: 10px;
}

#chat {
    min-height: 180px;
    height: 50%;
    overflow: auto;
}

#console {
    min-height: 60px;
    height: 30%;
    overflow: auto;
}
form {
    margin: 0 10px 0 0px;
    padding: 0;
}
form input {
    font-size: 1em;
    display: block;
    width: 100%;
    padding: 5px;
    margin: 0;
    background: #fff;
    border: none;
    border-top: #fff 1px solid;
    color: #666;
    -webkit-box-shadow: 0 1px 6px rgba(0,0,0,0.5), 0 -2px 10px rgba(0,0,0,0.3) inset;
    -box-shadow: 0 1px 6px rgba(0,0,0,0.5), 0 -2px 10px rgba(0,0,0,0.3) inset;
    box-shadow: 0 1px 6px rgba(0,0,0,0.5), 0 -2px 10px rgba(0,0,0,0.3) inset;
    -webkit-border-radius: 10px;
    -moz-border-radius: 10px;
}

        </style>
    </head>
    <body>
        <div id="chat">

        </div>
        <div id="console">
        </div>
        <form action="#" method="POST">
            <input name="msg" id="msg" placeholder="Type to chat :)"/>
        </form>
        <script>
            (function($) {

                //document.ready
                $(function() {
                    var chat = {
                        'settings': {
                            'server': '127.0.0.1:12345/chat/'
                        },
                        'id': null,
                        'getSuccess': function(messages) {
                            var time = null;
                            $.each(messages, function(i, v) {
                                time = new Date(parseInt(v.time * 1000));
                                $('#chat').append('<p class="'+v.type+'">'+time.getHours()+':'+time.getMinutes()+':'+time.getSeconds()+' - '+v.message+'</p>');
                            });
                        },
                        'connection': (function(){
                            if (typeof(WebSocket) === "undefined") {
                                $('#console').append('<p class="info">USING LONGPOLLING CONNECTION :/</p>');
                                return {
                                    'get': function() {
                                        if (chat.id === null || chat.timestamp === null) {
                                            $('#console').append('<p class="error">Cant fetch posts: no valid ID and/or timestamp!</p>');
                                            window.setTimeout(chat.connection.get, 1000);
                                            return;
                                        }
                                        $('#console').append('<p class="info">SENDING Get / '+chat.id+' / '+chat.timestamp+'</p>');
                                        $.ajax({
                                            'async': true,
                                            'url': 'http://' + chat.settings.server + 'get',
                                            'cache': false,
                                            'dataType': 'json',
                                            'timeout': 30000,
                                            'type': 'POST',
                                            'data': {'id': chat.id, 'timestamp': chat.timestamp},
                                            'success': function(data) {
                                   
                                                if (data) {

                                                    if (data.status == true) {
                                                        $('#console').append('<p class="info">'+data.status + ' / message: '+data.message+' / timestamp '+ data.timestamp + '</p>');
                                                        chat.timestamp = data.timestamp;
                                                        window.setTimeout(chat.connection.get, 1000);
                                                        if (data.messages) {
                                                            chat.getSuccess(data.messages);
                                                        }
                                                    }
                                                } else {
                                                    $('#console').append('<p class="info">Get returned FALSE</p>');
                                                    window.setTimeout(chat.connection.get, 5000);
                                                }
                                    
                                            },
                                            'error': function() {
                                                $('#console').append('<p class="info">Get timed out / no new messages!</p>');
                                                window.setTimeout(chat.connection.get, 5000);
                                            }
                                        });
                                    },
                                    'join': function() {
                                        $.ajax({
                                            'async': true,
                                            'url': 'http://' + chat.settings.server + 'join',
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
                                                        chat.connection.get();
                                                    }
                                                } else {
                                                    $('#console').append('<p class="info">JOIN returned FALSE</p>');
                                                    window.setTimeout(chat.connection.join, 1000);
                                                }
                                            },
                                            'error': function() {
                                                $('#console').append('<p class="info">Join timed out!</p>');
                                                window.setTimeout(chat.connection.join, 1000);
                                            }
                                        });
                                    },
                                    'set': function(message) {
                                        if (chat.id === null) {
                                            $('#console').append('<p class="error">Cant send post: no valid ID!</p>');
                                            window.setTimeout(chat.connection.set, 250, message);
                                            return;
                                        }
                                        if (!message || !message.length) {
                                            $('#console').append('<p class="error">Cant send post: no Message!</p>');
                                            return;
                                        }
                                        $.ajax({
                                            'async': true,
                                            'url': 'http://' + chat.settings.server + 'set',
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
                                                    window.setTimeout(chat.connection.set, 250, message);
                                                }
                                            },
                                            'error': function() {
                                                $('#console').append('<p class="info">Send timed out!</p>');
                                                window.setTimeout(chat.connection.set, 1000, message);
                                            }
                                        });
                                    }
                                };
                            } else {
                                var mySocket = null;
                                var mySocketOpen = false;

                                var init = function() {
                                    mySocket = new WebSocket('ws://' + chat.settings.server + 'socket');
                                    mySocket.onopen = function() {
                                        mySocketOpen = true;
                                        $('#console').append('<p class="info">Socket OPEN!</p>');
                                    }

                                    mySocket.onerror = function() {
                                        $('#console').append('<p class="info">Socket ERROR!</p>');
                                    }

                                    mySocket.onclose = function() {
                                        mySocketOpen = false;
                                        $('#console').append('<p class="info">Socket closed!</p>');
                                    }

                                    mySocket.onmessage = function(m) {
                                        data = JSON.parse(m.data);
                                        if (!data || !data.action) {
                                            $('#console').append('<p class="info">Error: got invalid response via WebSocket!</p>');
                                        }
                                        if (data.status == false) {
                                            $('#console').append('<p class="info">Error in '+data.action + ': '+data.message+'</p>');
                                        } else {
                                            if (data.action == 'join') {
                                                $('#console').append('<p class="info">'+data.status + ' / message: '+data.message+' / id '+data.id+' / timestamp '+data.timestamp+'</p>');
                                                chat.id = data.id;
                                                chat.timestamp = data.timestamp;
                                            } else if(data.action == 'set') {
                                                $('#console').append('<p class="info">'+data.status + ' / message: '+data.message+'</p>');
                                            } else if (data.action == 'get') {
                                                $('#console').append('<p class="info">'+data.status + ' / message: '+data.message+' / timestamp '+ data.timestamp + '</p>');
                                                chat.timestamp = data.timestamp;
                                                if (data.messages) {
                                                    chat.getSuccess(data.messages);
                                                }
                                            }
                                        }

                                    }
                                }
                                $('#console').append('<p class="info">USING WEBSOCKET CONNECTION :)</p>');
                                return {
                                    'get': function() {

                                    },
                                    'join': function() {
                                        if (mySocket === null) {
                                            init();
                                        }
                                        if (mySocketOpen == true) {
                                            mySocket.send('POST /chat/join'+"\n\n"+'username=User%20' + Math.floor(Math.random() * (999 - 100 + 1)) + 100);
                                        } else {
                                            window.setTimeout(chat.connection.join, 250);
                                        }
                                    },
                                    'set': function(message) {
                                        if (chat.id === null) {
                                            $('#console').append('<p class="error">Cant send post: no valid ID!</p>');
                                            window.setTimeout(chat.connection.set, 250, message);
                                            return;
                                        }
                                        if (!message || !message.length) {
                                            $('#console').append('<p class="error">Cant send post: no Message!</p>');
                                            return;
                                        }
                                        mySocket.send('POST /chat/set'+"\r\n\r\n"+'id=' + chat.id + '&message='+encodeURIComponent(message));
                                    }
                                };
                            }
                        })()
                    };

                    chat.connection.join();

                    $('#msg').keydown(function(event) {
                        if(13 == event.keyCode) {
                            chat.connection.set($(this).attr('value'));
                            $(this).attr('value', '');
                            return false;
                        }
                    });

                });
            })(jQuery);
        </script>
    </body>
</html>