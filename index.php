<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8" />
        <title>Chat</title>
        <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.4.2/jquery.min.js"></script>
    </head>
    <body>
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
                    $.ajax({
                        'async': true,
                        'url': settings.server + 'join',
                        'cache': false,
                        'dataType': 'json',
                        'timeout': 30000,
                        'type': 'POST',
                        'data': {'username': 'Foobar&so', 'url': 'http://example.com'},
                        'success': function(data) {
                            var id = data.id || 0;
                            alert(data.status + ' / message: '+data.message+' / id '+id);
                        }
                    });

                });
            })(jQuery);
        </script>
    </body>
</html>