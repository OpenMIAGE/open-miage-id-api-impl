<?php

Import::php("util.http.OpenM_Header");
Import::php("util.http.OpenM_URL");
Import::php("util.OpenM_Log");
Import::php("util.session.OpenM_SessionController");

/**
 * Description of OpenM_IDReturnToController
 *
 * @author Gaël Saunier
 */
class OpenM_ID_ReturnToController {
    
    const RETURN_TO_IN_SESSION = "OpenM_ID.return_to";
    
    public function returnTo() {
        if (!isset($_GET[OpenM_ID::NO_REDIRECT_TO_LOGIN_PARAMETER]))
            OpenM_Header::redirect(OpenM_URL::getURLwithoutParameters() . "?" . OpenM_ID::LOGIN_API . (($this->isReturnTo()) ? "&return_to=" . $this->getReturnTo() : ""));
        else if ($this->isReturnTo())
            OpenM_Header::redirect($this->getReturnTo());
        else {
            OpenM_Log::warning("returnTo called without return_to parameter");
            die("internal error occur");
        }
    }

    public function getReturnTo() {
        if ($this->returnTo !== null)
            return $this->returnTo;

        $method = $_SERVER["REQUEST_METHOD"];
        switch ($method) {
            case "GET":
                $returnTo = $_GET["return_to"];
                break;
            case "POST":
                $returnTo = $_POST["return_to"];
                break;

            default:
                $returnTo = null;
                break;
        }

        if ($returnTo == null)
            $returnTo = OpenM_SessionController::get(self::RETURN_TO_IN_SESSION);

        OpenM_SessionController::set(self::RETURN_TO_IN_SESSION, $returnTo);
        $this->returnTo = OpenM_URL::decode($returnTo);

        return $this->returnTo;
    }

    public function isReturnTo() {
        return $this->getReturnTo() != null;
    }

}

?>