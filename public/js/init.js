(function ()
{
  document.getElementById('btn-logout').onclick = function ()
  {
    ML.api('auth', 'logout', null, function ()
    {
      hasher.setHash('login');
    });
  };
  document.getElementById('btn-sync').onclick = function ()
  {
    ML.api('contact', 'sync');
  };
  document.getElementById('btn-contacts').onclick = function ()
  {
    hasher.setHash('contacts');
  };

  document.querySelector('#page-contacts .filter').onkeyup = function ()
  {
    var filter = this.value.toUpperCase(),
        lis = document.querySelectorAll('#page-contacts li');

    for (var i = 0; i < lis.length; i++)
    {
      var name = lis[i].getElementsByClassName('name')[0].innerHTML;
      if (name.toUpperCase().indexOf(filter) != -1) lis[i].style.display = 'list-item';
      else lis[i].style.display = 'none';
    }
  };
  document.querySelector('#page-chat .filter').onkeyup = function ()
  {
    var filter = this.value.toUpperCase(),
      lis = document.querySelectorAll('#page-chat li');

    for (var i = 0; i < lis.length; i++) {
      var name = lis[i].getElementsByClassName('tag')[0].innerHTML;
      if (name.toUpperCase().indexOf(filter) != -1) lis[i].style.display = 'list-item';
      else lis[i].style.display = 'none';
    }
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
  $('#composer .tag').on('click', function ()
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
  });

  var loginProc = function ()
  {
    var user = $('#page-login .username').val(),
        pass = $('#page-login .password').val();

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
      ML.user.sessionId = data.sessionId;
      hasher.setHash('contacts');
    });
  };

  $('#page-login .login').on('click', loginProc);
  $('#page-login .username').on('keydown', function (e)
  {
    if (e.keyCode == 13) $('#page-login .password').focus();
  });
  $('#page-login .password').on('keydown', function (e)
  {
    if (e.keyCode == 13) loginProc();
  });

  // check the status
  ML.api('auth', 'status', {}, function (data)
  {
    if (data.user)
    {
      ML.user.sessionId = data.sessionId;
      if (hasher.getHash() == '') hasher.setHash('contacts');
    }
  });

  // setup path dispatcher

  crossroads.addRoute('contacts', ML.showContacts);
  crossroads.addRoute('login', ML.showLogin);
  crossroads.addRoute('chat/{email}', function (email)
  {
    ML.showChat(email);
  });

  function parseHash(newHash, oldHash)
  {
    crossroads.parse(newHash);
  }

  hasher.initialized.add(parseHash);
  hasher.changed.add(parseHash);
  hasher.init();
})();
