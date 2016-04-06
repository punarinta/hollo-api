(function ()
{
  document.getElementById('btn-logout').onclick = function ()
  {
    ML.api('auth', 'logout', null, function ()
    {
      hasher.setHash('auth/login');
    });
  };
  document.getElementById('btn-contacts').onclick = function () { hasher.setHash('contacts'); };
  document.querySelector('#page-attach .attach').onclick = ML.attach;
  document.querySelector('#page-attach .confirm').onclick = ML.confirmAttach;

  document.querySelector('#page-contacts .filter').onkeyup = function ()
  {
    var filter = this.value.toUpperCase();
    Array.prototype.forEach.call(document.querySelectorAll('#page-contacts li'), function(el)
    {
      var name = el.getElementsByClassName('name')[0].innerHTML;
      if (name.toUpperCase().indexOf(filter) != -1) el.style.display = 'list-item';
      else el.style.display = 'none';
    });
  };
  document.querySelector('#page-chat .filter').onkeyup = function ()
  {
    var filter = this.value.toUpperCase();
    Array.prototype.forEach.call(document.querySelectorAll('#page-chat li'), function(el)
    {
      var name = el.getElementsByClassName('tag')[0].innerHTML;
      if (name.toUpperCase().indexOf(filter) != -1) el.style.display = 'list-item';
      else el.style.display = 'none';
    });
  };

  $('#composer textarea').on('focus click', function ()
  {
    if (!$('#composer .tags').is(':visible'))
    {
      $('#composer .tag').removeClass('sel');
    }
    $('#composer .tags').show();
  }).on('keydown', function (e)
  {
    if (e.keyCode == 27)
    {
      $('#composer .tags').hide();
    }
  });

  Array.prototype.forEach.call(document.querySelectorAll('#composer .tag'), function(el)
  {
    el.onclick = function ()
    {
      $('#composer .tag').removeClass('sel');
      $(this).addClass('sel');

      if ($(this).hasClass('new'))
      {
        var tag = prompt('New tag:', 'hollotag'),
          clone = $('#composer .tag').last().clone();
        clone[0].innerText = '#' + tag;
        $('#composer .tags').append(clone);
      }
    };
  });

  document.querySelector('#page-login .register').onclick = ML.register;
  document.querySelector('#page-login .login').onclick = ML.login;
  document.querySelector('#page-login .username').onkeydown = function (e)
  {
    if (e.keyCode == 13) document.querySelector('#page-login .password').focus();
  };
  document.querySelector('#page-login .password').onkeydown = function (e)
  {
    if (e.keyCode == 13) ML.login();
  };

  // setup path dispatcher
  crossroads.addRoute('auth/login', ML.showLogin);
  crossroads.addRoute('auth/attach', ML.showAttach);
  crossroads.addRoute('contacts', ML.showContacts);
  crossroads.addRoute('chat/{email}', function (email)
  {
    ML.showChat(email);
  });

  function parseHash(newHash/*, oldHash*/)
  {
    crossroads.parse(newHash);
  }

  hasher.initialized.add(parseHash);
  hasher.changed.add(parseHash);

  var contextIoToken = ML.getQueryVar('contextio_token');
  if (contextIoToken)
  {
    ML.api('auth', 'saveContextIdByToken', {token: contextIoToken});
  }

  // check the status
  ML.api('auth', 'status', {}, function (data)
  {
    if (data.user)
    {
      ML.sessionId = data.sessionId;
      ML.user = data.user;
      if (hasher.getHash() == '')
      {
        if (data.user.contextId) hasher.setHash('contacts');
        else hasher.setHash('auth/attach');
      }
    }
    else
    {
      hasher.setHash('auth/login');
    }

    hasher.init();
  });
})();
