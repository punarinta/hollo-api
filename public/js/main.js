ML.login = function ()
{
  var user = document.querySelector('#page-login .username').value,
      pass = document.querySelector('#page-login .password').value;

  if (!user.length || !pass.length)
  {
    alert('Both username and password are required');
  }

  ML.api('auth', 'login',
  {
    'identity': user,
    'credential': pass
  },
  function (data)
  {
    ML.sessionId = data.sessionId;
    ML.user = data.user;

    if (data.user.contextId) hasher.setHash('contacts');
    else hasher.setHash('auth/attach');
  });
};

ML.register = function ()
{
  var email = document.querySelector('#page-login .reg-email').value,
      password = document.querySelector('#page-login .reg-password').value;

  if (!email.length)
  {
    alert('Email is required');
  }

  ML.api('auth', 'register',
  {
    'password': password,
    'email': email
  },
  function (data)
  {
    ML.sessionId = data.sessionId;
    ML.user = data.user;
    hasher.setHash('auth/attach');
  });
};

ML.attach = function ()
{
  var email = document.querySelector('#page-attach .email').value;

  ML.api('auth', 'discoverEmail',
  {
    'email': email
  },
  function (data)
  {
    console.log(data);
    if (data.oauth)
    {
      // OAuth procedure
      window.location.href = data.url;
    }
    else
    {
      // prefill IMAP forms
      $('#page-attach .extra-options').show();
      $('#page-attach .server').val(data.server);
      $('#page-attach .port').val(data.port);
      $('#page-attach .username').val(data.username);
      $('#page-attach .attach').hide();
      $('#page-attach .confirm').show();
      $('#page-attach .password').focus();
    }
  });
};

ML.confirmAttach = function ()
{
  var email = document.querySelector('#page-attach .email').value,
      username = document.querySelector('#page-attach .username').value,
      password = document.querySelector('#page-attach .password').value,
      server = document.querySelector('#page-attach .server').value,
      port = document.querySelector('#page-attach .port').value;

  ML.api('auth', 'attachEmail',
  {
    'email': email,
    'username': username,
    'password': password,
    'server': server,
    'port': port
  },
  function ()
  {
    hasher.setHash('contacts');
  });
};

ML.showLogin = function ()
{
  $('.page').hide();
  $('#page-login').show();
};

ML.showAttach = function ()
{
  $('.page, #page-attach .extra-options').hide();
  $('#page-attach .email').val(ML.user.email);
  $('#page-attach').show();
};

ML.showContacts = function ()
{
  $('.page').hide();
  $('#page-contacts ul').html('<ul><li>Loading...</li></ul>');
  $('#page-contacts').show();

  ML.api('contact', 'find', null, function (data)
  {
    var html = '';

    for (var i in data)
    {
      html += '<li data-email="' + data[i].email + '"><div class="name">' + data[i].name + '</div><div>' + data[i].email + '</div></li>';
    }

    $('#page-contacts ul').html(html);

    $('#page-contacts ul li').on('click', function (e)
    {
      var email = $(e.target).closest('li').data('email');
      hasher.setHash('chat/' + email);
    });
  });
};

ML.showChat = function(email)
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
      var filesHtml = '', body = data[i].body.content,
        whose = data[i].from == email ? 'yours' : 'mine';

      // preprocess body
      var exp = /(\b(https?|ftp|file):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/i;
      body = body.replace(/(?:\r\n|\r|\n)/g, '<br />');
      body = body.replace(exp,"<a href='$1'>$1</a>");

      if (data[i].files)
      {
        filesHtml += '<hr>';
        for (var fi in data[i].files)
        {
          var file = data[i].files[fi];
          filesHtml += '<a target="_blank" class="file" href="/api/file?method=fetch&extId=' + file.extId + '&type='
            + file.type + '">' + file.name + '</a><br>';
        }
      }

      html += '<li class="' + whose + '"><div><div class="tag">' + data[i].subject + '</div><br><div class="msg">' + body + '</div><div>' + filesHtml + '</div></div></li>';
    }

    $('#page-chat ul').html(html);

    $('#page-chat li .tag').on('click', function ()
    {
      var filter = $(this).text();
      $('#page-chat .filter').val(filter).trigger('keyup');
    });
  });
};
