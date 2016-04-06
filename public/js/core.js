var ML =
{
  sessionId: null,
  user:
  {
    id: null,
    email: null
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
      var r = this.response.toString();
      if (/^[\],:{}\s]*$/.test(r.replace(/\\["\\\/bfnrtu]/g, '@').replace(/"[^"\\\n\r]*"|true|false|null|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?/g, ']').replace(/(?:^|:|,)(?:\s*\[)+/g, '')))
      {
        var json = JSON.parse(r);
        if (this.status >= 200 && this.status < 400)
        {
          callback(json.data);
        }
        else
        {
          console.log('Status: ', this.status);
          alert(json.errMsg);
        }
      }
      else
      {
        console.log('Malformed server response:', r);
        alert(r);
      }
    };
    r.onerror = function()
    {
      console.log('cannot connect to server');
    };
    r.send(JSON.stringify({ 'method': method, 'data': data }));
  },

  getQueryVar: function (v)
  {
    var query = window.location.search.substring(1),
        i, vars = query.split('&');

    for (i = 0; i < vars.length; i++)
    {
      var pair = vars[i].split('=');
      if (pair[0] == v) { return pair[1]; }
    }
    return null;
  },

  // functions
  login: null,
  register: null,
  attach: null,
  showLogin: null,
  showContacts: null,
  showChat: null
};


