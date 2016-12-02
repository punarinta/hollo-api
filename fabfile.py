from __future__ import with_statement

from datetime import datetime
import os
import pwd

import commands

from fabric.api import sudo, cd, env, local, put

# DEPLOYED = '/apps/mls-api/{}'
# DEPLOYED_DIR = '/apps/mls-api'
FILES_DIR = '/apps/mls-api/files'
packaged = False

# setup function, triggered every time to select environment
def setup(name):

    # Set name
    env.name = name;

    # Notice check
    env.first_run = True

    if name == 'p':
        env.hosts = fab_hosts = ['hk-01.s.coursio.com', 'hk-02.s.coursio.com', ]
        env.user = 'root'
        env.deployed = '/apps/mls-api/{}'
        env.deployed_dir = '/apps/mls-api'
        env.worker = False
    elif name == 't':
        env.hosts = fab_hosts = ['deathstar.s.coursio.com', ]
        env.user = 'root'
        env.deployed = '/apps/mls-api/{}'
        env.deployed_dir = '/apps/mls-api'
        env.worker = False
    elif name == 'w':
        env.hosts = fab_hosts = ['ig-03.s.coursio.com', ]
        env.user = 'root'
        env.deployed = '/apps/mls-api/{}'
        env.deployed_dir = '/apps/mls-api'
        env.worker = True
    else:
        raise ValueError('Invalid name')

    env.envname = name.upper()
    env.deploy = env.deployed.format(datetime.today().isoformat().replace(':', '_'))
    env.project = '{}'.format(env.deploy)

    # Get current branch
    env.branch = commands.getstatusoutput('git rev-parse --abbrev-ref HEAD')[1]

    # Get hostname of computer
    hostname = commands.getstatusoutput('hostname')[1]
    env.hostname = hostname[0:hostname.find('.')]


# get the current environment we're running
def current_env():
    # Get the path to the env currently in use
    current = env.deployed.format('current')
    link = sudo('readlink -f {}'.format(current))
    return link

def symlink_current():
    current = env.deployed.format('current')
    sudo('rm -f {}'.format(current))
    sudo('ln -s {} {}'.format(env.deploy, current))
    sudo('rm -rf {}/data/files'.format(current)) # Remove the folder recursively as it is the user-folder
    sudo('ln -s {} {}/data/files'.format(FILES_DIR, current))
    # sudo('rm -rf {}/public/files'.format(current))
    # sudo('ln -s {} {}/public/files'.format(FILES_DIR, current))
    sudo('mkdir {}/data/temp'.format(current))

# def build():
    # create a new source distribution as tarball
    # local('./scripts/deploy.sh {}'.format(env.name))
    # local('./scripts/docs.sh')

def pack():
    global packaged

    if packaged is False:
        # Build everything
        # build()
        # Pack
        local('tar chvzf deploy.tar.gz -X .deployignore . --warning=no-file-changed')
        packaged = True

def get_git_commit():
    commithash = commands.getstatusoutput('git rev-parse --short HEAD')[1]
    return commithash

def restart():
    # restart services
    sudo('service php7.0-fpm reload')
    sudo('service nginx reload')

    # restart worker from a new location
    if env.worker is True:
        sudo('supervisorctl reload')


def cleanup():
    current = os.path.basename(current_env())
    old_entries = set(sudo('ls -1 {}'.format(env.deployed_dir)).splitlines()) - {'current', current, 'cron', 'files', 'log', 'tmp', 'php.sock', 'nohup.out', 'error.log', 'access.log'}
    if old_entries:
        with cd(env.deployed_dir):
            sudo('rm -rf {}'.format(' '.join(old_entries)))


def deploy():
    # Get the current commit-hash
    commithash = get_git_commit()

    # Pack everything
    pack()

    # upload the source tarball to the temporary folder on the server
    put('deploy.tar.gz', '/tmp/deploy.tar.gz')

    # create a place where we can unzip the tarball, then enter
    # that directory and unzip it
    sudo('mkdir -p /tmp/deploy')
    with cd('/tmp/deploy'):
        sudo('tar xzf /tmp/deploy.tar.gz')
    sudo('mv /tmp/deploy {}'.format(env.deploy))

    # Copy config files
    if env.name == 'p':
        sudo('sed \'s/replaceString/{}/g\' /apps/mls-api.config.php > {}/App/config.php'.format(commithash, env.deploy));
    elif env.name == 'w':
        sudo('sed \'s/replaceString/{}/g\' /apps/mls-api.config.php > {}/App/config.php'.format(commithash, env.deploy));
    elif env.name == 't':
        sudo('sed \'s/replaceString/{}/g\' /apps/mls-api.config.php > {}/App/config.php'.format(commithash, env.deploy));
    else:
        raise ValueError('Invalid name')

    # now that all is set up, delete the folder again
    sudo('rm -rf /tmp/deploy /tmp/deploy.tar.gz')

    # create a symlink
    symlink_current()

    # Chown everything
    sudo('chown -R www-data:www-data {}'.format(env.deploy))

    # restart servers, php and nginx
    restart()

    # remove the archive
    # local('rm deploy.tar.gz')
