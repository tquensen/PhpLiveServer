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
                
                var settings = {
                    'server': '127.0.0.1:12345/chat/'
                };
                $(function() {
                    var chat = {};
                    var mySocket = new WebSocket('ws://'+settings.server+'socket');
                    mySocket.onopen = function() {
                       alert('OPEN!');
                       mySocket.send('POST /chat/join'+"\n\n"+'username=User%20' + Math.floor(Math.random() * (999 - 100 + 1)) + 100);
                    }
                    mySocket.onerror = function() {
                       alert('ERROR');                      
                    }

                    mySocket.onclose = function() {
                       alert('CLOSE');
                    }

                    mySocket.onmessage = function(m) {
                       data = JSON.parse(m.data);
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
                                    var time = null;
                                    $.each(data.messages, function(i, v) {
                                        time = new Date(parseInt(v.time * 1000));
                                        $('#chat').append('<p class="'+v.type+'">'+time.getHours()+':'+time.getMinutes()+':'+time.getSeconds()+' - '+v.message+'</p>');
                                    });
                                }
                           }
                       }

                    }

                    $('#msg').keydown(function(event) {
                         if(13 == event.keyCode) {
                             var msg = $(this).attr('value');
                             if (msg.length > 0) {
                                  mySocket.send('POST /chat/set'+"\r\n\r\n"+'id=' + chat.id + '&message='+encodeURIComponent(msg));
                             }
                             $(this).attr('value', '');
                             return false;
                         }
                    });

                });
            })(jQuery);
        </script>
    </body>
</html>