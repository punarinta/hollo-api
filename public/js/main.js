var ML =
{
  sessionId: null,

  api: function (endpoint, method, data, callback)
  {
    $.ajax(
    {
      type: 'POST',
      url: '/api/' + endpoint,
      headers: { 'token': ML.sessionId },
      data: JSON.stringify({ 'method': method, 'data': data }),
      dataType: 'json',
      contentType: 'application/json; charset=utf-8',
      success: function (json)
      {
        console.log('Call to ' + endpoint + '/' + method, json);
        callback(json.data)
      }
    });
  },

  init: function ()
  {
    $('#page-login .logout').on('click', function()
    {
      ML.api('auth', 'logout');
    });
    
    $('#page-login .login').on('click', function()
    {
      var user = $('#page-login .username').val(),
          pass = $('#page-login .password').val();

      if (!user.length || !pass.length)
      {
        alert('Both username and password are required');
      }

      ML.login(user, pass, function ()
      {
        ML.showContacts();
      });
    });

    // check the status
    ML.api('auth', 'status', {}, function (data)
    {
      if (data.user)
      {
        ML.sessionId = data.sessionId;
        ML.showContacts();
      }
    });
  },

  login: function (username, password, callback)
  {
    ML.api('auth', 'login',
    {
      'identity': username,
      'credential': password
    },
    function (data)
    {
      ML.sessionId = data.sessionId;
      if (typeof callback === 'function')
      {
        callback();
      }
    });
  },

  showContacts: function ()
  {
    $('.page').hide();
    $('#page-contacts').show();

    ML.api('contact', 'find', {},
    function (data)
    {
      console.log(data)
    });
  }
};

ML.init();