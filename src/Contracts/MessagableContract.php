<?php

namespace up2top\FlatFiles\Contracts;

interface MessagableContract
{
    public function addWarning($message);
    public function addPrompt($message);
    public function addError($message);
    public function showMessages();
    public function isStopRequired();
}
