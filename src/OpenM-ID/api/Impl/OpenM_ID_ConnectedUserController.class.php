<?php

Import::php("util.session.OpenM_SessionController");
Import::php("util.session.OpenM_CookiesController");
Import::php("OpenM-ID.api.Impl.DAO.OpenM_UserDAO");
Import::php("OpenM-ID.api.Impl.DAO.OpenM_UserSessionDAO");
Import::php("OpenM-ID.api.Impl.OpenM_IDImpl");
Import::php("util.time.Delay");
Import::php("util.time.Date");

/**
 * Description of OpenM_ID_UserController
 *
 * @package OpenM 
 * @subpackage OpenM\OpenM-ID\api\Impl 
 * @author Gaël Saunier
 */
class OpenM_ID_ConnectedUserController {

    const USER_SESSION_VALIDITY = "OpenM_ID.user.session.validity";
    const USER = "OpenM_ID_ConnectedUserController.user";
    const SESSION_ID = "OpenM_ID_ConnectedUserController.user_session_id";
    const BEGIN_TIME = "OpenM_ID_ConnectedUserController.user_session_begin_time";
    const COOKIE_NAME = "OpenM-ID_session_id";

    private static $session_validity;
    private static $secret;
    private static $hashAlgo;
    private static $user_session_begin_time;

    private static function init() {
        if (self::$session_validity != null)
            return;
        $p = Properties::fromFile(OpenM_IDImpl::CONFIG_FILE_NAME);
        $path = $p->get(OpenM_IDImpl::SPECIFIC_CONFIG_FILE_NAME);
        if ($path == null)
            throw new OpenM_ServiceImplException(OpenM_IDImpl::SPECIFIC_CONFIG_FILE_NAME . " property is not defined in " . self::CONFIG_FILE_NAME);
        $p2 = Properties::fromFile($path);
        if ($p2->get(self::USER_SESSION_VALIDITY) == null)
            throw new OpenM_ServiceImplException(self::USER_SESSION_VALIDITY . " property is not defined in $path");
        self::$session_validity = new Delay($p2->get(self::USER_SESSION_VALIDITY));
        if ($p2->get(OpenM_IDImpl::HASH_SECRET) == null)
            throw new OpenM_ServiceImplException(OpenM_IDImpl::HASH_SECRET . " property is not defined in $path");
        self::$secret = $p2->get(OpenM_IDImpl::HASH_SECRET);
        if ($p2->get(OpenM_IDImpl::HASH_ALGO) == null)
            throw new OpenM_ServiceImplException(OpenM_IDImpl::HASH_ALGO . " property is not defined in $path");
        self::$hashAlgo = $p2->get(OpenM_IDImpl::HASH_ALGO);
        if (!OpenM_Crypto::isAlgoValid(self::$hashAlgo))
            throw new OpenM_ServiceImplException(OpenM_IDImpl::HASH_ALGO . " property is not a valid crypto algo in $path");
    }

    public static function get() {
        self::init();
        $user = OpenM_SessionController::get(self::USER);
        $now = new Date();
        if (is_array($user)) {
            OpenM_Log::debug("user $user found", __CLASS__, __METHOD__, __LINE__);
            $user_session_begin_time = OpenM_SessionController::get(self::BEGIN_TIME);
            OpenM_Log::debug("session started from $user_session_begin_time", __CLASS__, __METHOD__, __LINE__);
            $sessionId = OpenM_SessionController::get(self::SESSION_ID);
            OpenM_Log::debug("sessionId=$sessionId", __CLASS__, __METHOD__, __LINE__);
            $begin_time = new Date(self::$user_session_begin_time);
            if ($begin_time->plus(self::$session_validity)->compareTo($now) > 0) {
                OpenM_Log::debug("user session OK", __CLASS__, __METHOD__, __LINE__);
                return HashtableString::from($user);
            } else {
                self::remove($sessionId);
                return null;
            }
        } else if (OpenM_CookiesController::contains(self::COOKIE_NAME)) {
            $sessionId = OpenM_CookiesController::get(self::COOKIE_NAME);
            OpenM_Log::debug("session $sessionId found in cookies", __CLASS__, __METHOD__, __LINE__);
            $userSessionDAO = new OpenM_UserSessionDAO();
            $session = $userSessionDAO->get($sessionId, self::getClientIp());
            if ($session == null) {
                OpenM_Log::debug("$sessionId not found in DAO", __CLASS__, __METHOD__, __LINE__);
                self::remove($sessionId);
                return null;
            }
            $begin_time = $session->get(OpenM_UserSessionDAO::SESSION_BEGIN_TIME);
            if ($begin_time->plus(self::$session_validity)->compareTo($now) < 0) {
                OpenM_Log::debug("session $sessionId expired", __CLASS__, __METHOD__, __LINE__);
                self::remove($sessionId);
                return null;
            }
            $userDAO = new OpenM_UserDAO();
            $user = $userDAO->get($session->get(OpenM_UserSessionDAO::USER_ID));
            if ($user == null) {
                OpenM_Log::debug("user of session not found", __CLASS__, __METHOD__, __LINE__);
                self::remove($sessionId);
                return null;
            }
            if (!$user->get(OpenM_UserDAO::USER_IS_VALID)) {
                OpenM_Log::debug("user of session not valid", __CLASS__, __METHOD__, __LINE__);
                self::remove($sessionId);
                return null;
            }
            OpenM_SessionController::set(self::USER, $user->toArray());
            OpenM_SessionController::set(self::BEGIN_TIME, $session->get(OpenM_UserSessionDAO::SESSION_BEGIN_TIME)->getTime());
            OpenM_SessionController::set(self::SESSION_ID, $session->get(OpenM_UserSessionDAO::SESSION_ID));
            OpenM_Log::debug("user saved in SESSION", __CLASS__, __METHOD__, __LINE__);
            return $user;
        }
        else
            OpenM_Log::debug("no user found", __CLASS__, __METHOD__, __LINE__);
    }

    public static function set(HashtableString $user, $rememberMe = true) {
        self::init();
        OpenM_Log::debug("Create new session", __CLASS__, __METHOD__, __LINE__);
        $userSessionDAO = new OpenM_UserSessionDAO();
        $userIp_hash = self::getClientIp();
        OpenM_Log::debug("Remove ghost user if exists", __CLASS__, __METHOD__, __LINE__);
        $userSessionDAO->removeUser($user->get(OpenM_UserDAO::USER_ID), $userIp_hash);
        $sessionId = OpenM_Crypto::hash(self::$hashAlgo, "" . self::$secret . (microtime(true)) . $userIp_hash . self::$secret);
        $userSessionDAO->create($sessionId, $user->get(OpenM_UserDAO::USER_ID), $userIp_hash);
        OpenM_Log::debug("session $sessionId created", __CLASS__, __METHOD__, __LINE__);
        OpenM_SessionController::set(self::USER, $user->toArray());
        OpenM_SessionController::set(self::SESSION_ID, $sessionId);
        $now = new Date();
        if ($rememberMe) {
            OpenM_CookiesController::set(self::COOKIE_NAME, $sessionId, $now->plus(self::$session_validity)->getTime());
            OpenM_Log::debug("session $sessionId saved in cookies", __CLASS__, __METHOD__, __LINE__);
        }
        $end = $now->plus(self::$session_validity);
        OpenM_SessionController::set(self::BEGIN_TIME, $end->getTime());
        OpenM_Log::debug("session $sessionId saved in SESSION", __CLASS__, __METHOD__, __LINE__);
    }

    public static function remove($sessionId = null) {
        self::init();
        if ($sessionId == null)
            $sessionId = OpenM_SessionController::get(self::SESSION_ID);
        OpenM_CookiesController::remove($sessionId);
        OpenM_Log::debug("session $sessionId removed from cookies", __CLASS__, __METHOD__, __LINE__);
        OpenM_SessionController::remove(self::USER);
        OpenM_SessionController::remove(self::BEGIN_TIME);
        OpenM_SessionController::remove(self::SESSION_ID);
        OpenM_Log::debug("session $sessionId removed from SESSION", __CLASS__, __METHOD__, __LINE__);
        if ($sessionId != null) {
            $now = new Date();
            $userSessionDAO = new OpenM_UserSessionDAO();
            $userSessionDAO->remove($sessionId);
            $userSessionDAO->removeBefore($now->less(self::$session_validity));
            OpenM_Log::debug("session $sessionId removed from DAO", __CLASS__, __METHOD__, __LINE__);
        }
    }

    private static function getClientIp() {
        return OpenM_Server::getClientIpCrypted(self::$hashAlgo, self::$secret . $_SERVER['HTTP_USER_AGENT']);
    }

}

?>