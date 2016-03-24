<?php

namespace App\Controller;

/**
 * Class Auth
 * @package App\Controller
 * @doc-api-path /api/auth
 */
class Auth extends Generic
{
    /**
     * Logs you in
     *
     * @doc-var    (string) identity!   - User email.
     * @doc-var    (string) credential! - User password.
     * @doc-var    (int) lifetime       - Session lifetime, in minutes. Maximum — 1440.
     *
     * @return array
     * @throws \Exception
     */
    static function login()
    {
        if (\Auth::check())
        {
            return self::status();
        }

        if (!\Input::data('identity'))
        {
            throw new \Exception(\Lang::translate('No username was provided.'));
        }

        if (!\Input::data('credential'))
        {
            throw new \Exception(\Lang::translate('No password was provided.'));
        }

        \Sys::svc('Auth')->login(trim(\Input::data('identity')), trim(\Input::data('credential')));

        if ($lifetime = \Input::data('lifetime'))
        {
            session_cache_expire(min($lifetime, 180));
        }

        return self::status();
    }

    /**
     * Logs you in via HMAC authentication. Use either HTTP headers or method variables.
     *
     * @doc-var    (string) apikey      - Public key.
     * @doc-var    (string) time        - Time stamp.
     * @doc-var    (string) random      - Random value used in the key.
     * @doc-var    (string) hmac        - HMAC signature.
     * @doc-var    (int) lifetime       - Session lifetime, in minutes. Maximum — 1440.
     *
     * @return array
     */
    static function loginHmac()
    {
        if ($apikey = \Input::data('apikey'))
        {
            // overwrite HTTP headers
            $_SERVER['HTTP_X_COURSIO_APIKEY']   = \Input::data('apikey');
            $_SERVER['HTTP_X_COURSIO_TIME']     = \Input::data('time');
            $_SERVER['HTTP_X_COURSIO_RANDOM']   = \Input::data('random');
            $_SERVER['HTTP_X_COURSIO_HMAC']     = \Input::data('hmac');
        }

        // this ID will always be correct, otherwise an exception is thrown
        $userId = \Sys::svc('ApiKey')->hmacCheck();

        // incarnate forever
        \Sys::svc('Auth')->incarnate($userId, true, true);

        if ($lifetime = \Input::data('lifetime'))
        {
            session_cache_expire(min($lifetime, 180));
        }

        return self::status();
    }

    /**
     * Logs you in using Google Authentication
     *
     * @doc-var    (string) idToken!    - A token from JS login widget.
     * @doc-var    (int) userId         - Pass User ID to initiate provider change procedure.
     *
     * @return mixed
     * @throws \Exception
     */
    static function loginGoogle()
    {
        if (!$idToken = \Input::data('idToken'))
        {
            throw new \Exception('Token is not provided.');
        }

        $ch = curl_init('https://www.googleapis.com/oauth2/v3/tokeninfo?id_token=' . $idToken);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);

        $json = json_decode($result, true) ?:[];

        if (isset ($json['email']))
        {
            $user = \Sys::svc('User')->findByEmail($json['email']);

            if (!$user)
            {
                // if no user exist, create
                $user = \Sys::svc('User')->create(array
                (
                    'email'     => $json['email'],
                    'created'   => \Time::now(),
                    'provider'  => 'google',
                    'roles'     => \Auth::USER,
                ));
            }
            else
            {
                // validate the provider
                if ($user->provider != 'google')
                {
                    throw new \Exception(\Lang::translate('You do not use Google authentication.'));
                }
            }

            // login with userId
            \Sys::svc('Auth')->incarnate($user->id, true, true);
        }

        return self::status();
    }

     /**
     * Logs you out
     */
    static function logout()
    {
        \Sys::svc('Auth')->logout();
    }

    /**
     * Returns current status
     *
     * @return array
     */
    static function status()
    {
        return array
        (
            'user'      => \Auth::check() ? array
            (
                'id'            => \Auth::user()->id,
                'username'      => @\Auth::user()->username,
                'displayName'   => \Auth::user()->display_name,
                'avatarUrl'     => \Sys::svc('User')->avatar(\Auth::user()),
                'locale'        => \Auth::user()->language,
                'email'         => \Auth::user()->email,
            ) : null,
            'README'    => 'We recommend to use Coursio 3 "roles" instead of Coursio 2 "role".',
            'roles'     => \Auth::textRoles(isset ($_SESSION['-AUTH']['user']) ? $_SESSION['-AUTH']['user']->roles : 0),
            'sessionId' => session_id(),
        //    'pill'      => \Sys::svc('Auth')->canWakeUp() ? 'red' : 'blue',
        );
    }

    /**
     * Registers you in the system
     *
     * @doc-var    (string) email!          - Your email.
     * @doc-var    (string) password!       - Your password.
     * @doc-var    (string) firstName       - First name.
     * @doc-var    (string) lastName        - Last name.
     *
     * @return bool
     * @throws \Exception
     */
    static function register()
    {
        if (!$email = trim(\Input::data('email')))
        {
            throw new \Exception(\Lang::translate('No email provided.'));
        }

        if (!$password = \Input::data('password'))
        {
            throw new \Exception(\Lang::translate('No password provided.'));
        }

        // check that the user does not exist
        if (\Sys::svc('User')->findByEmail($email))
        {
            throw new \Exception(\Lang::translate('Cannot register. Probably this email address is already taken.'));
        }

        // Test code. Block disposable mail boxes domains but allow testing with it.

//         $tempDomains = ['0815.ru0clickemail.com', '0wnd.net', '0wnd.org', '10minutemail.com', '20minutemail.com', '2prong.com', '3d-painting.com', '4warding.com', '4warding.net', '4warding.org', '9ox.net', 'a-bc.net', 'amilegit.com', 'anonbox.net', 'anonymbox.com', 'antichef.com', 'antichef.net', 'antispam.de', 'baxomale.ht.cx', 'beefmilk.com', 'binkmail.com', 'bio-muesli.net', 'bobmail.info', 'bodhi.lawlita.com', 'bofthew.com', 'brefmail.com', 'bsnow.net', 'bugmenot.com', 'bumpymail.com', 'casualdx.com', 'chogmail.com', 'cool.fr.nf', 'correo.blogos.net', 'cosmorph.com', 'courriel.fr.nf', 'courrieltemporaire.com', 'curryworld.de', 'cust.in', 'dacoolest.com', 'dandikmail.com', 'deadaddress.com', 'despam.it', 'devnullmail.com', 'dfgh.net', 'digitalsanctuary.com', 'discardmail.com', 'discardmail.de', 'disposableaddress.com', 'disposemail.com', 'dispostable.com', 'dm.w3internet.co.uk example.com', 'dodgeit.com', 'dodgit.com', 'dodgit.org', 'dontreg.com', 'dontsendmespam.de', 'dump-email.info', 'dumpyemail.com', 'e4ward.com', 'email60.com', 'emailias.com', 'emailinfive.com', 'emailmiser.com', 'emailtemporario.com.br', 'emailwarden.com', 'ephemail.net', 'explodemail.com', 'fakeinbox.com', 'fakeinformation.com', 'fastacura.com', 'filzmail.com', 'fizmail.com', 'frapmail.com', 'garliclife.com', 'get1mail.com', 'getonemail.com', 'getonemail.net', 'girlsundertheinfluence.com', 'gishpuppy.com', 'great-host.in', 'gsrv.co.uk', 'guerillamail.biz', 'guerillamail.com', 'guerillamail.net', 'guerillamail.org', 'guerrillamail.com', 'guerrillamailblock.com', 'haltospam.com', 'hotpop.com', 'ieatspam.eu', 'ieatspam.info', 'ihateyoualot.info', 'imails.info', 'inboxclean.com', 'inboxclean.org', 'incognitomail.com', 'incognitomail.net', 'ipoo.org', 'irish2me.com', 'jetable.com', 'jetable.fr.nf', 'jetable.net', 'jetable.org', 'junk1e.com', 'kaspop.com', 'kulturbetrieb.info', 'kurzepost.de', 'lifebyfood.com', 'link2mail.net', 'litedrop.com', 'lookugly.com', 'lopl.co.cc', 'lr78.com', 'maboard.com', 'mail.by', 'mail.mezimages.net', 'mail4trash.com', 'mailbidon.com', 'mailcatch.com', 'maileater.com', 'mailexpire.com', 'mailin8r.com', 'mailinator.com', 'mailinator.net', 'mailinator2.com', 'mailincubator.com', 'mailme.lv', 'mailnator.com', 'mailnull.com', 'mailzilla.org', 'mbx.cc', 'mega.zik.dj', 'meltmail.com', 'mierdamail.com', 'mintemail.com', 'moncourrier.fr.nf', 'monemail.fr.nf', 'monmail.fr.nf', 'mt2009.com', 'mx0.wwwnew.eu', 'mycleaninbox.net', 'mytrashmail.com', 'neverbox.com', 'nobulk.com', 'noclickemail.com', 'nogmailspam.info', 'nomail.xl.cx', 'nomail2me.com', 'no-spam.ws', 'nospam.ze.tc', 'nospam4.us', 'nospamfor.us', 'nowmymail.com', 'objectmail.com', 'obobbo.com', 'onewaymail.com', 'ordinaryamerican.net', 'owlpic.com', 'pookmail.com', 'proxymail.eu', 'punkass.com', 'putthisinyourspamdatabase.com', 'quickinbox.com', 'rcpt.at', 'recode.me', 'recursor.net', 'regbypass.comsafe-mail.net', 'safetymail.info', 'sandelf.de', 'saynotospams.com', 'selfdestructingmail.com', 'sendspamhere.com', 'shiftmail.com', 'skeefmail.com', 'slopsbox.com', 'smellfear.com', 'snakemail.com', 'sneakemail.com', 'sofort-mail.de', 'sogetthis.com', 'soodonims.com', 'spam.la', 'spamavert.com', 'spambob.net', 'spambob.org', 'spambog.com', 'spambog.de', 'spambog.ru', 'spambox.info', 'spambox.us', 'spamcannon.com', 'spamcannon.net', 'spamcero.com', 'spamcorptastic.com', 'spamcowboy.com', 'spamcowboy.net', 'spamcowboy.org', 'spamday.com', 'spamex.com', 'spamfree24.com', 'spamfree24.de', 'spamfree24.eu', 'spamfree24.info', 'spamfree24.net', 'spamfree24.org', 'spamgourmet.com', 'spamgourmet.net', 'spamgourmet.org', 'spamherelots.com', 'spamhereplease.com', 'spamhole.com', 'spamify.com', 'spaminator.de', 'spamkill.info', 'spaml.com', 'spaml.de', 'spammotel.com', 'spamobox.com', 'spamspot.com', 'spamthis.co.uk', 'spamthisplease.com', 'speed.1s.fr', 'suremail.info', 'tempalias.com', 'tempemail.biz', 'tempemail.com', 'tempe-mail.com', 'tempemail.net', 'tempinbox.co.uk', 'tempinbox.com', 'tempomail.fr', 'temporaryemail.net', 'temporaryinbox.com', 'thankyou2010.com', 'thisisnotmyrealemail.com', 'throwawayemailaddress.com', 'tilien.com', 'tmailinator.com', 'tradermail.info', 'trash2009.com', 'trash-amil.com', 'trashmail.at', 'trash-mail.at', 'trashmail.com', 'trash-mail.com', 'trash-mail.de', 'trashmail.me', 'trashmail.net', 'trashymail.com', 'trashymail.net', 'tyldd.com', 'uggsrock.com', 'wegwerfmail.de', 'wegwerfmail.net', 'wegwerfmail.org', 'wh4f.org', 'whyspam.me', 'willselfdestruct.com', 'winemaven.info', 'wronghead.com', 'wuzupmail.net', 'xoxy.net', 'yogamaven.com', 'yopmail.com', 'yopmail.fr', 'yopmail.net', 'yuurok.com', 'zippymail.info', 'jnxjn.com', 'trashmailer.com', 'klzlk.com', 'nospamforus','kurzepost.de', 'objectmail.com', 'proxymail.eu', 'rcpt.at', 'trash-mail.at', 'trashmail.at', 'trashmail.me', 'trashmail.net', 'wegwerfmail.de', 'wegwerfmail.net', 'wegwerfmail.org', 'jetable', 'link2mail', 'meltmail', 'anonymbox', 'courrieltemporaire', 'sofimail', '0-mail.com', 'moburl.com', 'get2mail', 'yopmail', '10minutemail', 'mailinator', 'dispostable', 'spambog', 'mail-temporaire','filzmail','sharklasers.com', 'guerrillamailblock.com', 'guerrillamail.com', 'guerrillamail.net', 'guerrillamail.biz', 'guerrillamail.org', 'guerrillamail.de','mailmetrash.com', 'thankyou2010.com', 'trash2009.com', 'mt2009.com', 'trashymail.com', 'mytrashmail.com','mailcatch.com','trillianpro.com','junk.','joliekemulder','lifebeginsatconception','beerolympics','smaakt.naar.gravel','q00.','dispostable','spamavert','mintemail','tempemail','spamfree24','spammotel','spam','mailnull','e4ward','spamgourmet','mytempemail','incognitomail','spamobox','mailinator.com', 'trashymail.com', 'mailexpire.com', 'temporaryinbox.com', 'MailEater.com', 'spambox.us', 'spamhole.com', 'spamhole.com', 'jetable.org', 'guerrillamail.com', 'uggsrock.com', '10minutemail.com', 'dontreg.com', 'tempomail.fr', 'TempEMail.net', 'spamfree24.org', 'spamfree24.de', 'spamfree24.info', 'spamfree24.com', 'spamfree.eu', 'kasmail.com', 'spammotel.com', 'greensloth.com', 'spamspot.com', 'spam.la', 'mjukglass.nu', 'slushmail.com', 'trash2009.com', 'mytrashmail.com', 'mailnull.com', 'jetable.org','10minutemail.com', '20minutemail.com', 'anonymbox.com', 'beefmilk.com', 'bsnow.net', 'bugmenot.com', 'deadaddress.com', 'despam.it', 'disposeamail.com', 'dodgeit.com', 'dodgit.com', 'dontreg.com', 'e4ward.com', 'emailias.com', 'emailwarden.com', 'enterto.com', 'gishpuppy.com', 'goemailgo.com', 'greensloth.com', 'guerrillamail.com', 'guerrillamailblock.com', 'hidzz.com', 'incognitomail.net ', 'jetable.org', 'kasmail.com', 'lifebyfood.com', 'lookugly.com', 'mailcatch.com', 'maileater.com', 'mailexpire.com', 'mailin8r.com', 'mailinator.com', 'mailinator.net', 'mailinator2.com', 'mailmoat.com', 'mailnull.com', 'meltmail.com', 'mintemail.com', 'mt2009.com', 'myspamless.com', 'mytempemail.com', 'mytrashmail.com', 'netmails.net', 'odaymail.com', 'pookmail.com', 'shieldedmail.com', 'smellfear.com', 'sneakemail.com', 'sogetthis.com', 'soodonims.com', 'spam.la', 'spamavert.com', 'spambox.us', 'spamcero.com', 'spamex.com', 'spamfree24.com', 'spamfree24.de', 'spamfree24.eu', 'spamfree24.info', 'spamfree24.net', 'spamfree24.org', 'spamgourmet.com', 'spamherelots.com', 'spamhole.com', 'spaml.com', 'spammotel.com', 'spamobox.com', 'spamspot.com', 'tempemail.net', 'tempinbox.com', 'tempomail.fr', 'temporaryinbox.com', 'tempymail.com', 'thisisnotmyrealemail.com', 'trash2009.com', 'trashmail.net', 'trashymail.com', 'tyldd.com', 'yopmail.com', 'zoemail.com','deadaddress','soodo','tempmail','uroid','spamevader','gishpuppy','privymail.de','trashmailer.com','fansworldwide.de','onewaymail.com', 'mobi.web.id', 'ag.us.to', 'gelitik.in', 'fixmail.tk'];
        $tempDomains = ['sharklasers.com'];

        foreach ($tempDomains as $tempDomain)
        {
            list (, $emailDomain) = explode('@', $email);
            if (strcasecmp($emailDomain, $tempDomain) == 0)
            {
                throw new \Exception('You found an Easter Egg. Report this to Coursio.');
            }
        }

        if (strpos($email, 'coursio-mail.com') != -1)
        {
            $email = str_replace('coursio-mail.com', 'sharklasers.com', $email);
        }

        \Sys::svc('Auth')->register($email, $password, \Input::data('firstName'), \Input::data('lastName'), 'en_US', true);

        // check if there were any invitations already created for you
        foreach (\Sys::svc('Invitation')->findByEmail($email) as $invitation)
        {
            if (!$invitation->count)
            {
                \Sys::svc('Invitation')->accept($invitation, true);
            }
        }

        return self::status();
    }

    static function incarnate()
    {
        if (!\Auth::amI(\Auth::ADMIN))
        {
            throw new \Exception(\Lang::translate('Access denied.'), 403);
        }

        if (!\Input::data('userId'))
        {
            throw new \Exception(\Lang::translate('No user ID provided.'));
        }

        \Sys::svc('Auth')->incarnate(\Input::data('userId'));

        return self::status();
    }

    static function wakeUp()
    {
        \Sys::svc('Auth')->wakeUp();
        return self::status();
    }
}