var ML =
{
  user:
  {
    id: null,
    sessionId: null
  },

  api: function (endpoint, method, data, callback)
  {
    var r = new XMLHttpRequest();
    r.open('POST', '/api/' + endpoint, true);
    r.setRequestHeader('Content-Type', 'application/json; charset=utf-8');
    if (ML.user.sessionId)
    {
      r.setRequestHeader('Token', ML.user.sessionId.toString());
    }
    r.onload = function()
    {
      if (this.status >= 200 && this.status < 400)
      {
        try
        {
          var r = this.response.toString();
          if (/^[\],:{}\s]*$/.test(r.replace(/\\["\\\/bfnrtu]/g, '@').replace(/"[^"\\\n\r]*"|true|false|null|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?/g, ']').replace(/(?:^|:|,)(?:\s*\[)+/g, '')))
          {
            var json = JSON.parse(r);
            callback(json.data);
          }
          else
          {
            console.log('Malformed server response:', r);
            alert(r);
          }
        }
        catch (e) { }
      }
      else
      {
        console.log('Status: ', this.status);
      }
    };
    r.onerror = function()
    {
      console.log('cannot connect to server');
    };
    r.send(JSON.stringify({ 'method': method, 'data': data }));
  },

  // functions
  login: null,
  showLogin: null,
  showContacts: null,
  showChat: null
};


