(function ()
{
  document.getElementById('btn-logout').onclick = function ()
  {
    ML.api('auth', 'logout', null, function ()
    {
      hasher.setHash('login');
    });
  };
  document.getElementById('btn-sync').onclick = function () { ML.api('contact', 'sync'); };
  document.getElementById('btn-contacts').onclick = function () { hasher.setHash('contacts'); };

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

  var loginProc = function ()
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
      ML.user.sessionId = data.sessionId;
      hasher.setHash('contacts');
    });
  };

  document.querySelector('#page-login .login').onclick = loginProc;
  document.querySelector('#page-login .username').onkeydown = function (e)
  {
    if (e.keyCode == 13) document.querySelector('#page-login .password').focus();
  };
  document.querySelector('#page-login .password').onkeydown = function (e)
  {
    if (e.keyCode == 13) loginProc();
  };

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
