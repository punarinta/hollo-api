ML.login = function (username, password, callback)
{
  ML.api('auth', 'login',
    {
      'identity': username,
      'credential': password
    },
    function (data)
    {
      ML.user.sessionId = data.sessionId;
      if (typeof callback === 'function')
      {
        callback();
      }
    });
};

ML.showLogin = function ()
{
  $('.page').hide();
  $('#page-login').show();
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
