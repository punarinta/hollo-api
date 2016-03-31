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
    $('#btn-logout').on('click', function()
    {
      ML.api('auth', 'logout', null, function () { ML.showLogin(); });
    });
    $('#btn-sync').on('click', function()
    {
      ML.api('contact', 'sync');
    });
    $('#btn-contacts').on('click', function()
    {
      ML.showContacts();
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
  
  showLogin: function ()
  {
    $('.page').hide();
    $('#page-login').show();
  },

  showContacts: function ()
  {
    $('.page').hide();
    $('#page-contacts ul').html('<ul><li>Loading...</li></ul>');
    $('#page-contacts').show();

    ML.api('contact', 'find', null, function (data)
    {
      var html = '';

      for (var i in data)
      {
        html += '<li data-email="' + data[i].email + '"><div>' + data[i].name + '</div><div>' + data[i].email + '</div></li>';
      }

      $('#page-contacts ul').html(html);

      $('#page-contacts ul li').on('click', function (e)
      {
        var email = $(e.target).closest('li').data('email');
        ML.showChat(email);
      });
    });
  },
  
  showChat: function(email)
  {
    $('.page').hide();
    $('#page-chat .interlocutor').html(email);
    $('#page-chat ul').html('<ul><li>Loading...</li></ul>');
    $('#page-chat').show();

    ML.api('message', 'findByEmail', {email: email}, function (data)
    {
      var html = '';

      for (var i in data)
      {
        var body = data[i].body.content,
            whose = data[i].from == email ? 'yours' : 'mine';

        // preprocess body
        var exp = /(\b(https?|ftp|file):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/i;
        body = body.replace(/(?:\r\n|\r|\n)/g, '<br />');
        body = body.replace(exp,"<a href='$1'>$1</a>");

        html += '<li class="' + whose + '"><div><div class="tag">' + data[i].subject + '</div><hr><div class="msg">' + body + '</div></div></li>';
      }

      $('#page-chat ul').html(html);
    });
  }
};

ML.init();
