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

p {
    margin: 0 0 0.5em;
}
            .chat, #console {
                margin-bottom: 10px;
                padding: 0;
                background: #fff;
                border-top: #fff 1px solid;
                color: #666;
                -webkit-box-shadow: 0 1px 6px rgba(0,0,0,0.5), 0 -2px 10px rgba(0,0,0,0.3) inset;
                -moz-box-shadow: 0 1px 6px rgba(0,0,0,0.5), 0 -2px 10px rgba(0,0,0,0.3) inset;
                box-shadow: 0 1px 6px rgba(0,0,0,0.5), 0 -2px 10px rgba(0,0,0,0.3) inset;
                -webkit-border-radius: 10px;
                -moz-border-radius: 10px;
                border-radius: 10px;
                position: relative;
}

.chat {
    height: 270px;
    overflow: hidden;
}

.chat .messages {
    padding: 10px 0 0 10px;
    margin: 0 0 60px 0;
    height: 200px;
    overflow: auto;
}
.chat .user {
    width: 180px;
    padding: 10px;
    float: right;
    -moz-box-sizing: border-box;
    -webkit-box-sizing: border-box;
    box-sizing: border-box;
    height: 100%;
}

.chat .user ul {
    display: block;
    list-style: none;
    margin: 0;
    padding: 0;
}
.chat .user li {
    display: block;
    margin: 0;
    padding: 0;
}

#console {
    min-height: 60px;
    height: 30%;
    overflow: auto;
    padding: 10px;
}
form {
    -moz-box-sizing: border-box;
    -webkit-box-sizing: border-box;
    box-sizing: border-box;
    margin: 0;
    padding: 10px 200px 10px 10px;
    position: absolute;
    bottom: 0;
    width: 100%;
}
form input {
    -moz-box-sizing: border-box;
    -webkit-box-sizing: border-box;
    box-sizing: border-box;
    outline: none;
    font-size: 1em;
    display: block;
    width: 100%;
    padding: 10px;
    margin: 0;
    background: #fff;
    border: none;
    border-top: rgba(0,0,0,0.2) 1px solid;
    color: #666;
    -webkit-box-shadow: 0 2px 6px rgba(0,0,0,0.2) inset;
    -moz-box-shadow: 0 2px 6px rgba(0,0,0,0.2) inset;
    box-shadow: 0 2px 6px rgba(0,0,0,0.2) inset;
    -webkit-border-radius: 5px;
    -moz-border-radius: 5px;
    border-radius: 5px;
    
}

        </style>
    </head>
    <body>
        <div id="chats">
        </div>
        
        <div id="console">
            <div></div>
        </div>
        
        <script>
            (function($) {

                //document.ready
                $(function() {
                    var chat = {
                        'settings': {
                            'server': '192.168.178.58:8080/chat/',
                            'defaultChannel': 'lounge'
                        },
                        'id': null,
                        'channels': {},
                        'getSuccess': function(messages) {
                            var time = null;
                            var updateUserChannels = {};
                            $.each(messages, function(i, v) {
                                time = new Date(parseInt(v.time * 1000));
                                if (v.type == 'join' || v.type == 'left') {
                                    updateUserChannels[v.channel] = true;
                                }
                                $('#chats > div[data-channel="'+v.channel+'"] > .messages').prepend('<p class="'+v.type+'">'+(time.getHours() < 10 ? '0'+time.getHours() : time.getHours())+':'+(time.getMinutes() < 10 ? '0'+time.getMinutes() : time.getMinutes())+':'+(time.getSeconds() < 10 ? '0'+time.getSeconds() : time.getSeconds())+' - '+v.message+'</p>');
                            });
                            for (updateChannel in updateUserChannels) {                                
                                chat.connection.userlist(updateChannel);
                            }
                        },
                        'addChannel': function(channel) {
                            chat.channels[channel] = true;
                            $('#chats').append('<div class="chat" data-channel="'+channel+'"><form action="#" method="POST" onsubmit="return false"><input placeholder="Type to chat :)"/></form><div class="user"></div><div class="messages"></div></div>');
                            $('#chats > div[data-channel="'+channel+'"] input').focus().keydown(function(event) {
                                if(13 == event.keyCode) {
                                    var channelName = $(this).closest('div').attr('data-channel');
                                    
                                    chat.connection.set($(this).attr('value'), channelName);
                                    $(this).attr('value', '');
                                    return false;
                                }
                            });
                        },
                        'removeChannel': function(channel) {
                            delete chat.channels[channel];

                            $('#chats > div[data-channel="'+channel+'"]').remove();
                        },
                        'execCommand': function(command, data, channel) {
                            if (typeof(chat.connection.commands[command]) == "function") {
                                chat.connection.commands[command](data, channel);
                            }
                        },
                        'connection': (function(){
                            if (typeof(WebSocket) === "undefined") {
                                $('#console').prepend('<p class="info">USING LONGPOLLING CONNECTION :/</p>');
                                return {
                                    'get': function() {
                                        if (chat.id === null || chat.timestamp === null) {
                                            $('#console').prepend('<p class="error">Cant fetch posts: no valid ID and/or timestamp!</p>');
                                            window.setTimeout(chat.connection.get, 1000);
                                            return;
                                        }
                                        $('#console').prepend('<p class="info">SENDING Get / '+chat.id+' / '+chat.timestamp+'</p>');
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
                                                        $('#console').prepend('<p class="info">'+data.status + ' / message: '+data.message+' / timestamp '+ data.timestamp + '</p>');
                                                        chat.timestamp = data.timestamp;
                                                        window.setTimeout(chat.connection.get, 1000);
                                                        if (data.messages) {
                                                            chat.getSuccess(data.messages);
                                                        }
                                                    }
                                                } else {
                                                    $('#console').prepend('<p class="info">Get returned FALSE</p>');
                                                    window.setTimeout(chat.connection.get, 5000);
                                                }
                                    
                                            },
                                            'error': function() {
                                                $('#console').prepend('<p class="info">Get timed out / no new messages!</p>');
                                                window.setTimeout(chat.connection.get, 5000);
                                            }
                                        });
                                    },
                                    'userlist': function(channel) {
                                        if (chat.id === null) {
                                            $('#console').prepend('<p class="error">Cant fetch userlist: no valid ID!</p>');
                                            return;
                                        }
                                        $('#console').prepend('<p class="info">Requesting userlist!</p>');
                                        $.ajax({
                                            'async': true,
                                            'url': 'http://' + chat.settings.server + 'userlist',
                                            'cache': false,
                                            'dataType': 'json',
                                            'timeout': 10000,
                                            'type': 'POST',
                                            'data': {'id': chat.id, 'channel': channel},
                                            'success': function(data) {

                                                if (data) {

                                                    if (data.status == true) {
                                                        $('#chats > div[data-channel="'+data.channel+'"] > .user').html('<ul><li><strong>'+data.message+'</strong></li><li>'+(data.userlist.join('</li><li>'))+'</li></ul>');
                                                        $('#console').prepend('<p class="info">'+data.message+'</p>');
                                                    }
                                                } else {
                                                    $('#console').prepend('<p class="info">Userlist returned FALSE</p>');
                                                }

                                            },
                                            'error': function() {
                                                $('#console').prepend('<p class="info">Userlist timed out</p>');
                                            }
                                        });
                                    },
                                    'channellist': function(channel) {
                                        if (chat.id === null) {
                                            $('#console').prepend('<p class="error">Cant fetch channellist: no valid ID!</p>');
                                            return;
                                        }
                                        $('#console').prepend('<p class="info">Requesting channellist!</p>');
                                        $.ajax({
                                            'async': true,
                                            'url': 'http://' + chat.settings.server + 'channellist',
                                            'cache': false,
                                            'dataType': 'json',
                                            'timeout': 10000,
                                            'type': 'POST',
                                            'data': {'id': chat.id, 'channel': channel},
                                            'success': function(data) {

                                                if (data) {

                                                    if (data.status == true) {
                                                        $('#chats > div[data-channel="'+data.channel+'"] > .messages').prepend('<p class="channellist">'+data.message+': '+(data.channellist.join(', '))+'</p>');
                                                        $('#console').prepend('<p class="info">'+data.message+'</p>');
                                                    }
                                                } else {
                                                    $('#console').prepend('<p class="info">Channellist returned FALSE</p>');
                                                }

                                            },
                                            'error': function() {
                                                $('#console').prepend('<p class="info">Channellist timed out</p>');
                                            }
                                        });
                                    },
                                    'joinChannel': function(channel) {
                                        if (chat.id === null) {
                                            $('#console').prepend('<p class="error">Cant join channel '+channel+': no valid ID!</p>');
                                            return;
                                        }
                                        if (typeof chat.channels[channel] !== "undefined") {
                                            $('#console').prepend('<p class="error">Cant join channel '+channel+': already joined!</p>');
                                            return;
                                        }
                                        $('#console').prepend('<p class="info">Joining channel '+channel+'!</p>');
                                        $.ajax({
                                            'async': true,
                                            'url': 'http://' + chat.settings.server + 'joinChannel',
                                            'cache': false,
                                            'dataType': 'json',
                                            'timeout': 10000,
                                            'type': 'POST',
                                            'data': {'id': chat.id, 'channel': channel, 'messages': 1},
                                            'success': function(data) {

                                                if (data) {
                                                    $('#console').prepend('<p class="info">'+data.status + ' / message: '+data.message+'</p>');
                                                    chat.addChannel(channel);
                                                    chat.connection.userlist(channel);
                                                    if (data.messages.length) {
                                                        chat.getSuccess(data.messages);
                                                    }
                                                } else {
                                                    $('#console').prepend('<p class="info">joining channel returned FALSE</p>');
                                                }

                                            },
                                            'error': function() {
                                                $('#console').prepend('<p class="info">Joining channel timed out</p>');
                                            }
                                        });
                                    },
                                    'leaveChannel': function(channel) {
                                        if (chat.id === null) {
                                            $('#console').prepend('<p class="error">Cant join channel '+channel+': no valid ID!</p>');
                                            return;
                                        }
                                        if (typeof chat.channels[channel] === "undefined") {
                                            $('#console').prepend('<p class="error">Cant leave channel '+channel+': not joined!</p>');
                                            return;
                                        }
                                        $('#console').prepend('<p class="info">Leaving channel '+channel+'!</p>');
                                        $.ajax({
                                            'async': true,
                                            'url': 'http://' + chat.settings.server + 'leaveChannel',
                                            'cache': false,
                                            'dataType': 'json',
                                            'timeout': 10000,
                                            'type': 'POST',
                                            'data': {'id': chat.id, 'channel': channel},
                                            'success': function(data) {

                                                if (data) {
                                                    $('#console').prepend('<p class="info">'+data.status + ' / message: '+data.message+'</p>');
                                                    chat.removeChannel(data.channel);
                                                } else {
                                                    $('#console').prepend('<p class="info">leaving channel returned FALSE</p>');
                                                }

                                            },
                                            'error': function() {
                                                $('#console').prepend('<p class="info">leaving channel timed out</p>');
                                            }
                                        });
                                    },
                                    'join': function() {
                                        var username = window.prompt('Username?', 'User ' + Math.floor(Math.random() * (99999)));
                                        var channel = window.prompt('Channel?', chat.settings.defaultChannel);
                                        $.ajax({
                                            'async': true,
                                            'url': 'http://' + chat.settings.server + 'join',
                                            'cache': false,
                                            'dataType': 'json',
                                            'timeout': 30000,
                                            'type': 'POST',
                                            'data': {'username': username, 'channel': channel, 'timestamp': 0 },
                                            'success': function(data) {
                                                if (data) {
                                                    $('#console').prepend('<p class="info">'+data.status + ' / message: '+data.message+' / id '+data.id+' / timestamp '+data.timestamp+'</p>');

                                                    if (data.status == true) {
                                                        chat.id = data.id;
                                                        chat.timestamp = data.timestamp;
                                                        chat.addChannel(data.channel);
                                                        chat.connection.userlist(data.channel);
                                                        chat.connection.channellist(data.channel);
                                                        chat.connection.get();
                                                    }
                                                } else {
                                                    $('#console').prepend('<p class="info">JOIN returned FALSE</p>');
                                                    window.setTimeout(chat.connection.join, 1000);
                                                }
                                            },
                                            'error': function() {
                                                $('#console').prepend('<p class="info">Join timed out!</p>');
                                                window.setTimeout(chat.connection.join, 1000);
                                            }
                                        });
                                    },
                                    'set': function(message, channel) {
                                        if (chat.id === null) {
                                            $('#console').prepend('<p class="error">Cant send post: no valid ID!</p>');
                                            //window.setTimeout(chat.connection.set, 250, message);
                                            return;
                                        }
                                        if (!channel || !chat.channels[channel]) {
                                            $('#console').prepend('<p class="error">Cant send post: no valid Channel!</p>');
                                            //window.setTimeout(chat.connection.set, 250, message);
                                            return;
                                        }
                                        if (!message || !message.length) {
                                            $('#console').prepend('<p class="error">Cant send post: no Message!</p>');
                                            return;
                                        }

                                        if (message.match(/^\//)) {
                                            var command = /^\/([a-zA-Z]+)( +(.*))?/;
                                            var result = command.exec(message);
                                            chat.execCommand(result[1], result[3], channel);
                                            return;
                                        }

                                        $('#console').prepend('<p class="info">SENDING SET / '+chat.id+' / '+message+' / '+channel+'</p>');
                                        $.ajax({
                                            'async': true,
                                            'url': 'http://' + chat.settings.server + 'set',
                                            'cache': false,
                                            'dataType': 'json',
                                            'timeout': 30000,
                                            'type': 'POST',
                                            'data': {'id': chat.id, 'channel': channel, 'message': message},
                                            'success': function(data) {
                                                if (data) {
                                                    $('#console').prepend('<p class="info">'+data.status + ' / message: '+data.message+'</p>');
                                                } else {
                                                    $('#console').prepend('<p class="info">SET returned FALSE</p>');
                                                    window.setTimeout(chat.connection.set, 250, message);
                                                }
                                            },
                                            'error': function() {
                                                $('#console').prepend('<p class="info">Send timed out!</p>');
                                                window.setTimeout(chat.connection.set, 1000, message);
                                            }
                                        });
                                    },
                                    'commands': {
                                        'join': function(data, channel) {
                                            chat.connection.joinChannel(data);
                                        },
                                        'exit': function(data, channel) {
                                            chat.connection.leaveChannel(channel);
                                        },
                                        'userlist': function(data, channel) {
                                            chat.connection.userlist(channel);
                                        },
                                        'channellist': function(data, channel) {
                                            chat.connection.channellist(channel);
                                        }
                                    }
                                };
                            } else {
                                var mySocket = null;
                                var mySocketOpen = false;

                                var init = function() {
                                    mySocket = new WebSocket('ws://' + chat.settings.server + 'socket');
                                    mySocket.onopen = function() {
                                        mySocketOpen = true;
                                        $('#console').prepend('<p class="info">Socket OPEN!</p>');
                                    }

                                    mySocket.onerror = function() {
                                        $('#console').prepend('<p class="info">Socket ERROR!</p>');
                                    }

                                    mySocket.onclose = function() {
                                        mySocketOpen = false;
                                        $('#console').prepend('<p class="info">Socket closed!</p>');
                                    }

                                    mySocket.onmessage = function(m) {
                                        data = JSON.parse(m.data);
                                        if (!data || !data.action) {
                                            $('#console').prepend('<p class="info">Error: got invalid response via WebSocket!</p>');
                                        }
                                        if (data.status == false) {
                                            $('#console').prepend('<p class="info">Error in '+data.action + ': '+data.message+'</p>');
                                        } else {
                                            if (data.action == 'join') {
                                                $('#console').prepend('<p class="info">'+data.status + ' / message: '+data.message+' / id '+data.id+' / timestamp '+data.timestamp+'</p>');
                                                chat.id = data.id;                                                
                                                chat.timestamp = data.timestamp;
                                                chat.addChannel(data.channel);
                                                chat.connection.userlist(data.channel);
                                                chat.connection.channellist(data.channel);
                                            } else if(data.action == 'set') {
                                                $('#console').prepend('<p class="info">'+data.status + ' / message: '+data.message+'</p>');
                                            } else if (data.action == 'get') {
                                                $('#console').prepend('<p class="info">'+data.status + ' / message: '+data.message+' / timestamp '+ data.timestamp + '</p>');
                                                chat.timestamp = data.timestamp;
                                                if (data.messages) {
                                                    chat.getSuccess(data.messages);
                                                }
                                            } else if (data.action == 'userlist') {
                                                $('#chats > div[data-channel="'+data.channel+'"] > .user').html('<ul><li><strong>'+data.message+'</strong></li><li>'+(data.userlist.join('</li><li>'))+'</li></ul>');
                                                $('#console').prepend('<p class="info">'+data.message+'</p>');
                                            } else if (data.action == 'joinChannel') {
                                                $('#console').prepend('<p class="info">'+data.status + ' / message: '+data.message+'</p>');
                                                chat.addChannel(data.channel);
                                                chat.connection.userlist(data.channel);
                                                //alert(data.messages.length);
                                                if (data.messages.length) {
                                                    chat.getSuccess(data.messages);
                                                }
                                            } else if (data.action == 'leaveChannel') {
                                                $('#console').prepend('<p class="info">'+data.status + ' / message: '+data.message+'</p>');
                                                chat.removeChannel(data.channel);
                                            } else if (data.action == 'channellist') {
                                                $('#chats > div[data-channel="'+data.channel+'"] > .messages').prepend('<p class="channellist">'+data.message+': '+(data.channellist.join(', '))+'</p>');
                                                $('#console').prepend('<p class="info">'+data.message+'</p>');
                                            }
                                        }

                                    }
                                }
                                $('#console').prepend('<p class="info">USING WEBSOCKET CONNECTION :)</p>');
                                return {
                                    'get': function() {

                                    },
                                    'join': function() {
                                        if (mySocket === null) {
                                            init();
                                        }
                                        if (mySocketOpen == true) {
                                            var username = window.prompt('Username?', 'User ' + Math.floor(Math.random() * (99999)));
                                            var channel = window.prompt('Channel?', chat.settings.defaultChannel);
                                            mySocket.send('POST /chat/join'+"\n\n"+'username='+encodeURIComponent(username)+'&channel='+encodeURIComponent(channel)+'&timestamp=0');
                                        } else {
                                            window.setTimeout(chat.connection.join, 250);
                                        }
                                    },
                                    'set': function(message, channel) {
                                        if (chat.id === null) {
                                            $('#console').prepend('<p class="error">Cant send post: no valid ID!</p>');
                                            window.setTimeout(chat.connection.set, 250, message);
                                            return;
                                        }
                                        if (!channel || !chat.channels[channel]) {
                                            $('#console').prepend('<p class="error">Cant send post: no valid Channel!</p>');
                                            //window.setTimeout(chat.connection.set, 250, message);
                                            return;
                                        }
                                        if (!message || !message.length) {
                                            $('#console').prepend('<p class="error">Cant send post: no Message!</p>');
                                            return;
                                        }

                                        if (message.match(/^\//)) {
                                            var command = /^\/([a-zA-Z]+)( +(.*))?/;
                                            var result = command.exec(message);
                                            chat.execCommand(result[1], result[3], channel);
                                            return;
                                        }

                                        mySocket.send('POST /chat/set'+"\r\n\r\n"+'id=' + chat.id + '&channel='+encodeURIComponent(channel)+'&message='+encodeURIComponent(message));
                                    },
                                    'joinChannel': function(channel) {
                                        if (chat.id === null) {
                                            $('#console').prepend('<p class="error">Cant join channel '+channel+': no valid ID!</p>');
                                            return;
                                        }
                                        if (typeof chat.channels[channel] !== "undefined") {
                                            $('#console').prepend('<p class="error">Cant join channel '+channel+': already joined!</p>');
                                            return;
                                        }
                                        $('#console').prepend('<p class="info">Joining channel '+channel+'!</p>');
                                        mySocket.send('POST /chat/joinChannel'+"\r\n\r\n"+'id=' + chat.id + '&channel='+encodeURIComponent(channel)+'&messages=1');
                                    },
                                    'leaveChannel': function(channel) {
                                        if (chat.id === null) {
                                            $('#console').prepend('<p class="error">Cant join channel '+channel+': no valid ID!</p>');
                                            return;
                                        }
                                        if (typeof chat.channels[channel] === "undefined") {
                                            $('#console').prepend('<p class="error">Cant leave channel '+channel+': not joined!</p>');
                                            return;
                                        }
                                        $('#console').prepend('<p class="info">Leaving channel '+channel+'!</p>');
                                        mySocket.send('POST /chat/leaveChannel'+"\r\n\r\n"+'id=' + chat.id + '&channel='+encodeURIComponent(channel));
                                    },
                                    'userlist': function(channel) {
                                        if (chat.id === null) {
                                            $('#console').prepend('<p class="error">Cant fetch userlist: no valid ID!</p>');
                                            return;
                                        }
                                        mySocket.send('POST /chat/userlist'+"\r\n\r\n"+'id=' + chat.id + '&channel=' + encodeURIComponent(channel));
                                    },
                                    'channellist': function(channel) {
                                        if (chat.id === null) {
                                            $('#console').prepend('<p class="error">Cant fetch channellist: no valid ID!</p>');
                                            return;
                                        }
                                        mySocket.send('POST /chat/channellist'+"\r\n\r\n"+'id=' + chat.id + '&channel=' + encodeURIComponent(channel));
                                    },
                                    'commands': {
                                        'join': function(data, channel) {
                                            chat.connection.joinChannel(data);
                                        },
                                        'exit': function(data, channel) {
                                            chat.connection.leaveChannel(channel);
                                        },
                                        'userlist': function(data, channel) {
                                            chat.connection.userlist(channel);
                                        },
                                        'channellist': function(data, channel) {
                                            chat.connection.channellist(channel);
                                        }
                                    }
                                };
                            }
                        })()
                    };

                    chat.connection.join();

                    

                });
            })(jQuery);
        </script>
    </body>
</html>